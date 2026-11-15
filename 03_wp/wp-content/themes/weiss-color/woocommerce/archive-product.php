<?php
/**
 * Каталог товаров (М15, п.4.1–4.3): полоса экрана, фильтр, сортировка,
 * сетка карточек и постраничная навигация.
 */

defined( 'ABSPATH' ) || exit;

$weiss_term   = is_product_taxonomy() ? get_queried_object() : null;
$weiss_title  = $weiss_term instanceof WP_Term ? $weiss_term->name : 'Каталог';
$weiss_total  = (int) $GLOBALS['wp_query']->found_posts;
$weiss_paged  = max( 1, (int) get_query_var( 'paged' ) );

$weiss_selected = isset( $_GET['category'] ) ? (array) wp_unslash( $_GET['category'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
$weiss_selected = array_map( 'sanitize_title', $weiss_selected );
$weiss_orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'popularity'; // phpcs:ignore WordPress.Security.NonceVerification
$weiss_min      = isset( $_GET['min_price'] ) ? (float) wp_unslash( $_GET['min_price'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$weiss_max      = isset( $_GET['max_price'] ) ? (float) wp_unslash( $_GET['max_price'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

/* Порядок как в макете М15: он совпадает с порядком создания категорий.
   Сортировка по имени перемешивала «наружные» и «мебель» между собой. */
$weiss_cats = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'orderby'    => 'term_id',
	'order'      => 'ASC',
) );

/* Служебная категория WooCommerce не должна попадать в подбор продукции:
   в макете М15 её нет, и лишний пункт сдвигал весь фильтр. */
if ( ! is_wp_error( $weiss_cats ) ) {
	$weiss_cats = array_values( array_filter( $weiss_cats, function ( $weiss_term ) {
		return 'uncategorized' !== $weiss_term->slug;
	} ) );
}

$weiss_sort_labels = array(
	'popularity' => 'по популярности',
	'price'      => 'по возрастанию цены',
	'price-desc' => 'по убыванию цены',
	'date'       => 'по новинкам',
);

$weiss_shop_url = get_permalink( wc_get_page_id( 'shop' ) );

weiss_set_main_class( '' );
get_header();
?>

<section class="page-head">
	<div class="container page-head__inner">
		<div>
			<nav class="breadcrumbs" aria-label="Хлебные крошки">
				<ol class="breadcrumbs__list">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
					<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
					<?php if ( $weiss_term instanceof WP_Term ) : ?>
						<li><a href="<?php echo esc_url( $weiss_shop_url ); ?>">Каталог</a></li>
						<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
						<li aria-current="page"><?php echo esc_html( $weiss_term->name ); ?></li>
					<?php else : ?>
						<li aria-current="page">Каталог</li>
					<?php endif; ?>
				</ol>
			</nav>
			<h1 class="page-head__title"><?php echo esc_html( $weiss_title ); ?></h1>
		</div>

		<ul class="features">
			<li class="features__item">
				<img src="<?php echo esc_url( weiss_asset( 'icons/leaf.svg' ) ); ?>" width="52" height="60" alt="" aria-hidden="true">
				<span>Безопасные составы</span>
			</li>
			<li class="features__item">
				<img src="<?php echo esc_url( weiss_asset( 'icons/shield-check.svg' ) ); ?>" width="51" height="60" alt="" aria-hidden="true">
				<span>Высокое качество</span>
			</li>
			<li class="features__item">
				<img src="<?php echo esc_url( weiss_asset( 'icons/truck.svg' ) ); ?>" width="117" height="60" alt="" aria-hidden="true">
				<span>Быстрая доставка</span>
			</li>
		</ul>
	</div>
</section>

<section class="catalog container" aria-label="Каталог продукции">
	<aside class="filter" aria-labelledby="filter-title">
		<form class="filter__form" method="get" action="<?php echo esc_url( $weiss_shop_url ); ?>">
			<h2 class="filter__title" id="filter-title">Подбор продукции</h2>

			<fieldset class="filter__group">
				<legend class="visually-hidden">Категория продукции</legend>
				<ul class="filter__list">
					<li>
						<label class="check">
							<input type="checkbox" name="category[]" value="all" <?php checked( empty( $weiss_selected ) || in_array( 'all', $weiss_selected, true ) ); ?>>
							<span class="check__box" aria-hidden="true"></span>
							<span class="check__label">Вся продукция</span>
						</label>
					</li>
					<?php foreach ( $weiss_cats as $weiss_cat ) : ?>
						<li>
							<label class="check">
								<input type="checkbox" name="category[]" value="<?php echo esc_attr( $weiss_cat->slug ); ?>"
									<?php checked( in_array( $weiss_cat->slug, $weiss_selected, true ) ); ?>>
								<span class="check__box" aria-hidden="true"></span>
								<span class="check__label"><?php echo esc_html( $weiss_cat->name ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</fieldset>

			<?php
			/* Границы ползунка — по реальным ценам каталога, а не по числам
			   макета: иначе шкала показывает 700–12 000 при товарах от 2 900. */
			$weiss_bounds     = weiss_price_bounds();
			$weiss_slider_min = (int) floor( $weiss_bounds['min'] );
			$weiss_slider_max = (int) ceil( $weiss_bounds['max'] );
			$weiss_slider_max = $weiss_slider_max > $weiss_slider_min ? $weiss_slider_max : $weiss_slider_min + 1000;
			$weiss_view_min   = $weiss_min ? $weiss_min : $weiss_slider_min;
			$weiss_view_max   = $weiss_max ? $weiss_max : $weiss_slider_max;
			?>
			<div class="filter__price">
				<h3 class="filter__subtitle" id="price-label">Цена</h3>

				<div class="range" role="group" aria-labelledby="price-label">
					<span class="range__track" aria-hidden="true"></span>
					<span class="range__fill" aria-hidden="true"></span>
					<input type="range" min="<?php echo esc_attr( $weiss_slider_min ); ?>" max="<?php echo esc_attr( $weiss_slider_max ); ?>"
					       step="100" value="<?php echo esc_attr( $weiss_view_min ); ?>" aria-label="Цена от" data-range-min>
					<input type="range" min="<?php echo esc_attr( $weiss_slider_min ); ?>" max="<?php echo esc_attr( $weiss_slider_max ); ?>"
					       step="100" value="<?php echo esc_attr( $weiss_view_max ); ?>" aria-label="Цена до" data-range-max>
				</div>

				<p class="filter__prices">
					<span>от <output><?php echo esc_html( weiss_price_number( $weiss_view_min ) ); ?></output> ₽</span>
					<span>до <output><?php echo esc_html( weiss_price_number( $weiss_view_max ) ); ?></output> ₽</span>
				</p>

				<input type="hidden" name="min_price" value="<?php echo esc_attr( $weiss_min ); ?>" data-price-input="min"
				       <?php echo $weiss_min > 0 ? 'data-touched="1"' : 'disabled'; ?>>
				<input type="hidden" name="max_price" value="<?php echo esc_attr( $weiss_max ); ?>" data-price-input="max"
				       <?php echo $weiss_max > 0 ? 'data-touched="1"' : 'disabled'; ?>>
			</div>

			<input type="hidden" name="orderby" value="<?php echo esc_attr( $weiss_orderby ); ?>" data-orderby-input>

			<button class="btn btn--teal filter__apply" type="submit">Применить</button>
			<a class="btn filter__reset" href="<?php echo esc_url( $weiss_shop_url ); ?>">Сбросить</a>
		</form>
	</aside>

	<div class="catalog__main">
		<div class="sort">
			<span class="sort__label" id="sort-label">Сортировать:</span>
			<div class="sort__dropdown" data-sort>
				<button class="sort__trigger" type="button"
				        aria-haspopup="listbox" aria-expanded="false"
				        aria-labelledby="sort-label sort-value">
					<span class="sort__value" id="sort-value"><?php echo esc_html( isset( $weiss_sort_labels[ $weiss_orderby ] ) ? $weiss_sort_labels[ $weiss_orderby ] : 'по популярности' ); ?></span>
					<svg class="sort__chevron" viewBox="0 0 15 11" fill="none" aria-hidden="true">
						<path d="M1 1.5 7.5 9 14 1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
				<ul class="sort__list" role="listbox" aria-labelledby="sort-label" hidden>
					<?php foreach ( $weiss_sort_labels as $weiss_value => $weiss_label ) : ?>
						<li class="sort__option" role="option" tabindex="-1"
						    aria-selected="<?php echo $weiss_value === $weiss_orderby ? 'true' : 'false'; ?>"
						    data-value="<?php echo esc_attr( $weiss_value ); ?>"><?php echo esc_html( $weiss_label ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<?php if ( have_posts() ) : ?>
			<ul class="products">
				<?php
				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}
				?>
			</ul>
		<?php else : ?>
			<div class="catalog__empty">
				<p>По выбранным условиям товаров не нашлось. Измените фильтр или сбросьте его.</p>
				<a class="btn btn--outline" href="<?php echo esc_url( $weiss_shop_url ); ?>">Сбросить фильтр</a>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php weiss_pagination(); ?>

<?php
get_footer();
