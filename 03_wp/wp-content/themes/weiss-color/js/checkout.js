/* ==========================================================================
   checkout.js — М01 «Оформление заказа». Форма уходит в штатный обработчик
   WooCommerce (?wc-ajax=checkout), поэтому здесь только поведение полей:
   способ оплаты, сумма доставки в панели, окно реквизитов и подготовка
   данных к отправке. Проверка полей — validation.js (ТЗ 8.1).
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('checkout-form');
  if (!form) return;

  /* --- Условная логика полей --- */

  var paymentRadios = form.querySelectorAll('input[name="payment_method"]');
  var payNote = document.getElementById('pay-note');
  var invoiceOpen = document.getElementById('invoice-open');

  function selectedValue(radios) {
    for (var i = 0; i < radios.length; i++) {
      if (radios[i].checked) return radios[i].value;
    }
    return '';
  }

  function syncPayment() {
    var value = selectedValue(paymentRadios);
    if (payNote) payNote.toggleAttribute('data-visible', value === 'weiss_card');
    if (invoiceOpen) invoiceOpen.hidden = value !== 'bacs';
  }

  paymentRadios.forEach(function (el) {
    el.addEventListener('change', function () {
      syncPayment();
      /* ТЗ 8.2: при выборе счёта сразу предлагаем заполнить реквизиты */
      if (el.value === 'bacs' && el.checked && invoiceOpen) invoiceOpen.focus();
    });
  });

  /* --- Окно реквизитов (М22) --- */

  var invoiceDialog = document.getElementById('invoice-dialog');

  if (invoiceOpen && invoiceDialog) {
    invoiceOpen.addEventListener('click', function () {
      if (typeof invoiceDialog.showModal === 'function') invoiceDialog.showModal();
    });

    invoiceDialog.addEventListener('click', function (event) {
      /* клик по подложке закрывает окно */
      if (event.target === invoiceDialog) invoiceDialog.close('cancel');
    });
  }

  /* Сохранение реквизитов: значения переносятся в скрытые поля заказа,
     чтобы уйти на сервер вместе с формой (заказ создаёт WooCommerce). */
  var invoiceForm = document.getElementById('invoice-form');

  if (invoiceForm && invoiceOpen) {
    /* ИП: КПП не требуется, ОГРН подписывается как ОГРНИП (ТЗ 8.2) */
    var invoiceKpp  = invoiceForm.querySelector('[name="kpp"]');
    var invoiceKppField = invoiceForm.querySelector('[data-field="kpp"]');
    var invoiceOgrn = invoiceForm.querySelector('[name="ogrn"]');
    var invoiceOgrnLabel = invoiceForm.querySelector('[data-ogrn-label]');
    var invoiceInn = invoiceForm.querySelector('[name="inn"]');

    function syncInvoicePayer() {
      var checked = invoiceForm.querySelector('[name="payer"]:checked');
      var isIp = checked && checked.value === 'ip';

      if (invoiceKpp && invoiceKppField) {
        invoiceKpp.required = !isIp;
        invoiceKppField.hidden = isIp;
        if (isIp) {
          invoiceKpp.value = '';
          invoiceKpp.removeAttribute('aria-invalid');
        }
      }

      if (invoiceInn) {
        invoiceInn.pattern = isIp ? '[0-9]{12}' : '[0-9]{10}';
        invoiceInn.placeholder = isIp ? 'Например, 770123456789' : 'Например, 7701234567';
      }

      if (invoiceOgrn) {
        invoiceOgrn.pattern = isIp ? '[0-9]{15}' : '[0-9]{13}';
        invoiceOgrn.placeholder = isIp ? 'Например, 304500116000157' : 'Например, 1027700132195';
      }
      if (invoiceOgrnLabel) invoiceOgrnLabel.textContent = isIp ? 'ОГРНИП *' : 'ОГРН *';
    }

    invoiceForm.querySelectorAll('[name="payer"]').forEach(function (radio) {
      radio.addEventListener('change', syncInvoicePayer);
    });
    syncInvoicePayer();

    invoiceForm.addEventListener('submit', function (event) {
      if (event.submitter && event.submitter.value === 'cancel') return;

      var map = {
        org: 'weiss_invoice[org]',
        inn: 'weiss_invoice[inn]',
        kpp: 'weiss_invoice[kpp]',
        ogrn: 'weiss_invoice[ogrn]',
        'legal-address': 'weiss_invoice[address]',
        'invoice-email': 'weiss_invoice[email]',
        'invoice-phone': 'weiss_invoice[phone]',
        'invoice-comment': 'weiss_invoice[comment]'
      };

      Object.keys(map).forEach(function (name) {
        var from = invoiceForm.querySelector('[name="' + name + '"]');
        var to = form.querySelector('[name="' + map[name] + '"]');
        if (from && to) to.value = from.value.trim();
      });

      var payer = invoiceForm.querySelector('[name="payer"]:checked');
      var payerField = form.querySelector('[name="weiss_invoice[payer]"]');
      if (payer && payerField) payerField.value = payer.value;

      invoiceOpen.textContent = 'Реквизиты для счёта указаны';
    });
  }

  /* --- Доставка в панели заказа: точная цена по региону (ТЗ 7.5) --- */

  var deliveryValue = document.getElementById('delivery-value');
  var totalValue = document.querySelector('.checkout-total dd');
  var shippingRadios = form.querySelectorAll('input[name^="shipping_method"]');
  var courierNote = document.getElementById('courier-note');
  var regionInput = form.querySelector('input[name="billing_state"]');
  var regionRoot = regionInput ? regionInput.closest('[data-select]') : null;
  var quoteNote = document.getElementById('checkout-quote-note');
  var quotePayment = document.getElementById('quote-payment');
  var paymentRow = form.querySelector('.choice-row--payment');

  function money(value) {
    return String(Math.round(Number(value))).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0') + ' ₽';
  }

  /* Тариф выбранного региона: null — регион ещё не выбран; cost === null —
     тарифа нет, заказ оформляется заявкой на расчёт без списания. */
  function regionTariff() {
    if (!regionRoot) return null;
    var options = regionRoot.querySelectorAll('.select__option');
    for (var i = 0; i < options.length; i++) {
      if (options[i].getAttribute('aria-selected') !== 'true') continue;
      var raw = options[i].getAttribute('data-cost');
      var freeFrom = options[i].getAttribute('data-free-from');
      return {
        name: options[i].textContent.trim(),
        cost: raw === null || raw === '' ? null : Number(raw),
        term: options[i].getAttribute('data-term') || '',
        freeFrom: freeFrom === null || freeFrom === '' ? null : Number(freeFrom)
      };
    }
    return null;
  }

  function syncShipping() {
    var picked = '';
    shippingRadios.forEach(function (radio) {
      if (radio.checked) picked = radio.value;
    });

    var pickup = picked.indexOf('local_pickup') === 0;
    var tariff = regionTariff();
    var quote = false;
    var needRegion = false;
    var cost = 0;

    if (!pickup) {
      if (!tariff) needRegion = true;
      else if (tariff.cost === null) quote = true;
      else cost = tariff.cost;

      /* Порог бесплатной доставки — от суммы товаров после скидки (ТЗ 7.3) */
      var goods = totalValue ? Number(totalValue.dataset.baseTotal || 0) : 0;
      if (cost > 0 && tariff && tariff.freeFrom && goods >= tariff.freeFrom) cost = 0;
    }

    if (courierNote) {
      courierNote.textContent = pickup ? 'Бесплатно'
        : needRegion ? 'Выберите регион'
        : quote ? 'Рассчитает менеджер'
        : cost > 0 ? money(cost) : 'Бесплатно';
    }

    if (deliveryValue) {
      deliveryValue.textContent = pickup ? 'Бесплатно'
        : needRegion ? '—'
        : quote ? 'Рассчитает менеджер'
        : cost > 0 ? money(cost) : 'Бесплатно';
    }
    if (totalValue) totalValue.textContent = money(Number(totalValue.dataset.baseTotal || 0) + cost);

    /* Заявка на расчёт: карта и счёт не нужны, оплата придёт ссылкой */
    if (quoteNote) quoteNote.hidden = !quote;
    if (paymentRow) paymentRow.hidden = quote;
    paymentRadios.forEach(function (radio) {
      radio.disabled = quote;
      if (quote) radio.checked = false;
    });
    if (quotePayment) quotePayment.disabled = !quote;
    syncPayment();

    /* ТЗ 7.6: при самовывозе адрес не обязателен. */
    var address = form.querySelector('.form-card--address');
    if (address) {
      address.toggleAttribute('data-optional', pickup);
      var fields = address.querySelectorAll('[required], [data-required-backup]');
      Array.prototype.forEach.call(fields, function (field) {
        if (pickup) {
          if (field.hasAttribute('required')) field.setAttribute('data-required-backup', '1');
          field.removeAttribute('required');
          field.setAttribute('aria-required', 'false');
        } else if (field.hasAttribute('data-required-backup')) {
          field.setAttribute('required', '');
          field.removeAttribute('data-required-backup');
          field.setAttribute('aria-required', 'true');
        }
      });
    }
  }

  shippingRadios.forEach(function (radio) {
    radio.addEventListener('change', syncShipping);
  });
  if (regionInput) regionInput.addEventListener('input', syncShipping);
  syncShipping();

  /* --- Проверка перед отправкой --- */

  var consent = document.getElementById('consent');
  var consentError = document.getElementById('consent-error');
  var submitBtn = document.querySelector('.checkout-actions__submit');
  var notice = document.getElementById('checkout-notice');

  function markInvalid(field, invalid) {
    if (!field) return;
    if (invalid) field.setAttribute('aria-invalid', 'true');
    else field.removeAttribute('aria-invalid');
  }

  function showNotice(message) {
    if (!notice) return;
    notice.textContent = message;
    notice.hidden = false;
    notice.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }

  function hideNotice() {
    if (!notice) return;
    notice.hidden = true;
    notice.textContent = '';
  }

  function selectedPayment() {
    if (quotePayment && !quotePayment.disabled) return quotePayment.value;
    var radios = form.querySelectorAll('input[name="payment_method"]');
    for (var i = 0; i < radios.length; i++) {
      if (radios[i].checked) return radios[i].value;
    }
    return '';
  }

  /* Сообщения WooCommerce приходят HTML-списком — оставляем текст. */
  function messagesFrom(json) {
    if (!json || !json.messages) return '';
    var doc = new DOMParser().parseFromString(json.messages, 'text/html');
    var items = doc.querySelectorAll('li');
    if (!items.length) return (doc.body.textContent || '').trim();
    return Array.prototype.map.call(items, function (item) {
      return item.textContent.trim();
    }).join(' ');
  }

  function restoreSubmit() {
    if (!submitBtn) return;
    submitBtn.disabled = false;
    submitBtn.textContent = window.weissCheckout ? weissCheckout.submitLabel : 'Подтвердить заказ';
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    hideNotice();

    /* validation.js уже проверил поля и согласие; здесь — выбор оплаты
       и отправка в штатный обработчик WooCommerce (?wc-ajax=checkout). */
    if (!selectedPayment()) {
      showNotice(window.weissCheckout ? weissCheckout.noPayment : 'Выберите способ оплаты.');
      var firstPayment = form.querySelector('input[name="payment_method"]');
      if (firstPayment) firstPayment.focus();
      return;
    }

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Оформляем заказ…';
    }

    var endpoint = window.weissCheckout ? weissCheckout.endpoint : '/?wc-ajax=checkout';

    fetch(endpoint, {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin'
    })
      .then(function (response) { return response.text(); })
      .then(function (text) {
        var json = null;
        try { json = JSON.parse(text); } catch (e) { json = null; }

        if (json && json.result === 'success' && json.redirect) {
          window.location.href = json.redirect;
          return;
        }

        showNotice(messagesFrom(json) || (window.weissCheckout ? weissCheckout.placeError : 'Не удалось оформить заказ.'));
        restoreSubmit();
      })
      .catch(function () {
        showNotice(window.weissCheckout ? weissCheckout.placeError : 'Не удалось оформить заказ.');
        restoreSubmit();
      });
  });

  /* Ошибка снимается, как только поле исправлено — данные не теряются.
     Подсветку полей ведёт js/validation.js; здесь — только запасной путь
     на случай, если он не подключён. */
  form.addEventListener('input', function (event) {
    var field = event.target;
    if (field.matches('.field__input, .field__select') && !window.WCForm) {
      markInvalid(field, false);
    }
    if (field === consent && field.checked && consentError) {
      consentError.removeAttribute('data-visible');
      field.removeAttribute('aria-invalid');
    }
  });

  /* --- Кастомные селекты «Регион» / «Город» ---
     Нативный <select> под макет не оформить (список рисует ОС), поэтому
     разметка — кнопка + ul[role="listbox"], а поведение повторяет select:
     клик/Enter/Space открывают, стрелки ходят по пунктам, Esc и клик мимо
     закрывают. Значение пишется в скрытый input — его проверяет validation.js
     (data-validate), фокус при ошибке уводится на кнопку (data-focus). */

  var selects = [];

  function initSelect(root) {
    var trigger = root.querySelector('.select__trigger');
    var valueEl = root.querySelector('.select__value');
    var list = root.querySelector('.select__list');
    var input = root.querySelector('input[type="hidden"]');
    var options = Array.prototype.slice.call(root.querySelectorAll('.select__option'));
    if (!trigger || !valueEl || !list || !input || !options.length) return null;

    var placeholder = valueEl.textContent;
    var selected = -1;

    function isOpen() {
      return trigger.getAttribute('aria-expanded') === 'true';
    }

    function highlight(index) {
      options.forEach(function (opt, i) {
        opt.classList.toggle('is-active', i === index);
      });
    }

    function open() {
      /* одновременно открыт только один список */
      selects.forEach(function (other) {
        if (other !== api) other.close(false);
      });
      var start = selected >= 0 ? selected : 0;
      list.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      root.classList.add('is-open');
      highlight(start);
      options[start].focus();
    }

    function close(refocus) {
      if (!isOpen()) return;
      list.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      root.classList.remove('is-open');
      options.forEach(function (opt) { opt.classList.remove('is-active'); });
      if (refocus) trigger.focus();
    }

    function choose(index) {
      selected = index;
      options.forEach(function (opt, i) {
        opt.setAttribute('aria-selected', i === index ? 'true' : 'false');
      });
      valueEl.textContent = options[index].textContent.trim();
      trigger.setAttribute('data-filled', '');
      input.value = options[index].textContent.trim();
      close(true);
      /* сообщаем валидатору, что значение изменилось */
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function reset() {
      selected = -1;
      options.forEach(function (opt) { opt.setAttribute('aria-selected', 'false'); });
      valueEl.textContent = placeholder;
      trigger.removeAttribute('data-filled');
      input.value = '';
      close(false);
    }

    trigger.addEventListener('click', function () {
      if (isOpen()) close(true);
      else open();
    });

    trigger.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open();
      }
    });

    list.addEventListener('keydown', function (event) {
      var i = options.indexOf(document.activeElement);

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        var next = (i + 1) % options.length;
        highlight(next);
        options[next].focus();
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        var prev = (i - 1 + options.length) % options.length;
        highlight(prev);
        options[prev].focus();
      } else if (event.key === 'Home') {
        event.preventDefault();
        highlight(0);
        options[0].focus();
      } else if (event.key === 'End') {
        event.preventDefault();
        highlight(options.length - 1);
        options[options.length - 1].focus();
      } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        if (i >= 0) choose(i);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
      } else if (event.key === 'Tab') {
        close(false);
      }
    });

    options.forEach(function (opt, i) {
      opt.addEventListener('click', function () { choose(i); });
      opt.addEventListener('mousemove', function () { highlight(i); });
    });

    var api = { root: root, close: close, reset: reset };
    return api;
  }

  form.querySelectorAll('[data-select]').forEach(function (root) {
    var api = initSelect(root);
    if (api) selects.push(api);
  });

  /* клик или фокус вне компонента закрывают список */
  document.addEventListener('click', function (event) {
    selects.forEach(function (s) {
      if (!s.root) return;
      if (!s.root.contains(event.target)) s.close(false);
    });
  });

  form.addEventListener('reset', function () {
    setTimeout(function () {
      selects.forEach(function (s) { s.reset(); });
    }, 0);
  });

  /* --- Маска телефона: +7 (999) 123-45-67 по мере ввода ---
     Курсор пересчитываем по количеству цифр, иначе он прыгает в конец. */

  function localDigits(str) {
    var d = String(str).replace(/\D/g, '');
    if (d.charAt(0) === '7' || d.charAt(0) === '8') d = d.slice(1);
    return d;
  }

  function maskPhoneValue(value) {
    var d = localDigits(value).slice(0, 10);
    if (!d) return '';
    var out = '+7 (' + d.slice(0, 3);
    if (d.length >= 3) out += ')';
    if (d.length > 3) out += ' ' + d.slice(3, 6);
    if (d.length > 6) out += '-' + d.slice(6, 8);
    if (d.length > 8) out += '-' + d.slice(8, 10);
    return out;
  }

  function caretAfter(masked, localCount) {
    if (localCount <= 0) return 0;
    var seen = 0;
    for (var i = 0; i < masked.length; i++) {
      var ch = masked.charAt(i);
      if (ch >= '0' && ch <= '9') {
        /* первая цифра — код страны (+7), её не считаем */
        if (i === 1 && masked.charAt(0) === '+') continue;
        seen += 1;
        if (seen === localCount) return i + 1;
      }
    }
    return masked.length;
  }

  form.querySelectorAll('input[type="tel"]').forEach(function (phone) {
    phone.addEventListener('input', function () {
      var caret = phone.selectionStart || 0;
      var before = localDigits(phone.value.slice(0, caret)).length;
      var masked = maskPhoneValue(phone.value);
      if (masked === phone.value) return;
      phone.value = masked;
      var pos = caretAfter(masked, before);
      try {
        phone.setSelectionRange(pos, pos);
      } catch (e) {
        /* некоторые браузеры не дают выставлять курсор — не критично */
      }
    });
  });

  syncPayment();
})();