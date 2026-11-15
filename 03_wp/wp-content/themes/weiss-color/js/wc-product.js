// wc-product.js — выбор объёма на карточке товара: кнопки переключают
// цену и вариант, который уйдёт в корзину (data-weiss-variation на кнопке).
// При выбранном цвете цена включает доплату за колеровку (ТЗ 4.5).
(() => {
  'use strict';

  const volumes = document.querySelector('[data-weiss-volumes]');
  if (!volumes) return;

  const items = volumes.querySelectorAll('.p-volumes__item');
  const priceEl = document.querySelector('[data-weiss-price]');
  const noteEl = document.querySelector('[data-weiss-price-note]');
  const boxEl = document.querySelector('[data-weiss-tint]');
  const addBtn = document.querySelector('.p-price__btn[data-weiss-add]');
  const vizLink = document.querySelector('[data-weiss-viz-base]');

  /* Копейки — только когда они есть (как weiss_price_compact): доплата
     за колеровку бывает дробной, округление до рубля расходилось с корзиной. */
  const format = (value) => {
    const number = Number(value);
    const decimals = Math.abs(number - Math.round(number)) > 0.004 ? 2 : 0;
    return number.toFixed(decimals)
      .replace('.', ',')
      .replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0') + '₽';
  };

  const tinted = boxEl && boxEl.getAttribute('data-weiss-tint') === '1';

  const note = (state) => {
    if (!noteEl) return;
    if (!tinted) {
      noteEl.textContent = 'Цена указана за базовую краску, итоговая стоимость рассчитывается при выбранном объёме';
    } else if (state === 'off') {
      noteEl.textContent = 'Колеровка для этой фасовки не предусмотрена. Выберите другой объём или базовый цвет без колеровки.';
    } else if (state === 'unknown') {
      noteEl.textContent = 'Стоимость колеровки для этого объёма ещё не согласована — оформить заказ с колеровкой нельзя. Выберите другой объём или купите базовый цвет без колеровки.';
    } else {
      noteEl.textContent = 'Цена указана за выбранный объём с доплатой за колеровку.';
    }
  };

  const skuEl = document.querySelector('[data-weiss-sku] span');
  const skuBox = document.querySelector('[data-weiss-sku]');
  const stockEl = document.querySelector('[data-weiss-stock] span');
  const noticeEl = document.querySelector('[data-weiss-variation-notice]');
  const heroImg = document.querySelector('.p-hero__can img');

  const apply = (item) => {
    const base = Number(item.getAttribute('data-price'));
    const surcharge = item.getAttribute('data-surcharge');
    const known = surcharge !== null && surcharge !== '';
    const tinting = item.getAttribute('data-tinting') !== '0';
    const inStock = item.getAttribute('data-stock') !== '0';
    const sku = item.getAttribute('data-sku') || '';
    const image = item.getAttribute('data-image');

    if (priceEl) {
      priceEl.textContent = format(tinted && tinting && known ? base + Number(surcharge) : base);
    }
    note(tinted ? (tinting ? (known ? 'known' : 'unknown') : 'off') : '');

    if (skuBox && skuEl) {
      skuEl.textContent = sku;
      skuBox.hidden = !sku;
    }
    if (stockEl) stockEl.textContent = inStock ? 'В наличии' : 'Нет в наличии';
    if (heroImg && image) heroImg.src = image;

    /* Товар не в наличии не продаётся (ТЗ 4.4): кнопка не должна выглядеть рабочей */
    if (addBtn) {
      addBtn.disabled = !inStock;
      addBtn.textContent = inStock ? 'В корзину' : 'Нет в наличии';
    }

    if (noticeEl) {
      const problem = !inStock
        ? 'Этой фасовки нет в наличии. Выберите другой объём.'
        : (tinted && !tinting ? 'Колеровка для этой фасовки не предусмотрена. Выберите другой объём.' : '');
      noticeEl.textContent = problem;
      noticeEl.hidden = !problem;
    }
  };

  volumes.addEventListener('click', (event) => {
    const item = event.target.closest('.p-volumes__item');
    if (!item) return;

    items.forEach((btn) => btn.setAttribute('aria-pressed', String(btn === item)));

    apply(item);
    if (addBtn) {
      addBtn.setAttribute('data-weiss-variation', item.getAttribute('data-variation'));
      const attributes = item.getAttribute('data-weiss-attributes');
      if (attributes) addBtn.setAttribute('data-weiss-attributes', attributes);
    }
    if (vizLink) {
      const url = new URL(vizLink.getAttribute('data-weiss-viz-base'), window.location.href);
      url.searchParams.set('weiss_variation', item.getAttribute('data-variation'));
      vizLink.href = url.toString();
    }
  });
})();
