// main.js — общая инициализация: мобильное меню, модальное окно звонка.
// Чистый JS без сборщика; подключается на страницах с defer.

/* ---------- Мобильное меню (п.3.1 / п.3.3 ТЗ) ---------- */
(() => {
  const burger = document.querySelector('.burger');
  const nav = document.querySelector('.site-nav');
  if (!burger || !nav) return;

  const label = burger.querySelector('.visually-hidden');
  const desktop = window.matchMedia('(min-width: 1100px)');

  const setOpen = (open) => {
    burger.setAttribute('aria-expanded', String(open));
    nav.toggleAttribute('data-open', open);
    if (label) label.textContent = open ? 'Скрыть меню' : 'Показать меню';
  };

  burger.addEventListener('click', () => {
    setOpen(burger.getAttribute('aria-expanded') !== 'true');
  });

  // Esc закрывает меню; фокус возвращаем на кнопку
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && burger.getAttribute('aria-expanded') === 'true') {
      setOpen(false);
      burger.focus();
    }
  });

  // Клик по пункту меню закрывает его (навигация происходит по ссылке)
  nav.addEventListener('click', (e) => {
    if (e.target.closest('a') && !desktop.matches) setOpen(false);
  });

  // На десктопе меню всегда раскрыто — сбрасываем состояние
  desktop.addEventListener('change', (e) => { if (e.matches) setOpen(false); });
})();

/* ---------- Модальное окно «Заказать звонок» (М25, п.9.3) ---------- */
(() => {
  const dialog = document.getElementById('callback-dialog');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  document.querySelectorAll('[data-modal-open]').forEach((btn) => {
    btn.addEventListener('click', () => dialog.showModal());
  });

  dialog.querySelector('[data-modal-close]')?.addEventListener('click', () => dialog.close());

  // Клик по подложке закрывает окно (клиент по краю — не кнопка)
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) dialog.close();
  });

  // Демо-отправка: на этапе 1 бэкенда нет. Поля проверяет validation.js
  // (сообщения под полями), здесь — закрытие окна и очистка формы.
  // Фокус после закрытия возвращается на вызвавший элемент автоматически.
  const form = document.getElementById('callback-form');
  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    const valid = window.WCForm ? window.WCForm.validate(form) : form.checkValidity();
    if (!valid) return;
    dialog.close();
    form.reset();
    window.WCForm?.clear(form);
  });
})();

/* ---------- Счётчик количества вне корзины (М06 «Пробник») ----------
   cart.js работает только при наличии .cart-list и на этой странице
   выходит сразу, поэтому кнопки «−»/«+» обрабатываются здесь.
   Правила те же: целое число не меньше min (по умолчанию 1). */
(() => {
  const LIMITS = (input) => ({
    min: input.min === '' ? 1 : Number(input.min),
    max: input.max === '' ? Infinity : Number(input.max),
    step: Number(input.step) || 1
  });

  /* Приводит любое число к допустимому: целое, в границах, кратно шагу */
  const normalize = (input, value) => {
    const { min, max, step } = LIMITS(input);
    let result = Math.round(value);
    if (!Number.isFinite(result)) result = min;
    result = Math.min(Math.max(result, min), max);
    // выравнивание по шагу от min, как у нативного input[type=number]
    if (step > 0) result = min + Math.round((result - min) / step) * step;
    return result;
  };

  const currentValue = (input) => normalize(input, Number(String(input.value).replace(',', '.')));

  const setValue = (input, value) => {
    if (input.value === String(value)) return;
    input.value = String(value);
    // событие нужно, чтобы validation.js снял ошибку под полем
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-qty]');
    // в корзине счётчиком управляет cart.js — не дублируем
    if (!button || button.closest('.cart-list')) return;

    const input = button.closest('.qty')?.querySelector('.qty__input');
    if (!input || input.disabled) return;

    const { step } = LIMITS(input);
    const direction = button.getAttribute('data-qty') === 'inc' ? 1 : -1;
    // границы применяем к результату, иначе «−» на минимуме уводит в 0
    setValue(input, normalize(input, currentValue(input) + direction * step));
  });

  // ручной ввод: приводим к допустимому значению при подтверждении
  document.addEventListener('change', (event) => {
    const input = event.target.closest?.('.qty__input');
    if (!input || input.closest('.cart-list')) return;
    setValue(input, normalize(input, Number(String(input.value).replace(',', '.'))));
  });
})();
