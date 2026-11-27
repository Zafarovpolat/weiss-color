/* Подгонка полотна под окно.

   Масштаб зависит только от окна и одинаков на всех страницах. Раньше он
   считался от высоты конкретной страницы: на окне 2843×1448 главная
   получала 0,987, каталог — 1,015, «Выбор цвета» — 1,002, контакты — 1,104.
   При переходе между страницами полотно каждый раз меняло размер — это и
   читалось как «страница сразу масштабируется». Теперь масштаб один:
   при переходе меняется только содержимое, размер полотна остаётся прежним.

   Как считается:
   - полотно 2560 CSS-пикселей должно влезть по ширине: zoom = ширина / 2560.
     Ширину берём на 4px меньше: тогда полотно в раскладке выходит чуть шире
     2560, и заморозка макета (медиазапросы и контейнерные запросы
     min-width: 2560) остаётся включённой даже при дробном масштабе;
   - по высоте страница = контент + шапка и подвал. Шапка и подвал на экране
     не масштабируются вместе с полотном (1cqw, см. responsive.css), при
     ширине окна W они занимают 0,0805·W. Самая высокая страница при
     масштабе 1 — 1234px контента, итого 1234 + 0,0805·W. Отсюда
     zoom = высота окна / (1234 + 0,0805·W);
   - берём меньший из двух, ниже 0,75 не опускаем: текст перестаёт читаться,
     длинная страница просто прокручивается;
   - из результата вычитаем 0,0005. Это не косметика: при масштабе ровно
     «окно / 2560» полотно встаёт в 2560 без запаса, на главной карточка
     «Почему стоит обратиться» переносится на строку больше, страница
     вырастает на 20px и появляется полоса прокрутки — а с ней ширина окна
     падает на 15px, и текст переносится ещё раньше: страница застревает
     в прокрутке. Полпикселя запаса включают режим fit-fill (полотно шире
     2560), переносы остаются макетными.

   Проверено на 2843×1448 (окно клиента при масштабе браузера 90 %):
   на главной, каталоге, товаре, «Выборе цвета», контактах и корзине
   масштаб одинаковый — 0,987, каждая страница укладывается в окно без
   прокрутки. На 2560×1440, 2600×1400, 2843×1599, 3000×1300 и 3840×2160
   прокрутки тоже нет.

   Скрипт стоит в конце <body>, но браузер успевает отрисовать первый экран
   раньше, чем парсер дойдёт до него — поэтому в <head> печатается правило
   html{visibility:hidden} (только от 2550px), а здесь страница показывается
   уже с нужным масштабом. Ждём fonts.ready: до шрифтов запасной шрифт даёт
   другую высоту и другие переносы. Масштаб от шрифтов больше не зависит,
   поэтому даже страховочный показ через 2 с не вызовет скачка размера.
   Если этот файл не загрузится, страницу открывает встроенный в <head>
   таймер (window.__wcFit). */
