/* Material & Space: interaction, route, responsive and progressive-enhancement checks. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const evidence = path.join(root, 'evidence');
fs.mkdirSync(evidence, { recursive: true });
let assertions = 0;
const check = (value, message) => { assert.ok(value, message); assertions++; console.log('PASS ' + message); };
const base = process.env.NORTHLINE_DESIGN_URL || 'http://127.0.0.1:8091';
async function images(page) {
  await page.evaluate(async () => {
    const all = [...document.images];
    all.forEach(image => image.loading = 'eager');
    await Promise.all(all.map(image => image.decode().catch(() => {})));
  });
  check(await page.evaluate(() => [...document.images].every(image => image.naturalWidth > 0)), 'all content images decode');
}
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined });
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/');
    await images(page);
    check(await page.locator('h1').count() === 1, 'homepage has a single primary heading');
    check(await page.locator('.nl-hero-v2').count() === 1, 'new editorial homepage is mounted');
    check(await page.locator('.nl-floorplan svg').count() === 1, 'empty-attribute floorplan block renders');
    check(await page.locator('[data-material-panel]:visible').count() === 1, 'one material study is shown after enhancement');
    const stone = page.getByRole('button', { name: 'Honed limestone', exact: false });
    await stone.focus();
    await stone.press('Enter');
    check(await stone.getAttribute('aria-pressed') === 'true', 'Enter selects the limestone study');
    check(await page.locator('[data-material-panel="limestone"]').isVisible(), 'selected study is visible');
    check(await page.locator('[data-material-panel="oak"]').isHidden(), 'unselected study is hidden');
    check((await page.locator('.nl-material-status').innerText()).includes('softer'), 'selection is announced in a live region');
    const lime = page.getByRole('button', { name: 'Mineral limewash', exact: false });
    await lime.focus(); await lime.press('Space');
    check(await lime.getAttribute('aria-pressed') === 'true', 'Space selects the limewash study');
    const ids = await page.locator('[id]').evaluateAll(nodes => nodes.map(node => node.id));
    check(new Set(ids).size === ids.length, 'homepage does not contain duplicate IDs');
    await page.getByRole('button', { name: 'Quarter-sawn oak', exact: false }).click();
    await page.screenshot({ path: path.join(evidence, 'material-space-desktop.png'), fullPage: true });
    await page.locator('.nl-material-atelier').screenshot({ path: path.join(evidence, 'material-library.png') });
    await page.evaluate(() => scrollTo(0,0));
    await page.screenshot({ path: path.join(evidence, 'material-space-hero.png') });
    for (const width of [320, 360, 390, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      // A viewport change is asynchronous in Chromium; inspect the settled layout.
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      const overflow = await page.evaluate(() => ({ width: document.documentElement.scrollWidth, viewport: innerWidth, nodes: [...document.body.querySelectorAll('*')].filter(node => node.getBoundingClientRect().right > innerWidth + 1).map(node => ({ tag: node.tagName, className: node.className, right: node.getBoundingClientRect().right })).slice(0, 30) }));
      if (overflow.width > overflow.viewport + 1) {
        fs.writeFileSync(path.join(evidence, 'overflow-' + width + '.json'), JSON.stringify(overflow, null, 2));
        await page.screenshot({ path: path.join(evidence, 'overflow-' + width + '.png'), fullPage: true });
      }
      check(overflow.width <= overflow.viewport + 1, 'homepage fits ' + width + 'px');
      const touch = await page.locator('[data-material-target]').evaluateAll(nodes => nodes.every(node => node.getBoundingClientRect().height >= 48));
      check(touch, 'material touch targets remain at least 48px at ' + width + 'px');
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => scrollTo(0,0));
    const menu = page.getByRole('button', { name: 'Menu +', exact: true });
    await menu.click();
    check(await menu.getAttribute('aria-expanded') === 'true', 'mobile menu exposes expanded state');
    check(await page.getByRole('navigation', { name: 'Main navigation' }).isVisible(), 'mobile menu links are visible');
    await page.keyboard.press('Escape');
    check(await menu.getAttribute('aria-expanded') === 'false', 'Escape closes mobile navigation');
    check(await menu.evaluate(node => node === document.activeElement), 'Escape returns focus to menu button');
    await page.screenshot({ path: path.join(evidence, 'material-space-mobile.png'), fullPage: true });
    const routes = ['', 'studio', 'approach', 'kitchens', 'extensions', 'whole-home', 'projects', 'plan-your-renovation', 'consultation', 'privacy', 'demo-desk', ...['birch','tidal','alder','orchard','courtyard','harbour'].map(name => 'project/' + name + '-house')];
    for (const route of routes) {
      const response = await page.goto(base + '/' + (route ? route + '/' : ''));
      check(response.status() === 200, 'route responds: /' + route);
      check(await page.locator('h1').count() === 1, 'one primary heading: /' + route);
      check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'mobile route fits: /' + route);
      await images(page);
    }
    const nojs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const fallback = await nojs.newPage();
    await fallback.goto(base + '/');
    check(await fallback.locator('[data-material-panel]:visible').count() === 3, 'without JavaScript every material study is readable');
    check(await fallback.locator('.nl-material-controls').isHidden(), 'without JavaScript nonfunctional controls are hidden');
    check(await fallback.locator('.nl-static-nav').isVisible(), 'without JavaScript mobile navigation remains visible');
    await nojs.close();
    check(errors.length === 0, 'no JavaScript exceptions across routes: ' + errors.join('; '));
    fs.writeFileSync(path.join(evidence, 'design-results.json'), JSON.stringify({ status: 'passed', assertions, routes: routes.length, viewports: [320,360,390,768,1024,1440], errors }, null, 2) + '\n');
    console.log(assertions + ' design assertions passed.');
    await context.close();
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
