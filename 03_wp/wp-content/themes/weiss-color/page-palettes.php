<?php
/**
 * Колеровка цвета (М18): обзор всех 28 палитр реестра.
 * Разметка карточек — из 02_верстка/pages/palettes.html, содержимое — из
 * таблицы цветов: настоящие названия палитр, число записей и пагинация.
 */
weiss_set_main_class( 'palettes-page' );
get_header();

$weiss_palettes = weiss_color_palettes_active();
$weiss_per_page = 8;
$weiss_page     = max( 1, (int) ( $_GET['palette_page'] ?? 1 ) );
$weiss_total    = count( $weiss_palettes );
$weiss_pages    = max( 1, (int) ceil( $weiss_total / $weiss_per_page ) );
$weiss_page     = min( $weiss_page, $weiss_pages );
$weiss_slice    = array_slice( $weiss_palettes, ( $weiss_page - 1 ) * $weiss_per_page, $weiss_per_page );

$weiss_brand_images = array(
	'ACE Paint Colors for Your Life'       => array( 'img/palette-ace.png', 'Палитра красок Ace Paint' ),
	'Little Greene Colours of England'     => array( 'img/palette-little.png', 'Веер палитры Little Greene' ),
	'Little Greene Colour Scales'          => array( 'img/palette-little.png', 'Веер палитры Little Greene' ),
	'NCS Index 2050'                       => array( 'img/palette-ncs.png', 'Веер палитры NCS Index' ),
	'Benjamin Moore Color Preview'         => array( 'img/palette-benjamin.png', 'Веер палитры Benjamin Moore Preview' ),
	'Benjamin Moore Historical Colors'     => array( 'img/palette-benjamin.png', 'Веер палитры Benjamin Moore' ),
	'Benjamin Moore Off-White Colors'      => array( 'img/palette-benjamin.png', 'Веер палитры Benjamin Moore' ),
	'Farrow&Ball Colours'                  => array( 'img/palette-farrow.png', 'Веер палитры Farrow & Ball' ),
	'RAL Design D2'                        => array( 'img/palette-ral.png', 'Веер палитры RAL Design' ),
	'Ral Effect'                           => array( 'img/palette-ral.png', 'Веер палитры RAL Effect' ),
	'RAL 840HR'                            => array( 'img/palette-ral.png', 'Веер палитры RAL Classic' ),
);
?>
<div class="palettes-head container">
	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<ol class="breadcrumbs__list">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a></li>
			<li class="breadcrumbs__sep" aria-hidden="true">&gt;</li>
			<li aria-current="page">Колеровка цвета</li>
		</ol>
	</nav>
</div>

<div class="palettes-main container">
	<div class="palettes-inner">
		<h1 class="palettes-title">Обзор палитр</h1>

		<?php if ( ! $weiss_palettes ) : ?>
			<p class="palettes-empty">Каталог цветов ещё не загружен.</p>
		<?php else : ?>
			<ul class="palettes-grid" aria-label="Палитры колеровки">
				<?php foreach ( $weiss_slice as $weiss_palette ) : ?>
					<?php $weiss_name = $weiss_palette->palette; ?>
					<li>
						<a class="palette-card" href="<?php echo esc_url( weiss_shades_url( $weiss_name ) ); ?>">
							<span class="palette-card__media">
								<?php if ( isset( $weiss_brand_images[ $weiss_name ] ) ) : ?>
									<img src="<?php echo esc_url( weiss_asset( $weiss_brand_images[ $weiss_name ][0] ) ); ?>" width="431" height="328"
										alt="<?php echo esc_attr( $weiss_brand_images[ $weiss_name ][1] ); ?>" loading="lazy" decoding="async">
								<?php else : ?>
									<span class="palette-mosaic" aria-hidden="true">
										<?php foreach ( weiss_color_preview_hexes( $weiss_name, 6 ) as $weiss_hex ) : ?>
											<span style="background:<?php echo esc_attr( $weiss_hex ); ?>"></span>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
							</span>
							<span class="palette-card__label"><?php echo esc_html( $weiss_name ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php
	weiss_colors_pagination(
		$weiss_page,
		$weiss_pages,
		function ( $page ) {
			return add_query_arg( 'palette_page', $page, weiss_page_url( 'palettes' ) );
		},
		'Страницы обзора палитр'
	);
	?>
</div>
<?php
get_footer();
