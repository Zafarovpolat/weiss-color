/* ==========================================================================
   ui.js — кликабельность служебных элементов: пагинация и табы-фильтры.

   Настоящей постраничности на этапе вёрстки нет: реальные наборы (24 196
   оттенков, товары) подключаются на этапе 2 из WordPress. Но ссылки вида
   href="#" — это мёртвые кнопки, поэтому клик переключает активную страницу,
   состояние «Назад/Вперёд» и прокручивает список к началу.
   ========================================================================== */
(function () {
  'use strict';

  var pagers = document.querySelectorAll('.pagination:not(.viz-pagination)');

  Array.prototype.forEach.call(pagers, function (pager) {
    var items = Array.prototype.slice.call(pager.querySelectorAll('.pagination__item'));

    /* Настоящая постраничность из WordPress ходит по ссылкам — демо-логику
       для href="#" к ней не применяем. */
    var real = items.some(function (item) {
      var href = item.getAttribute('href');
      return href && href !== '#';
    });
    if (real) return;

    var numeric = items.filter(function (item) {
      return /^\d+$/.test(item.textContent.trim());
    });
    if (!numeric.length) return;

    var scope = pager.closest('main') || document;
    var list = scope.querySelector('.catalog__grid, .products, .shades-grid, .palettes-grid, .c-products');

    function setPage(index) {
      var target = Math.min(Math.max(index, 0), numeric.length - 1);

      numeric.forEach(function (item, i) {
        if (i === target) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
      });

      items.forEach(function (item) {
        var label = item.textContent.trim();
        if (/^\d+$/.test(label)) return;
        var disabled = /назад/i.test(label) ? target === 0 : target === numeric.length - 1;
        item.setAttribute('aria-disabled', String(disabled));
      });

      if (list) list.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    pager.addEventListener('click', function (event) {
      var item = event.target.closest('.pagination__item');
      if (!item) return;
      event.preventDefault();
      if (item.getAttribute('aria-disabled') === 'true') return;

      var label = item.textContent.trim();
      var current = 0;
      numeric.forEach(function (node, i) {
        if (node.getAttribute('aria-current') === 'page') current = i;
      });

      if (/^\d+$/.test(label)) setPage(Number(label) - 1);
      else if (/назад/i.test(label)) setPage(current - 1);
      else setPage(current + 1);
    });
  });

  /* Табы «Цвет и совместимые материалы»: раньше все шесть были href="#"
     и не переключались. Теперь клик не только меняет активное состояние,
     но и фильтрует карточки по data-material (ТЗ, п.5.5). */
  var tabs = document.querySelectorAll('.c-tabs__item');

  Array.prototype.forEach.call(tabs, function (tab) {
    tab.addEventListener('click', function (event) {
      event.preventDefault();

      Array.prototype.forEach.call(tabs, function (other) {
        var active = other === tab;
        other.classList.toggle('is-active', active);
        if (active) other.setAttribute('aria-current', 'page');
        else other.removeAttribute('aria-current');
      });

      var material = tab.getAttribute('data-material');
      if (!material) return;

      Array.prototype.forEach.call(document.querySelectorAll('.c-products .c-product'), function (card) {
        card.hidden = material !== 'all' && card.getAttribute('data-material') !== material;
      });
    });
  });
})();
