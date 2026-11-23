/* Подгонка полотна под окно.
   1. Шире макета (2560): полотно масштабируется на ширину окна —
   zoom = ширина / 2560, поэтому поля остаются макетными 8,75 %, как на
   экранах до 2559; без этого при замороженных размерах блоки центрировались
   полосой по бокам. Масштаб ограничен высотой окна, но только пока ужаться
   можно не ниже 0,875: первый экран влезает без прокрутки. Если из-за
   высоты масштаб вышел меньше «окно / 2560», полотно уже окна — тогда
   html получает fit-fill и контейнер снова занимает всю ширину с полями
   8,75 % (высота сжата, ширина как в макете). Длинные страницы при полной
   ширине выше окна в разы — их не трогаем, они скроллятся как обычно.
   2. На макетной ширине — подгонка по высоте. Макет — один экран 2560×1440,
   и на окне ниже 1440 (браузерная обвязка съедает ~140px) страница
   прокручивается ровно на «пол колеса» и упирается в подвал. Одноэкранные
   страницы (высота до 1,25 макета, т.е. ~1800px) ужимаем до высоты окна
   при любом запасе, но не ниже 0,75: главная влезает целиком и на окне
   2560×1100 (0,76). Страницы заметно длиннее макета ужимаем только при
   запасе до 12 % — иначе каталог и корзина стали бы нечитаемо мелкими
   вместо обычной прокрутки. Работает только там, где размеры заморожены
   (--u: 25.6px, min-width 2560 от окна — см. base.css): при
   пропорциональном --u html zoom не меняет высоту.

   Скрипт стоит в конце <body>, но браузер успевает отрисовать первый экран
   раньше, чем парсер дойдёт до него (замер: отрисовка на 552 мс, масштаб на
   708 мс) — и страница один раз «прыгает». Поэтому в <head> печатается
   правило html{visibility:hidden} (только от 2550px), а здесь страница
   показывается уже с нужным масштабом. Ждём fonts.ready: до шрифтов
   запасной шрифт даёт другую высоту, и показ с последующим пересчётом
   выглядел как рывок. Если этот файл не загрузится, страницу открывает
   встроенный в <head> таймер (window.__wcFit).

   Пересчёт после загрузки шрифтов и картинок возвращает масштаб вверх
   всегда, если страница снова влезает: иначе транзиентная высота при
   загрузке оставляла страницу ужатой (2560×1440 показывал 0,985).
   Уменьшение применяется всегда: иначе оставался скролл на 20px. */
