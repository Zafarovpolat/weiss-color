<?php
/**
 * Страница цвета (М21): выбранная палитра и код, плашка цвета, совместимые
 * материалы из WooCommerce, переход к пробнику (ТЗ, п.5.5).
 */
weiss_set_main_class( 'color-page' );

$weiss_palette = isset( $_GET['palette'] ) ? sanitize_text_field( wp_unslash( $_GET['palette'] ) ) : '';
$weiss_code    = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
$weiss_color   = ( $weiss_palette && $weiss_code ) ? weiss_color_get( $weiss_palette, $weiss_code ) : null;

/* Подпись оттенка как в макете: марка палитры и код — «Цвет ACE PAINT F316».
   Коды ACE уже начинаются с марки («ACE A1-1») — её не повторяем. */
$weiss_color_name = '';
if ( $weiss_color ) {
	$weiss_brand = weiss_palette_brand( $weiss_color->palette );
	$weiss_short = strtok( $weiss_brand, ' ' );
	$weiss_label = $weiss_color->code;
	if ( $weiss_short && 0 === stripos( $weiss_label, $weiss_short . ' ' ) ) {
		$weiss_label = ltrim( substr( $weiss_label, strlen( $weiss_short ) ) );
	}
	$weiss_color_name = trim( $weiss_brand . ' ' . $weiss_label );
}

/* У заголовка вкладки — выбранный оттенок, а не название страницы-шаблона (ТЗ, п. 11.7). */
add_filter( 'pre_get_document_title', function () use ( $weiss_color_name, $weiss_code ) {
	$label = '' !== $weiss_color_name ? $weiss_color_name : $weiss_code;
	return ( $label ? 'Цвет ' . $label : 'Цвет' ) . ' — ' . get_bloginfo( 'name' );
} );

get_header();

if ( ! $weiss_color ) {
	?>
	<div class="container color-page__head">
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<ol class="breadcrumbs__list">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
				<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
				<li><a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a></li>
			</ol>
		</nav>
		<h1 class="color-page__title">Цвет не найден</h1>
	</div>
	<div class="container">
		<p class="shades-empty">
			Выберите оттенок в <a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">каталоге палитр</a>.
			<?php if ( $weiss_palette && $weiss_code ) : ?>
				Запись «<?php echo esc_html( $weiss_code ); ?>» недоступна: возможно, ждёт уточнения цвета.
			<?php endif; ?>
		</p>
	</div>
	<?php
	get_footer();
	return;
}

$weiss_items    = weiss_color_products( $weiss_color );
$weiss_shades   = weiss_shades_url( $weiss_palette );
$weiss_sampler  = add_query_arg(
	array(
		'palette' => $weiss_palette,
		'code'    => $weiss_code,
	),
	weiss_page_url( 'sampler' )
);
$weiss_materials = array( 'all' => 'Все' );
foreach ( $weiss_items as $weiss_item ) {
	$weiss_materials[ $weiss_item['material'] ] = weiss_color_materials()[ $weiss_item['material'] ]['label'] ?? $weiss_item['material'];
}
?>
<div class="container color-page__head">
	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<ol class="breadcrumbs__list">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
			<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
			<li><a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a></li>
			<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
			<li><a href="<?php echo esc_url( $weiss_shades ); ?>"><?php echo esc_html( $weiss_palette ); ?></a></li>
			<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
			<li aria-current="page">Цвет <?php echo esc_html( $weiss_code ); ?></li>
		</ol>
	</nav>
	<h1 class="color-page__title">Цвет <?php echo esc_html( $weiss_color_name ); ?></h1>
</div>

