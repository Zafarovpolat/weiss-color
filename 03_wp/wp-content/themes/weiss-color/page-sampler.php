<?php
/**
 * Заказ пробного образца (М06). Разметка перенесена из 02_верстка/pages/modal-sampler.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( 'sampler-page' );
get_header();

$weiss_palette = isset( $_GET['palette'] ) ? sanitize_text_field( wp_unslash( $_GET['palette'] ) ) : '';
$weiss_code    = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
$weiss_color   = ( $weiss_palette && $weiss_code ) ? weiss_color_get( $weiss_palette, $weiss_code ) : null;

$weiss_palettes = weiss_page_url( 'palettes' );
$weiss_product  = weiss_sampler_product();
/* ТЗ 6.6: перечень разрешённых цветов пробника задаёт магазин */
$weiss_allowed  = ( $weiss_color && weiss_sampler_allows( $weiss_color ) );
$weiss_have     = (bool) ( $weiss_allowed && $weiss_product );

$weiss_color_label = $weiss_color ? $weiss_color->code : 'не выбран';
$weiss_hex         = $weiss_color ? $weiss_color->hex : '#f4e7e1';
$weiss_swatch_note = $weiss_color ? 'Цвет ' . $weiss_color->code . ' — ' . $weiss_color->palette : 'Цвет не выбран';
$weiss_stock       = ( $weiss_product && $weiss_product->is_in_stock() ) ? 'В наличии' : 'Нет в наличии';
$weiss_price       = $weiss_product ? $weiss_product->get_price() : '';
/* Остаток — граница количества (ТЗ 6.6); без учёта остатков границы нет. */
$weiss_max_qty     = ( $weiss_product && $weiss_product->managing_stock() && $weiss_product->get_stock_quantity() )
	? (int) $weiss_product->get_stock_quantity()
	: 0;

$weiss_back = $weiss_color
	? weiss_color_url( $weiss_color->palette, $weiss_color->code )
	: $weiss_palettes;
?>
<nav class="crumbs" aria-label="Хлебные крошки">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
	<a href="<?php echo esc_url( $weiss_palettes ); ?>">Колеровка цвета</a> &gt;
	<?php if ( $weiss_color ) : ?>
		<a href="<?php echo esc_url( weiss_shades_url( $weiss_color->palette ) ); ?>"><?php echo esc_html( $weiss_color->palette ); ?></a> &gt;
		<a href="<?php echo esc_url( $weiss_back ); ?>">Цвет <?php echo esc_html( $weiss_color->code ); ?></a> &gt;
	<?php endif; ?>
	<span aria-current="page">Пробный образец</span>
