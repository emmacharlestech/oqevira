/* Oqevira admin: phone menu toggle. Escape or a tap outside closes it. */
(() => {
  'use strict';
  const toggle = document.querySelector('.topbar__toggle');
  const nav = document.getElementById('admin-nav');
  if (!toggle || !nav) return;

  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
    toggle.querySelector('.topbar__toggle-label').textContent = open ? 'Close' : 'Menu';
  };

  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) { setOpen(false); toggle.focus(); }
  });
  document.addEventListener('click', (e) => {
    if (nav.classList.contains('is-open') && !nav.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
  });
})();

/* Forms with data-confirm ask before submitting (deletes) */
document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (e) => {
    if (!window.confirm(form.dataset.confirm)) e.preventDefault();
  });
});

/* File inputs with data-preview show the chosen image before upload */
document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
  const img = document.getElementById(input.dataset.preview);
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!img || !file) return;
    img.src = URL.createObjectURL(file);
    img.hidden = false;
  });
});

/* Open the waitlist entry named in the address (#s-12), e.g. from the dashboard */
(() => {
  const target = location.hash && document.querySelector(location.hash + ' details');
  if (target) target.open = true;
})();