<section class="container c-hero" aria-label="Визуализация цвета">
	<figure class="c-hero__photo">
		<img src="<?php echo esc_url( weiss_asset( 'img/color-room.png' ) ); ?>" width="1073" height="479"
			alt="Интерьер с цветом <?php echo esc_attr( $weiss_palette . ' ' . $weiss_code ); ?>" fetchpriority="high" decoding="async">
	</figure>

	<div class="c-hero__side">
		<div class="c-swatch">
			<span class="c-swatch__fill" style="background:<?php echo esc_attr( $weiss_color->hex ); ?>" aria-hidden="true"></span>
			<span class="c-swatch__label">Цвет <?php echo esc_html( $weiss_code ); ?></span>
		</div>

		<p class="c-swatch__note">
			Отображение цвета зависит от настроек экрана. Рекомендуем проверить оттенок в естественной среде.
		</p>

		<div class="c-info">
			<img class="c-info__icon" src="<?php echo esc_url( weiss_asset( 'icons/color-info.svg' ) ); ?>" width="40" height="40" alt="">
			<p class="c-info__text">
				На окончательный вид цвета влияют освещение, основание и фактура стены.<br>
				Для точной оценки закажите пробный образец
			</p>
		</div>

		<a class="btn btn--teal c-hero__cta" href="<?php echo esc_url( $weiss_sampler ); ?>">Заказать пробный образец 300 мл</a>
	</div>
</section>

<section class="container c-materials" aria-labelledby="materials-title">
	<h2 class="c-materials__title" id="materials-title">Материалы, которые колеруются в цвет <?php echo esc_html( $weiss_code ); ?></h2>

	<nav class="c-tabs" aria-label="Фильтр по типу материала">
		<?php $weiss_first = true; ?>
		<?php foreach ( $weiss_materials as $weiss_key => $weiss_label ) : ?>
			<?php if ( ! $weiss_first ) : ?>
				<span class="c-tabs__sep" aria-hidden="true">I</span>
			<?php endif; ?>
			<a class="c-tabs__item<?php echo $weiss_first ? ' is-active' : ''; ?>" href="#" data-material="<?php echo esc_attr( $weiss_key ); ?>"<?php echo $weiss_first ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $weiss_label ); ?></a>
			<?php $weiss_first = false; ?>
		<?php endforeach; ?>
	</nav>

	<ul class="c-products">
		<?php foreach ( $weiss_items as $weiss_item ) : ?>
			<?php
			$weiss_product = $weiss_item['product'];
			$weiss_link    = add_query_arg(
				array(
					'palette' => $weiss_palette,
					'code'    => $weiss_code,
				),
				get_permalink( $weiss_product->get_id() )
			);
			$weiss_image   = wp_get_attachment_image( $weiss_product->get_image_id(), 'medium', false, array(
				'alt'     => $weiss_product->get_name(),
				'loading' => 'lazy',
			) );
			$weiss_desc    = wp_strip_all_tags( $weiss_product->get_short_description() );

			/* В макете бренд стоит первой строкой, тип краски — со второй. */
			$weiss_name     = explode( ' ', $weiss_product->get_name(), 2 );
			$weiss_title_b  = 0 === stripos( $weiss_name[0], 'ACRILUX' ) ? array_shift( $weiss_name ) : '';
			$weiss_title_r  = $weiss_title_b ? implode( ' ', $weiss_name ) : $weiss_product->get_name();
			?>
			<li class="c-product" data-material="<?php echo esc_attr( $weiss_item['material'] ); ?>">
				<a class="c-product__media" href="<?php echo esc_url( $weiss_link ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo $weiss_image; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
				<div class="c-product__body">
					<h3 class="c-product__title"><a href="<?php echo esc_url( $weiss_link ); ?>"><?php echo $weiss_title_b ? esc_html( $weiss_title_b ) . '<br>' : ''; ?><?php echo esc_html( $weiss_title_r ); ?></a></h3>
					<?php if ( $weiss_desc ) : ?>
						<p class="c-product__desc"><?php echo esc_html( $weiss_desc ); ?></p>
					<?php endif; ?>
					<div class="c-product__row">
						<span class="c-product__price tabular"><?php echo esc_html( 'от ' . weiss_price_number( $weiss_product->get_price() ) . ' ₽' ); ?></span>
						<?php if ( $weiss_product->is_type( 'variable' ) ) : ?>
							<a class="btn product__btn" href="<?php echo esc_url( $weiss_link ); ?>">В корзину</a>
						<?php else : ?>
							<button class="btn product__btn" type="button"
							        data-weiss-add="<?php echo esc_attr( $weiss_product->get_id() ); ?>"
							        data-weiss-palette="<?php echo esc_attr( $weiss_palette ); ?>"
							        data-weiss-code="<?php echo esc_attr( $weiss_code ); ?>"
							        data-weiss-url="<?php echo esc_url( $weiss_link ); ?>">В корзину</button>
						<?php endif; ?>
					</div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php
get_footer();