(function () {
  var BASE = 2560;
  /* Высота самой высокой страницы (контент) при масштабе 1 и полотне 2560:
     main = 1234px. Шапка и подвал на экране считаются в процентах ширины
     полотна: 110 + 96 = 206px при 2560, то есть 0,0805·W. */
  var MAIN_MAX = 1234;
  var CHROME_RATIO = 206 / BASE;
  var MIN_ZOOM = 0.75;
  var de = document.documentElement;
  var applied = 0;
  var timer = 0;

  function reveal() {
    window.__wcFit = 1;
    de.style.visibility = 'visible';
  }

  /* Если масштаб меньше «окно / 2560», полотно уже окна и по бокам остались
     бы полосы. Тогда html получает fit-fill: потолок 2560 снимается, поля
     считаются как 8,75 % раскладки, то есть ровно макетные 8,75 % на экране —
     высота сжата, ширина как в макете. */
  function syncFill() {
    if (!applied) { de.classList.remove('fit-fill'); return; }
    de.classList.toggle('fit-fill', applied < de.clientWidth / BASE - 0.0002);
  }

  function setZoom(zoom) {
    de.style.zoom = String(zoom);
    /* vh считается от окна без масштаба: выше 1 компенсируем, иначе 100vh
       превращается в «окно × zoom» и появляется пустой низ (см. base.css). */
    if (zoom > 1) de.style.setProperty('--wc-zoom', String(zoom));
    else de.style.removeProperty('--wc-zoom');
    /* Поля полотна — 8,75 % окна на экране; 8.75vw считается до масштаба,
       поэтому в fit-fill делим на фактический zoom. */
    if (zoom !== 1) de.style.setProperty('--fit-zoom', String(zoom));
    else de.style.removeProperty('--fit-zoom');
    applied = zoom;
    syncFill();
  }

  function fit(first) {
    if (de.classList.contains('zoom-09')) return;

    /* Ширину берём по clientWidth — это ширина, реально доступная полотну
       (без полосы прокрутки). Масштаб зависит только от неё и от высоты
       окна, но не от высоты страницы, поэтому полоса прокрутки больше не
       «пересчитывает» полотно. При полосе (15px) масштаб выходит чуть
       меньше, полотно встаёт ровно в 2560 и остаётся макетным: заморозка
       раскладки и контейнерные запросы считают ширину полотна, а не окна. */
    var avail = de.clientWidth;

    if (window.innerWidth < BASE) {
      if (applied) {
        de.style.zoom = '';
        de.style.removeProperty('--wc-zoom');
        de.style.removeProperty('--fit-zoom');
        de.classList.remove('fit-fill');
        applied = 0;
      }
      de.style.removeProperty('--vs');
      return;
    }

    var wide = (avail - 4) / BASE;
    /* Высота страницы = контент·zoom + шапка и подвал (они не масштабируются),
       поэтому масштаб по высоте: (окно − шапка/подвал) / контент. */
    var heightZoom = (window.innerHeight - avail * CHROME_RATIO) / MAIN_MAX;
    var zoom = Math.max(MIN_ZOOM, Math.min(wide, heightZoom) - 0.0005);

    if (!first && Math.abs(zoom - applied) < 0.0005) { squeeze(); return; }
    setZoom(zoom);
    squeeze();
  }

  /* Вертикальная усадка коротких страниц в fit-fill.
     Ширина полотна в этом режиме равна окну, поэтому «Где купить»,
     «О компании», «Инструкция колеровки» и «Контакты» выше окна. Ширину не
     трогаем, а сжимаем их вертикальный «воздух» — отступы блоков и высоты
     героев — общим множителем --vs, пока main не встанет в окно. Страницы,
     которым усадка не нужна или которые законно прокручиваются, множитель
     не используют (var(--vs, 1) стоит только в их css), поэтому прочим
     страницам переменная не мешает.

     Цель — низ подвала ровно по нижней кромке окна (как у корзины и
     каталога). Высота страницы от --vs почти линейна, но в правилах есть
     max()/min() с полами, поэтому после первой оценки уточняем по факту.
     Меряем честную высоту содержимого: main растягивается флексом до низа
     окна, поэтому на время замера снимаем растяжку (flex: 0 0 auto) — иначе
     высоты ниже окна не отличить друг от друга. */
  function squeeze() {
    var main = document.querySelector('main');
    if (!main || !de.classList.contains('fit-fill')) {
      de.style.removeProperty('--vs');
      return;
    }
    var head = document.querySelector('.site-header');
    var foot = document.querySelector('.site-footer');
    var chrome = (head ? head.getBoundingClientRect().height : 0) +
                 (foot ? foot.getBoundingClientRect().height : 0);
    var avail = de.clientHeight - chrome;
    var prevFlex = main.style.flex;

    main.style.flex = '0 0 auto';
    function height(vs) {
      if (vs == null) de.style.removeProperty('--vs');
      else de.style.setProperty('--vs', String(vs));
      return main.getBoundingClientRect().height;
    }
    var result = null;
    try {
      var h1 = height(null);
      if (h1 > avail - 1) {
        /* Страница, не использующая --vs, от неё не меняется — выходим сразу:
           длинные страницы (юридические и т.п.) прокручиваются как раньше. */
        if (height(0) < h1 - 1) {
          /* Бисекция, а не оценка по наклону: в правилах есть max() с полами
             (минвысоты карточек и героя), и высота от --vs меняется кусками. */
          var lo = 0.3;
          var hi = 1;
          if (height(lo) <= avail - 1) {
            for (var i = 0; i < 7; i++) {
              var mid = (lo + hi) / 2;
              if (height(mid) <= avail - 1) lo = mid;
              else hi = mid;
            }
            result = Math.floor(lo * 10000) / 10000;
          } else {
            result = lo;
          }
        }
      }
    } finally {
      main.style.flex = prevFlex;
      height(result);
    }
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { fit(false); }, 100);
  }

  /* Показываем страницу только после загрузки шрифтов: до них другие
     переносы, и первый кадр выглядел бы иначе. Масштаб уже окончательный —
     он считается от окна, а не от высоты страницы. Таймер 2 с страхует,
     если шрифты не придут. */
  function settle() {
    fit(false);
    reveal();
  }

  window.__wcFitInit = 1;
  try {
    if (window.CSS && CSS.supports && !CSS.supports('zoom', '0.9')) { reveal(); return; }
    fit(true);
    window.addEventListener('load', schedule);
    window.addEventListener('resize', schedule);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(settle, settle);
    else settle();
    setTimeout(settle, 2000);
  } catch (e) {
    reveal();
  }
})();
