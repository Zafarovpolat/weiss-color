// wc-cart-page.js — корзина на реальном WooCommerce: количество,
// удаление позиции и промокод. Суммы и разметку присылает сервер,
// страница не перезагружается.
(() => {
  'use strict';

  const config = window.weissCart;
  const region = document.querySelector('[data-cart-region]');
  if (!config || !region) return;

  const request = async (params) => {
    const body = new URLSearchParams(params);
    body.set('action', 'weiss_cart_update');
    body.set('nonce', config.nonce);

    const response = await fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
    });
    return response.json();
  };

  const replaceRegion = (data) => {
    if (!data || !data.success) return;

    const fresh = document.createElement('div');
    fresh.innerHTML = data.data.html.trim();
    const next = fresh.firstElementChild;
    if (!next) return;

    const current = document.querySelector('[data-cart-region]');
    if (current) current.replaceWith(next);

    document.querySelectorAll('.cart__count').forEach((el) => {
      el.textContent = data.data.count;
    });
    const link = document.querySelector('.cart');
    if (link) link.setAttribute('aria-label', 'Корзина, товаров: ' + data.data.count);

    const removed = document.querySelector('.cart-count');
    if (removed && data.data.count === 0) removed.hidden = true;
  };

  const sync = async (params) => {
    params.page = String(new URLSearchParams(location.search).get('page') || 1);
    const data = await request(params).catch(() => null);
    replaceRegion(data);
    return data;
  };

  document.addEventListener('click', async (event) => {
    const remove = event.target.closest('[data-cart-remove]');
    if (remove) {
      event.preventDefault();
      await sync({ remove: '1', key: remove.getAttribute('data-cart-key') });
      return;
    }

    const step = event.target.closest('[data-qty]');
    if (step) {
      event.preventDefault();
      const item = step.closest('.cart-item');
      const input = item && item.querySelector('.qty__input');
      if (!input) return;

      const current = Math.max(1, parseInt(input.value, 10) || 1);
      const next = step.getAttribute('data-qty') === 'inc' ? current + 1 : current - 1;
      if (next < 1) return;

      input.value = String(next);
      await sync({ key: step.getAttribute('data-cart-key'), quantity: String(next) });
      return;
    }

    const removeCoupon = event.target.closest('.promo__remove');
    if (removeCoupon) {
      event.preventDefault();
      await sync({ remove_coupon: removeCoupon.getAttribute('data-coupon') });
      return;
    }

    const promo = event.target.closest('.promo__btn');
    if (promo) {
      event.preventDefault();
      const input = document.getElementById('promo-code');
      if (!input) return;

      input.removeAttribute('aria-invalid');

      if (!input.value.trim()) {
        showPromoError(input, 'Введите промокод');
        return;
      }

      const data = await sync({ coupon: input.value.trim() });
      const message = data && data.success ? data.data.error : '';
      /* Блок перерисован — поле берём из свежей разметки, иначе
         сообщение вставится в отсоединённый узел. */
      if (message) {
        const fresh = document.getElementById('promo-code');
        if (fresh) showPromoError(fresh, message);
      }
    }
  });

  document.addEventListener('change', async (event) => {
    const input = event.target.closest('.qty__input');
    if (!input) return;

    const raw = parseInt(input.value, 10);
    const next = Number.isFinite(raw) && raw >= 1 ? raw : 1;
    input.value = String(next);
    await sync({ key: input.getAttribute('data-cart-key'), quantity: String(next) });
  });

  const showPromoError = (input, message) => {
    input.setAttribute('aria-invalid', 'true');

    const row = input.closest('.promo__row');
    let error = document.querySelector('.promo__error');
    if (!error) {
      error = document.createElement('span');
      error.className = 'promo__error';
      error.id = 'promo-error';
      row.insertAdjacentElement('afterend', error);
    }
    error.textContent = message;
    input.setAttribute('aria-describedby', 'promo-error');
    input.focus();
  };
})();
