/* ==========================================================================
   checkout.js — М01 «Оформление заказа»: этап 1, демо-логика формы.
   ТЗ 8.1: проверка обязательных полей на клиенте, введённые данные не теряются;
   согласие не предустановлено.
   ТЗ 8.2: реквизиты юрлица — отдельное окно (счёт выставляет менеджер).
   ТЗ 8.3: карта вводится только на стороне банка — своих полей карты нет.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('checkout-form');
  if (!form) return;

  /* --- Условная логика полей --- */

  var deliveryRadios = form.querySelectorAll('input[name="delivery"]');
  var paymentRadios = form.querySelectorAll('input[name="payment"]');
  var payNote = document.getElementById('pay-note');
  var invoiceOpen = document.getElementById('invoice-open');
  var deliveryValue = document.getElementById('delivery-value');

  function selectedValue(radios) {
    for (var i = 0; i < radios.length; i++) {
      if (radios[i].checked) return radios[i].value;
    }
    return '';
  }

  /* Адрес обязателен при любом способе получения — поля отмечены «*»
     в макете, поэтому required с них не снимаем (раньше при самовывозе
     обязательность снималась и форму можно было отправить без адреса).
     Здесь остаётся только сумма доставки. */
  function syncDelivery() {
    var pickup = selectedValue(deliveryRadios) === 'pickup';
    if (deliveryValue) deliveryValue.textContent = pickup ? 'Бесплатно' : 'от 300₽';
  }

  function syncPayment() {
    var value = selectedValue(paymentRadios);
    if (payNote) payNote.toggleAttribute('data-visible', value === 'online');
    if (invoiceOpen) invoiceOpen.hidden = value !== 'invoice';
  }

  deliveryRadios.forEach(function (el) {
    el.addEventListener('change', syncDelivery);
  });

  paymentRadios.forEach(function (el) {
    el.addEventListener('change', function () {
      syncPayment();
      /* ТЗ 8.2: при выборе счёта сразу предлагаем заполнить реквизиты */
      if (el.value === 'invoice' && el.checked && invoiceOpen) invoiceOpen.focus();
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

  /* --- Проверка перед отправкой --- */

  var consent = document.getElementById('consent');
  var consentError = document.getElementById('consent-error');

  function markInvalid(field, invalid) {
    if (!field) return;
    if (invalid) field.setAttribute('aria-invalid', 'true');
    else field.removeAttribute('aria-invalid');
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var firstInvalid = null;

    form.querySelectorAll('.field__input, .field__select').forEach(function (field) {
      if (field.disabled) return;
      var invalid = !field.checkValidity();
      markInvalid(field, invalid);
      if (invalid && !firstInvalid) firstInvalid = field;
    });

    var consentMissing = !consent || !consent.checked;
    if (consentError) consentError.toggleAttribute('data-visible', consentMissing);
    if (consentMissing && consent) consent.setAttribute('aria-invalid', 'true');
    else if (consent) consent.removeAttribute('aria-invalid');

    if (consentMissing && !firstInvalid) firstInvalid = consent;

    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }

    /* Этап 1: заказа на сервере нет — ведём на экран оплаты (ТЗ 8.3),
       оттуда «Перейти к оплате» уводит на подтверждение заказа. */
    window.location.href = 'payment.html';
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

  syncDelivery();
  syncPayment();
})();