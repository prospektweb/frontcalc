(function (window, document) {
  if (window.__frontcalcAuthRequiredReady) {
    return;
  }
  window.__frontcalcAuthRequiredReady = true;

  var STORAGE_KEY = "frontcalc_auth_intent";
  var INTENT_TTL = 30 * 60 * 1000;
  var AUTH_TITLE = "Для расчёта стоимости необходима авторизация";
  var POPUP_WAIT_TIMEOUT = 3000;
  var POPUP_POLL_INTERVAL = 100;

  function getConfig() {
    return window.FrontcalcAuthConfig || {};
  }

  function isAuthorized() {
    return getConfig().isAuthorized === true;
  }

  function findTarget(node) {
    var current = node;
    while (current && current !== document) {
      if (current.classList && current.classList.contains("js-frontcalc-auth-required")) {
        return current;
      }
      current = current.parentNode;
    }
    return null;
  }

  function storageAvailable() {
    try {
      return !!window.localStorage;
    } catch (e) {
      return false;
    }
  }

  function getCurrentOriginUrl() {
    try {
      var url = new URL(window.location.href, window.location.origin);
      if (url.origin !== window.location.origin) {
        return null;
      }
      return url;
    } catch (e) {
      return null;
    }
  }

  function saveIntent() {
    if (!storageAvailable()) {
      return;
    }

    var currentUrl = getCurrentOriginUrl();
    if (!currentUrl) {
      return;
    }

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
        source: "frontcalc",
        origin: window.location.origin,
        url: currentUrl.href,
        expiresAt: Date.now() + INTENT_TTL
      }));
    } catch (e) {}
  }

  function readIntent() {
    if (!storageAvailable()) {
      return null;
    }

    var raw = null;
    try {
      raw = window.localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      return null;
    }

    if (!raw) {
      return null;
    }

    var saved = null;
    try {
      saved = JSON.parse(raw);
    } catch (e) {
      removeIntent();
      return null;
    }

    if (!saved || saved.source !== "frontcalc") {
      removeIntent();
      return null;
    }

    var expiresAt = Number(saved.expiresAt);
    if (!isFinite(expiresAt) || expiresAt < Date.now()) {
      removeIntent();
      return null;
    }
    saved.expiresAt = expiresAt;

    if (!saved.url) {
      removeIntent();
      return null;
    }

    try {
      saved.url = new URL(saved.url, window.location.origin);
    } catch (e) {
      removeIntent();
      return null;
    }

    if (saved.url.origin !== window.location.origin) {
      removeIntent();
      return null;
    }

    return saved;
  }

  function removeIntent() {
    if (!storageAvailable()) {
      return;
    }

    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch (e) {}
  }

  function getBackUrl() {
    return encodeURIComponent(window.location.pathname + window.location.search + window.location.hash);
  }

  function fallbackToAuthPage() {
    window.location.href = "/auth/?backurl=" + getBackUrl();
  }

  function isElementVisible(element) {
    if (!element || !element.ownerDocument || !element.getClientRects) {
      return false;
    }

    return element.getClientRects().length > 0;
  }

  function matchesText(element, pattern) {
    return pattern.test(String(element.textContent || "").replace(/\s+/g, " "));
  }

  function findAuthPopup() {
    if (!document.querySelectorAll) {
      return null;
    }

    var candidates = document.querySelectorAll(
      ".jqmWindow, .popup, .popup-window, .modal, .modal-dialog, #popup_iframe_wrapper > div"
    );
    var authTextPattern = /авторизац|вход|войти|login|auth/i;
    var authFieldSelector = "input[name='USER_LOGIN'], input[name='USER_PASSWORD'], input[name='AUTH_FORM'], form[action*='auth']";
    var matched = null;

    for (var i = candidates.length - 1; i >= 0; i--) {
      var popup = candidates[i];
      if (!isElementVisible(popup)) {
        continue;
      }

      if (popup.querySelector && popup.querySelector(authFieldSelector)) {
        return popup;
      }

      if (!matched && matchesText(popup, authTextPattern)) {
        matched = popup;
      }
    }

    return matched;
  }

  function replaceAuthTitle(popup) {
    if (!popup || !popup.querySelectorAll) {
      return false;
    }

    var selectors = [
      "h1", "h2", "h3", "h4", "h5",
      ".form-header", ".popup-title", ".modal-title", ".jqmTitle",
      ".auth-title", ".title", ".font_24", ".font_22"
    ];
    var titlePattern = /авторизац|вход|войти|login|auth/i;
    var titles = popup.querySelectorAll(selectors.join(","));

    for (var i = 0; i < titles.length; i++) {
      if (matchesText(titles[i], titlePattern)) {
        titles[i].textContent = AUTH_TITLE;
        return true;
      }
    }

    return false;
  }

  function waitForAuthPopup(onFound, onTimeout) {
    var startedAt = Date.now();

    function poll() {
      var popup = findAuthPopup();
      if (popup) {
        onFound(popup);
        return;
      }

      if (Date.now() - startedAt >= POPUP_WAIT_TIMEOUT) {
        onTimeout();
        return;
      }

      window.setTimeout(poll, POPUP_POLL_INTERVAL);
    }

    poll();
  }

  function openAsproAuthPopup(onOpened, onFailed) {
    if (!document.querySelector) {
      onFailed();
      return;
    }

    var selectors = [
      '[data-event="jqm"][data-param-form_id="AUTH"]',
      '[data-event="jqm"][data-name="auth"]',
      '.animate-load[data-param-form_id="AUTH"]',
      '.animate-load[data-name="auth"]',
      '.js-popup-auth',
      '.js-auth'
    ];

    for (var i = 0; i < selectors.length; i++) {
      var trigger = document.querySelector(selectors[i]);
      if (trigger && typeof trigger.click === "function") {
        trigger.click();
        waitForAuthPopup(function (popup) {
          replaceAuthTitle(popup);
          onOpened();
        }, onFailed);
        return;
      }
    }

    onFailed();
  }

  function processAuthIntent() {
    if (!isAuthorized()) {
      return;
    }

    var intent = readIntent();
    if (!intent) {
      return;
    }

    removeIntent();

    var currentUrl = getCurrentOriginUrl();
    if (currentUrl && intent.url.href === currentUrl.href) {
      return;
    }

    window.location.href = intent.url.href;
  }

  document.addEventListener("click", function (event) {
    var button = findTarget(event.target);
    if (!button) {
      return;
    }

    event.preventDefault();
    saveIntent();

    if (typeof window.CustomEvent === "function") {
      var authEvent = new CustomEvent("frontcalc:authRequired", {
        bubbles: true,
        cancelable: true,
        detail: { button: button }
      });
      if (!button.dispatchEvent(authEvent)) {
        return;
      }
    }

    openAsproAuthPopup(function () {}, fallbackToAuthPage);
  }, false);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", processAuthIntent);
  } else {
    processAuthIntent();
  }
})(window, document);
