<?php
/**
 * Визуализатор (М08). Разметка перенесена из 02_верстка/pages/visualizer.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( 'viz-page' );
get_header();

$weiss_default_palette = 'ACE Paint Colors for Your Life';
$weiss_palette         = isset( $_GET['palette'] ) ? sanitize_text_field( wp_unslash( $_GET['palette'] ) ) : $weiss_default_palette;
$weiss_code            = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

/* Товар и фасовка из карточки (ТЗ, п. 6.1) важнее совместимого по умолчанию.
   Префикс weiss_ обязателен: product/variation — публичные query var WooCommerce. */
$weiss_product_id   = isset( $_GET['weiss_product'] ) ? absint( $_GET['weiss_product'] ) : 0;
$weiss_variation_id = isset( $_GET['weiss_variation'] ) ? absint( $_GET['weiss_variation'] ) : 0;

/* При выбранном товаре список ограничен совместимой группой цветов (ТЗ, п.5.4–5.5). */
$weiss_group = $weiss_product_id ? weiss_product_color_group( $weiss_product_id ) : '';
$weiss_pages = weiss_color_palettes_active( $weiss_group );
$weiss_known = wp_list_pluck( $weiss_pages, 'palette' );

if ( ! in_array( $weiss_palette, $weiss_known, true ) ) {
	$weiss_palette = $weiss_known ? $weiss_known[0] : '';
}

$weiss_viz_page = max( 1, (int) ( $_GET['viz_page'] ?? 1 ) );
$weiss_viz      = weiss_colors_query( array( 'palette' => $weiss_palette, 'group' => $weiss_group, 'per_page' => 18, 'page' => $weiss_viz_page ) );

if ( ! $weiss_viz['items'] && ! isset( $_GET['palette'] ) && $weiss_group ) {
	$weiss_start = weiss_palette_first_compatible( $weiss_group );
	if ( $weiss_start ) {
		$weiss_palette  = $weiss_start;
		$weiss_viz_page = 1;
		$weiss_viz      = weiss_colors_query( array( 'palette' => $weiss_palette, 'group' => $weiss_group, 'per_page' => 18, 'page' => 1 ) );
	}
}

if ( $weiss_viz_page > $weiss_viz['max_pages'] ) {
	$weiss_viz_page = $weiss_viz['max_pages'];
	$weiss_viz      = weiss_colors_query( array( 'palette' => $weiss_palette, 'group' => $weiss_group, 'per_page' => 18, 'page' => $weiss_viz_page ) );
}

$weiss_first  = $weiss_viz['items'] ? $weiss_viz['items'][0] : null;
$weiss_target = $weiss_first ? weiss_color_cart_target( $weiss_first ) : null;

if ( $weiss_product_id ) {
	$weiss_chosen = weiss_product_cart_target( wc_get_product( $weiss_product_id ), $weiss_variation_id );
	if ( $weiss_chosen ) {
		$weiss_target = $weiss_chosen;
	}
}

$weiss_active_code = $weiss_first ? $weiss_first->code : '';
if ( $weiss_code ) {
	foreach ( $weiss_viz['items'] as $weiss_row ) {
		if ( $weiss_row->code === $weiss_code ) {
			$weiss_active_code = $weiss_code;
			break;
		}
	}
}

$weiss_brand = weiss_palette_brand( $weiss_palette );

