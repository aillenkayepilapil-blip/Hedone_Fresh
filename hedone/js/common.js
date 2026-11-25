/* =====================================================================
   Hedone Fresh – shared helpers (used by index.html and dashboard.html)
   ===================================================================== */
window.Hedone = (function () {
  'use strict';

  const $ = selector => document.querySelector(selector);

  /* ---------- talking to the server ---------- */
  async function csrfToken() {
    const response = await fetch('php/csrf.php');
    return (await response.json()).token;
  }

  // POST a FormData (the secret CSRF token is added automatically). Never throws.
  async function post(url, formData) {
    try {
      formData.append('csrf', await csrfToken());
      const response = await fetch(url, { method: 'POST', body: formData });
      return await response.json();
    } catch (problem) {
      return { ok: false, error: 'Cannot reach the server. Open the site at http://localhost//11-18-Hedone/hedone/ (Apache must be running).', network: true };
    }
  }

  async function get(url) {
    try {
      const response = await fetch(url);
      const data = await response.json();
      data.status = response.status;
      return data;
    } catch (problem) {
      return { ok: false, error: 'Cannot reach the server.', network: true };
    }
  }

  /* ---------- toast notifications ---------- */
  // options: { kind: 'success' | 'warn' | 'info', sticky: true, action: { label, run } }
  function toast(title, message, options) {
    options = options || {};
    let box = $('#toasts');

    if (!box) {
      box = document.createElement('div');
      box.id = 'toasts';
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      document.body.appendChild(box);
    }

    const element = document.createElement('div');
    element.className = 'toast ' + (options.kind || 'success');
    element.innerHTML =
      '<span class="toast-icon" aria-hidden="true">' + (options.kind === 'warn' ? '!' : options.kind === 'info' ? 'i' : '✓') + '</span>' +
      '<div class="toast-text"><strong></strong><span></span></div>' +
      '<button type="button" class="toast-close" aria-label="Dismiss">×</button>';

    // textContent (not innerHTML) so nothing typed by a user can inject code
    element.querySelector('strong').textContent = title;
    element.querySelector('.toast-text span').textContent = message;

    const dismiss = () => {
      element.classList.add('leaving');
      setTimeout(() => element.remove(), 300);
    };

    element.querySelector('.toast-close').addEventListener('click', dismiss);

    if (options.action) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'toast-action';
      button.textContent = options.action.label;
      button.addEventListener('click', () => { options.action.run(); dismiss(); });
      element.querySelector('.toast-text').appendChild(button);
    }

    box.appendChild(element);
    if (!options.sticky) setTimeout(dismiss, 5000);
    return { dismiss };
  }

  /* ---------- idle timeout: warning 1 minute before, then automatic log-out ---------- */
  let warnTimer, endTimer, warning, pingedAt = 0, idleSeconds = 0, idleHandler = null;

  function armIdle(onTimeout) {
    clearTimeout(warnTimer);
    clearTimeout(endTimer);
    if (warning) { warning.dismiss(); warning = null; }

    warnTimer = setTimeout(() => {
      warning = toast('Still there?', 'You will be logged out in 1 minute because of inactivity.', {
        kind: 'warn',
        sticky: true,
        action: { label: 'Stay logged in', run: () => keepAlive(onTimeout) }
      });
    }, (idleSeconds - 60) * 1000);

    endTimer = setTimeout(onTimeout, idleSeconds * 1000);
  }

  async function keepAlive(onTimeout) {
    pingedAt = Date.now();
    await get('php/me.php');            // tells the server we are still here
    armIdle(onTimeout);
  }

  function startIdle(seconds, onTimeout) {
    stopIdle();
    idleSeconds = seconds;
    armIdle(onTimeout);

    idleHandler = () => {
      if (Date.now() - pingedAt > 30000) keepAlive(onTimeout);   // at most one ping every 30 seconds
    };
    ['click', 'keydown'].forEach(name => document.addEventListener(name, idleHandler));
  }

  function stopIdle() {
    clearTimeout(warnTimer);
    clearTimeout(endTimer);
    if (warning) { warning.dismiss(); warning = null; }
    if (idleHandler) {
      ['click', 'keydown'].forEach(name => document.removeEventListener(name, idleHandler));
      idleHandler = null;
    }
  }

  return { $, post, get, toast, startIdle, stopIdle };
})();
