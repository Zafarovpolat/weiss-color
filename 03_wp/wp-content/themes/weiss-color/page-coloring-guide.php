<?php
/**
 * Как колеровать (М25). Разметка перенесена из 02_верстка/pages/coloring-guide.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( 'guide-page' );
get_header();

/* Товар, фасовка и цвет из карточки передаются в визуализатор (ТЗ, п. 6.1).
   Имена с префиксом: product и variation — публичные query var WooCommerce,
   без префикса WordPress отвечает на них 404. */
$weiss_map = array(
	'weiss_product'   => 'weiss_product',
	'weiss_variation' => 'weiss_variation',
	'palette'         => 'palette',
	'code'            => 'code',
);
$weiss_forward = array();
foreach ( $weiss_map as $weiss_key => $weiss_param ) {
	if ( isset( $_GET[ $weiss_param ] ) && '' !== $_GET[ $weiss_param ] ) { // phpcs:ignore WordPress.Security.NonceVerification
		$weiss_forward[ $weiss_key ] = sanitize_text_field( wp_unslash( $_GET[ $weiss_param ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
}
$weiss_viz_url = $weiss_forward
	? add_query_arg( $weiss_forward, weiss_page_url( 'visualizer' ) )
	: weiss_page_url( 'visualizer' );

$weiss_sampler_url = add_query_arg(
	array_filter( array(
		'palette' => $weiss_forward['palette'] ?? '',
		'code'    => $weiss_forward['code'] ?? '',
	) ),
	weiss_page_url( 'sampler' )
);
?>
<nav class="crumbs crumbs--overlay" aria-label="Хлебные крошки">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
	<a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a> &gt;
	<span aria-current="page">Как колеровать</span>
</nav>
<?php
get_template_part( 'template-parts/guide-card', null, array(
	'viz_url'     => $weiss_viz_url,
	'sampler_url' => $weiss_sampler_url,
) );
get_footer();
