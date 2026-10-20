/* ==========================================================================
   invoice.js — М22 «Реквизиты юридического лица»: этап 1, демо-логика.
   ТЗ 8.2: КПП и ОГРН обязательны для юрлица; для ИП — ОГРНИП без
   обязательного КПП. Сохранённые данные доступны для проверки и исправления
   (этап 2: серверное сохранение и счёт от менеджера).
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('invoice-page-form');
  if (!form) return;

  var kppField = form.querySelector('.field[data-for="legal"]');
  var ogrnLabel = form.querySelector('[data-label-legal]');
  var payerRadios = form.querySelectorAll('input[name="payer"]');

  function payer() {
    for (var i = 0; i < payerRadios.length; i++) {
      if (payerRadios[i].checked) return payerRadios[i].value;
    }
    return 'legal';
  }

  /* ИП: КПП необязателен и скрыт, ОГРН меняется на ОГРНИП (15 цифр) */
  function syncPayer() {
    var isIp = payer() === 'ip';
    var kpp = kppField ? kppField.querySelector('input') : null;

    if (kppField && kpp) {
      kppField.hidden = isIp;
      kpp.required = !isIp;
      if (isIp) kpp.removeAttribute('aria-invalid');
    }

    if (ogrnLabel) {
      ogrnLabel.innerHTML = isIp
        ? "ОГРНИП <b aria-hidden='true'>*</b>"
        : "ОГРН <b aria-hidden='true'>*</b>";
    }

    var ogrn = form.querySelector('input[name="ogrn"]');
    if (ogrn) {
      ogrn.pattern = '[0-9]{13}|[0-9]{15}';
      ogrn.placeholder = isIp ? 'Например, 304500116000157' : 'Например, 1027700132195';
    }
  }

  payerRadios.forEach(function (el) { el.addEventListener('change', syncPayer); });

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var firstInvalid = null;

    form.querySelectorAll('.field__input').forEach(function (field) {
      var invalid = !field.checkValidity();
      if (invalid) {
        field.setAttribute('aria-invalid', 'true');
        if (!firstInvalid) firstInvalid = field;
      } else {
        field.removeAttribute('aria-invalid');
      }
    });

    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }

    /* Этап 1: данные некуда сохранять — возвращаемся к оформлению заказа */
    window.location.href = 'checkout.html';
  });

  form.addEventListener('input', function (event) {
    if (event.target.matches('.field__input')) event.target.removeAttribute('aria-invalid');
  });

  syncPayer();
})();
