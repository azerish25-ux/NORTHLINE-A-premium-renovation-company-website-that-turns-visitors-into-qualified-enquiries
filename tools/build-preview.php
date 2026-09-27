<?php
/** Generate the browser-only demonstration from the same content as WordPress. */
declare(strict_types=1);
use Northline\Content;
use Northline\Blocks;
use Northline\Estimate;
$root = dirname(__DIR__);
foreach (['Domain', 'Content', 'Blocks'] as $file) require $root . '/plugins/northline-planner/includes/' . $file . '.php';
$out = $root . '/preview';
@mkdir($out . '/assets/images', 0775, true);
foreach (glob($root . '/theme/northline/assets/images/*') ?: [] as $file) if (is_file($file)) copy($file, $out . '/assets/images/' . basename($file));
foreach (['site.css', 'site.js'] as $file) copy($root . '/theme/northline/assets/' . $file, $out . '/assets/' . $file);
foreach (['planner.js', 'demo.js'] as $file) if (is_file($root . '/plugins/northline-planner/assets/' . $file)) copy($root . '/plugins/northline-planner/assets/' . $file, $out . '/assets/' . $file);
$routes = array_keys(Content::pages());
foreach (Content::projects() as $project) $routes[] = 'project/' . $project['slug'];
$routes[] = 'demo-desk';
foreach ($routes as $route) {
    $base = $route === '' ? './' : str_repeat('../', count(explode('/', $route)));
    $GLOBALS['nl_preview_base'] = $base;
    $GLOBALS['nl_preview_assets'] = $base . 'assets/images/';
    if (str_starts_with($route, 'project/')) {
        $project = Content::projects()[substr($route, 8)]; $title = $project['title']; $content = Content::caseStudy($project);
    } elseif ($route === 'demo-desk') {
        $title = 'Demo studio desk'; $content = Content::group('nl-page-intro nl-shell', Content::p('BROWSER DEMONSTRATION / NOT WORDPRESS ADMIN', 'nl-kicker') . Content::h('The studio side<br>of <em>the conversation.</em>', 1)) . Content::group('nl-shell nl-planner-shell', Blocks::planner('demo-desk'));
    } else { $page = Content::pages()[$route]; $title = $page['title']; $content = $page['content']; }
    $content = preg_replace_callback('/<!-- wp:northline\/([a-z-]+)\s+(\{.*?\})\s+\/-->/s', static fn ($m) => Blocks::render($m[1], json_decode($m[2], true, 512, JSON_THROW_ON_ERROR)), $content);
    $content = preg_replace('/<!--\s*\/?wp:.*?-->/s', '', $content);
    $nav = '';
    foreach (['studio/' => 'The studio', 'approach/' => 'Our approach', 'projects/' => 'Projects', 'plan-your-renovation/' => 'Plan your renovation ↗'] as $path => $label) $nav .= '<a href="' . $base . $path . '"' . ($path === 'plan-your-renovation/' ? ' class="nl-nav-cta"' : '') . '>' . $label . '</a>';
    $header = '<a class="nl-skip" href="#main-content">Skip to content</a><div class="nl-demo-banner"><span>FICTIONAL PRACTICE / ORIGINAL VISUALISATIONS / BROWSER-ONLY DEMO</span><a href="' . $base . 'demo-desk/">Open demo studio desk ↗</a></div><header class="nl-header nl-shell"><p class="nl-wordmark"><a href="' . $base . '" aria-label="NORTHLINE home"><span class="nl-north" aria-hidden="true">↗</span> NORTHLINE</a></p><button class="nl-menu-toggle" aria-expanded="false" aria-controls="nl-navigation">Menu +</button><nav id="nl-navigation" class="nl-static-nav" aria-label="Main navigation">' . $nav . '</nav></header>';
    $footer = '<footer class="nl-footer"><div class="nl-footer-top nl-shell"><div><p class="nl-kicker">DESIGN & BUILD / PRINCE EDWARD ISLAND</p><h2>Good homes.<br><em>Carefully considered.</em></h2></div><div class="nl-footer-links"><p><a href="' . $base . 'kitchens/">Kitchens</a><br><a href="' . $base . 'extensions/">Extensions</a><br><a href="' . $base . 'whole-home/">Whole-home renovations</a></p><p><a href="' . $base . 'studio/">The studio</a><br><a href="' . $base . 'approach/">Our approach</a><br><a href="' . $base . 'projects/">Selected projects</a></p></div></div><p class="nl-footer-wordmark">NORTHLINE<span>↗</span></p><div class="nl-footer-bottom nl-shell"><p>Fictional practice. Original architectural visualisations.<br>No completed client work, awards or testimonials are claimed.</p><p><a href="' . $base . 'privacy/">Privacy & project information</a><br>© 2026 NORTHLINE / Portfolio study</p></div></footer>';
    $settings = ['home' => $base, 'demo' => true, 'api' => '', 'timezone' => 'America/Halifax', 'rateCard' => Estimate::RATE_CARD];
    $scripts = '<script src="' . $base . 'assets/site.js" defer></script>';
    if (str_contains($content, 'data-nl-planner')) $scripts .= '<script>window.NORTHLINE=' . json_encode($settings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script><script src="' . $base . 'assets/demo.js" defer></script><script src="' . $base . 'assets/planner.js" defer></script>';
    $html = '<!doctype html><html lang="en-CA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f3f0e9"><meta name="referrer" content="no-referrer"><meta name="robots" content="noindex,nofollow"><title>' . Content::e($title) . ' — NORTHLINE</title><link rel="stylesheet" href="' . $base . 'assets/site.css"></head><body>' . $header . '<main id="main-content" class="nl-main">' . $content . '</main>' . $footer . $scripts . '</body></html>';
    $dir = $out . ($route ? '/' . $route : ''); @mkdir($dir, 0775, true); file_put_contents($dir . '/index.html', $html); echo ($route ?: '/') . PHP_EOL;
}
file_put_contents($out . '/README.txt', "NORTHLINE browser demonstration\nRun from this folder: python3 -m http.server 8080 --bind 127.0.0.1\nOpen http://127.0.0.1:8080\nNo real email is sent; demo enquiries stay in this browser.\nThe production workflow is in the WordPress packages.\n");
