/* ==========================================================================
   payment.js — М24 «Оплата заказа»: этап 1, демо-логика.
   ТЗ 8.3: своих полей карты нет — оплата уходит на защищённую страницу банка.
   ТЗ 8.4: успех/ожидание/отмена/ошибка приходят от банка; статус «Оплачен»
            не ставится по факту открытия страницы успеха.
   Здесь: выбор способа меняет подпись кнопки; для счёта платёж не создаётся.
   ========================================================================== */
(function () {
  'use strict';

  var form = document.querySelector('.payment-methods');
  var submit = document.getElementById('pay-submit');
  if (!form || !submit) return;

  function method() {
    var checked = form.querySelector('input[name="payment-method"]:checked');
    return checked ? checked.value : 'card';
  }

  function syncLabel() {
    submit.textContent = method() === 'invoice' ? 'Отправить на счёт' : 'Перейти к оплате';
  }

  form.addEventListener('change', syncLabel);

  submit.addEventListener('click', function () {
    /* Этап 1: демо-переход на экран статуса.
       В бою: создание платежа на сервере и редирект на форму Сбербанка
       (для счёта — отправка реквизитов менеджеру и экран ожидания счёта). */
    window.location.href = 'order-success.html';
  });

  syncLabel();
})();
