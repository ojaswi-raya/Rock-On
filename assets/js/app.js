/* RockOn — progressive enhancement. Every page works without JavaScript except the confirmation dialogs
   and the map's zoom/pop-ups; this file adds those plus small conveniences. No libraries. */
(function () {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

  // ---------- Mobile nav + account menu ----------
  const burger = $('.nav-burger');
  if (burger) {
    burger.addEventListener('click', () => {
      const open = $('#main-links').classList.toggle('open');
      burger.setAttribute('aria-expanded', String(open));
    });
  }
  $$('[data-menu]').forEach((menu) => {
    const btn = $('.menu-btn', menu);
    const pop = $('.menu-pop', menu);
    const set = (open) => { pop.hidden = !open; btn.setAttribute('aria-expanded', String(open)); };
    btn.addEventListener('click', (e) => { e.stopPropagation(); set(pop.hidden); });
    document.addEventListener('click', (e) => { if (!menu.contains(e.target)) set(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') set(false); });
  });

  // ---------- Demo bar can be hidden for this browser session ----------
  const strip = $('[data-demo-strip]');
  try { if (strip && sessionStorage.getItem('rockon-demo-hidden') === '1') strip.hidden = true; } catch (e) { /* ignore */ }
  $$('[data-hide-demo]').forEach((b) => b.addEventListener('click', () => {
    strip.hidden = true;
    try { sessionStorage.setItem('rockon-demo-hidden', '1'); } catch (e) { /* ignore */ }
  }));

  // ---------- Toasts ----------
  $$('.toast').forEach((t) => {
    const close = () => t.remove();
    $('[data-dismiss]', t)?.addEventListener('click', close);
    setTimeout(close, 4500);
  });

  // ---------- Dialogs (native <dialog>; Esc closes; focus returns to the opener) ----------
  $$('[data-open]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const d = document.getElementById(btn.dataset.open);
      if (!d) return;
      d._opener = btn;
      if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
      (d.querySelector('textarea, input:not([type=hidden]), button') || d).focus();
    });
  });
  $$('dialog').forEach((d) => {
    $$('[data-close]', d).forEach((b) => b.addEventListener('click', () => d.close()));
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); }); // click on backdrop
    d.addEventListener('close', () => d._opener?.focus());
  });
  // Report dialog: a reason is required
  $$('form[data-require-radio]').forEach((f) => {
    f.addEventListener('submit', (e) => {
      const name = f.dataset.requireRadio;
      if (!f.querySelector(`input[name="${name}"]:checked`)) {
        e.preventDefault();
        const err = $('[data-radio-error]', f);
        if (err) { err.hidden = false; err.setAttribute('role', 'alert'); }
      }
    });
  });

  // ---------- Prevent double submits ----------
  // (runs after every other submit handler, so forms blocked by validation stay usable)
  $$('form[method="post"]').forEach((f) => f.addEventListener('submit', (e) => {
    setTimeout(() => { if (!e.defaultPrevented) $$('button[type=submit]', f).forEach((b) => { b.disabled = true; }); }, 0);
  }));

  // ---------- Focus the first invalid field after a server-side validation error ----------
  const focusForm = $('[data-focus]');
  if (focusForm) document.getElementById(focusForm.dataset.focus)?.focus();

  // ---------- Filters submit on change (search on Enter / pause) ----------
  $$('form[data-autosubmit]').forEach((f) => {
    let t;
    $$('select, input[type=checkbox]', f).forEach((el) => el.addEventListener('change', () => f.submit()));
    $$('input[type=search], input.search', f).forEach((el) => el.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => f.submit(), 700);
    }));
  });
  // keep the caret at the end of the search box after the reload
  const q = $('input.search');
  if (q && q.value) { q.focus(); q.setSelectionRange(q.value.length, q.value.length); }

  // ---------- Password show/hide, Caps Lock hint, length meter ----------
  $$('[data-toggle-pw]').forEach((b) => b.addEventListener('click', () => {
    const input = document.getElementById(b.dataset.togglePw);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.textContent = show ? 'Hide' : 'Show';
    b.setAttribute('aria-pressed', String(show));
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }));
  $$('[data-caps]').forEach((input) => input.addEventListener('keyup', (e) => {
    const hint = $('[data-caps-hint]');
    if (hint && e.getModifierState) hint.hidden = !e.getModifierState('CapsLock');
  }));
  $$('[data-pw-meter]').forEach((input) => input.addEventListener('input', () => {
    const out = $('[data-pw-meter-out]');
    const n = input.value.length;
    out.textContent = n === 0 ? '' : n < 12 ? ` · ${12 - n} more characters needed` : ' · Good length';
  }));
  $$('[data-count]').forEach((ta) => ta.addEventListener('input', () => {
    document.getElementById(ta.dataset.count).textContent = `${ta.maxLength - ta.value.length} characters left`;
  }));

  // ---------- Chat: start scrolled to the newest message ----------
  const log = $('[data-chat-log]');
  if (log) log.scrollTop = log.scrollHeight;

  // ---------- Hero image: low-resolution photos get a blurred backdrop instead of being stretched ----------
  $$('[data-hero] img').forEach((img) => {
    const check = () => { if (img.naturalWidth && img.naturalWidth < 500) img.parentElement.classList.add('low'); };
    if (img.complete) check(); else img.addEventListener('load', check);
  });

  // ---------- Rating scale: live "x / 5" readout ----------
  $$('.criterion').forEach((fs) => {
    const out = $('[data-scale-out]', fs);
    $$('input[type=radio]', fs).forEach((r) => r.addEventListener('change', () => { out.textContent = `${r.value} / 5`; }));
  });
  $$('form[data-rating-form]').forEach((f) => f.addEventListener('submit', (e) => {
    const missing = ['punctuality', 'respect', 'fun'].some((k) => !f.querySelector(`input[name=${k}]:checked`));
    if (missing) {
      e.preventDefault();
      let err = $('.alert.err', f);
      if (!err) { err = document.createElement('p'); err.className = 'alert err'; err.setAttribute('role', 'alert'); f.querySelector('.row').before(err); }
      err.textContent = 'Choose a score from 1 to 5 for each criterion.';
    }
  }));

  // ---------- Create / edit form: live preview ----------
  const pf = $('[data-preview-form]');
  if (pf) {
    const data = JSON.parse($('#pv-data').textContent);
    const DAY = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    const v = (n) => (pf.elements[n] ? pf.elements[n].value : '');
    const update = () => {
      $('[data-pv-title]').textContent = v('title') || 'Your activity title';
      const cat = $('[data-pv-cat]');
      cat.hidden = !v('categoryId');
      cat.textContent = data.categories[v('categoryId')] || '';
      const date = v('date') ? DAY.format(new Date(v('date') + 'T12:00')) : '—';
      const place = data.places[v('placeId')] || 'City Square';
      $('[data-pv-meta]').textContent = `${place}${v('meetingPoint') ? ' · ' + v('meetingPoint') : ''} · ${date} · ${v('startTime')}–${v('endTime')}`;
      $('[data-pv-cap]').textContent = v('capacity') || '?';
      const policy = pf.querySelector('input[name=joinPolicy]:checked')?.value;
      $('[data-pv-policy]').textContent = policy === 'open' ? 'Open joining' : 'Host approval';
      const mr = $('[data-pv-minrep]');
      mr.hidden = !v('minReputation');
      mr.textContent = v('minReputation') ? `Min. ${Number(v('minReputation')).toFixed(1)} ★` : '';
      $$('.choice', pf).forEach((c) => c.classList.toggle('selected', c.querySelector('input').checked));
    };
    pf.addEventListener('input', update);
    pf.addEventListener('change', update);
    update();
    // Photo preview
    pf.elements.photo?.addEventListener('change', (e) => {
      const f = e.target.files[0];
      if (!f || !f.type.startsWith('image/')) return;
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.alt = 'Selected photo';
      img.style.cssText = 'width:100%;height:200px;object-fit:cover;border-radius:14px';
      const box = $('[data-pv-image]');
      box.innerHTML = '';
      box.appendChild(img);
    });
  }

  // ---------- Explore map: zoom, pan, pop-ups, list ↔ map highlighting ----------
  const map = $('[data-map]');
  if (map) {
    const svg = $('svg', map);
    const pop = $('.map-pop', map);
    const W = 1000, H = 600;
    let view = { x: 0, y: 0, w: W, h: H };
    let selected = null;
    const apply = () => {
      svg.setAttribute('viewBox', `${view.x} ${view.y} ${view.w} ${view.h}`);
      const k = Math.pow(view.w / W, 0.6); // pins shrink a little less than the zoom
      $$('.pin', svg).forEach((p) => p.setAttribute('transform', `translate(${p.dataset.x} ${p.dataset.y}) scale(${k})`));
      placePop();
    };
    const zoom = (f) => {
      const cx = view.x + view.w / 2, cy = view.y + view.h / 2;
      const w = Math.min(W, Math.max(W / 4, view.w * f)), h = (w * H) / W;
      view = { w, h, x: Math.min(W - w, Math.max(0, cx - w / 2)), y: Math.min(H - h, Math.max(0, cy - h / 2)) };
      apply();
    };
    $$('[data-zoom]', map).forEach((b) => b.addEventListener('click', () => zoom(b.dataset.zoom === 'in' ? 1 / 1.4 : 1.4)));

    let drag = null;
    svg.addEventListener('pointerdown', (e) => {
      if (e.target.closest('.pin')) return;
      drag = { sx: e.clientX, sy: e.clientY, v: { ...view } };
      svg.setPointerCapture(e.pointerId);
    });
    svg.addEventListener('pointermove', (e) => {
      if (!drag) return;
      const r = svg.getBoundingClientRect();
      const s = Math.max(r.width / drag.v.w, r.height / drag.v.h);
      view = { ...drag.v, x: Math.min(W - drag.v.w, Math.max(0, drag.v.x - (e.clientX - drag.sx) / s)), y: Math.min(H - drag.v.h, Math.max(0, drag.v.y - (e.clientY - drag.sy) / s)) };
      apply();
    });
    const end = () => { drag = null; };
    svg.addEventListener('pointerup', end);
    svg.addEventListener('pointerleave', end);

    function placePop() {
      if (!selected) { pop.hidden = true; return; }
      const r = svg.getBoundingClientRect();
      const s = Math.max(r.width / view.w, r.height / view.h); // preserveAspectRatio="slice"
      const offX = (r.width - view.w * s) / 2, offY = (r.height - view.h * s) / 2;
      const px = (selected.dataset.x - view.x) * s + offX;
      const py = (selected.dataset.y - view.y) * s + offY;
      if (px < 0 || px > r.width || py < 0 || py > r.height) { pop.hidden = true; return; }
      pop.hidden = false;
      pop.style.left = Math.min(Math.max(px, 100), r.width - 100) + 'px';
      pop.style.top = py + 'px';
    }
    const select = (id) => {
      $$('.pin', svg).forEach((p) => {
        const on = p.dataset.id === id;
        p.setAttribute('aria-pressed', String(on));
        $('.dot', p).setAttribute('fill', on ? '#9e1f2c' : '#c4304a');
        if (on) selected = p;
      });
      if (!id) selected = null;
      $$('.act-card').forEach((c) => c.classList.toggle('hl', c.dataset.act === id));
      if (selected) {
        const a = $('a', pop);
        a.textContent = selected.dataset.title;
        a.href = selected.dataset.href;
        $('p', pop).textContent = selected.dataset.sub;
      }
      placePop();
    };
    $$('.pin', svg).forEach((p) => {
      p.addEventListener('click', () => select(p.dataset.id));
      p.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); select(p.dataset.id); } });
    });
    svg.addEventListener('click', (e) => { if (!e.target.closest('.pin')) select(null); });
    $$('.act-card').forEach((c) => {
      c.addEventListener('mouseenter', () => select(c.dataset.act));
      c.addEventListener('focusin', () => select(c.dataset.act));
    });
    window.addEventListener('resize', placePop);
    apply();
  }
})();
