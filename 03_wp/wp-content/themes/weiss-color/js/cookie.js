/* ==========================================================================
   cookie.js — уведомление о cookie (М20, п.9.5 ТЗ).

   Категории: необходимые / аналитика / реклама. Необходимые включены всегда,
   остальные — только после явного согласия. Закрытие крестиком не равно
   «Принять все»: сохраняются только необходимые.

   Выбор лежит в cookie weiss_cookie_consent: JSON
   {"necessary":true,"analytics":false,"ads":false,"ts":1699999999999}
   на 365 дней, Path=/, SameSite=Lax.

   API для необязательных скриптов:
     window.weissCookie.allowed('analytics')  // true | false
     window.weissCookie.get()
     window.weissCookie.open()                // открыть настройки из подвала

   Событие weiss:consent (detail — сохранённый выбор) — точка запуска
   аналитики и рекламных пикселей после согласия.
   ========================================================================== */
(function () {
  'use strict';

  var NAME = 'weiss_cookie_consent';
  var DAYS = 365;
  var CATEGORIES = ['necessary', 'analytics', 'ads'];

  var panel = document.querySelector('[data-cookie-panel]');
  if (!panel) return;

  var settings = panel.querySelector('#cookie-settings');
  var openers = document.querySelectorAll('[data-cookie-open]');
  var lastTrigger = null;

  function readCookie() {
    var match = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)'));
    if (!match) return null;
    try {
      var data = JSON.parse(decodeURIComponent(match[1]));
      return data && typeof data === 'object' ? data : null;
    } catch (e) {
      return null;
    }
  }

  function writeCookie(consent) {
    var value = encodeURIComponent(JSON.stringify(consent));
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = NAME + '=' + value + '; Max-Age=' + (DAYS * 86400) +
      '; Path=/; SameSite=Lax' + secure;
  }

  function normalize(data) {
    return {
      necessary: true,
      analytics: !!(data && data.analytics),
      ads: !!(data && data.ads),
      ts: data && data.ts ? data.ts : Date.now()
    };
  }

  var consent = readCookie();

  function save(next) {
    consent = normalize(next);
    consent.ts = Date.now();
    writeCookie(consent);
    syncInputs();
    syncOpeners();
    document.documentElement.dataset.cookieConsent = consent.analytics || consent.ads ? 'custom' : 'necessary';
    document.dispatchEvent(new CustomEvent('weiss:consent', { detail: consent }));
  }

  function syncInputs() {
    Array.prototype.forEach.call(panel.querySelectorAll('[data-cookie-category]'), function (input) {
      var key = input.dataset.cookieCategory;
      input.checked = key === 'necessary' ? true : !!(consent && consent[key]);
    });
  }

  /* Кнопка «Настройки cookie» в подвале нужна только после первого выбора:
     до него подвал должен совпадать с макетом. */
  function syncOpeners() {
    Array.prototype.forEach.call(openers, function (opener) {
      opener.hidden = !consent;
    });
  }

  function setSettings(open) {
    if (!settings) return;
    settings.hidden = !open;
    Array.prototype.forEach.call(panel.querySelectorAll('[data-cookie="settings"]'), function (button) {
      button.setAttribute('aria-expanded', String(open));
      button.setAttribute('aria-controls', 'cookie-settings');
    });
  }

  function show(openSettings) {
    syncInputs();
    setSettings(!!openSettings);
    panel.hidden = false;
  }

  function hide() {
    panel.hidden = true;
    setSettings(false);
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    lastTrigger = null;
  }

  /* Значения по умолчанию в панели: сохранённый выбор либо только необходимые */
  syncInputs();
  syncOpeners();

  if (!consent) {
    show(false);
  } else {
    document.documentElement.dataset.cookieConsent = consent.analytics || consent.ads ? 'custom' : 'necessary';
  }

  panel.addEventListener('click', function (event) {
    var button = event.target.closest('[data-cookie]');
    if (!button) return;

    var action = button.dataset.cookie;

    if (action === 'settings') {
      var willOpen = settings ? settings.hidden : false;
      setSettings(willOpen);
      lastTrigger = button;
      if (willOpen && settings) {
        var first = settings.querySelector('input:not(:disabled)');
        if (first) first.focus();
      }
      return;
    }

    if (action === 'accept') {
      save({ necessary: true, analytics: true, ads: true });
      hide();
      return;
    }

    if (action === 'close') {
      /* Крестик не включает аналитику и рекламу. Если выбор уже сохранён
         (баннер открыли из подвала), он не сбрасывается — просто закрываем. */
      if (!consent) save({ necessary: true, analytics: false, ads: false });
      hide();
      return;
    }

    if (action === 'save') {
      var next = { necessary: true };
      Array.prototype.forEach.call(panel.querySelectorAll('[data-cookie-category]'), function (input) {
        if (input.dataset.cookieCategory !== 'necessary') {
          next[input.dataset.cookieCategory] = input.checked;
        }
      });
      save(next);
      hide();
    }
  });

  /* Кнопка в подвале открывает сразу настройки с текущим выбором */
  Array.prototype.forEach.call(openers, function (opener) {
    opener.addEventListener('click', function () {
      lastTrigger = opener;
      show(true);
      var title = panel.querySelector('#cookie-title');
      if (title) {
        title.setAttribute('tabindex', '-1');
        title.focus();
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || panel.hidden) return;
    /* Esc — тот же сценарий, что крестик */
    if (!consent) save({ necessary: true, analytics: false, ads: false });
    hide();
  });

  window.weissCookie = {
    get: function () { return consent ? normalize(consent) : null; },
    allowed: function (category) {
      if (category === 'necessary') return true;
      return !!(consent && consent[category]);
    },
    open: function () { show(true); }
  };
})();
