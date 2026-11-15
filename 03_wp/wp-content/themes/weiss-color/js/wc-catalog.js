// wc-catalog.js — связка фильтра и сортировки макета с запросом каталога.
// Панель собрана как форма GET: категории — чекбоксы, цена — два ползунка,
// сортировка — кастомный список (UI в catalog.js). Здесь значения переносятся
// в скрытые поля и форма отправляется.
(() => {
  'use strict';

  const form = document.querySelector('.filter__form');
  if (!form) return;

  const orderby = form.querySelector('[data-orderby-input]');
  const priceMin = form.querySelector('[data-price-input="min"]');
  const priceMax = form.querySelector('[data-price-input="max"]');
  const rangeMin = form.querySelector('[data-range-min]');
  const rangeMax = form.querySelector('[data-range-max]');

  /* Значения ползунков попадают в запрос только после того, как их тронули:
     стартовые 700–12 000 из макета не должны фильтровать каталог сразу. */
  const syncPrices = () => {
    if (rangeMin && priceMin && rangeMin.dataset.touched) priceMin.value = rangeMin.value;
    if (rangeMax && priceMax && rangeMax.dataset.touched) priceMax.value = rangeMax.value;
  };

  [rangeMin, rangeMax].forEach((input) => {
    if (input) input.addEventListener('input', () => {
      input.dataset.touched = '1';
      syncPrices();
    });
  });

  /* «Вся продукция» исключает остальные категории, и наоборот. */
  const boxes = Array.from(form.querySelectorAll('input[name="category[]"]'));
  const all = boxes.find((box) => box.value === 'all');

  boxes.forEach((box) => {
    box.addEventListener('change', () => {
      if (box === all && box.checked) {
        boxes.forEach((other) => { if (other !== all) other.checked = false; });
      } else if (box.checked && all) {
        all.checked = false;
      }
      if (boxes.every((other) => !other.checked) && all) all.checked = true;
    });
  });

  /* Выбор сортировки сразу перестраивает выдачу (catalog.js уже обновил UI). */
  const list = form.parentElement.querySelector('.sort__list');
  if (list && orderby) {
    list.addEventListener('click', (event) => {
      const option = event.target.closest('.sort__option');
      if (!option) return;
      const value = option.getAttribute('data-value');
      if (!value) return;
      orderby.value = value;
      syncPrices();
      form.submit();
    });
  }

  form.addEventListener('submit', syncPrices);
})();
