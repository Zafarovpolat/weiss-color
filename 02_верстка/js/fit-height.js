/* Подгонка полотна под окно.
   1. Шире макета (2560): полотно масштабируется на ширину окна —
   zoom = ширина / 2560, поэтому поля остаются макетными 8,75 %, как на
   экранах до 2559; без этого при замороженных размерах блоки центрировались
   полосой по бокам. Масштаб ограничен высотой окна, но только пока ужаться
   можно не ниже 0,875: первый экран влезает без прокрутки, а на окне ниже
   16:9 по бокам остаётся полоса (на окне 2936×1560 — по 180px). Длинные
   страницы при полной ширине выше окна в разы — их не трогаем, они
   скроллятся как обычно.
   2. На макетной ширине — подгонка по высоте. Макет — один экран 2560×1440,
   и на окне ниже 1440 (браузерная обвязка съедает ~140px) страница
   прокручивается ровно на «пол колеса» и упирается в подвал. Если запас
   небольшой (до 12 %), ужимаем полотно до высоты окна — тот же вид, что при
   масштабе 90 %, но без ручного зума. Работает только там, где размеры
   заморожены (--u: 25.6px, min-width 2560 от окна — см. base.css): при
   пропорциональном --u html zoom не меняет высоту. Длинные страницы (каталог, корзина) не
   трогаем — у них запас больше 12 %.

   Скрипт стоит в конце <body>, но браузер успевает отрисовать первый экран
   раньше, чем парсер дойдёт до него (замер: отрисовка на 552 мс, масштаб на
   708 мс) — и страница один раз «прыгает». Поэтому в <head> печатается
   правило html{visibility:hidden} (только от 2550px), а здесь страница
   показывается уже с нужным масштабом. Если этот файл не загрузится,
   страницу открывает встроенный в <head> таймер (window.__wcFit).

   Пересчёт после загрузки шрифтов и картинок меняет масштаб только если
   разница больше 2 % — иначе это был бы видимый рывок. */
(function () {
  var BASE = 2560;
  var MIN_ZOOM = 0.875;
  var MAX_OVERFLOW = 0.12;
  var RETRY_DELTA = 0.02;
  var de = document.documentElement;
  var applied = 0;
  var mode = '';
  var timer = 0;

  function reveal() {
    window.__wcFit = 1;
    de.style.visibility = 'visible';
  }

  function setZoom(zoom, nextMode) {
    de.style.zoom = String(zoom);
    /* vh считается от окна без масштаба: выше 1 компенсируем, иначе 100vh
       превращается в «окно × zoom» и появляется пустой низ (см. base.css). */
    if (zoom > 1) de.style.setProperty('--wc-zoom', String(zoom));
    else de.style.removeProperty('--wc-zoom');
    applied = zoom;
    mode = nextMode;
  }

  /* natural — натуральная высота страницы при zoom: 1. На повторных
     пересчётах берём её из текущего scrollHeight, чтобы не сбрасывать
     масштаб в 1: сброс сам по себе давал видимый рывок. */
  function fit(first) {
    if (de.classList.contains('zoom-09')) return;

    /* clientWidth уже отдаёт ширину окна с учётом действующего zoom —
       это ширина, на которую раскладываем полотно. Порог берём по innerWidth:
       медиазапросы считают его же, поэтому заморозка макета (min-width 2560)
       и этот скрипт включаются на одном и том же окне. */
    var avail = de.clientWidth;
    var win = window.innerWidth;

    if (win < 2560) {
      if (mode) { de.style.zoom = ''; de.style.removeProperty('--wc-zoom'); applied = 0; mode = ''; }
      return;
    }

    /* Шире макета: масштабируем полотно целиком, чтобы поля остались
       макетными, но не больше высоты окна — иначе первый экран уходит под
       прокрутку. Если окно ниже 16:9, масштаб ограничивает высота и по
       бокам остаётся небольшая полоса. */
    if (avail > BASE) {
      var wide = avail / BASE;
      setZoom(wide, 'wide');
      var limit = window.innerHeight - 2;
      var height = de.scrollHeight;
      /* Ужимаем по высоте только до читаемого масштаба. Длинная страница
         (каталог, корзина) при полной ширине выше окна в разы — её не
         трогаем, она скроллится как обычно. */
      if (height > limit && wide * limit / height >= MIN_ZOOM) {
        /* После масштаба разметка может перестроиться — 1–2 поправки. */
        for (var k = 0; k < 3 && height > limit; k++) {
          setZoom(Math.max(MIN_ZOOM, applied * limit / height), 'wide');
          height = de.scrollHeight;
        }
      }
      return;
    }

    if (mode !== 'height') setZoom(1, 'height');
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
    if (!first && Math.abs(zoom - applied) < RETRY_DELTA) return;

    /* После масштабирования текст может перенестись иначе; 1–2 поправки
       доводят страницу до высоты окна. */
    for (var i = 0; i < 3; i++) {
      setZoom(zoom, 'height');
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

  try {
    if (window.CSS && CSS.supports && !CSS.supports('zoom', '0.9')) return;
    fit(true);
    window.addEventListener('load', schedule);
    window.addEventListener('resize', schedule);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
  } finally {
    reveal();
  }
})();
