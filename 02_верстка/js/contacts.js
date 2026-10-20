/* ==========================================================================
   contacts.js — М04 «Контакты»: этап 1, демо-отправка формы обращения.
   ТЗ 9.3: форма обращения с именем, регионом, телефоном и сообщением;
   проверка обязательных полей на клиенте, данные не теряются при ошибке.
   Отправка на сервер и результат в окне М17 — этап 2.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('contacts-form');
  if (!form) return;

  var error = document.getElementById('contacts-error');

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    var firstInvalid = null;

    form.querySelectorAll('[required]').forEach(function (field) {
      var invalid = !field.checkValidity();
      field.toggleAttribute('aria-invalid', invalid);
      if (invalid && !firstInvalid) firstInvalid = field;
    });

    if (error) error.toggleAttribute('data-visible', Boolean(firstInvalid));

    if (firstInvalid) {
      firstInvalid.focus();
      return;
    }

    /* Этап 1: сервера нет — показываем подтверждение вместо перезагрузки */
    var card = form.closest('.contacts-form-card');
    if (card) {
      card.innerHTML =
        '<h2 class="contacts-form-card__title">Спасибо, ' +
        (form.elements.name.value.trim().split(' ')[0] || 'мы получили заявку') +
        '!</h2>' +
        '<p class="contacts-form-card__lead">Мы перезвоним в рабочее время: с 9:00 до 18:00 (МСК).</p>';
    }
  });

  form.addEventListener('input', function (event) {
    if (event.target.matches('[required]')) {
      event.target.removeAttribute('aria-invalid');
      if (error) error.removeAttribute('data-visible');
    }
  });
})();
