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

  var addressCard = document.getElementById('address-title');
  var addressSection = addressCard ? addressCard.closest('.form-card') : null;
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

  var addressHint = document.getElementById('address-hint');

  /* ТЗ 8.1: адресные поля условные. При самовывозе они остаются доступными
     для заполнения (как в макете), но не обязательны; для курьера — обязательны. */
  function syncDelivery() {
    var pickup = selectedValue(deliveryRadios) === 'pickup';
    if (addressSection) {
      addressSection.querySelectorAll('input, select').forEach(function (el) {
        if (pickup) {
          el.removeAttribute('required');
          el.removeAttribute('aria-invalid');
        } else {
          el.setAttribute('required', '');
        }
      });
    }
    if (addressHint) addressHint.hidden = !pickup;
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

    /* Этап 1: заказа на сервере нет — ведём на экран подтверждения */
    window.location.href = 'order-success.html';
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

  syncDelivery();
  syncPayment();
})();
