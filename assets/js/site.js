/* COOP NATCCO Partylist - public website behaviour (vanilla JavaScript) */
(function () {
  'use strict';
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  var focusEl = document.querySelector('[data-focus]');
  if (focusEl) { focusEl.focus(); }

  /* Mobile navigation */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('siteNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = !nav.classList.contains('open');
      nav.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  /* Dropdown menus: click/tap and keyboard, close on outside click and Escape */
  var subs = $$('.has-sub');
  function closeAll(except) {
    subs.forEach(function (li) {
      if (li !== except) { li.classList.remove('open'); var b = li.querySelector('.sub-toggle'); if (b) { b.setAttribute('aria-expanded', 'false'); } }
    });
  }
  subs.forEach(function (li) {
    var btn = li.querySelector('.sub-toggle');
    btn.addEventListener('click', function () {
      var open = !li.classList.contains('open');
      closeAll(li);
      li.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    li.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { li.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
    });
    li.addEventListener('focusout', function (e) {
      if (window.matchMedia('(min-width: 1081px)').matches && !li.contains(e.relatedTarget)) { li.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); }
    });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.has-sub')) { closeAll(null); } });

  /* Cascading region/province/city selects on public forms */
  $$('select[data-geo="region"]').forEach(function (r) {
    var g = r.dataset.geoGroup;
    var c = document.querySelector('select[data-geo="city"][data-geo-group="' + g + '"]');
    var p = document.querySelector('select[data-geo="province"][data-geo-group="' + g + '"]');
    function fill(sel, items) {
      var ph = sel.options[0] ? sel.options[0].textContent : '';
      sel.textContent = '';
      var o = document.createElement('option'); o.value = ''; o.textContent = ph; sel.appendChild(o);
      items.forEach(function (it) { var x = document.createElement('option'); x.value = it.id; x.textContent = it.name; sel.appendChild(x); });
    }
    function load(type, params, sel) {
      var q = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k]); }).join('&');
      fetch(base + '/api/geo.php?type=' + type + '&' + q, { credentials: 'same-origin' }).then(function (res) { return res.json(); }).then(function (d) { fill(sel, d.items || []); });
    }
    r.addEventListener('change', function () {
      if (p) { load('provinces', { region: r.value }, p); }
      if (c) { load('cities', { region: r.value }, c); }
    });
    if (p && c) { p.addEventListener('change', function () { load('cities', { region: r.value, province: p.value }, c); }); }
  });

  /* Gallery lightbox */
  var lb = document.getElementById('lightbox');
  if (lb && typeof lb.showModal === 'function') {
    var img = lb.querySelector('img');
    var cap = lb.querySelector('p');
    $$('[data-lightbox]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        img.src = a.getAttribute('href');
        img.alt = a.dataset.caption || '';
        cap.textContent = a.dataset.caption || '';
        lb.showModal();
      });
    });
    lb.addEventListener('click', function (e) { if (e.target === lb) { lb.close(); } });
  }

  /* Character counters for long text fields */
  $$('textarea[maxlength]').forEach(function (t) {
    var max = parseInt(t.getAttribute('maxlength'), 10);
    if (max < 500) { return; }
    var note = document.createElement('p');
    note.className = 'help';
    note.setAttribute('aria-live', 'polite');
    function upd() { note.textContent = (max - t.value.length) + ' characters left'; }
    t.addEventListener('input', upd);
    upd();
    t.parentNode.appendChild(note);
  });

  /* The service worker and the installable app are handled by pwa.js. */
})();
