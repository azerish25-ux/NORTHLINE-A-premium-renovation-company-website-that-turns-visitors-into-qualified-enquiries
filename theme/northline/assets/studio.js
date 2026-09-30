/* Material & Space: progressive, keyboard-native study controls. */
(function () {
  'use strict';
  document.querySelectorAll('[data-material-study]').forEach(study => {
    const controls = study.querySelector('.nl-material-controls');
    const buttons = [...study.querySelectorAll('[data-material-target]')];
    const panels = [...study.querySelectorAll('[data-material-panel]')];
    const status = study.querySelector('.nl-material-status');
    if (!controls || !buttons.length || panels.length !== buttons.length) return;
    function select(key, announce) {
      const target = panels.find(panel => panel.dataset.materialPanel === key);
      if (!target) return;
      panels.forEach(panel => { panel.hidden = panel !== target; });
      buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.materialTarget === key)));
      if (announce && status) status.textContent = target.querySelector('h3').textContent;
    }
    select(buttons[0].dataset.materialTarget, false);
    controls.hidden = false;
    buttons.forEach(button => button.addEventListener('click', () => select(button.dataset.materialTarget, true)));
  });
  // Active navigation is a state, not a different page title or duplicated menu.
  const current = location.pathname.replace(/\/+$/, '');
  document.querySelectorAll('.nl-static-nav > a').forEach(link => {
    if (new URL(link.href, location.href).pathname.replace(/\/+$/, '') === current) link.setAttribute('aria-current', 'page');
  });
})();
