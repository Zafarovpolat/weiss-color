<?php
/**
 * Карточка товара (М05). Разметка повторяет 02_верстка/pages/product.html:
 * герой с фото банки, объёмы, блок цены, описание и характеристики.
 *
 * @package Weiss_Color
 */

defined( 'ABSPATH' ) || exit;

global $product;

$weiss_product = wc_get_product( get_the_ID() );
if ( ! $weiss_product ) {
	return;
}

$weiss_cats = wp_get_post_terms( $weiss_product->get_id(), 'product_cat' );
$weiss_cat  = ( ! is_wp_error( $weiss_cats ) && ! empty( $weiss_cats ) ) ? $weiss_cats[0] : null;

/* Объёмы: у вариативного товара — кнопки, у простого блок скрыт.
   У фасовки свои артикул, остаток, картинка и доплата (ТЗ 4.5);
   доплата null — правила колеровки для неё не согласованы. */
$weiss_variations = array();
if ( $weiss_product->is_type( 'variable' ) ) {
	$weiss_parent_image = wp_get_attachment_image_url( $weiss_product->get_image_id(), 'large' );
	foreach ( $weiss_product->get_available_variations( 'objects' ) as $weiss_variation ) {
		$weiss_tinting = get_post_meta( $weiss_variation->get_id(), '_weiss_tinting', true );
		$weiss_variations[] = array(
			'id'         => $weiss_variation->get_id(),
			'label'      => implode( ' / ', $weiss_variation->get_variation_attributes() ),
			'price'      => (float) $weiss_variation->get_price(),
			'surcharge'  => weiss_tint_surcharge( $weiss_product->get_id(), $weiss_variation->get_id() ),
			'sku'        => (string) $weiss_variation->get_sku(),
			'stock'      => $weiss_variation->is_in_stock(),
			'image'      => wp_get_attachment_image_url( $weiss_variation->get_image_id(), 'large' ) ?: $weiss_parent_image,
			'tinting'    => 'no' !== $weiss_tinting,
			'attributes' => array_filter( $weiss_variation->get_variation_attributes() ),
		);
	}
}

$weiss_first_variation = $weiss_variations ? $weiss_variations[0]['id'] : 0;
$weiss_price           = $weiss_variations ? $weiss_variations[0]['price'] : (float) $weiss_product->get_price();
$weiss_surcharge       = $weiss_variations ? $weiss_variations[0]['surcharge'] : weiss_tint_surcharge( $weiss_product->get_id() );
$weiss_tinting_off     = 'no' === get_post_meta( $weiss_product->get_id(), '_weiss_tinting', true );
$weiss_first_sku       = $weiss_variations ? $weiss_variations[0]['sku'] : (string) $weiss_product->get_sku();
$weiss_in_stock        = $weiss_variations ? $weiss_variations[0]['stock'] : $weiss_product->is_in_stock();
$weiss_stock_label     = $weiss_in_stock ? 'В наличии' : 'Нет в наличии';

/* Цвет приходит из каталога оттенков: пара palette+code в адресе. */
$weiss_palette  = isset( $_GET['palette'] ) ? sanitize_text_field( wp_unslash( $_GET['palette'] ) ) : '';
$weiss_code     = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
$weiss_selected = ( $weiss_palette && $weiss_code ) ? weiss_color_get( $weiss_palette, $weiss_code ) : null;

/* Цвет из адреса обязан подходить товару — иначе сбрасываем с сообщением (ТЗ, п.5.5). */
$weiss_color_reset = false;
if ( $weiss_selected && ! weiss_color_fits_product( $weiss_selected, $weiss_product->get_id(), $weiss_first_variation ) ) {
	$weiss_selected    = null;
	$weiss_color_reset = true;
}

/* Цена конфигурации: базовая фасовка плюс доплата за колеровку (ТЗ, 4.5).
   Пока доплата не согласована, показываем базовую цену и объясняем причину. */
$weiss_tint_state = '';
if ( $weiss_selected ) {
	if ( null === $weiss_surcharge ) {
		$weiss_tint_state = 'unknown';
	} else {
		$weiss_tint_state = 'known';
		$weiss_price      = round( $weiss_price + $weiss_surcharge, 2 );
	}
}

$weiss_color_url    = $weiss_selected ? weiss_color_url( $weiss_selected->palette, $weiss_selected->code ) : weiss_page_url( 'palettes' );
/* «Колеровка онлайн» ведёт сразу в визуализатор: товар и фасовка
   сохраняются (ТЗ, п. 6.1), инструкция М21 открывается там окном. */
$weiss_coloring_url = add_query_arg(
	array_filter( array(
		'weiss_product'   => $weiss_product->get_id(),
		'weiss_variation' => $weiss_first_variation,
		'palette'         => $weiss_selected ? $weiss_selected->palette : '',
		'code'            => $weiss_selected ? $weiss_selected->code : '',
	) ),
	weiss_page_url( 'visualizer' )
);

