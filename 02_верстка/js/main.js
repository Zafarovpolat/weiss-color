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

  // Демо-отправка: на этапе 1 бэкенда нет, форма проверяется браузером (required).
  // Фокус после закрытия возвращается на вызвавший элемент автоматически.
  const form = document.getElementById('callback-form');
  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!form.reportValidity()) return;
    dialog.close();
    form.reset();
  });
})();