/* Пробник передаёт выбранный цвет (ТЗ, п.6.6). */
$weiss_sampler_url = add_query_arg(
	array_filter(
		array(
			'palette' => $weiss_palette,
			'code'    => $weiss_active_code,
		)
	),
	weiss_page_url( 'sampler' )
);
?>
<div class="viz-main" data-palette="<?php echo esc_attr( $weiss_palette ); ?>" data-code="<?php echo esc_attr( $weiss_code ); ?>" data-page="<?php echo esc_attr( $weiss_viz_page ); ?>" data-pages="<?php echo esc_attr( $weiss_viz['max_pages'] ); ?>" data-product="<?php echo esc_attr( $weiss_product_id ); ?>" data-variation="<?php echo esc_attr( $weiss_variation_id ); ?>" data-group="<?php echo esc_attr( $weiss_group ); ?>">

      <nav class="crumbs crumbs--overlay" aria-label="Хлебные крошки">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
        <a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a> &gt;
        <span aria-current="page">Визуализатор</span>
      </nav>

      <!-- Заголовок экрана + подсказка -->
      <div class="viz-head">
        <h1 class="viz-title">Колеровка цвета<span><?php echo esc_html( $weiss_brand ); ?></span></h1>
        <a class="viz-help" href="<?php echo esc_url( weiss_page_url( 'coloring-guide' ) ); ?>" data-modal-open="guide-dialog">Как это работает?</a>
      </div>

      <div class="viz-cols">

        <!-- ============ Левая колонка: карточка товара и оттенки ============ -->
        <div class="viz-left">
          <article class="viz-product">
            <img class="viz-product__img" src="<?php echo esc_url( $weiss_target && $weiss_target['image'] ? $weiss_target['image'] : WEISS_URI . '/assets/img/viz-can.png' ); ?>" width="158" height="226" alt="<?php echo esc_attr( $weiss_target ? $weiss_target['name'] : 'Банка краски ACRILUX' ); ?>">
            <div class="viz-product__body">
              <h2 class="viz-product__title">
                <span class="viz-product__brand">ACRILUX</span>
                <?php echo esc_html( $weiss_target ? $weiss_target['short'] : 'Краска для стен и потолков' ); ?>
              </h2>
              <?php if ( $weiss_target && $weiss_target['desc'] ) : ?>
                <p class="viz-product__desc"><?php echo esc_html( $weiss_target['desc'] ); ?></p>
              <?php endif; ?>
              <p class="viz-product__price"><?php echo $weiss_target ? 'от ' . esc_html( weiss_price_number( $weiss_target['price'] ) ) . ' ₽' : ''; ?></p>
            </div>
          </article>

          <h2 class="viz-choose">Выберите цвет</h2>

          <div class="viz-palette-row">
          <div class="viz-palette">
            <button class="viz-select" type="button" aria-haspopup="listbox" aria-expanded="false"
                    aria-controls="viz-palette-options">
              <span class="viz-select__label">Каталог <?php echo esc_html( $weiss_brand ); ?></span>
              <svg viewBox="0 0 30 18" fill="none" aria-hidden="true">
                <path d="M2 2l13 13L28 2" stroke="currentColor" stroke-width="3" stroke-linecap="square"/>
              </svg>
            </button>

            <ul class="viz-palette__list" id="viz-palette-options" role="listbox" aria-label="Каталог оттенков">
              <?php foreach ( $weiss_pages as $weiss_option ) : ?>
                <li class="viz-palette__option" role="option" aria-selected="<?php echo $weiss_option->palette === $weiss_palette ? 'true' : 'false'; ?>" tabindex="0"
                    data-palette="<?php echo esc_attr( $weiss_option->palette ); ?>" data-brand="<?php echo esc_attr( weiss_palette_brand( $weiss_option->palette ) ); ?>"><?php echo esc_html( $weiss_option->palette ); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <form class="viz-search" role="search" onsubmit="return false">
            <label class="visually-hidden" for="viz-search-input">Поиск по коду оттенка</label>
            <input class="viz-search__input" id="viz-search-input" type="search" placeholder="Поиск по коду">
          </form>
          </div>

          <ul class="viz-swatches" aria-label="Оттенки каталога <?php echo esc_attr( $weiss_palette ); ?>">
            <?php
            foreach ( $weiss_viz['items'] as $weiss_index => $weiss_color ) :
              $weiss_active = $weiss_color->code === $weiss_active_code;
              ?>
              <li><button class="viz-swatch<?php echo $weiss_active ? ' viz-swatch--active' : ''; ?>" type="button" aria-pressed="<?php echo $weiss_active ? 'true' : 'false'; ?>" data-hex="<?php echo esc_attr( $weiss_color->hex ); ?>"><span class="viz-swatch__fill" style="background:<?php echo esc_attr( $weiss_color->hex ); ?>"></span><span class="viz-swatch__label"><?php echo esc_html( $weiss_color->code ); ?></span></button></li>
            <?php endforeach; ?>
          </ul>

          <?php
          $weiss_viz_base = array_filter( array(
              'palette'         => $weiss_palette,
              'code'            => $weiss_code,
              'weiss_product'   => $weiss_product_id,
              'weiss_variation' => $weiss_variation_id,
          ) );
          weiss_viz_pagination(
              $weiss_viz_page,
              $weiss_viz['max_pages'],
              function ( $page ) use ( $weiss_viz_base ) {
                  return add_query_arg( array_merge( $weiss_viz_base, array( 'viz_page' => $page ) ), weiss_page_url( 'visualizer' ) );
              }
          );
          ?>
        </div>

        <!-- ============ Правая колонка: визуализатор ============ -->
        <div class="viz-right">
          <figure class="viz-hero">
            <img class="viz-hero__img" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/scene-kids.png" width="1334" height="746" alt="Интерьер детской, окрашенной в выбранный оттенок">
            <span class="viz-hero__paint" aria-hidden="true"></span>

            <ul class="viz-thumbs" aria-label="Превью интерьеров">
              <li><button class="viz-thumb" type="button" data-scene="living"
                          data-img="<?php echo esc_url( WEISS_URI ); ?>/assets/img/scene-living.png"
                          data-mask="<?php echo esc_url( WEISS_URI ); ?>/assets/masks/scene-living.png"
                          data-alt="Интерьер гостиной, окрашенной в выбранный оттенок"><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/viz-thumb1.png" width="199" height="119" alt="Интерьер 1 — гостиная"></button></li>
              <li><button class="viz-thumb" type="button" data-scene="bath"
                          data-img="<?php echo esc_url( WEISS_URI ); ?>/assets/img/scene-bath.png"
                          data-mask="<?php echo esc_url( WEISS_URI ); ?>/assets/masks/scene-bath.png"
                          data-alt="Интерьер ванной, окрашенной в выбранный оттенок"><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/viz-thumb2.png" width="199" height="119" alt="Интерьер 2 — ванная"></button></li>
              <li><button class="viz-thumb" type="button" data-scene="kitchen"
                          data-img="<?php echo esc_url( WEISS_URI ); ?>/assets/img/scene-kitchen.png"
                          data-mask="<?php echo esc_url( WEISS_URI ); ?>/assets/masks/scene-kitchen.png"
                          data-alt="Интерьер кухни, окрашенной в выбранный оттенок"><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/viz-thumb3.png" width="199" height="119" alt="Интерьер 3 — кухня"></button></li>
              <li><button class="viz-thumb viz-thumb--active" type="button" aria-current="true" data-scene="kids"
                          data-img="<?php echo esc_url( WEISS_URI ); ?>/assets/img/scene-kids.png"
                          data-mask="<?php echo esc_url( WEISS_URI ); ?>/assets/masks/scene-kids.png"
                          data-alt="Интерьер детской, окрашенной в выбранный оттенок"><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/viz-thumb4.png" width="199" height="119" alt="Интерьер 4 — детская"></button></li>
            </ul>

            <button class="viz-prev" type="button" aria-label="Предыдущий интерьер">
              <svg class="viz-arrow" viewBox="0 0 45 54" aria-hidden="true" focusable="false">
                <path d="M18 17 28 27 18 37" fill="none" stroke="currentColor" stroke-width="4.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>

            <button class="viz-next" type="button" aria-label="Следующий интерьер">
              <svg class="viz-arrow" viewBox="0 0 45 54" aria-hidden="true" focusable="false">
                <path d="M18 17 28 27 18 37" fill="none" stroke="currentColor" stroke-width="4.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </figure>

          <aside class="viz-info">
            <img class="viz-info__icon" src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/color-info.svg" width="40" height="40" alt="">
            <p class="viz-info__text">
              Цвет на экране отличается от реального.<br>
              Рекомендуем проверять цвет в печатном каталоге или сделать пробный выкрас
            </p>
            <a class="viz-info__btn" href="<?php echo esc_url( $weiss_sampler_url ); ?>">Заказать пробный образец</a>
          </aside>

          <?php
          /* ТЗ 6.5: фасовку выбирает покупатель. Без выбранной фасовки кнопка
             ведёт в карточку товара, а не кладёт первую фасовку в корзину. */
          $weiss_need_pack = $weiss_target && ! empty( $weiss_target['variable'] ) && empty( $weiss_target['variation'] );
          ?>
          <?php if ( $weiss_target && $weiss_viz['items'] ) : ?>
          <div class="viz-buy">
            <span class="viz-price">от <?php echo esc_html( weiss_price_number( $weiss_target['price'] ) ); ?> ₽</span>
            <?php if ( $weiss_need_pack ) : ?>
              <a class="btn viz-cart" href="<?php echo esc_url( $weiss_target['url'] ); ?>">Выбрать фасовку</a>
            <?php else : ?>
              <button class="btn viz-cart" type="button"
                      data-weiss-add="<?php echo esc_attr( $weiss_target['id'] ); ?>"
                      data-weiss-variation="<?php echo esc_attr( $weiss_target['variation'] ); ?>"
                      data-weiss-attributes="<?php echo esc_attr( wp_json_encode( $weiss_target['attributes'] ) ); ?>"
                      data-weiss-palette="<?php echo esc_attr( $weiss_palette ); ?>"
                      data-weiss-code="<?php echo esc_attr( $weiss_active_code ); ?>"
                      data-weiss-name="<?php echo esc_attr( $weiss_target['name'] ); ?>"
                      data-weiss-url="<?php echo esc_url( $weiss_target['url'] ); ?>">В корзину</button>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <?php /* М21 из макета — окно «Как это работает?» поверх визуализатора. */ ?>
    <dialog class="guide-modal" id="guide-dialog" aria-labelledby="guide-title">
      <?php get_template_part( 'template-parts/guide-card', null, array(
        'sampler_url' => $weiss_sampler_url,
        'close_cta'   => true,
        'h2'          => true,
      ) ); ?>
    </dialog>
<?php
get_footer();
