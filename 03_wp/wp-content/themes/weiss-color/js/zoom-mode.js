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

/* Подгонки по высоте окна больше нет: она считалась уже после первой
   отрисовки, и на экранах от 2550 страница на глазах уменьшалась с 1 до 0,9 —
   это и читалось как «сайт дёргается». Масштаб задаёт только CSS (base.css):
   0.9 от 1420px и 1:1 от 2560px, без пересчёта в рантайме. */

