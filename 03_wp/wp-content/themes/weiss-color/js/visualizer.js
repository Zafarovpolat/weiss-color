// visualizer.js — визуализатор (раздел 6): переключение сцен интерьера
// (гостиная / кухня / ванная / детская), выбор оттенка и постраничный
// список оттенков каталога ACE PAINT.
//
// Перекраска стен: слой цвета по маске сцены + mix-blend-mode: multiply.

(() => {
  const root = document.querySelector('.viz-page');
  if (!root) return;
  const stage = root.querySelector('.viz-main') || root;

  /* ---------- Элементы ---------- */
  const grid = root.querySelector('.viz-swatches');
  const pager = root.querySelector('.viz-pagination');
  const hero = root.querySelector('.viz-hero');
  const heroImg = root.querySelector('.viz-hero__img');
  const paint = root.querySelector('.viz-hero__paint');
  const thumbs = Array.from(root.querySelectorAll('.viz-thumb'));
  const nextBtn = root.querySelector('.viz-next');
  const prevBtn = root.querySelector('.viz-prev');

  /* ---------- Данные ---------- */
  const COLORS = Array.isArray(window.ACE_COLORS) ? window.ACE_COLORS.slice() : [];
  const PER_PAGE = 18;
  const remote = window.weissViz && window.weissViz.ajaxUrl ? window.weissViz : null;
  /* В WordPress страница уже отрисована сервером: число страниц берём из
     разметки, а не из демо-списка (его на витрине нет). */
  let TOTAL_PAGES = remote
    ? Math.max(1, parseInt(stage.dataset.pages, 10) || 1)
    : Math.max(1, Math.ceil(COLORS.length / PER_PAGE));
  let remotePalette = stage.dataset.palette || (remote && remote.palette) || '';
  const remoteProduct = stage.dataset.product || '';
  const remoteVariation = stage.dataset.variation || '';
  const remoteGroup = stage.dataset.group || '';
  let remoteSearch = '';
  let initialCode = stage.dataset.code || '';

  // Средняя яркость белой стены сцены: компенсирует затемнение от multiply,
  // чтобы итоговый тон стены совпадал со свотчем.
  const WALL_RGB = {
    living: [253, 253, 253],
    bath: [250, 250, 250],
    kitchen: [250, 250, 250],
    kids: [251, 251, 251],
  };

  let page = Math.max(0, (parseInt(stage.dataset.page, 10) || 1) - 1);
  let scene = Math.max(0, thumbs.findIndex((t) => t.classList.contains('viz-thumb--active')));
  let wall = WALL_RGB[thumbs[scene]?.dataset.scene] || [255, 255, 255];
  let currentHex = null;

  // Маски грузим заранее: иначе при смене сцены слой краски на миг
  // показывается без маски — весь кадр, включая мебель.
  const maskPreloads = [];
  thumbs.forEach((thumb) => {
    if (!thumb.dataset.mask) return;
    const img = new Image();
    img.src = thumb.dataset.mask;
    maskPreloads.push(img);
  });

  /* ---------- Перекраска стен ---------- */
  function hexToRgb(hex) {
    const value = String(hex || '').replace('#', '');
    if (value.length !== 6) return null;
    const num = parseInt(value, 16);
    return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
  }

  function applyColor(hex) {
    if (!hex || !paint) return;
    currentHex = hex;
    const rgb = hexToRgb(hex);
    if (!rgb) return;

    // layer = target * 255 / wall — multiply на фото даёт ровно target
    const layer = rgb.map((channel, i) =>
      Math.min(255, Math.round((channel * 255) / wall[i]))
    );
    paint.style.backgroundColor = 'rgb(' + layer.join(', ') + ')';
  }

  /* ---------- Список оттенков ---------- */
  function selectSwatch(button) {
    if (!button) return;
    grid.querySelectorAll('.viz-swatch').forEach((item) => {
      const active = item === button;
      item.classList.toggle('viz-swatch--active', active);
      item.setAttribute('aria-pressed', String(active));
    });
    applyColor(button.dataset.hex);

    const cart = root.querySelector('.viz-cart');
    const label = button.querySelector('.viz-swatch__label');
    if (cart && label) cart.setAttribute('data-weiss-code', label.textContent.trim());

    // пробник забирает текущую пару палитра+код (ТЗ, п.6.6)
    const sampler = root.querySelector('.viz-info__btn');
    if (sampler && label && remotePalette) {
      const url = new URL(sampler.href, window.location.href);
      url.searchParams.set('palette', remotePalette);
      url.searchParams.set('code', label.textContent.trim());
      sampler.href = url.toString();
    }
  }

  function renderSwatches() {
    if (!grid) return;

    grid.innerHTML = '';
    if (!COLORS.length) {
      const empty = document.createElement('li');
      empty.className = 'viz-empty';
      empty.textContent = remoteGroup
        ? 'В этом каталоге нет оттенков, совместимых с выбранным товаром. Выберите другой каталог.'
        : 'Ничего не найдено. Проверьте код или название.';
      grid.appendChild(empty);
      return;
    }

    // в AJAX-режиме COLORS — уже только текущая страница с сервера
    const start = remote ? 0 : page * PER_PAGE;
    const slice = COLORS.slice(start, start + PER_PAGE);

    slice.forEach((color) => {
      const li = document.createElement('li');
      const button = document.createElement('button');
      button.className = 'viz-swatch';
      button.type = 'button';
      button.dataset.hex = color.hex;
      button.setAttribute('aria-pressed', 'false');
      button.setAttribute('aria-label', 'Оттенок ' + color.code);
      button.innerHTML =
        '<span class="viz-swatch__fill" style="background:' + color.hex + '"></span>' +
        '<span class="viz-swatch__label">' + color.code + '</span>';
      li.appendChild(button);
      grid.appendChild(li);
    });

    // подсвечиваем оттенок из адреса, иначе первый на странице
    let active = grid.querySelector('.viz-swatch');
    if (initialCode) {
      const match = Array.from(grid.querySelectorAll('.viz-swatch')).find((button) =>
        button.querySelector('.viz-swatch__label').textContent.trim() === initialCode
      );
      if (match) active = match;
      initialCode = '';
    }
    selectSwatch(active);
  }

  grid?.addEventListener('click', (event) => {
    const button = event.target.closest('.viz-swatch');
    if (button) selectSwatch(button);
  });

  /* ---------- Пагинация оттенков ---------- */
  function renderPager() {
    if (!pager) return;

    pager.hidden = TOTAL_PAGES < 2;
    if (pager.hidden) return;

    const current = page;
    const start = Math.max(0, Math.min(current - 3, TOTAL_PAGES - 7));
    const end = Math.min(TOTAL_PAGES - 1, start + 6);
    const items = [];

    items.push({ label: 'Назад', href: '#', disabled: current <= 0, prev: true });
    for (let index = start; index <= end; index++) {
      items.push({ label: String(index + 1), href: '#', current: index === current });
    }
    items.push({ label: 'Вперед', href: '#', disabled: current >= TOTAL_PAGES - 1, next: true });

    pager.innerHTML = items.map((item) => {
      const attrs = (item.current ? ' aria-current="page"' : '') + (item.disabled ? ' aria-disabled="true"' : '');
      const label = item.prev ? 'Предыдущая страница' : item.next ? 'Следующая страница' : 'Страница ' + item.label;
      return '<a class="pagination__item" href="#" aria-label="' + label + '"' + attrs + '>' + item.label + '</a>';
    }).join('');
  }

  function goToPage(next) {
    const target = Math.min(Math.max(next, 0), TOTAL_PAGES - 1);
    if (target === page) return;
    page = target;
    if (remote) {
      loadColors();
      return;
    }
    renderSwatches();
    renderPager();
  }

  pager?.addEventListener('click', (event) => {
    const item = event.target.closest('.pagination__item');
    if (!item) return;
    event.preventDefault();

    const label = item.textContent.trim();
    if (/^\d+$/.test(label)) goToPage(Number(label) - 1);
    else if (/назад/i.test(label)) goToPage(page - 1);
    else goToPage(page + 1);
  });

  /* ---------- Карточка товара: цвет меняет совместимый продукт ---------- */
  const formatPrice = (value) =>
    String(Math.round(Number(value))).replace(/\B(?=(\d{3})+(?!\d))/g, '\u00A0');

  function applyProduct(product) {
    if (!product) return;

    const cart = root.querySelector('.viz-cart');
    if (cart) {
      cart.setAttribute('data-weiss-add', product.id);
      cart.setAttribute('data-weiss-variation', product.variation || 0);
      cart.setAttribute('data-weiss-attributes', JSON.stringify(product.attributes || {}));
      cart.setAttribute('data-weiss-name', product.name);
      cart.setAttribute('data-weiss-url', product.url || window.location.href);
    }

    const title = root.querySelector('.viz-product__title');
    if (title) {
      const brand = String(product.name).split(' ')[0].toUpperCase();
      title.innerHTML = '<span class="viz-product__brand"></span> ';
      title.querySelector('.viz-product__brand').textContent = brand;
      title.appendChild(document.createTextNode(product.short || product.name));
    }

    const desc = root.querySelector('.viz-product__desc');
    if (desc) {
      desc.textContent = product.desc || '';
      desc.hidden = !product.desc;
    }

    const img = root.querySelector('.viz-product__img');
    if (img && product.image) {
      img.src = product.image;
      img.alt = product.name;
    }

    const price = 'от ' + formatPrice(product.price) + '\u00A0₽';
    const cardPrice = root.querySelector('.viz-product__price');
    const buyPrice = root.querySelector('.viz-price');
    if (cardPrice) cardPrice.textContent = price;
    if (buyPrice) buyPrice.textContent = price;
  }

  /* ---------- Оттенки из реестра WordPress ---------- */
  function loadError(shown) {
    if (!grid) return;
    let note = grid.parentNode.querySelector('.viz-load-error');
    if (!shown) {
      if (note) note.remove();
      return;
    }
    if (!note) {
      note = document.createElement('p');
      note.className = 'viz-load-error';
      note.setAttribute('role', 'status');
      grid.parentNode.insertBefore(note, grid);
    }
    note.textContent = 'Не удалось загрузить оттенки. Проверьте соединение и обновите страницу.';
  }

  function loadColors() {
    if (!remote) return;

    const url = new URL(remote.ajaxUrl, window.location.href);
    url.searchParams.set('action', 'weiss_colors');
    url.searchParams.set('nonce', remote.nonce);
    url.searchParams.set('palette', remotePalette);
    url.searchParams.set('page', String(page + 1));
    if (remoteSearch) url.searchParams.set('q', remoteSearch);
    if (remoteProduct) url.searchParams.set('weiss_product', remoteProduct);
    if (remoteVariation) url.searchParams.set('weiss_variation', remoteVariation);

    fetch(url, { credentials: 'same-origin' })
      .then((response) => response.json())
      .then((json) => {
        if (!json || !json.success || !json.data) {
          loadError(true);
          return;
        }
        loadError(false);
        COLORS.length = 0;
        json.data.items.forEach((item) => COLORS.push(item));
        TOTAL_PAGES = Math.max(1, json.data.max_pages || 1);
        page = Math.min(page, TOTAL_PAGES - 1);
        applyProduct(json.data.product);
        const buy = root.querySelector('.viz-buy');
        if (buy) buy.hidden = !COLORS.length;
        renderSwatches();
        renderPager();
      })
      .catch(() => loadError(true));
  }

  const searchInput = root.querySelector('.viz-search__input');
  if (searchInput) {
    let timer = 0;
    searchInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        remoteSearch = searchInput.value.trim();
        page = 0;
        loadColors();
      }, 300);
    });
    searchInput.form?.addEventListener('submit', (event) => event.preventDefault());
  }

  root.querySelectorAll('.viz-palette__option').forEach((option) => {
    option.addEventListener('click', () => {
      remotePalette = option.dataset.palette || remotePalette;
      remoteSearch = '';
      page = 0;
      if (searchInput) searchInput.value = '';
      const brand = root.querySelector('.viz-title span');
      if (brand && option.dataset.brand) brand.textContent = option.dataset.brand;
      const cart = root.querySelector('.viz-cart');
      if (cart) cart.setAttribute('data-weiss-palette', remotePalette);
      if (remote) loadColors();
    });
  });

  /* ---------- Сцены интерьера ---------- */
  function renderThumbs() {
    thumbs.forEach((thumb, index) => {
      const active = index === scene;
      thumb.classList.toggle('viz-thumb--active', active);
      if (active) thumb.setAttribute('aria-current', 'true');
      else thumb.removeAttribute('aria-current');
    });
  }

  function setScene(index) {
    if (!thumbs.length) return;
    // без прокрутки по кругу: на краях соответствующая стрелка скрыта
    scene = Math.min(Math.max(index, 0), thumbs.length - 1);
    renderThumbs();

    if (prevBtn) prevBtn.hidden = scene === 0;
    if (nextBtn) nextBtn.hidden = scene === thumbs.length - 1;

    const thumb = thumbs[scene];
    const src = thumb.dataset.img;
    const alt = thumb.dataset.alt;
    const mask = thumb.dataset.mask;

    if (src && heroImg.getAttribute('src') !== src) heroImg.src = src;
    if (alt) heroImg.alt = alt;
    if (mask && hero) hero.style.setProperty('--viz-mask', 'url("' + mask + '")');

    wall = WALL_RGB[thumb.dataset.scene] || [255, 255, 255];
    applyColor(currentHex);
  }

  thumbs.forEach((thumb, index) => {
    thumb.addEventListener('click', () => setScene(index));
  });

  nextBtn?.addEventListener('click', () => setScene(scene + 1));
  prevBtn?.addEventListener('click', () => setScene(scene - 1));

  /* ---------- Старт ---------- */
  setScene(scene);
  if (remote) {
    renderPager();
    loadColors();
  } else {
    renderSwatches();
    renderPager();
  }
})();
