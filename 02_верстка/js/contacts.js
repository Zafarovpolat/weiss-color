/* ==========================================================================
   contacts.js — М04 «Контакты»: кастомный селект региона, маска телефона
   и переход на экран «Спасибо за обращение» после успешной отправки.
   Проверку полей выполняет js/validation.js (сообщения под полями);
   серверная отправка — этап 2.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('contacts-form');
  if (!form) return;

  /* ------------------------------------------------------------------------
     Кастомный селект региона.
     Нативный <select> под макет не оформить (список рисует ОС), поэтому
     разметка — кнопка + ul[role="listbox"]. Поведение повторяет select:
     клик/Enter/Space открывают, стрелки ходят по пунктам, Esc и клик мимо
     закрывают. Значение хранится в скрытом input[name="region"].
     ------------------------------------------------------------------------ */

  var select = form.querySelector('[data-select]');

  if (select) {
    var trigger = select.querySelector('.select__trigger');
    var valueEl = select.querySelector('.select__value');
    var list = select.querySelector('.select__list');
    var input = select.querySelector('input[type="hidden"]');
    var options = Array.prototype.slice.call(select.querySelectorAll('.select__option'));

    if (trigger && valueEl && list && input && options.length) {
      var placeholder = valueEl.textContent;
      var selected = -1;

      var isOpen = function () {
        return trigger.getAttribute('aria-expanded') === 'true';
      };

      var highlight = function (index) {
        options.forEach(function (opt, i) {
          opt.classList.toggle('is-active', i === index);
        });
      };

      var open = function () {
        var start = selected >= 0 ? selected : 0;
        list.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        select.classList.add('is-open');
        highlight(start);
        options[start].focus();
      };

      var close = function (refocus) {
        if (!isOpen()) return;
        list.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        select.classList.remove('is-open');
        options.forEach(function (opt) { opt.classList.remove('is-active'); });
        if (refocus) trigger.focus();
      };

      var choose = function (index) {
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
      };

      var resetSelect = function () {
        selected = -1;
        options.forEach(function (opt) { opt.setAttribute('aria-selected', 'false'); });
        valueEl.textContent = placeholder;
        trigger.removeAttribute('data-filled');
        input.value = '';
        close(false);
      };

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

      /* клик или фокус вне компонента закрывают список */
      document.addEventListener('click', function (event) {
        if (isOpen() && !select.contains(event.target)) close(false);
      });
      document.addEventListener('focusin', function (event) {
        if (isOpen() && !select.contains(event.target)) close(false);
      });

      form.addEventListener('reset', function () {
        setTimeout(resetSelect, 0);
      });
    }
  }

  /* ------------------------------------------------------------------------
     Маска телефона: по мере ввода приводим номер к +7 (999) 123-45-67.
     Курсор считаем по цифрам, иначе он прыгал бы в конец строки.
     ------------------------------------------------------------------------ */

  var phone = form.querySelector('input[type="tel"]');

  if (phone) {
    var localDigits = function (str) {
      var d = String(str).replace(/\D/g, '');
      if (d.charAt(0) === '7' || d.charAt(0) === '8') d = d.slice(1);
      return d;
    };

    var maskPhone = function (value) {
      var d = localDigits(value).slice(0, 10);
      if (!d) return '';
      var out = '+7 (' + d.slice(0, 3);
      if (d.length >= 3) out += ')';
      if (d.length > 3) out += ' ' + d.slice(3, 6);
      if (d.length > 6) out += '-' + d.slice(6, 8);
      if (d.length > 8) out += '-' + d.slice(8, 10);
      return out;
    };

    var caretAfter = function (masked, localCount) {
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
    };

    phone.addEventListener('input', function () {
      var caret = phone.selectionStart || 0;
      var before = localDigits(phone.value.slice(0, caret)).length;
      var masked = maskPhone(phone.value);
      if (masked === phone.value) return;
      phone.value = masked;
      var pos = caretAfter(masked, before);
      try {
        phone.setSelectionRange(pos, pos);
      } catch (e) {
        /* некоторые браузеры не дают выставлять курсор — не критично */
      }
    });
  }

  /* ------------------------------------------------------------------------
     Отправка: «спасибо» больше не подменяет карточку — уходим на отдельный
     экран результата отправки формы.
     ------------------------------------------------------------------------ */

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    /* Страховка, если validation.js не подключён */
    var valid = window.WCForm ? window.WCForm.validate(form) : form.checkValidity();
    if (!valid) return;

    window.location.href = 'modal-form-result.html';
  });
})();
