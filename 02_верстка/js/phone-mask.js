/* ==========================================================================
   phone-mask.js — автомаска телефона для всех input[type="tel"] на странице.
   Формат: +7 (999) 123-45-67. Ввод, вставка и Backspace работают,
   курсор остаётся после последней введённой цифры (не прыгает в конец).
   Подключение: <script src="../js/phone-mask.js"></script> после разметки,
   перед js/validation.js.
   ========================================================================== */
(function () {
  'use strict';

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

  /* позиция курсора после N-й цифры — чтобы он не прыгал в конец */
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

  function attach(input) {
    if (input.dataset.phoneMask === 'on') return;
    input.dataset.phoneMask = 'on';

    input.addEventListener('input', function () {
      var caret = input.selectionStart || 0;
      var before = localDigits(input.value.slice(0, caret)).length;
      var masked = maskPhoneValue(input.value);
      if (masked === input.value) return;
      input.value = masked;
      var pos = caretAfter(masked, before);
      try {
        input.setSelectionRange(pos, pos);
      } catch (e) {
        /* некоторые браузеры не дают выставлять курсор — не критично */
      }
    });
  }

  function init() {
    Array.prototype.forEach.call(
      document.querySelectorAll('input[type="tel"]'),
      attach
    );
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* окна, вставленные в DOM позже (модалки) — тоже подхватываем */
  window.WCPhoneMask = { attach: attach, init: init, value: maskPhoneValue };
})();
