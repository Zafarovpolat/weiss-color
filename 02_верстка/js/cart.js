/* ==========================================================================
   cart.js — М16 «Корзина»: этап 1, демо-состояние.
   ТЗ 7.1: количество меняется кнопками и вводом, отрицательные/нулевые/дробные
   значения не принимаются; есть удаление позиции и пересчёт сумм.
   Хранение корзины, проверка остатков и цены — этап 2 (WordPress/WooCommerce).
   ========================================================================== */
(function () {
  'use strict';

  var list = document.querySelector('.cart-list');
  if (!list) return;

  var pageCount = document.querySelector('.cart-count');      // плашка у H1
  var panelCount = document.querySelector('.order-panel__count');
  var panelGoods = document.querySelector('.order-panel__goods'); // «Товары (N шт.)»
  var panelTotal = document.querySelector('.order-panel__sum dd');
  var headerCount = document.querySelector('.cart__count');
  var panel = document.querySelector('.order-panel');

  /* --- форматирование --- */

  function money(value) {
    return new Intl.NumberFormat('ru-RU').format(value) + ' ₽';
  }

  function itemsWord(n) {
    var mod10 = n % 10;
    var mod100 = n % 100;
    if (mod10 === 1 && mod100 !== 11) return 'товар';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'товара';
    return 'товаров';
  }

  /* --- данные позиции --- */

  function items() {
    return Array.prototype.slice.call(list.querySelectorAll('.cart-item'));
  }

  function priceOf(item) {
    return Number(item.getAttribute('data-price')) || 0;
  }

  function qtyInput(item) {
    return item.querySelector('.qty__input');
  }

  function qtyOf(item) {
    return Math.max(1, parseInt(qtyInput(item).value, 10) || 1);
  }

  /* --- пересчёт --- */

  function recalc() {
    var rows = items();
    var total = 0;
    var units = 0;

    rows.forEach(function (item) {
      var qty = qtyOf(item);
      var sum = priceOf(item) * qty;
      total += sum;
      units += qty;

      var cell = item.querySelector('.cart-item__total');
      if (cell) cell.textContent = money(sum);

      /* кнопка «−» недоступна при количестве 1 */
      var minus = item.querySelector('.qty__btn--minus');
      if (minus) minus.disabled = qty <= 1;
    });

    var label = units + ' ' + itemsWord(units);

    if (pageCount) pageCount.textContent = label;
    if (panelCount) panelCount.textContent = label;
    if (panelGoods) panelGoods.textContent = 'Товары (' + units + ' шт. )';
    if (panelTotal) panelTotal.textContent = money(total);
    if (headerCount) headerCount.textContent = String(units);

    var cartLink = document.querySelector('.cart');
    if (cartLink) cartLink.setAttribute('aria-label', 'Корзина, товаров: ' + units);

    if (rows.length === 0) showEmpty();
  }

  /* --- пустое состояние --- */

  function showEmpty() {
    var layout = document.querySelector('.cart-layout');
    var head = document.querySelector('.cart-head__row');
    var pagination = document.querySelector('.cart-pagination');

    list.hidden = true;
    if (pagination) pagination.hidden = true;
    if (panel) panel.hidden = true;
    if (pageCount) pageCount.hidden = true;
    if (head && !document.querySelector('.cart-empty')) {
      var empty = document.createElement('div');
      empty.className = 'cart-empty';
      empty.innerHTML =
        '<p class="cart-empty__title">В корзине пока нет товаров</p>' +
        '<p class="cart-empty__text">Загляните в каталог — подберём краску и оттенок.</p>' +
        '<a class="cart-empty__link" href="catalog.html">Перейти в каталог</a>';
      layout.parentNode.insertBefore(empty, layout);
    }
  }

  /* --- количество --- */

  function setQty(item, value) {
    var input = qtyInput(item);
    input.value = String(Math.max(1, Math.floor(value)));
    recalc();
  }

  list.addEventListener('click', function (event) {
    var button = event.target.closest('[data-qty], [data-cart-remove]');
    if (!button) return;

    var item = button.closest('.cart-item');
    if (!item) return;

    if (button.hasAttribute('data-cart-remove')) {
      item.remove();
      recalc();
      return;
    }

    var delta = button.getAttribute('data-qty') === 'inc' ? 1 : -1;
    setQty(item, qtyOf(item) + delta);
  });

  /* ТЗ 7.1: отрицательные, нулевые и дробные значения покупки не принимаются.
     Во время ввода показываем сумму только для целых положительных значений,
     при подтверждении (change/blur) приводим поле к целому >= 1. */
  list.addEventListener('input', function (event) {
    var input = event.target.closest('.qty__input');
    if (!input) return;

    var raw = input.value.trim();
    var valid = /^\d+$/.test(raw) && parseInt(raw, 10) >= 1;
    input.setAttribute('aria-invalid', valid ? 'false' : 'true');

    if (!valid) return;
    var item = input.closest('.cart-item');
    var cell = item.querySelector('.cart-item__total');
    if (cell) cell.textContent = money(priceOf(item) * Math.min(parseInt(raw, 10), 999));
  });

  list.addEventListener('change', function (event) {
    var input = event.target.closest('.qty__input');
    if (!input) return;

    var raw = parseFloat(String(input.value).replace(',', '.'));
    var qty = Number.isFinite(raw) ? Math.floor(raw) : 1; // 2.7 → 2, −3 → 1
    input.removeAttribute('aria-invalid');
    setQty(input.closest('.cart-item'), qty || 1);
  });

  /* --- промокод: на этапе 1 без сервера, только валидация ввода --- */

  var promo = document.querySelector('.promo__btn');
  if (promo) {
    promo.addEventListener('click', function () {
      var input = document.getElementById('promo-code');
      if (!input) return;
      if (!input.value.trim()) {
        input.setAttribute('aria-invalid', 'true');
        input.focus();
        return;
      }
      input.removeAttribute('aria-invalid');
    });
  }

  recalc();
})();
