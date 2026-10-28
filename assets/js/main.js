/* =========================================================
   Oqevira — Coming Soon landing page
   ========================================================= */

/*
 * Launch configuration — the only place these need to change.
 *   appStoreUrl / googlePlayUrl: paste the real store listing URLs once published.
 *     While empty, both store buttons stay labelled "Coming soon" and do not link anywhere.
 *   signupEndpoint: URL of the approved email service / backend that accepts
 *     POST { email } as JSON. While empty, the form validates input but saves nothing
 *     and tells the visitor so.
 */
const OQ_CONFIG = {
  appStoreUrl: '',
  googlePlayUrl: '',
  signupEndpoint: ''
};

(() => {
  const doc = document.documentElement;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ---------- Header: solid after leaving the top ---------- */
  const header = document.querySelector('[data-header]');
  const setHeader = () => header.classList.toggle('is-solid', window.scrollY > 24);
  setHeader();
  window.addEventListener('scroll', setHeader, { passive: true });

  /* ---------- Mobile menu ---------- */
  const toggle = document.querySelector('[data-nav-toggle]');
  const toggleLabel = document.querySelector('[data-nav-label]');
  const nav = document.getElementById('site-nav');

  const setMenu = (open) => {
    doc.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggleLabel.textContent = open ? 'Close menu' : 'Open menu';
  };
  toggle.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
  nav.addEventListener('click', (e) => { if (e.target.closest('a')) setMenu(false); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && doc.classList.contains('nav-open')) { setMenu(false); toggle.focus(); }
  });
  window.matchMedia('(min-width: 961px)').addEventListener('change', (e) => { if (e.matches) setMenu(false); });
  document.querySelector('[data-nav-backdrop]')?.addEventListener('click', () => setMenu(false));

  /* ---------- Phones: sticky launch-updates bar after the hero ---------- */
  const mobileCta = document.querySelector('[data-mobile-cta]');
  if (mobileCta && 'IntersectionObserver' in window) {
    // Hidden while the hero, the signup form, the store section or the footer is on screen.
    const hideWhen = ['.hero', '#updates', '#download', '.site-footer'].map((s) => document.querySelector(s));
    const onScreen = new Set();
    const ctaIo = new IntersectionObserver((entries) => {
      entries.forEach((en) => (en.isIntersecting ? onScreen.add(en.target) : onScreen.delete(en.target)));
      mobileCta.classList.toggle('is-on', onScreen.size === 0);
    });
    hideWhen.forEach((el) => el && ctaIo.observe(el));
  }

  /* ---------- Reveal on scroll ---------- */
  const revealEls = document.querySelectorAll('[data-reveal]');
  if (reduceMotion.matches || !('IntersectionObserver' in window)) {
    revealEls.forEach((el) => el.classList.add('is-visible'));
  } else {
    // Hero content animates in straight away rather than waiting on the observer.
    const heroEls = document.querySelectorAll('.hero [data-reveal]');
    requestAnimationFrame(() => requestAnimationFrame(() => heroEls.forEach((el) => el.classList.add('is-visible'))));
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    revealEls.forEach((el) => { if (!el.closest('.hero')) io.observe(el); });
  }

  /* ---------- Gentle hero parallax (larger screens, motion allowed) ---------- */
  const heroImg = document.querySelector('[data-parallax]');
  if (heroImg && !reduceMotion.matches) {
    let ticking = false;
    const update = () => {
      const y = window.scrollY;
      if (y < window.innerHeight * 1.2 && window.innerWidth > 760) {
        heroImg.style.transform = `translate3d(0, ${y * 0.22}px, 0) scale(1.04)`;
      }
      ticking = false;
    };
    window.addEventListener('scroll', () => {
      if (!ticking) { requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
  }

  /* ---------- Store buttons ---------- */
  const storeUrls = { ios: OQ_CONFIG.appStoreUrl, android: OQ_CONFIG.googlePlayUrl };
  document.querySelectorAll('[data-store]').forEach((btn) => {
    const url = storeUrls[btn.dataset.store];
    if (url) {
      btn.href = url;
      btn.removeAttribute('aria-disabled');
      btn.removeAttribute('role');
      btn.removeAttribute('tabindex');
      btn.setAttribute('rel', 'noopener');
      btn.querySelector('[data-store-soon]')?.remove();
    } else {
      const store = btn.dataset.store === 'ios' ? 'App Store' : 'Google Play';
      btn.setAttribute('aria-label', `${store} — coming soon, not yet available`);
      const notice = () => toast(`Oqevira isn't on ${store} yet. Get launch updates to hear first.`);
      btn.addEventListener('click', (e) => { e.preventDefault(); notice(); });
      btn.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); notice(); } });
    }
  });

  /* ---------- Placeholder links (contact, legal, social) ---------- */
  document.querySelectorAll('a[data-placeholder]').forEach((a) => {
    if (a.getAttribute('href') !== '#') return;
    a.addEventListener('click', (e) => { e.preventDefault(); toast('This link is coming soon.'); });
  });

  /* ---------- Toast ---------- */
  const toastEl = document.querySelector('[data-toast]');
  let toastTimer;
  function toast(message) {
    toastEl.textContent = message;
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-on'), 3600);
  }

  /* ---------- Destination ribbon controls ---------- */
  const track = document.querySelector('[data-ribbon]');
  const prev = document.querySelector('[data-ribbon-prev]');
  const next = document.querySelector('[data-ribbon-next]');
  if (track) {
    const step = () => (track.querySelector('.dest')?.offsetWidth || 280) + 18;
    const sync = () => {
      prev.disabled = track.scrollLeft <= 4;
      next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    };
    prev.addEventListener('click', () => track.scrollBy({ left: -step() }));
    next.addEventListener('click', () => track.scrollBy({ left: step() }));
    track.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    sync();
  }

  /* ---------- Card carousel dots (phones) ---------- */
  const cardRow = document.querySelector('[data-cards]');
  const dotsEl = document.querySelector('[data-dots]');
  if (cardRow && dotsEl) {
    const cards = [...cardRow.children];
    dotsEl.innerHTML = cards.map(() => '<i></i>').join('');
    const dots = [...dotsEl.children];
    const syncDots = () => {
      const rowLeft = cardRow.getBoundingClientRect().left + parseFloat(getComputedStyle(cardRow).paddingLeft);
      let active = 0, best = Infinity;
      cards.forEach((c, i) => {
        const d = Math.abs(c.getBoundingClientRect().left - rowLeft);
        if (d < best) { best = d; active = i; }
      });
      // At the end of the row, the last card is the active one.
      if (cardRow.scrollLeft + cardRow.clientWidth >= cardRow.scrollWidth - 4) active = cards.length - 1;
      dots.forEach((dot, i) => dot.classList.toggle('is-on', i === active));
    };
    cardRow.addEventListener('scroll', () => requestAnimationFrame(syncDots), { passive: true });
    syncDots();
  }

  /* ---------- Launch signup ---------- */
  const form = document.querySelector('[data-signup]');
  if (form) {
    const input = form.querySelector('input[type="email"]');
    const msg = form.querySelector('#signup-msg');
    const btn = form.querySelector('button[type="submit"]');
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    const icons = { error: 'i-alert', success: 'i-check', info: 'i-info' };

    const show = (type, text) => {
      msg.className = `signup__msg is-${type}`;
      msg.innerHTML = `<svg aria-hidden="true"><use href="#${icons[type]}"/></svg><span></span>`;
      msg.querySelector('span').textContent = text;
    };
    const clear = () => { msg.className = 'signup__msg'; msg.textContent = ''; input.removeAttribute('aria-invalid'); };

    input.addEventListener('input', () => { if (input.getAttribute('aria-invalid')) clear(); });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = input.value.trim();

      if (!email) {
        input.setAttribute('aria-invalid', 'true');
        show('error', 'Please enter your email address.');
        input.focus();
        return;
      }
      if (!EMAIL_RE.test(email)) {
        input.setAttribute('aria-invalid', 'true');
        show('error', 'That email doesn’t look quite right. Please check it and try again.');
        input.focus();
        return;
      }
      input.removeAttribute('aria-invalid');

      // No approved email service is connected yet: be honest, save nothing.
      if (!OQ_CONFIG.signupEndpoint) {
        show('info', 'Launch updates aren’t open just yet, so we haven’t saved your email. Please check back soon.');
        return;
      }

      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
      try {
        const res = await fetch(OQ_CONFIG.signupEndpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ email })
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        form.reset();
        show('success', 'You’re on the list. We’ll email you when Oqevira launches.');
      } catch (err) {
        show('error', 'Something went wrong on our side. Please try again in a moment.');
      } finally {
        btn.classList.remove('is-loading');
        btn.removeAttribute('aria-busy');
      }
    });
  }

  /* ---------- Footer year ---------- */
  const year = document.querySelector('[data-year]');
  if (year) year.textContent = new Date().getFullYear();
})();
