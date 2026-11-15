// wc-cart.js — добавление в корзину на реальном WooCommerce.
// Кнопка в карточке каталога несёт data-weiss-add="<id товара>": POST на
// стандартный wc-ajax=add_to_cart, затем окно М23 и обновление счётчика.
(() => {
  'use strict';

  const nav = window.weissNav || {};
  const cartUrl = (window.wcSettings && wcSettings.cartUrl) || nav.cart || '/cart/';
  const ajaxUrl = (window.wcSettings && wcSettings.wcAjaxUrl) || nav.wcAjax || '/?wc-ajax=add_to_cart';

  const dialog = document.createElement('dialog');
  dialog.className = 'mcard mcard-add';
  dialog.id = 'add-to-cart-dialog';
  dialog.tabIndex = -1;
  dialog.setAttribute('aria-labelledby', 'add-to-cart-title');
  dialog.innerHTML = `
    <button class="mcard__close" type="button" data-modal-close aria-label="Закрыть окно">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
        <path d="M3 3l18 18M21 3 3 21" stroke-linecap="round"/>
      </svg>
    </button>
    <h2 class="mcard__title" id="add-to-cart-title">Товар успешно добавлен в корзину</h2>
    <img class="mcard-add__image" src="" width="198" height="291" alt="" decoding="async">
    <p class="mcard-add__name"></p>
    <div class="mcard__actions">
      <a class="btn btn--brown" href="${cartUrl}">В корзину</a>
      <button class="btn btn--outline" type="button" data-modal-close>Продолжить покупку</button>
    </div>`;
  document.body.appendChild(dialog);

  const modalImage = dialog.querySelector('.mcard-add__image');
  const modalName = dialog.querySelector('.mcard-add__name');

  const syncScrollLock = () => {
    const open = Boolean(document.querySelector('dialog[open]'));
    document.documentElement.classList.toggle('is-modal-open', open);
    document.body.classList.toggle('is-modal-open', open);
  };
  dialog.addEventListener('toggle', syncScrollLock);
  dialog.addEventListener('close', syncScrollLock);

  dialog.querySelectorAll('[data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', () => dialog.close());
  });
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  const showModal = (trigger, shade) => {
    const card = trigger.closest('.product, .c-product');
    const productPage = trigger.closest('.p-price-box');
    let img;
    let heading = null;

    if (card) {
      img = card.querySelector('.product__media img, .c-product__media img');
      heading = card.querySelector('.product__title, .c-product__title');
    } else if (productPage) {
      img = document.querySelector('.p-hero__can img');
      heading = document.querySelector('.p-hero__title');
    } else if (trigger.classList.contains('viz-cart')) {
      img = document.querySelector('.viz-product__img');
      heading = null;
    } else {
      img = document.querySelector('.sampler-photo img');
      heading = document.querySelector('.sampler-aside__title');
    }

    /* В заголовке карточки бренд отделён <br>, во «внутреннем тексте» это
       перевод строки — иначе «Acriluxкраска для кухни и ванной». */
    let text = '';
    if (trigger.classList.contains('viz-cart')) {
      text = trigger.getAttribute('data-weiss-name') || '';
    } else if (heading) {
      text = heading.innerText || heading.textContent || '';
    }
    text = text.replace(/\s+/g, ' ').trim();
    if (shade) text += (text ? ' · ' : '') + shade;

    modalImage.hidden = !img;
    modalImage.src = img ? (img.currentSrc || img.src) : '';
    modalImage.alt = text;
    modalName.textContent = text;
    modalName.hidden = !text;

    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
      dialog.focus();
    } else {
      dialog.setAttribute('open', '');
    }
  };

  const updateCount = (fragments) => {
    if (!fragments || !fragments['.cart__count']) return;
    const holder = document.createElement('div');
    holder.innerHTML = fragments['.cart__count'];
    const fresh = holder.firstElementChild;
    const current = document.querySelector('.cart__count');
    if (fresh && current) current.textContent = fresh.textContent;
    const link = document.querySelector('.cart');
    if (fresh && link) {
      link.setAttribute('aria-label', 'Корзина, товаров: ' + fresh.textContent.trim());
    }
  };

  const currentShade = (trigger) => {
    if (trigger.classList.contains('viz-cart')) {
      const label = document.querySelector('.viz-swatch--active .viz-swatch__label');
      return label ? label.textContent.trim() : trigger.getAttribute('data-weiss-code') || '';
    }
    /* У пробника код уже в заголовке — второй раз не повторяем. */
    if (trigger.tagName === 'FORM') return '';
    return trigger.getAttribute('data-weiss-code') || '';
  };

  const add = async (source, quantity) => {
    const productId = source.getAttribute('data-weiss-add');
    const variationId = source.getAttribute('data-weiss-variation');
    const palette = source.getAttribute('data-weiss-palette');
    const code = source.getAttribute('data-weiss-code');
    const fallbackUrl = source.getAttribute('data-weiss-url') || source.getAttribute('href') || '/';

    const body = new URLSearchParams();
    /* AJAX-добавление WooCommerce ждёт в product_id саму вариацию, не родителя. */
    const chosen = variationId && variationId !== '0' ? variationId : productId;
    body.set('product_id', chosen);
    body.set('quantity', String(quantity || 1));
    if (variationId && variationId !== '0') body.set('variation_id', variationId);
    const attributes = source.getAttribute('data-weiss-attributes');
    if (attributes) {
      try {
        const map = JSON.parse(attributes);
        Object.keys(map).forEach((key) => body.set('variation[' + key + ']', map[key]));
      } catch (error) {
        /* без атрибутов WooCommerce сам отклонит добавление фасовки */
      }
    }
    if (palette && code) {
      body.set('weiss_palette', palette);
      body.set('weiss_code', code);
    }

    try {
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      });
      const data = await response.json();

      if (data && data.error && data.product_url) {
        window.location.href = data.product_url;
        return;
      }
      if (data && data.error) {
        window.location.href = fallbackUrl;
        return;
      }

      updateCount(data && data.fragments);
      showModal(source, currentShade(source));
    } catch (error) {
      window.location.href = fallbackUrl;
    }
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-weiss-add]');
    if (!trigger) return;
    if (trigger.tagName === 'FORM') return;

    event.preventDefault();
    add(trigger, 1);
  });

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-weiss-add]');
    if (!form) return;

    event.preventDefault();
    const input = form.querySelector('input[name="qty"], .qty__input');
    const quantity = Math.max(1, parseInt(input ? input.value : '1', 10) || 1);
    add(form, quantity);
  });
})();