/* Текст с переносами строк из редактора превращаем в <br>, как в макете. */
$weiss_lead = wp_kses_post( nl2br( $weiss_product->get_short_description() ) );

/* В макете бренд стоит отдельной строкой: «ACRILUX» / «КРАСКА ДЛЯ СТЕН
   И ПОТОЛКОВ», а в крошке последний пункт идёт без бренда. Название в
   каталоге хранится одной строкой «Acrilux краска для стен и потолков». */
$weiss_slug_parts = preg_split( '/\s+/u', trim( $weiss_product->get_name() ), 2 );
$weiss_brand      = '';
$weiss_name_rest  = $weiss_product->get_name();
if ( isset( $weiss_slug_parts[1] ) && 0 === strcasecmp( $weiss_slug_parts[0], 'Acrilux' ) ) {
	$weiss_brand     = $weiss_slug_parts[0];
	$weiss_name_rest = $weiss_slug_parts[1];
	/* В крошке макета — «Краска для стен и потолков» с заглавной. */
	$weiss_name_rest = mb_strtoupper( mb_substr( $weiss_name_rest, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $weiss_name_rest, 1, null, 'UTF-8' );
}

weiss_set_main_class( '' );
get_header();
?>

<section class="p-hero" aria-labelledby="product-title">
	<div class="container p-hero__inner">
		<nav class="breadcrumbs p-hero__breadcrumbs" aria-label="Хлебные крошки">
			<ol class="breadcrumbs__list">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
				<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
				<li><a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Каталог</a></li>
				<?php if ( $weiss_cat ) : ?>
					<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
					<li><a href="<?php echo esc_url( get_term_link( $weiss_cat ) ); ?>"><?php echo esc_html( $weiss_cat->name ); ?></a></li>
				<?php endif; ?>
				<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
				<li aria-current="page"><?php echo esc_html( $weiss_name_rest ); ?></li>
			</ol>
		</nav>

		<div class="p-hero__grid">
			<figure class="p-hero__can">
				<?php
				echo wp_get_attachment_image(
					$weiss_product->get_image_id(),
					'large',
					false,
					array(
						'width'         => 326,
						'height'        => 480,
						'alt'           => $weiss_product->get_name(),
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
				?>
			</figure>

			<div class="p-hero__copy">
				<h1 class="p-hero__title" id="product-title"><?php echo $weiss_brand ? esc_html( $weiss_brand ) . '<br>' : ''; ?><?php echo esc_html( $weiss_name_rest ); ?></h1>

				<?php if ( $weiss_lead ) : ?>
					<p class="p-hero__lead"><?php echo $weiss_lead; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
				<?php endif; ?>

				<?php if ( $weiss_variations ) : ?>
					<div class="p-volumes" role="group" aria-label="Объём упаковки" data-weiss-volumes>
						<?php foreach ( $weiss_variations as $weiss_index => $weiss_item ) : ?>
							<button class="p-volumes__item" type="button"
								aria-pressed="<?php echo 0 === $weiss_index ? 'true' : 'false'; ?>"
								data-variation="<?php echo esc_attr( $weiss_item['id'] ); ?>"
								data-price="<?php echo esc_attr( $weiss_item['price'] ); ?>"
								data-surcharge="<?php echo esc_attr( null === $weiss_item['surcharge'] ? '' : $weiss_item['surcharge'] ); ?>"
								data-sku="<?php echo esc_attr( $weiss_item['sku'] ); ?>"
								data-stock="<?php echo esc_attr( $weiss_item['stock'] ? '1' : '0' ); ?>"
								data-tinting="<?php echo esc_attr( $weiss_item['tinting'] ? '1' : '0' ); ?>"
								data-image="<?php echo esc_url( $weiss_item['image'] ); ?>"
								data-weiss-attributes="<?php echo esc_attr( wp_json_encode( $weiss_item['attributes'] ) ); ?>"><?php echo esc_html( $weiss_item['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! $weiss_tinting_off ) : ?>
					<a class="p-coloring" href="<?php echo esc_url( $weiss_coloring_url ); ?>"
					   data-weiss-viz-base="<?php echo esc_url( $weiss_coloring_url ); ?>">
						<span>Колеровка онлайн</span>
						<img src="<?php echo esc_url( weiss_asset( 'icons/product-coloring.svg' ) ); ?>" width="34" height="34" alt="" aria-hidden="true">
					</a>
				<?php endif; ?>

				<p class="p-color">
					<b>Цвет:</b>
					<?php if ( $weiss_tinting_off ) : ?>
						Базовый (колеровка для этого товара не предусмотрена)
					<?php elseif ( $weiss_selected ) : ?>
						<a href="<?php echo esc_url( $weiss_color_url ); ?>"><?php echo esc_html( $weiss_selected->code . ' — ' . $weiss_selected->palette ); ?></a>
					<?php else : ?>
						<a href="<?php echo esc_url( $weiss_color_url ); ?>">Белый (без колеровки)</a>
					<?php endif; ?>
				</p>
				<?php if ( $weiss_color_reset ) : ?>
					<p class="p-color__notice" role="alert">Выбранный оттенок не подходит для этого товара. Выберите цвет заново.</p>
				<?php endif; ?>

				<p class="p-color p-sku" data-weiss-sku<?php echo $weiss_first_sku ? '' : ' hidden'; ?>><b>Артикул:</b> <span><?php echo esc_html( $weiss_first_sku ); ?></span></p>
				<p class="p-color p-stock" data-weiss-stock><b>Наличие:</b> <span><?php echo $weiss_stock_label; ?></span></p>
				<p class="p-color__notice" data-weiss-variation-notice role="alert" hidden></p>

				<div class="p-price-box" data-weiss-tint="<?php echo $weiss_selected ? '1' : '0'; ?>">
					<p class="p-price tabular" data-weiss-price><?php echo esc_html( weiss_price_compact( $weiss_price ) ); ?></p>
					<?php if ( 'unknown' === $weiss_tint_state ) : ?>
						<p class="p-price__note" data-weiss-price-note>Стоимость колеровки для этого объёма ещё не согласована — оформить заказ с колеровкой нельзя. Выберите другой объём или купите базовый цвет без колеровки.</p>
					<?php elseif ( 'known' === $weiss_tint_state ) : ?>
						<p class="p-price__note" data-weiss-price-note>Цена указана за выбранный объём с доплатой за колеровку.</p>
					<?php else : ?>
						<p class="p-price__note" data-weiss-price-note>
							Цена указана за базовую краску, итоговая<br>
							стоимость рассчитывается при выбранном<br>
							объёме
						</p>
					<?php endif; ?>
					<button class="btn p-price__btn" type="button"
						data-weiss-add="<?php echo esc_attr( $weiss_product->get_id() ); ?>"
						data-weiss-variation="<?php echo esc_attr( $weiss_first_variation ); ?>"
						data-weiss-attributes="<?php echo esc_attr( wp_json_encode( $weiss_variations ? $weiss_variations[0]['attributes'] : array() ) ); ?>"
						<?php if ( $weiss_selected ) : ?>
							data-weiss-palette="<?php echo esc_attr( $weiss_selected->palette ); ?>"
							data-weiss-code="<?php echo esc_attr( $weiss_selected->code ); ?>"
						<?php endif; ?>
						data-weiss-url="<?php echo esc_url( add_query_arg( array_filter( array(
							'palette' => $weiss_selected ? $weiss_selected->palette : '',
							'code'    => $weiss_selected ? $weiss_selected->code : '',
						) ), get_permalink( $weiss_product->get_id() ) ) ); ?>">В корзину</button>
				</div>
			</div>
		</div>
	</div>
</section>

<section class="p-details">
	<div class="container p-details__grid">
		<div class="p-tabs">
			<h2 class="p-tab p-tab--active">Описание товара</h2>
			<h2 class="p-tab">Технические характеристики</h2>
		</div>

		<div class="p-details__col">
			<div class="p-desc">
				<?php echo wp_kses_post( wpautop( $weiss_product->get_description() ) ); ?>
			</div>
		</div>

		<div class="p-details__col">
			<?php if ( $weiss_product->has_attributes() ) : ?>
				<ul class="p-specs">
					<?php foreach ( $weiss_product->get_attributes() as $weiss_attribute ) : ?>
						<?php
						/* Объёмы — это кнопки в герое, в таблицу характеристик не дублируем.
						   «Краткое описание» — атрибут вывода, а не характеристика. */
						$weiss_label = wc_attribute_label( $weiss_attribute->get_name(), $weiss_product );
						if ( ! $weiss_attribute->get_visible() || $weiss_attribute->get_variation()
							|| 'краткое описание' === mb_strtolower( $weiss_label ) ) {
							continue;
						}
						if ( $weiss_attribute->is_taxonomy() ) {
							$weiss_terms = wc_get_product_terms( $weiss_product->get_id(), $weiss_attribute->get_name(), array( 'fields' => 'names' ) );
							$weiss_value = implode( ', ', $weiss_terms );
						} else {
							$weiss_value = implode( ', ', $weiss_attribute->get_options() );
						}
						?>
						<li class="p-specs__row">
							<span class="p-specs__label"><?php echo esc_html( $weiss_label ); ?></span>
							<span class="p-specs__value"><?php echo esc_html( $weiss_value ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php
get_footer();
