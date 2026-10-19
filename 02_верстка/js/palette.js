// palette.js — палитры и оттенки (раздел 5): обзор 28 палитр (М13),
// селектор палитры (М19), список оттенков с поиском по коду (М07),
// плашки цвета из RGB/HEX. На вёрстке — демо-набор оттенков.

// М19: раскрытие/скрытие селектора палитры (состояние с макета — открыто)
document.querySelectorAll('.pselect').forEach(root => {
  const control = root.querySelector('.pselect__control');
  if (!control) return;
  control.addEventListener('click', () => {
    const open = root.classList.toggle('is-open');
    control.setAttribute('aria-expanded', String(open));
  });
});