(function () {
  var BASE = 2560;
  var BASE_H = 1440;
  var MIN_ZOOM = 0.875;
  /* Одноэкранные страницы (макет — 2560×1440) ужимаем до высоты окна без
     ограничения в 12 %: иначе на коротком окне (2560×1240) главная
     оставалась в масштабе 1 и уезжала под прокрутку на 213px. Ниже 0,75
     не опускаем — текст перестаёт читаться. */
  var MIN_NEAR = 0.75;
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

  /* Если фит ужал полотно сильнее, чем просит ширина окна (zoom меньше
     «окно / 2560»), полотно уже окна и по бокам остаются полосы. Тогда
     html получает fit-fill: потолок 2560 снимается, поля считаются как
     8,75 % раскладки, то есть ровно макетные 8,75 % на экране — высота
     сжата, ширина как в макете. */
  function syncFill() {
    if (!applied) { de.classList.remove('fit-fill'); return; }
    var w = de.clientWidth;
    de.classList.toggle('fit-fill', applied < w / BASE - 0.001);
  }

  function setZoom(zoom, nextMode) {
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
    mode = nextMode;
    syncFill();
  }

  /* Высота страницы без учёта полосы прокрутки. Если полоса уже есть, она
     отнимает 15px ширины — на них текст переносится иначе, страница
     становится выше, и полоса не исчезает уже никогда. Меряем с временно
     скрытой полосой: ширина 2560, высота макетные 1440. */
  function naturalHeight() {
    var h;
    if (window.innerWidth - de.clientWidth > 0) {
      var prev = de.style.overflowY;
      de.style.overflowY = 'hidden';
      h = de.scrollHeight;
      de.style.overflowY = prev;
    } else {
      h = de.scrollHeight;
    }
    return h;
  }

  /* Натуральная высота при zoom 1. При меньшем масштабе 100vh растягивает
     body до окна, и scrollHeight / applied завышает высоту: страница на
     2560×1440, транзиентно выросшая при загрузке шрифтов, ужималась до
     0,985 и уже не возвращалась в 1:1. Меряем честно: ставим zoom 1,
     читаем scrollHeight (это форсирует раскладку) и возвращаем масштаб.
     Между вызовами отрисовки нет — рывка не видно. */
  function naturalZoom1() {
    var keep = applied;
    if (keep !== 1) setZoom(1, mode);
    var h = naturalHeight();
    if (keep !== 1) setZoom(keep, mode);
    return h;
  }

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
      de.style.removeProperty('--fit-zoom');
      de.classList.remove('fit-fill');
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
      /* На широком окне, ужатом по высоте, полотно переходит в fit-fill:
         шапка и подвал перестают масштабироваться, и влезть помогает тот же
         запас, что и в режиме высоты — до 0,75 для одноэкранных страниц
         (иначе 2936×1300 не влезал на 11px при полу 0,875). */
      var floor2 = height / wide <= BASE_H * 1.25 ? MIN_NEAR : MIN_ZOOM;
      if (height > limit && wide * limit / height >= floor2) {
        /* После масштаба разметка может перестроиться — 1–2 поправки. */
        for (var k = 0; k < 4 && height > limit; k++) {
          setZoom(Math.max(floor2, applied * limit / height), 'wide');
          height = de.scrollHeight;
        }
      }
      return;
    }

    if (mode !== 'height') setZoom(1, 'height');
    var natural = naturalZoom1();
    var viewport = window.innerHeight;
    /* 2px запаса: при дробном масштабе сотые доли пикселя переполнения
       оставляли полосу прокрутки, хотя scrollHeight уже округлялся до
       clientHeight. При 2560×1440 запас не действует — страница ровно
       1440px не ужимается вовсе (natural > viewport ложно). */
    var target = viewport - 2;

    /* Однокранная страница (главная) — всё, что близко к макетным 1440px.
       Её ужимаем до окна при любом запасе, длинные страницы (каталог,
       корзина) по-прежнему не трогаем: у них запас больше 12 %. */
    var nearMock = natural <= BASE_H * 1.25;
    var floor = nearMock ? MIN_NEAR : MIN_ZOOM;

    var zoom = 1;
    if (!natural) return;
    if (natural > viewport && (nearMock || (natural - viewport) / natural <= MAX_OVERFLOW)) {
      zoom = Math.max(floor, target / natural);
    }
    /* Уменьшение применяем всегда, даже на 1–2 %: иначе оставался скролл
       на 20px, который порог 2 % считал «мелким рывком». Возврат вверх не
       глушим: после загрузки шрифтов страница снова влезает в 1:1, а порог
       2 % оставлял её ужатой навсегда. Порог — только для мелкого дожатия
       вниз, чтобы пересчёт не дёргал страницу на пиксель. */
    if (!first && zoom < applied && applied - zoom < RETRY_DELTA) return;
    if (!first && Math.abs(zoom - applied) < 0.0005) return;

    /* В режиме fit-fill шапка и подвал остаются макетными (110/96px) и не
       ужимаются вместе с полотном, поэтому natural * zoom занижает реальную
       высоту на их долю. Меряем фактическую высоту после каждого масштаба:
       de.scrollHeight — уже в экранных пикселях. */
    for (var i = 0; i < 4; i++) {
      setZoom(zoom, 'height');
      var actual = de.scrollHeight;
      if (actual <= target + 1 || zoom === 1) return;
      var next = Math.max(floor, zoom * target / actual);
      if (next >= zoom - 0.0005) return;
      zoom = next;
    }
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(function () { fit(false); }, 100);
  }

  /* Показываем страницу только с окончательным масштабом. До загрузки
     шрифтов раскладка выше: запасной шрифт даёт другие переносы, первый
     замер ставит, например, 0,98, а после шрифтов настоящие 1,0 — видимый
     пересчёт читается как прыжок логотипа и полей. Поэтому reveal — после
     fonts.ready; картинки высоту не меняют, у всех заданы width/height.
     Таймер 2 с страхует, если шрифты не придут. */
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
