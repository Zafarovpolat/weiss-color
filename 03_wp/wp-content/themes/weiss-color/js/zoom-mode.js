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
   размеры заморожены (--u: 25.6px, порог 2550px — см. base.css): при
   пропорциональном --u html zoom не меняет высоту. Длинные страницы
   (каталог, корзина) не трогаем. */
(function () {
  var MIN_ZOOM = 0.875;
  var MAX_OVERFLOW = 0.12;
  var de = document.documentElement;
  var timer = 0;
  var applied = 0;

  /* natural — натуральная высота страницы при zoom: 1. На повторных
     пересчётах берём её из текущего scrollHeight, чтобы не сбрасывать
     масштаб в 1: сброс сам по себе давал видимый рывок. */
  function fit(first) {
    if (de.classList.contains('zoom-09')) return;
    if (window.innerWidth < 2550) { de.style.zoom = ''; applied = 0; return; }

    if (first) de.style.zoom = '1';
    var natural = first ? de.scrollHeight : de.scrollHeight / (applied || 1);
    var viewport = window.innerHeight;
    /* 2px запаса нужны и когда страница влезает ровно впритык: при нулевом
       запасе полоса прокрутки от округления появлялась, отнимала ширину,
       страница становилась выше — и скролл начинал мигать. */
    var target = viewport - 2;

    var zoom = 1;
    if (!natural) return;
    if (natural > target && (natural - viewport) / natural <= MAX_OVERFLOW) {
      zoom = Math.max(MIN_ZOOM, target / natural);
    }
    /* Пересчёт после шрифтов и картинок меняет масштаб на доли процента —
       такой рывок не виден, но перерисовывать из-за него не нужно. */
    if (!first && Math.abs(zoom - applied) < 0.01) return;

    /* После масштабирования текст может перенестись иначе; 1–2 поправки
       доводят страницу до высоты окна. */
    for (var i = 0; i < 3; i++) {
      de.style.zoom = zoom.toFixed(3);
      applied = zoom;
      var got = de.scrollHeight;
      if (got <= target + 1 || zoom === 1) return;
      var next = Math.max(MIN_ZOOM, zoom * target / got);
      if (next >= zoom - 0.0005) return;
      zoom = next;
    }
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { fit(false); }, 100);
  }

  /* Первый пересчёт — сразу после разбора документа, без задержки:
     раньше он срабатывал через 100 мс после DOMContentLoaded, то есть уже
     после первой отрисовки, и страница на глазах уменьшалась на 10%. */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { fit(true); }, { once: true });
  } else {
    fit(true);
  }
  window.addEventListener('load', schedule);
  window.addEventListener('resize', schedule);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
})();
