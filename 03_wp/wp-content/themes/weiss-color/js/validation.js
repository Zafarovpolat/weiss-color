/* ==========================================================================
   validation.js — единая клиентская валидация полей форм (ТЗ 8.1, 9.3).
   Этап 1 (вёрстка): проверка без сервера, сообщения под полем, введённые
   данные не теряются. Серверная проверка — на этапе натяжки WordPress.

   Правила:
     • required            — «Заполните поле» (или текст из data-error);
     • type="tel"          — российский номер, 10 цифр или 11 с 7/8;
                             на blur значение приводится к +7 (999) 123-45-67;
     • type="email"        — name@domain.ru (домен с точкой);
     • name="name"         — минимум 2 буквы, без цифр;
     • pattern             — проверка по атрибуту (ИНН, КПП, ОГРН, индекс);
     • type="number"       — min / max / step (количество);
     • minlength           — минимальная длина.

   Подключение: <script src="../js/validation.js" defer></script> на страницах
   с формами. Форму можно исключить атрибутом data-no-validate.
   ========================================================================== */
(function () {
  'use strict';

  var DEFAULT = {
    required: 'Заполните поле',
    select: 'Выберите значение из списка',
    name: 'Укажите имя — минимум 2 буквы, без цифр',
    email: 'Укажите e-mail, например name@mail.ru',
    phone: 'Введите телефон: +7 (999) 123-45-67',
    pattern: 'Проверьте формат поля',
    number: 'Введите число',
    min: 'Минимальное значение — ',
    max: 'Максимальное значение — ',
    short: 'Слишком короткое значение'
  };

  var SKIP = ['checkbox', 'radio', 'submit', 'button', 'reset', 'file', 'hidden', 'image'];
  var touched = new WeakSet();
  var errorSeq = 0;

  function digits(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function isValidPhone(value) {
    var d = digits(value);
    if (d.length === 11) return d.charAt(0) === '7' || d.charAt(0) === '8';
    return d.length === 10;
  }

  function formatPhone(value) {
    var d = digits(value);
    if (d.length === 11 && (d.charAt(0) === '7' || d.charAt(0) === '8')) d = d.slice(1);
    if (d.length !== 10) return value;
    return '+7 (' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6, 8) + '-' + d.slice(8, 10);
  }

  function isValidName(value) {
    var v = String(value || '').trim();
    return v.length >= 2 && /^[А-Яа-яЁёA-Za-z][А-Яа-яЁёA-Za-z\-' ]*$/.test(v);
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[A-Za-zА-Яа-яЁё]{2,}$/.test(String(value || '').trim());
  }

  /* Только поля внутри формы: у счётчиков количества вне форм (корзина)
     своя логика в cart.js, и лишние сообщения им не нужны.
     Исключение — скрытые input'ы с data-validate: так работает кастомный
     селект (значение хранится в input[type="hidden"]). */
  function isField(el) {
    return el && el.matches && el.matches('input, select, textarea') &&
      !el.disabled && el.form &&
      (SKIP.indexOf(el.type) === -1 || el.hasAttribute('data-validate'));
  }

  /* Элемент, который получает фокус при ошибке: у кастомных контролов
     сам input скрыт, поэтому фокус уводим на видимую кнопку (data-focus) */
  function focusTargetOf(field) {
    if (!field.dataset.focus) return null;
    var holder = field.closest('.field') || field.parentNode;
    return holder ? holder.querySelector(field.dataset.focus) : null;
  }

  /* Текст ошибки для поля; пустая строка — поле валидно */
  function messageFor(field) {
    var value = String(field.value || '').trim();

    if (value === '') {
      if (!field.required) return '';
      return field.dataset.error || (field.tagName === 'SELECT' ? DEFAULT.select : DEFAULT.required);
    }

    if (field.type === 'tel' && !isValidPhone(value)) return field.dataset.error || DEFAULT.phone;
    if (field.type === 'email' && !isValidEmail(value)) return field.dataset.error || DEFAULT.email;
    if ((field.name === 'name' || field.hasAttribute('data-validate-name')) && !isValidName(value)) return field.dataset.error || DEFAULT.name;

    if (field.type === 'number') {
      var num = Number(value);
      if (isNaN(num)) return field.dataset.error || DEFAULT.number;
      if (field.min !== '' && num < Number(field.min)) return DEFAULT.min + field.min;
      if (field.max !== '' && num > Number(field.max)) return DEFAULT.max + field.max;
    }

    if (field.pattern) {
      var re = null;
      try { re = new RegExp('^(?:' + field.pattern + ')$'); } catch (e) { re = null; }
      if (re && !re.test(value)) return field.dataset.error || DEFAULT.pattern;
    }

    if (field.minLength > 0 && value.length < field.minLength) return DEFAULT.short;

    return '';
  }

  function holderFor(field) {
    return field.closest('.field') ||
      field.closest('.callback-field') ||
      field.closest('.sampler-qty') ||
      field.closest('.qty') ||
      field.closest('label') ||
      field.parentNode;
  }

  function errorNode(field) {
    var holder = holderFor(field);
    if (!holder) return null;
    var node = holder.querySelector('.field__error');
    if (!node) {
      node = document.createElement('span');
      node.className = 'field__error';
      /* id не из name: на странице несколько форм с полями name/phone,
         одинаковые id сломали бы связку aria-describedby */
      do { errorSeq += 1; } while (document.getElementById('err-' + errorSeq));
      node.id = 'err-' + errorSeq;
      holder.appendChild(node);
    }
    return node;
  }

  function setError(field, message) {
    var node = errorNode(field);
    if (!node) return;
    node.textContent = message;
    field.setAttribute('aria-invalid', 'true');
    var target = focusTargetOf(field);
    if (target) target.setAttribute('aria-invalid', 'true');
    if (field.dataset.describedby === undefined) {
      field.dataset.describedby = field.getAttribute('aria-describedby') || '';
    }
    var ids = (field.dataset.describedby ? field.dataset.describedby + ' ' : '') + node.id;
    field.setAttribute('aria-describedby', ids);
    if (target) target.setAttribute('aria-describedby', ids);
  }

  function clearError(field) {
    var holder = holderFor(field);
    var node = holder ? holder.querySelector('.field__error') : null;
    if (node) node.remove();
    field.removeAttribute('aria-invalid');
    var target = focusTargetOf(field);
    if (target) {
      target.removeAttribute('aria-invalid');
      target.removeAttribute('aria-describedby');
    }
    if (field.dataset.describedby !== undefined) {
      if (field.dataset.describedby) field.setAttribute('aria-describedby', field.dataset.describedby);
      else field.removeAttribute('aria-describedby');
    }
  }

  function fieldsOf(form) {
    return Array.prototype.filter.call(form.elements, isField);
  }

  /* Согласие на обработку данных: ошибка выводится в готовый блок
     (.consent__error рядом с чекбоксом) либо под подписью */
  function checkConsent(form) {
    var first = null;

    Array.prototype.forEach.call(form.querySelectorAll('input[type="checkbox"][required]'), function (box) {
      if (box.disabled || box.checked) {
        box.removeAttribute('aria-invalid');
        var described = box.getAttribute('aria-describedby');
        var message = described ? document.getElementById(described) : null;
        if (message && message.classList.contains('consent__error')) message.removeAttribute('data-visible');
        var label = box.closest('label') || box.parentNode;
        var tipNode = label ? label.querySelector('.field__error') : null;
        if (tipNode) tipNode.remove();
        return;
      }

      box.setAttribute('aria-invalid', 'true');
      var id = box.getAttribute('aria-describedby');
      var node = id ? document.getElementById(id) : null;

      if (node && node.classList.contains('consent__error')) {
        node.toggleAttribute('data-visible', true);
      } else {
        var holder = box.closest('label') || box.parentNode;
        var tip = holder.querySelector('.field__error');
        if (!tip) {
          tip = document.createElement('span');
          tip.className = 'field__error';
          holder.appendChild(tip);
        }
        tip.textContent = box.dataset.error || 'Отметьте согласие на обработку персональных данных';
      }

      if (!first) first = box;
    });

    return first;
  }

  /* Проверка всей формы; возвращает true, если ошибок нет */
  function validate(form) {
    var first = null;

    fieldsOf(form).forEach(function (field) {
      touched.add(field);
      var message = messageFor(field);
      if (message) {
        setError(field, message);
        if (!first) first = field;
      } else {
        clearError(field);
      }
    });

    var consent = checkConsent(form);
    if (!first) first = consent;

    if (first) {
      var target = focusTargetOf(first) || first;
      target.focus();
      if (target.scrollIntoView) target.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return false;
    }
    return true;
  }

  function clear(form) {
    fieldsOf(form).forEach(function (field) {
      touched.delete(field);
      clearError(field);
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[type="checkbox"][required]'), function (box) {
      box.removeAttribute('aria-invalid');
      var id = box.getAttribute('aria-describedby');
      var node = id ? document.getElementById(id) : null;
      if (node && node.classList.contains('consent__error')) node.removeAttribute('data-visible');
    });
  }

  /* --- Проверка при отправке: перехватываем раньше обработчиков страницы --- */

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.tagName !== 'FORM') return;
    if (form.hasAttribute('data-no-validate')) return;

    /* «Отменить» в окне реквизитов не должен запускать проверку */
    var submitter = event.submitter;
    if (submitter && submitter.value === 'cancel') return;

    if (!validate(form)) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  }, true);

  /* --- Живая проверка: после первой попытки отправки или по blur --- */

  document.addEventListener('input', function (event) {
    var field = event.target;
    if (!isField(field) || !touched.has(field)) return;
    var message = messageFor(field);
    if (message) setError(field, message);
    else clearError(field);
  }, true);

  document.addEventListener('blur', function (event) {
    var field = event.target;
    if (!isField(field)) return;

    /* Телефон приводим к единому виду, когда поле заполнено */
    if (field.type === 'tel' && field.value.trim()) {
      var formatted = formatPhone(field.value);
      if (formatted !== field.value) field.value = formatted;
    }

    if (!touched.has(field) && !field.value.trim()) return;
    touched.add(field);

    var message = messageFor(field);
    if (message) setError(field, message);
    else clearError(field);
  }, true);

  /* Сброс ошибок при reset формы */
  document.addEventListener('reset', function (event) {
    if (event.target && event.target.tagName === 'FORM') clear(event.target);
  }, true);

  /* Отправка формы на сервер; ошибка сервера приходит текстом для показа. */
  function send(form, type) {
    var config = window.weissForms;
    if (!config || !config.ajaxUrl) {
      return Promise.reject(new Error('Форма временно недоступна. Попробуйте позже.'));
    }

    var data = new FormData(form);
    data.append('action', 'weiss_lead');
    data.append('nonce', config.nonce);
    data.append('type', type);
    data.append('page', window.location.href);

    return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
      /* Не-JSON ответ (просроченный ключ, ошибка сервера) — показываем
         понятный текст, а не «Unexpected token» из JSON.parse */
      .then(function (response) { return response.json().catch(function () { return null; }); })
      .then(function (json) {
        if (!json || !json.success) {
          var message = json && json.data && json.data.message
            ? json.data.message
            : 'Не удалось отправить обращение. Попробуйте ещё раз.';
          throw new Error(message);
        }
        return json.data;
      })
      .catch(function (error) {
        if (error && error.message && !/^(Unexpected|Failed to fetch|NetworkError|Load failed)/i.test(error.message)) {
          throw error;
        }
        throw new Error('Не удалось отправить обращение. Проверьте соединение и попробуйте ещё раз.');
      });
  }

  window.WCForm = { validate: validate, clear: clear, send: send };
})();
