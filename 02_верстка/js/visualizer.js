// visualizer.js — визуализатор (раздел 6): переключение сцен интерьера
// (гостиная / кухня / ванная / детская), выбор оттенка и постраничный
// список оттенков каталога ACE PAINT.
//
// Перекраска стены по маске пока отключена — сцены просто меняют фото.

(() => {
  const root = document.querySelector('.viz-page');
  if (!root) return;

  /* ---------- Элементы ---------- */
  const grid = root.querySelector('.viz-swatches');
  const pager = root.querySelector('.viz-pagination');
  const heroImg = root.querySelector('.viz-hero__img');
  const thumbs = Array.from(root.querySelectorAll('.viz-thumb'));
  const nextBtn = root.querySelector('.viz-next');
  const prevBtn = root.querySelector('.viz-prev');

  /* ---------- Данные ---------- */
  const COLORS = Array.isArray(window.ACE_COLORS) ? window.ACE_COLORS : [];
  const PER_PAGE = 18;
  const TOTAL_PAGES = Math.max(1, Math.ceil(COLORS.length / PER_PAGE));

  let page = 0;
  let scene = Math.max(0, thumbs.findIndex((t) => t.classList.contains('viz-thumb--active')));

  /* ---------- Список оттенков ---------- */
  function selectSwatch(button) {
    if (!button) return;
    grid.querySelectorAll('.viz-swatch').forEach((item) => {
      const active = item === button;
      item.classList.toggle('viz-swatch--active', active);
      item.setAttribute('aria-pressed', String(active));
    });
  }

  function renderSwatches() {
    if (!grid || !COLORS.length) return;

    const start = page * PER_PAGE;
    const slice = COLORS.slice(start, start + PER_PAGE);

    grid.innerHTML = '';
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

    // на каждой странице сразу подсвечиваем первый оттенок
    selectSwatch(grid.querySelector('.viz-swatch'));
  }

  grid?.addEventListener('click', (event) => {
    const button = event.target.closest('.viz-swatch');
    if (button) selectSwatch(button);
  });

  /* ---------- Пагинация оттенков ---------- */
  function renderPager() {
    if (!pager) return;

    pager.querySelectorAll('.pagination__item').forEach((item) => {
      const label = item.textContent.trim();

      if (/^\d+$/.test(label)) {
        const index = Number(label) - 1;
        if (index === page) item.setAttribute('aria-current', 'page');
        else item.removeAttribute('aria-current');
        return;
      }

      const isPrev = /назад/i.test(label);
      const disabled = isPrev ? page === 0 : page >= TOTAL_PAGES - 1;
      item.setAttribute('aria-disabled', String(disabled));
    });
  }

  function goToPage(next) {
    const target = Math.min(Math.max(next, 0), TOTAL_PAGES - 1);
    if (target === page) return;
    page = target;
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
    scene = (index + thumbs.length) % thumbs.length;
    renderThumbs();

    const thumb = thumbs[scene];
    const src = thumb.dataset.img;
    const alt = thumb.dataset.alt;

    if (src && heroImg.getAttribute('src') !== src) heroImg.src = src;
    if (alt) heroImg.alt = alt;
  }

  thumbs.forEach((thumb, index) => {
    thumb.addEventListener('click', () => setScene(index));
  });

  nextBtn?.addEventListener('click', () => setScene(scene + 1));
  prevBtn?.addEventListener('click', () => setScene(scene - 1));

  /* ---------- Старт ---------- */
  renderSwatches();
  renderPager();
  renderThumbs();
})();