</nav>

    <div class="sampler-layout">
      <!-- заголовок живёт в той же сетке, что и правая колонка: на макете
           «Назад к выбору цвета» стоит на уровне H1, а карточка цвета — ниже -->
      <h1 class="sampler-title">
        Заказ пробного образца
        <span class="sampler-title__color">Цвет <?php echo esc_html( $weiss_color_label ); ?></span>

        <!-- на десктопе ссылка уходит вправо на линию заголовка,
             на ≤768 остаётся последней строкой этого блока -->
        <div class="sampler-head">
          <a class="sampler-back" href="<?php echo esc_url( $weiss_back ); ?>">
            <!-- стрелка заливкой, как у «Продолжить покупки» в корзине;
                 исходник смотрит вправо, поэтому отзеркалена -->
            <svg viewBox="0 0 512 243.58" aria-hidden="true" focusable="false">
              <path fill="currentColor" fill-rule="nonzero" transform="translate(512 0) scale(-1 1)"
                    d="M373.57 0 512 120.75 371.53 243.58l-20.92-23.91 94.93-83L0 137.09v-31.75l445.55-.41-92.89-81.02z"/>
            </svg>
            <span>Назад к выбору цвета</span>
          </a>
        </div>
      </h1>

      <!-- цвет-квадрат: в проде подставляется цвет выбранного оттенка -->
      <div class="sampler-swatch" role="img" aria-label="<?php echo esc_attr( $weiss_swatch_note ); ?>"
           style="background:<?php echo esc_attr( $weiss_hex ); ?>"></div>

      <div class="sampler-photo">
        <div class="sampler-photo__can">
          <img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/sampler-can.png" width="389" height="534"
               alt="Баночка пробного образца краски 300 мл в цвете <?php echo esc_attr( $weiss_color_label ); ?>" loading="lazy">
        </div>
      </div>

      <aside class="sampler-aside" aria-labelledby="sampler-title">
        <h2 class="sampler-aside__title" id="sampler-title">Пробный образец<?php echo $weiss_color ? ' цвета ' . esc_html( $weiss_color->code ) : ''; ?></h2>
        <p class="sampler-aside__text">
          Баночка краски 300 мл<?php echo $weiss_color ? ' в цвете ' . esc_html( $weiss_color->code ) : ''; ?> — идеальное решение,<br class="sampler-br"> чтобы оценить,
          как оттенок выглядит именно в вашем интерьере. Нанесите на стену и посмотрите
          на цвет при разном освещении в течение дня.
        </p>

        <ul class="sampler-benefits">
          <li class="sampler-benefit">
            <!-- веер цветовых карточек -->
            <img src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/sampler-swatches.svg" width="40" height="40"
                 alt="" aria-hidden="true" loading="lazy">
            <span>Реальный цвет<br>в вашем интерьере</span>
          </li>
          <li class="sampler-benefit">
            <!-- дом с окном и малярной кистью -->
            <img src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/sampler-brush.svg" width="44" height="40"
                 alt="" aria-hidden="true" loading="lazy">
            <span>Удобно<br>тестировать</span>
          </li>
          <li class="sampler-benefit">
            <!-- грузовик с линиями скорости -->
            <img src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/sampler-truck.svg" width="63" height="33"
                 alt="" aria-hidden="true" loading="lazy">
            <span>Доставка по России</span>
          </li>
        </ul>

        <?php if ( $weiss_have ) : ?>
        <div class="sampler-price-row">
          <p class="sampler-price"><?php echo esc_html( weiss_price_number( $weiss_price ) ); ?> ₽</p>
          <span class="sampler-stock"><?php echo esc_html( $weiss_stock ); ?></span>
        </div>

        <form class="sampler-buy" action="<?php echo esc_url( $weiss_back ); ?>" method="post" novalidate
              data-weiss-add="<?php echo esc_attr( $weiss_product->get_id() ); ?>"
              data-weiss-palette="<?php echo esc_attr( $weiss_color->palette ); ?>"
              data-weiss-code="<?php echo esc_attr( $weiss_color->code ); ?>"
              data-weiss-url="<?php echo esc_url( $weiss_back ); ?>">
          <div class="sampler-qty">
            <label class="sampler-qty__label" for="sampler-qty">Количество</label>
            <div class="qty">
              <button class="qty__btn qty__btn--minus" type="button" data-qty="dec"
                      aria-label="Уменьшить количество" aria-controls="sampler-qty">−</button>
              <input class="qty__input" id="sampler-qty" name="qty" type="number"
                     inputmode="numeric" min="1" step="1" value="1"<?php echo $weiss_max_qty ? ' max="' . esc_attr( $weiss_max_qty ) . '"' : ''; ?> aria-label="Количество, шт.">
              <button class="qty__btn" type="button" data-qty="inc"
                      aria-label="Увеличить количество" aria-controls="sampler-qty">+</button>
            </div>
          </div>

          <button class="btn btn--brown sampler-add" type="submit">Добавить в корзину</button>
          <a class="btn btn--outline sampler-continue" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Продолжить покупки</a>
        </form>
        <?php else : ?>
        <div class="sampler-price-row">
          <p class="sampler-price">300 мл</p>
          <span class="sampler-stock"><?php echo ( $weiss_color && ! $weiss_allowed ) ? 'Цвет не продаётся пробником' : 'Выберите цвет'; ?></span>
        </div>

        <a class="btn btn--brown sampler-add" href="<?php echo esc_url( $weiss_palettes ); ?>">Выбрать цвет</a>
        <?php endif; ?>

        <div class="sampler-notice">
          <!-- в макете — заливной синий круг с белым «!» -->
          <svg viewBox="0 0 44 44" aria-hidden="true">
            <circle cx="22" cy="22" r="22" fill="currentColor"/>
            <rect x="19.9" y="9" width="4.2" height="16.5" rx="2.1" fill="#ffffff"/>
            <circle cx="22" cy="31.4" r="2.5" fill="#ffffff"/>
          </svg>
          <p>
            Цвет на экране отличается от реального.<br>
            Рекомендуем заказывать пробный образец, чтобы оценить цвет<br class="sampler-notice-br">
            в вашем интерьере.
          </p>
        </div>
      </aside>
    </div>
<?php
get_footer();
