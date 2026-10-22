/* ==========================================================================
   contacts.js — М04 «Контакты»: этап 1, демо-отправка формы обращения.
   ТЗ 9.3: форма обращения с именем, регионом, телефоном и сообщением.
   Проверку полей выполняет js/validation.js (сообщения под полями);
   здесь — только демо-результат отправки. Сервер — этап 2.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.getElementById('contacts-form');
  if (!form) return;

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    /* Страховка, если validation.js не подключён */
    var valid = window.WCForm ? window.WCForm.validate(form) : form.checkValidity();
    if (!valid) return;

    /* Этап 1: сервера нет — показываем подтверждение вместо перезагрузки */
    var card = form.closest('.contacts-form-card');
    if (card) {
      var name = (form.elements.name.value || '').trim().split(' ')[0];
      card.innerHTML =
        '<h2 class="contacts-form-card__title">Спасибо' +
        (name ? ', ' + name : '') +
        '!</h2>' +
        '<p class="contacts-form-card__lead">Мы перезвоним в рабочее время: с 9:00 до 18:00 (МСК).</p>';
    }
  });
})();
