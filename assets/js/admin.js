/* COOP NATCCO Partylist - administration UI (vanilla JavaScript, no inline scripts) */
(function () {
  'use strict';
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function el(tag, attrs, text) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (text !== undefined) { n.textContent = text; }
    return n;
  }

  /* Sidebar (mobile) */
  var sidebar = $('#sidebar');
  var backdrop = $('.sb-backdrop');
  var opener = $('[data-sidebar-open]');
  function setSidebar(open) {
    if (!sidebar) { return; }
    sidebar.classList.toggle('open', open);
    if (backdrop) { backdrop.hidden = !open; }
    if (opener) { opener.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    if (open) { var a = $('a', sidebar); if (a) { a.focus(); } } else if (opener) { opener.focus(); }
  }
  if (opener) { opener.addEventListener('click', function () { setSidebar(true); }); }
  $$('[data-sidebar-close]').forEach(function (b) { b.addEventListener('click', function () { setSidebar(false); }); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) { setSidebar(false); }
  });
  var userMenu = $('.tb-user');
  document.addEventListener('click', function (e) { if (userMenu && userMenu.open && !userMenu.contains(e.target)) { userMenu.open = false; } });

  /* Focus the error summary */
  var focusEl = $('[data-focus]');
  if (focusEl) { focusEl.focus(); }

  /* Confirmation dialogs */
  var dlg = $('#confirmDialog');
  $$('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmed === '1' || !dlg || typeof dlg.showModal !== 'function') { return; }
      e.preventDefault();
      $('#confirmText').textContent = form.dataset.confirm;
      var remarksInput = $('input[data-remarks]', form);
      var box = $('.cd-remarks', dlg);
      var field = $('#confirmRemarks');
      box.hidden = !remarksInput;
      field.value = '';
      if (remarksInput) { $('label', box).textContent = remarksInput.dataset.remarks; }
      dlg.returnValue = '';
      dlg.showModal();
      dlg.addEventListener('close', function onClose() {
        dlg.removeEventListener('close', onClose);
        if (dlg.returnValue === 'ok') {
          if (remarksInput) { remarksInput.value = field.value; }
          form.dataset.confirmed = '1';
          if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
        }
      });
    });
  });

  /* Unsaved changes warning */
  $$('form[data-unsaved]').forEach(function (form) {
    var dirty = false;
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });

  /* Prevent double submit */
  $$('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) { return; }
      setTimeout(function () { $$('button[type=submit]', form).forEach(function (b) { b.disabled = true; }); }, 0);
      setTimeout(function () { $$('button[type=submit]', form).forEach(function (b) { b.disabled = false; }); }, 8000);
    });
  });

  /* Cascading region > province > city selects */
  function fillSelect(sel, items, placeholder) {
    var keep = sel.value;
    sel.textContent = '';
    sel.appendChild(el('option', { value: '' }, placeholder));
    items.forEach(function (it) { sel.appendChild(el('option', { value: String(it.id) }, it.name)); });
    if (items.some(function (it) { return String(it.id) === keep; })) { sel.value = keep; }
  }
  function geoFetch(params) {
    var q = Object.keys(params).map(function (k) { return k + '=' + encodeURIComponent(params[k] || ''); }).join('&');
    return fetch(base + '/api/geo.php?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  var groups = {};
  $$('[data-geo]').forEach(function (s) {
    var g = s.dataset.geoGroup || 'g';
    var form = s.form ? (s.form.getAttribute('action') || '') + (s.form.className || '') : '';
    var key = g + '|' + form;
    groups[key] = groups[key] || {};
    groups[key][s.dataset.geo] = s;
  });
  Object.keys(groups).forEach(function (k) {
    var grp = groups[k];
    var r = grp.region, p = grp.province, c = grp.city;
    var pPh = p ? (p.options[0] ? p.options[0].textContent : '') : '';
    var cPh = c ? (c.options[0] ? c.options[0].textContent : '') : '';
    function loadCities() {
      if (!c) { return; }
      if (!r || !r.value) { fillSelect(c, [], cPh); return; }
      geoFetch({ type: 'cities', region: r.value, province: p ? p.value : '' }).then(function (d) { fillSelect(c, d.items || [], cPh); });
    }
    if (r) {
      r.addEventListener('change', function () {
        if (p) {
          if (!r.value) { fillSelect(p, [], pPh); loadCities(); return; }
          geoFetch({ type: 'provinces', region: r.value }).then(function (d) { p.value = ''; fillSelect(p, d.items || [], pPh); loadCities(); });
        } else { loadCities(); }
      });
    }
    if (p) { p.addEventListener('change', loadCities); }
  });

  /* Search-as-you-type lookups (cooperative, person, user) */
  $$('[data-lookup]').forEach(function (box) {
    var type = box.dataset.lookup;
    var input = $('.lookup-input', box);
    var hidden = $('.lookup-value', box);
    var list = $('.lookup-list', box);
    var timer = null;
    var active = -1;
    var listId = 'lk-' + Math.random().toString(36).slice(2);
    list.id = listId;
    input.setAttribute('aria-controls', listId);
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
    function choose(li) {
      hidden.value = li.dataset.id;
      input.value = li.textContent;
      input.dataset.chosen = li.textContent;
      close();
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }
    function render(items) {
      list.textContent = '';
      if (!items.length) { list.appendChild(el('li', { class: 'lk-empty', role: 'option', 'aria-disabled': 'true' }, 'No matches')); }
      items.forEach(function (it, i) {
        var li = el('li', { role: 'option', id: listId + '-' + i, 'data-id': String(it.id) }, it.label);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(li); });
        list.appendChild(li);
      });
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      active = -1;
    }
    input.addEventListener('input', function () {
      if (input.value !== input.dataset.chosen) { hidden.value = ''; }
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { close(); return; }
      timer = setTimeout(function () {
        fetch(base + '/admin/api/lookup.php?type=' + encodeURIComponent(type) + '&q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.json(); }).then(function (d) { render(d.items || []); }).catch(close);
      }, 220);
    });
    input.addEventListener('keydown', function (e) {
      var items = $$('li[data-id]', list);
      if (list.hidden || !items.length) { return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach(function (li, i) { li.setAttribute('aria-selected', i === active ? 'true' : 'false'); });
        input.setAttribute('aria-activedescendant', items[active].id);
      } else if (e.key === 'Enter' && active >= 0) {
        e.preventDefault();
        choose(items[active]);
      } else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', function () { setTimeout(close, 150); });
    if (input.value) { input.dataset.chosen = input.value; }
  });

  /* Person field: existing vs new */
  $$('[data-person-field]').forEach(function (wrap) {
    function sync() {
      var mode = ($('input[name=person_mode]:checked', wrap) || {}).value;
      $('.person-existing', wrap).hidden = mode === 'new';
      $('.person-new', wrap).hidden = mode !== 'new';
    }
    $$('input[name=person_mode]', wrap).forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  });

  /* Minimal rich text editor (server sanitizes everything on save) */
  $$('textarea[data-rich]').forEach(function (ta) {
    var wrap = el('div', { class: 'rte' });
    var bar = el('div', { class: 'rte-bar', role: 'toolbar', 'aria-label': 'Formatting' });
    var area = el('div', { class: 'rte-area', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'data-placeholder': 'Start writing…' });
    var lbl = ta.id ? $('label[for="' + ta.id + '"]') : null;
    if (lbl) { lbl.id = lbl.id || ta.id + '_lbl'; area.setAttribute('aria-labelledby', lbl.id); lbl.addEventListener('click', function () { area.focus(); }); }
    var tools = [['B', 'bold', 'Bold'], ['I', 'italic', 'Italic'], ['H2', 'formatBlock:h2', 'Heading'], ['H3', 'formatBlock:h3', 'Subheading'], ['P', 'formatBlock:p', 'Paragraph'],
      ['• List', 'insertUnorderedList', 'Bulleted list'], ['1. List', 'insertOrderedList', 'Numbered list'], ['Quote', 'formatBlock:blockquote', 'Quote'], ['Link', 'link', 'Insert link'],
      ['Unlink', 'unlink', 'Remove link'], ['Clear', 'removeFormat', 'Clear formatting']];
    tools.forEach(function (t) {
      var b = el('button', { type: 'button', title: t[2], 'aria-label': t[2] }, t[0]);
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        area.focus();
        if (t[1] === 'link') {
          var u = window.prompt('Link address (https://…)', 'https://');
          if (u && /^(https?:\/\/|mailto:|\/)/i.test(u)) { document.execCommand('createLink', false, u); }
        } else if (t[1].indexOf('formatBlock:') === 0) {
          document.execCommand('formatBlock', false, t[1].split(':')[1]);
        } else { document.execCommand(t[1], false, null); }
        sync();
      });
      bar.appendChild(b);
    });
    area.innerHTML = ta.value; // existing value was sanitized by the server when saved
    function sync() { ta.value = area.innerHTML.trim() === '<br>' ? '' : area.innerHTML; ta.dispatchEvent(new Event('input', { bubbles: true })); }
    area.addEventListener('input', sync);
    area.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      var html = text.split(/\n{2,}/).map(function (p) {
        return '<p>' + p.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p>';
      }).join('');
      document.execCommand('insertHTML', false, html);
      sync();
    });
    ta.hidden = true;
    ta.removeAttribute('required');
    wrap.appendChild(bar);
    wrap.appendChild(area);
    ta.parentNode.insertBefore(wrap, ta);
    if (ta.form) { ta.form.addEventListener('submit', sync); }
  });

  /* Progress bars */
  $$('[data-width]').forEach(function (b) { b.style.width = Math.max(0, Math.min(100, parseInt(b.dataset.width, 10) || 0)) + '%'; });

  /* Batched import runner */
  $$('form[data-import-runner]').forEach(function (form) {
    var bar = $('.progress-bar');
    var txt = $('.progress-text');
    var prog = $('.progress');
    function step() {
      var fd = new FormData(form);
      return fetch(form.action || window.location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf, Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (bar) { bar.style.width = d.percent + '%'; }
          if (prog) { prog.setAttribute('aria-valuenow', d.percent); }
          if (txt) { txt.textContent = d.percent + '% complete'; }
          if (d.done) { window.location.href = form.dataset.doneUrl; return null; }
          return step();
        });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      $$('button', form).forEach(function (b) { b.disabled = true; b.textContent = 'Importing…'; });
      step().catch(function () { if (txt) { txt.textContent = 'Processing stopped. Reload the page to continue.'; } });
    });
    form.dispatchEvent(new Event('submit', { cancelable: true }));
  });

  /* MFA QR code */
  var qr = $('[data-qr]');
  if (qr && window.qrcode) {
    try {
      var code = window.qrcode(0, 'M');
      code.addData(qr.dataset.qr);
      code.make();
      var img = el('img', { alt: 'QR code for your authenticator app', src: code.createDataURL(5, 2) });
      qr.textContent = '';
      qr.appendChild(img);
    } catch (err) { qr.textContent = 'Enter the key manually.'; }
  }

  /* Select-all checkboxes */
  $$('[data-check-all]').forEach(function (cb) {
    cb.addEventListener('change', function () { $$(cb.dataset.checkAll).forEach(function (x) { x.checked = cb.checked; }); });
  });

  /* Print buttons */
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
})();

// Filter panel toggle on small screens
document.querySelectorAll('.filter-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var box = document.getElementById(btn.getAttribute('aria-controls'));
    if (!box) return;
    var open = !box.classList.contains('open');
    box.classList.toggle('open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
});
