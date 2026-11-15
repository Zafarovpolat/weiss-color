/* ==========================================================================
   payment-card.js — модалка «Реквизиты для оплаты»: этап 1, демо-логика.
   Автомаска номера карты (0000 0000 0000 0000) и демо-переход после сохранения.
   В бою: номер уходит в платёжный сервис, у нас не хранится (ТЗ 8.3).
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('pcard-form');
  if (!form) return;

  var card = form.querySelector('input[name="card"]');

  function digits(value) {
    return String(value).replace(/\D/g, '');
  }

  function maskCardValue(value) {
    var d = digits(value).slice(0, 16);
    return d.replace(/(\d{4})(?=\d)/g, '$1 ');
  }

  /* позиция курсора после N-й цифры — чтобы он не прыгал в конец */
  function caretAfter(masked, count) {
    if (count <= 0) return 0;
    var seen = 0;
    for (var i = 0; i < masked.length; i++) {
      if (masked.charAt(i) >= '0' && masked.charAt(i) <= '9') {
        seen += 1;
        if (seen === count) return i + 1;
      }
    }
    return masked.length;
  }

  if (card) {
    card.addEventListener('input', function () {
      var caret = card.selectionStart || 0;
      var before = digits(card.value.slice(0, caret)).length;
      var masked = maskCardValue(card.value);
      if (masked === card.value) return;
      card.value = masked;
      var pos = caretAfter(masked, before);
      try {
        card.setSelectionRange(pos, pos);
      } catch (e) {
        /* некоторые браузеры не дают выставлять курсор — не критично */
      }
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    /* Этап 1: данные некуда сохранять — возвращаемся к оформлению заказа */
    window.location.href = 'checkout.html';
  });
})();
