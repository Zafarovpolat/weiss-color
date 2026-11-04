// add-to-cart.js — М23 «Товар добавлен в корзину»: одно окно на страницу.
// Перехватывает «В корзину» в карточках каталога и цвета, на странице товара,
// в визуализаторе и в заказе пробника: сначала подтверждение, дальше —
// «В корзину» или «Продолжить покупку» (закрыть окно и остаться).
(() => {
  const inPages = location.pathname.includes('/pages/');
  const cartHref = inPages ? 'cart.html' : 'pages/cart.html';
  const fallbackImg = inPages
    ? '../assets/img/modal-can.png'
    : 'assets/img/modal-can.png';

  /* Текст без переносов: <br> в заголовках склеивает слова вплотную */
  const readText = (el) => {
    if (!el) return '';
    const clone = el.cloneNode(true);
    clone.querySelectorAll('br').forEach((br) => br.replaceWith(' '));
    return clone.textContent.replace(/\s+/g, ' ').trim();
  };

  const dialog = document.createElement('dialog');
  dialog.className = 'mcard mcard-add';
  dialog.id = 'add-to-cart-dialog';
  /* Фокус ставим на само окно: иначе браузер выделяет крестик
     и вокруг него загорается рамка, которой нет в макете */
  dialog.tabIndex = -1;
  dialog.setAttribute('aria-labelledby', 'add-to-cart-title');
  dialog.innerHTML = `
    <button class="mcard__close" type="button" data-modal-close aria-label="Закрыть окно">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
        <path d="M3 3l18 18M21 3 3 21" stroke-linecap="round"/>
      </svg>
    </button>
    <h2 class="mcard__title" id="add-to-cart-title">Товар успешно добавлен в корзину</h2>
    <img class="mcard-add__image" src="${fallbackImg}" width="198" height="291" alt="" decoding="async">
    <p class="mcard-add__name"></p>
    <div class="mcard__actions">
      <a class="btn btn--brown" href="${cartHref}">В корзину</a>
      <button class="btn btn--outline" type="button" data-modal-close>Продолжить покупку</button>
    </div>`;
  document.body.appendChild(dialog);

  const image = dialog.querySelector('.mcard-add__image');
  const name = dialog.querySelector('.mcard-add__name');

  /* Карточка товара, страница товара, визуализатор, пробник */
  const describe = (trigger) => {
    const card = trigger.closest('.product, .c-product');
    if (card) {
      return {
        img: card.querySelector('.product__media img, .c-product__media img'),
        title: card.querySelector('.product__title, .c-product__title')
      };
    }
    if (trigger.closest('.p-price-box')) {
      return {
        img: document.querySelector('.p-hero__can img'),
        title: document.querySelector('.p-hero__title')
      };
    }
    if (trigger.classList.contains('viz-cart')) {
      return {
        img: document.querySelector('.viz-product__img'),
        title: document.querySelector('.viz-product__title'),
        shade: readText(document.querySelector('.viz-swatch--active .viz-swatch__label'))
      };
    }
    if (trigger.classList.contains('sampler-add')) {
      return {
        img: document.querySelector('.sampler-photo img'),
        title: document.querySelector('.sampler-aside__title')
      };
    }
    return {};
  };

  const show = (trigger) => {
    const info = describe(trigger);
    const text = [readText(info.title), info.shade].filter(Boolean).join(' ');
    const src = info.img ? info.img.currentSrc || info.img.getAttribute('src') : '';

    image.src = src || fallbackImg;
    image.alt = text;
    name.textContent = text;
    name.hidden = !text;

    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
      dialog.focus();
    } else {
      dialog.setAttribute('open', '');
    }
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.product__btn, .p-price__btn, .viz-cart');
    if (!trigger) return;
    event.preventDefault();
    show(trigger);
  });

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('.sampler-buy');
    if (!form) return;
    event.preventDefault();
    show(form.querySelector('.sampler-add'));
  });

  dialog.querySelectorAll('[data-modal-close]').forEach((btn) => {
    btn.addEventListener('click', () => dialog.close());
  });

  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });

  /* Пока окно открыто, страница под ним не прокручивается
     (main.js вешает блокировку только на диалоги, существовавшие при загрузке) */
  const syncScrollLock = () => {
    const open = Boolean(document.querySelector('dialog[open]'));
    document.documentElement.classList.toggle('is-modal-open', open);
    document.body.classList.toggle('is-modal-open', open);
  };

  dialog.addEventListener('toggle', syncScrollLock);
  dialog.addEventListener('close', syncScrollLock);
})();
