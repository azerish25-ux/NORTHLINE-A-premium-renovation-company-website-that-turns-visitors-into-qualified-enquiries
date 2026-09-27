/* Progressive enhancements. Reading content never depends on this script. */
(function () {
  'use strict';
  document.querySelectorAll('[data-project-gallery]').forEach(gallery => {
    const cards = [...gallery.querySelectorAll('[data-category]')];
    const count = gallery.querySelector('.nl-gallery-count');
    const update = category => {
      let shown = 0;
      cards.forEach(card => { card.hidden = category !== 'all' && card.dataset.category !== category; if (!card.hidden) shown++; });
      gallery.querySelectorAll('[data-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.filter === category)));
      if (count) count.textContent = String(shown).padStart(2, '0') + ' / ' + (shown === 1 ? 'DESIGN STUDY' : 'DESIGN STUDIES');
    };
    gallery.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => update(button.dataset.filter)));
    update('all');
  });
  document.querySelectorAll('.nl-compare-input').forEach(input => input.addEventListener('input', () => {
    input.closest('.nl-compare-stage').style.setProperty('--split', input.value + '%');
    input.setAttribute('aria-valuetext', input.value + ' percent of proposed view revealed');
  }));
  const toggle = document.querySelector('.nl-menu-toggle');
  const nav = document.querySelector('.nl-static-nav');
  if (toggle && nav) {
    const close = () => { nav.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => { const open = toggle.getAttribute('aria-expanded') !== 'true'; toggle.setAttribute('aria-expanded', String(open)); nav.classList.toggle('is-open', open); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && nav.classList.contains('is-open')) { close(); toggle.focus(); } });
    nav.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
    window.matchMedia('(min-width:801px)').addEventListener('change', event => { if (event.matches) close(); });
  }
})();
