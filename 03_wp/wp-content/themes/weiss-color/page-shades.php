<?php
/**
 * Список оттенков палитры (М20): настоящие цвета выбранной палитры,
 * поиск по коду и названию, пагинация по полному набору (ТЗ, п.5.4).
 */
weiss_set_main_class( 'shades-page' );

$weiss_palettes = weiss_color_palettes_active();
$weiss_default  = 'ACE Paint Colors for Your Life';
$weiss_palette  = isset( $_GET['palette'] ) ? wp_unslash( $_GET['palette'] ) : $weiss_default;
$weiss_palette  = sanitize_text_field( $weiss_palette );

$weiss_known = wp_list_pluck( $weiss_palettes, 'palette' );
if ( ! in_array( $weiss_palette, $weiss_known, true ) ) {
	/* Несуществующая палитра — это 404, а не молчаливая подмена первой:
	   иначе любая ссылка с опечаткой открывает чужую палитру с кодом 200. */
	status_header( 404 );
	nocache_headers();
	add_filter( 'pre_get_document_title', function () {
		return 'Палитра не найдена — ' . get_bloginfo( 'name' );
	} );
	get_header();
	?>
	<div class="shades-head container">
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<ol class="breadcrumbs__list">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
				<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
				<li><a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a></li>
			</ol>
		</nav>
	</div>
	<div class="container">
		<h1 class="shades-title">Палитра не найдена</h1>
		<p class="shades-empty">
			Выберите палитру в <a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">каталоге палитр</a>.
		</p>
	</div>
	<?php
	get_footer();
	return;
}

$weiss_search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$weiss_page   = max( 1, (int) ( $_GET['shade_page'] ?? 1 ) );
$weiss_list   = weiss_colors_query( array(
	'palette'  => $weiss_palette,
	'search'   => $weiss_search,
	'page'     => $weiss_page,
	'per_page' => 32,
) );

if ( $weiss_page > $weiss_list['max_pages'] ) {
	$weiss_page = $weiss_list['max_pages'];
	$weiss_list = weiss_colors_query( array(
		'palette'  => $weiss_palette,
		'search'   => $weiss_search,
		'page'     => $weiss_page,
		'per_page' => 32,
	) );
}

/* Своя страница каждого набора оттенков: заголовок вкладки и canonical
   с палитрой, иначе все палитры делят один адрес и одно название (ТЗ 11.7).
   Canonical отдаёт ядро — подменяем адрес страницы-шаблона своим. */
add_filter( 'pre_get_document_title', function () use ( $weiss_palette ) {
	return 'Оттенки ' . $weiss_palette . ' — ' . get_bloginfo( 'name' );
} );

add_filter( 'get_canonical_url', function () use ( $weiss_palette, $weiss_page ) {
	return weiss_shades_url( $weiss_palette, $weiss_page );
}, 20 );

get_header();
?>
<div class="shades-head container">
	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<ol class="breadcrumbs__list">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
			<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
			<li><a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a></li>
			<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
			<li aria-current="page"><?php echo esc_html( $weiss_palette ); ?></li>
		</ol>
	</nav>
</div>

<div class="shades-main container">
	<div class="shades-inner">
	<div class="shades-headline">
		<h1 class="shades-title">Оттенки палитры</h1>

		<div class="shades-toolbar">
			<p class="shades-count">
				<?php echo esc_html( $weiss_palette ); ?> —
				<strong><?php echo (int) $weiss_list['total']; ?></strong>
				<?php echo esc_html( weiss_colors_word( $weiss_list['total'], array( 'оттенок', 'оттенка', 'оттенков' ) ) ); ?>
			</p>

			<form class="shades-search" role="search" method="get" action="<?php echo esc_url( weiss_page_url( 'shades' ) ); ?>">
				<input type="hidden" name="palette" value="<?php echo esc_attr( $weiss_palette ); ?>">
				<label class="visually-hidden" for="shades-q">Поиск по коду или названию</label>
				<input class="shades-search__input" id="shades-q" type="search" name="q" value="<?php echo esc_attr( $weiss_search ); ?>" placeholder="Код или название">
				<button class="btn btn--brown shades-search__btn" type="submit">Найти</button>
			</form>
		</div>
	</div>

		<?php if ( $weiss_list['items'] ) : ?>
			<ul class="shades-grid" aria-label="Образцы оттенков">
				<?php foreach ( $weiss_list['items'] as $weiss_color ) : ?>
					<li>
						<a class="shade-card" href="<?php echo esc_url( weiss_color_url( $weiss_color->palette, $weiss_color->code ) ); ?>">
							<span class="shade-card__swatch" style="background:<?php echo esc_attr( $weiss_color->hex ); ?>"></span>
							<span class="shade-card__label"><?php echo esc_html( $weiss_color->code ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="shades-empty">
				<?php echo $weiss_search ? 'По запросу «' . esc_html( $weiss_search ) . '» ничего не найдено.' : 'В этой палитре пока нет доступных оттенков.'; ?>
			</p>
		<?php endif; ?>
	</div>

	<?php
	weiss_colors_pagination(
		$weiss_page,
		$weiss_list['max_pages'],
		function ( $page ) use ( $weiss_palette, $weiss_search ) {
			return weiss_shades_url( $weiss_palette, $page, $weiss_search );
		},
		'Страницы списка оттенков'
	);
	?>
</div>
<?php
get_footer();
