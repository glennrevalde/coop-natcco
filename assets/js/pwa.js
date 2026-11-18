/*
 * Installable app (PWA): service worker registration and the "Install app" buttons.
 *
 * - sw.js is registered only on https:// or on localhost (browsers allow service workers nowhere else).
 * - When the browser offers the install itself (Chrome, Edge, Android), the button opens that prompt.
 *   Otherwise it shows the steps for the browser in use (iPhone: Share > Add to Home Screen, and so on).
 * - Inside the installed app the buttons are hidden.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var meta = document.querySelector('meta[name="base-url"]');
  var base = ((meta && meta.getAttribute('content')) || '').replace(/\/+$/, '') + '/';

  function mq(q) {
    return !!(window.matchMedia && window.matchMedia(q).matches);
  }
  var standalone = mq('(display-mode: standalone)') || mq('(display-mode: fullscreen)') || mq('(display-mode: minimal-ui)')
    || mq('(display-mode: window-controls-overlay)') || window.navigator.standalone === true;
  if (standalone) {
    root.classList.add('pwa-standalone');
  }

  var host = location.hostname;
  var isLocal = host === 'localhost' || host === '[::1]' || host === '::1' || /^127(\.\d{1,3}){3}$/.test(host) || /\.localhost$/.test(host);
  var secure = location.protocol === 'https:' || isLocal;

  /* ---------------------------------------------------------------- service worker */
  if ('serviceWorker' in navigator && secure) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () { /* the site still works without it */ });
    });
  }

  /* ---------------------------------------------------------------- install */
  var deferredPrompt = null;
  var installed = false;

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();          // keep the browser's own mini-bar away; our button opens the prompt
    deferredPrompt = e;
    root.classList.add('pwa-can-prompt');
  });

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    installed = true;
    root.classList.remove('pwa-can-prompt');
    var note = document.querySelector('#pwaDialog .pwa-installed-note');
    if (note) {
      note.hidden = false;
    }
  });

  /** Which instructions fit this browser. */
  function browserKey() {
    var ua = navigator.userAgent || '';
    var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (ios) {
      return 'ios';
    }
    if (/Android/i.test(ua)) {
      if (/SamsungBrowser/i.test(ua)) {
        return 'android-samsung';
      }
      if (/Firefox/i.test(ua)) {
        return 'android-firefox';
      }
      return 'android-chrome';
    }
    if (/Edg\//.test(ua)) {
      return 'edge';
    }
    if (/Firefox\//.test(ua)) {
      return 'firefox';
    }
    if (/Chrome\/|Chromium\//.test(ua) && !/OPR\//.test(ua)) {
      return 'chrome';
    }
    if (/Macintosh/.test(ua) && /Safari\//.test(ua)) {
      return 'safari-mac';
    }
    return 'other';
  }

  function showSteps() {
    var dialog = document.getElementById('pwaDialog');
    if (!dialog) {
      return;
    }
    var key = browserKey();
    var sections = dialog.querySelectorAll('[data-pwa-steps]');
    var more = dialog.querySelector('.pwa-more-list');
    var steps = dialog.querySelector('.pwa-steps');
    sections.forEach(function (s) {
      var k = s.getAttribute('data-pwa-steps');
      var main = (k === key) || (k === 'insecure' && !secure);
      s.hidden = false;
      s.classList.toggle('pwa-step-main', main);
      if (main) {
        steps.appendChild(s);
      } else if (k !== 'insecure') {
        more.appendChild(s);
      } else {
        s.hidden = true;
      }
    });
    // the secure-address notice goes first
    var insecure = dialog.querySelector('[data-pwa-steps="insecure"]');
    if (insecure && !secure) {
      steps.insertBefore(insecure, steps.firstChild);
    }
    dialog.querySelector('.pwa-installed-note').hidden = !installed;
    if (typeof dialog.showModal === 'function') {
      if (!dialog.open) {
        dialog.showModal();
      }
    } else {
      dialog.setAttribute('open', '');   // very old browsers without <dialog>
    }
    var close = dialog.querySelector('button[value="close"]');
    if (close) {
      close.focus();
    }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-pwa-install]') : null;
    if (btn) {
      e.preventDefault();
      if (deferredPrompt) {
        var p = deferredPrompt;
        deferredPrompt = null;   // a prompt can be used only once
        root.classList.remove('pwa-can-prompt');
        p.prompt();
        if (p.userChoice) {
          p.userChoice.then(function (choice) {
            if (choice && choice.outcome === 'accepted') {
              installed = true;
            }
          }).catch(function () {});
        }
        return;
      }
      showSteps();
      return;
    }
    // close the fallback dialog in browsers without <dialog> support
    var dialog = document.getElementById('pwaDialog');
    if (dialog && typeof dialog.showModal !== 'function' && e.target.closest && e.target.closest('#pwaDialog button[value="close"]')) {
      e.preventDefault();
      dialog.removeAttribute('open');
    }
  });

  // clicking the dimmed backdrop closes the dialog
  var dlg = document.getElementById('pwaDialog');
  if (dlg) {
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg && typeof dlg.close === 'function') {
        dlg.close();
      }
    });
  }
})();
