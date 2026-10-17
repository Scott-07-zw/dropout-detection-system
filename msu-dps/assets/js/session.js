// Browser-side session guard: (1) Back from the account signs the user out and shows the login screen,
// (2) an idle timer signs the user out after `timeout` seconds without activity (the server enforces it too).
(function () {
  window.startSessionGuard = function (o) {
    // o: { timeout (s), tickMs, logout() -> Promise, toLogin(expired), ping(), warn(secondsLeft), unwarn() }
    const WARN_AT = 120, PING_EVERY = 60000;
    let lastActive = Date.now(), lastPing = Date.now(), warned = false, leaving = false;
    const mark = () => { lastActive = Date.now(); };
    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(ev => addEventListener(ev, mark, { passive: true, capture: true }));

    function leave(expired) {
      if (leaving) return; leaving = true;
      Promise.resolve().then(() => o.logout()).catch(() => { }).then(() => o.toLogin(expired));
    }
    function stay() { mark(); lastPing = Date.now(); if (warned) { warned = false; o.unwarn(); } o.ping(); }
    function tick() {
      if (leaving) return;
      const idle = (Date.now() - lastActive) / 1000, left = Math.ceil(o.timeout - idle);
      if (left <= 0) return leave(true);
      if (left <= WARN_AT) { warned = true; o.warn(left); return; }
      if (warned) { warned = false; o.unwarn(); }                                  // activity resumed
      if (idle < 60 && Date.now() - lastPing >= PING_EVERY) { lastPing = Date.now(); o.ping(); }   // only an active user keeps the session alive
    }
    setInterval(tick, o.tickMs || 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

    // Back button: keep one extra history entry, so pressing Back lands here first and we sign out.
    history.replaceState({ dps: 'app' }, '', location.href);
    history.pushState({ dps: 'guard' }, '', location.href);
    addEventListener('popstate', () => leave(false));
    addEventListener('pageshow', e => { if (e.persisted) o.ping(); });              // restored from the browser cache: re-check the session
    return { tick, mark, stay, leave };
  };
})();
