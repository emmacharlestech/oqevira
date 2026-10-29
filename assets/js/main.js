/* =========================================================
   Oqevira — Coming Soon landing page
   ========================================================= */

/*
 * Launch configuration — the only place these need to change.
 *   appStoreUrl / googlePlayUrl: paste the real store listing URLs once published.
 *     While empty, both store buttons stay labelled "Coming soon" and do not link anywhere.
 *   signupEndpoint: the waitlist handler (waitlist.php on the oqevira.com host), which
 *     saves each sign-up and emails it to support@oqevira.com. Pages served from
 *     oqevira.com or localhost post to their own copy; other hosts (the Vercel mirror)
 *     post to oqevira.com.
 */
const OQ_SITE = /(^|\.)oqevira\.com$|^localhost$|^127\.0\.0\.1$/.test(location.hostname)
  ? new URL('.', location.href).href
  : 'https://oqevira.com/';
const OQ_CONFIG = {
  appStoreUrl: '',
  googlePlayUrl: '',
  signupEndpoint: OQ_SITE + 'waitlist.php',
  // Updates and gallery photos managed in /admin
  contentEndpoint: OQ_SITE + 'api/content.php'
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
    // Hidden while the hero, a waitlist button or form, the store section or the footer is on screen.
    const hideWhen = ['.hero', '.seven__cta', '#updates', '#download', '.site-footer'].map((s) => document.querySelector(s));
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

  /* ---------- Waitlist ---------- */
  const form = document.querySelector('[data-waitlist]');
  if (form) {
    const msg = form.querySelector('#signup-msg');
    const btn = form.querySelector('button[type="submit"]');
    const done = document.querySelector('[data-waitlist-done]');
    const extras = form.querySelectorAll('[data-role-extra]');
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    const icons = { error: 'i-alert', success: 'i-check', info: 'i-info' };

    form.querySelector('[data-started]').value = String(Date.now());

    const show = (type, text) => {
      msg.className = `signup__msg is-${type}`;
      msg.innerHTML = `<svg aria-hidden="true"><use href="#${icons[type]}"/></svg><span></span>`;
      msg.querySelector('span').textContent = text;
    };
    const clearMsg = () => { msg.className = 'signup__msg'; msg.textContent = ''; };

    // Each rule returns an error message, or '' when the field is fine.
    const rules = {
      name: (v) => (v.length < 2 ? 'Please enter your full name.' : ''),
      email: (v) => (!v ? 'Please enter your email address.' : !EMAIL_RE.test(v) ? 'That email doesn’t look quite right.' : ''),
      phone: (v) => {
        const digits = v.replace(/\D/g, '').length;
        if (!v) return 'Please enter your phone number.';
        return /^[0-9+()\-.\s]+$/.test(v) && digits >= 7 && digits <= 15 ? '' : 'Please enter a valid phone number, including the country code.';
      },
      location: (v) => (v.length < 2 ? 'Please tell us your city and country.' : ''),
      guide_area: (v) => (v.length < 2 ? 'Please tell us where you guide.' : ''),
      business_name: (v) => (v.length < 2 ? 'Please enter your business name.' : ''),
      business_type: (v) => (!v ? 'Please choose your type of business.' : '')
    };
    const errorFor = (name) => {
      if (name === 'role') return form.elements.role.value ? '' : 'Please choose how you’ll use Oqevira.';
      if (name === 'consent') return form.elements.consent.checked ? '' : 'Please tick this box so we can contact you about your place on the waitlist.';
      const el = form.elements[name];
      return rules[name] ? rules[name](el.value.trim()) : '';
    };
    const errEl = (name) => ({
      name: 'wl-name-err', email: 'wl-email-err', phone: 'wl-phone-err', location: 'wl-location-err',
      role: 'wl-role-err', consent: 'wl-consent-err', guide_area: 'wl-guide-area-err',
      business_name: 'wl-biz-name-err', business_type: 'wl-biz-type-err'
    })[name];
    const setError = (name, text) => {
      const out = document.getElementById(errEl(name));
      if (out) out.textContent = text;
      const el = form.elements[name];
      if (el && !(el instanceof RadioNodeList)) el.toggleAttribute('aria-invalid', Boolean(text));
      if (name === 'role') form.querySelectorAll('input[name="role"]').forEach((r) => r.toggleAttribute('aria-invalid', Boolean(text)));
    };

    // Fields that must be filled: the core ones plus whatever the chosen role reveals.
    const requiredNames = () => {
      const names = ['name', 'email', 'phone', 'location', 'role'];
      extras.forEach((box) => {
        if (!box.hidden) box.querySelectorAll('[data-required-when-shown]').forEach((el) => names.push(el.name));
      });
      return [...names, 'consent'];
    };

    const syncRole = () => {
      extras.forEach((box) => {
        const on = box.dataset.roleExtra === form.elements.role.value;
        box.hidden = !on;
        box.querySelectorAll('[data-required-when-shown]').forEach((el) => {
          el.required = on;
          if (!on) setError(el.name, '');
        });
      });
    };
    form.querySelectorAll('input[name="role"]').forEach((r) => r.addEventListener('change', () => { syncRole(); setError('role', ''); }));

    // Once a field has shown an error, re-check it as the visitor fixes it.
    form.addEventListener('input', (e) => {
      const name = e.target.name;
      if (errEl(name) && document.getElementById(errEl(name))?.textContent) setError(name, errorFor(name));
    });
    form.addEventListener('change', (e) => {
      if (e.target.name === 'consent') setError('consent', errorFor('consent'));
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearMsg();

      let firstBad = null;
      requiredNames().forEach((name) => {
        const text = errorFor(name);
        setError(name, text);
        if (text && !firstBad) firstBad = name;
      });
      if (firstBad) {
        show('error', 'Please check the highlighted fields and try again.');
        const el = firstBad === 'role' ? form.querySelector('input[name="role"]') : form.elements[firstBad];
        el.focus();
        return;
      }

      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
      try {
        const res = await fetch(OQ_CONFIG.signupEndpoint, {
          method: 'POST',
          headers: { Accept: 'application/json' },
          body: new FormData(form)
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          if (data.field && errEl(data.field)) {
            setError(data.field, data.error);
            form.elements[data.field]?.focus?.();
          }
          show('error', data.error || 'Something went wrong on our side. Please try again in a moment.');
          return;
        }
        const first = form.elements.name.value.trim().split(/\s+/)[0];
        done.querySelector('[data-done-title]').textContent = data.already
          ? `You're already on the list, ${first}!`
          : `You're on the list, ${first}!`;
        form.reset();
        syncRole();
        form.hidden = true;
        done.hidden = false;
        done.focus();
      } catch (err) {
        show('error', 'We couldn’t reach the server. Check your connection and try again, or email support@oqevira.com.');
      } finally {
        btn.classList.remove('is-loading');
        btn.removeAttribute('aria-busy');
      }
    });
  }

  /* ---------- Admin-managed content: latest updates + destination gallery ---------- */
  const imgUrl = (path) => new URL(path, OQ_SITE).href;
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  // Plain text → paragraphs with clickable https links (text is escaped first).
  const richText = (text) => text.trim().split(/\n{2,}/).map((p) =>
    `<p>${esc(p).replace(/\n/g, '<br>').replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')}</p>`).join('');

  const renderNews = (updates) => {
    const section = document.querySelector('[data-news]');
    const list = document.querySelector('[data-news-list]');
    const reader = document.querySelector('[data-reader]');
    if (!section || !updates.length) return;

    list.innerHTML = updates.map((u, i) => `
      <li class="post">
        <button type="button" class="post__btn" data-post="${i}">
          ${u.image ? `<span class="post__img"><img src="${esc(imgUrl(u.thumb || u.image))}" alt="" loading="lazy"></span>` : ''}
          <span class="post__body">
            <span class="post__date">${esc(u.date)}</span>
            <span class="post__title">${esc(u.title)}</span>
            <span class="post__text">${esc(u.body.length > 160 ? u.body.slice(0, 160).trim() + '…' : u.body)}</span>
            <span class="post__more">Read more <svg aria-hidden="true"><use href="#i-arrow"/></svg></span>
          </span>
        </button>
      </li>`).join('');
    section.hidden = false;
    document.querySelector('[data-news-link]')?.removeAttribute('hidden');

    list.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-post]');
      if (!btn || !reader) return;
      const u = updates[+btn.dataset.post];
      const img = reader.querySelector('[data-reader-img]');
      img.hidden = !u.image;
      if (u.image) img.src = imgUrl(u.image);
      reader.querySelector('[data-reader-date]').textContent = u.date;
      reader.querySelector('[data-reader-title]').textContent = u.title;
      reader.querySelector('[data-reader-text]').innerHTML = richText(u.body);
      reader.showModal();
      doc.classList.add('reader-open');
    });
    reader?.addEventListener('close', () => doc.classList.remove('reader-open'));
    reader?.querySelector('[data-reader-close]').addEventListener('click', () => reader.close());
    // A click on the dimmed backdrop (outside the panel) closes it
    reader?.addEventListener('click', (e) => { if (e.target === reader) reader.close(); });
  };

  const renderGallery = (items) => {
    if (!track || !items.length) return;
    track.innerHTML = items.map((g) => `
      <li class="dest"><img src="${esc(imgUrl(g.image))}" alt="${esc(g.alt || g.title)}" loading="lazy"><p><strong>${esc(g.title)}</strong>${g.subtitle ? `<span>${esc(g.subtitle)}</span>` : ''}</p></li>`).join('');
    track.scrollLeft = 0;
    track.dispatchEvent(new Event('scroll'));
  };

  fetch(OQ_CONFIG.contentEndpoint, { headers: { Accept: 'application/json' } })
    .then((res) => (res.ok ? res.json() : null))
    .then((data) => {
      if (!data?.ok) return;
      renderNews(data.updates || []);
      renderGallery(data.gallery || []);
    })
    .catch(() => { /* keep the built-in destinations if the content service is unreachable */ });

  /* ---------- Footer year ---------- */
  const year = document.querySelector('[data-year]');
  if (year) year.textContent = new Date().getFullYear();
})();
