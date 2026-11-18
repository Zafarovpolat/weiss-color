/* Подгонка полотна под окно.
   1. Шире макета (2560): полотно целиком масштабируется на ширину окна —
   zoom = ширина / 2560, поэтому поля остаются макетными 8,75 %, как на
   экранах до 2559; без этого при замороженных размерах блоки центрировались
   полосой по бокам (на окне 2936 — по 188px с каждой стороны).
   2. На макетной ширине — подгонка по высоте. Макет — один экран 2560×1440,
   и на окне ниже 1440 (браузерная обвязка съедает ~140px) страница
   прокручивается ровно на «пол колеса» и упирается в подвал. Если запас
   небольшой (до 12 %), ужимаем полотно до высоты окна — тот же вид, что при
   масштабе 90 %, но без ручного зума. Работает только там, где размеры
   заморожены (--u: 25.6px, порог 2550px — см. base.css): при пропорциональном
   --u html zoom не меняет высоту. Длинные страницы (каталог, корзина) не
   трогаем — у них запас больше 12 %. Шире макета подгонку по высоте не
   делаем: она уменьшила бы полотно и снова открыла поля слева и справа.

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
    applied = zoom;
    mode = nextMode;
  }

  /* natural — натуральная высота страницы при zoom: 1. На повторных
     пересчётах берём её из текущего scrollHeight, чтобы не сбрасывать
     масштаб в 1: сброс сам по себе давал видимый рывок. */
  function fit(first) {
    if (de.classList.contains('zoom-09')) return;

    /* clientWidth уже отдаёт ширину окна с учётом действующего zoom —
       это и есть ширина, на которую раскладываем полотно. */
    var avail = de.clientWidth;

    if (avail < 2550) {
      if (mode) { de.style.zoom = ''; applied = 0; mode = ''; }
      return;
    }

    /* Шире макета: масштабируем полотно целиком, поля остаются макетными. */
    if (avail > BASE) {
      var wide = avail / BASE;
      if (mode !== 'wide' || wide !== applied) setZoom(wide, 'wide');
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
