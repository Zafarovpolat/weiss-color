/* Демо-масштаб сайта.
   ?zoom=09   — 0.9 на экранах 1920px и шире (в обычной версии от 2560px 1:1);
   ?zoom=auto — обычный адаптивный масштаб (0.9 от 1420px, 1:1 от 2560px).
   Режим запоминается во вкладке, чтобы переходы по сайту его не сбрасывали
   и две версии можно было держать открытыми рядом. */
(function () {
  try {
    var search = new URLSearchParams(location.search);
    var mode = search.get('zoom');
    if (mode === '09' || mode === 'auto') sessionStorage.setItem('wc-demo-zoom', mode);
    if (sessionStorage.getItem('wc-demo-zoom') !== '09') return;
    document.documentElement.classList.add('zoom-09');
    if (!search.has('zoom')) {
      var url = new URL(location.href);
      url.searchParams.set('zoom', '09');
      history.replaceState(null, '', url);
    }
  } catch (e) {}
})();

/* Подгонка по высоте окна.
   Макет — один экран 2560×1440, и на окне ниже 1440 (браузерная обвязка
   съедает ~140px) страница прокручивается ровно на «пол колеса» и упирается
   в подвал. Если запас небольшой, ужимаем полотно до высоты окна — тот же
   вид, что при масштабе 90%, но без ручного зума. Работает только там, где
   размеры заморожены на 2560 (--u: 25.6px): при пропорциональном --u html
   zoom не меняет высоту. Длинные страницы (каталог, корзина) не трогаем. */
(function () {
  var MIN_ZOOM = 0.875;
  var MAX_OVERFLOW = 0.12;
  var de = document.documentElement;
  var timer = 0;

  function fit() {
    if (de.classList.contains('zoom-09')) return;
    if (window.innerWidth < 2560) { de.style.zoom = ''; return; }
    de.style.zoom = '1';
    var height = de.scrollHeight;
    var viewport = window.innerHeight;
    if (!height || height <= viewport + 1) return;
    if ((height - viewport) / height > MAX_OVERFLOW) return;
    /* После масштабирования текст может перенестись иначе; 1–2 поправки
       доводят страницу до высоты окна. Минус 2px — чтобы округление
       не оставляло однопиксельный скролл. */
    var target = viewport - 2;
    var zoom = target / height;
    for (var i = 0; i < 3; i++) {
      zoom = Math.max(MIN_ZOOM, zoom);
      de.style.zoom = zoom.toFixed(3);
      var got = de.scrollHeight;
      if (got <= target + 1) return;
      var next = zoom * target / got;
      if (next >= zoom - 0.0005) return;
      zoom = next;
    }
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(fit, 100);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', schedule);
  } else {
    schedule();
  }
  window.addEventListener('load', schedule);
  window.addEventListener('resize', schedule);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
})();
