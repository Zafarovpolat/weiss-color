<?php
/**
 * Страница не найдена (М24). Разметка перенесена из 02_верстка/pages/404.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( 'error-page' );
get_header();
?>
<p class="error-page__eyebrow">Страница не найдена</p>
    <img class="error-page__code" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/404.svg" width="496" height="221" alt="" aria-hidden="true">
    <h1 class="error-page__title">Кажется, эта страница не найдена</h1>
    <p class="error-page__text">
      <span class="error-page__text-line error-page__text-line--first">Возможно, страница была перемещена, удалена или вы ввели неверный адрес.</span>
      <span class="error-page__text-line error-page__text-line--second">Но мы поможем быстро вернуться к нужному разделу.</span>
    </p>

    <div class="error-page__actions">
      <a class="btn btn--brown" href="<?php echo esc_url( home_url( '/' ) ); ?>">
        На главную
        <svg class="btn__arrow" viewBox="0 0 52 15" fill="none" aria-hidden="true"><path d="M0 6h44v3H0zM41 0l11 7.5L41 15z" fill="currentColor"/></svg>
      </a>
      <a class="btn btn--outline" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">В каталог</a>
    </div>
<?php
get_footer();
