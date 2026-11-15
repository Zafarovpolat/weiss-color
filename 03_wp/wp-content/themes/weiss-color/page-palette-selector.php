<?php
/**
 * Селектор палитры (М19): тот же каталог, что на обзоре М18, — все 28 палитр.
 * Скрытая в панели палитра сюда не попадает: её страница отдаёт 404.
 */
weiss_set_main_class( 'selector-page' );
get_header();

$weiss_palettes = weiss_color_palettes_active();
?>
<div class="selector-stage">
	<nav class="crumbs crumbs--overlay" aria-label="Хлебные крошки">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
		<a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a> &gt;
		<span aria-current="page">Подбор палитры</span>
	</nav>
	<div class="pselect is-open">
		<button class="pselect__control" type="button" aria-expanded="true"
			aria-haspopup="listbox" aria-controls="palette-options">
			<span class="visually-hidden">Выбранная палитра</span>
			<svg class="pselect__icon" viewBox="0 0 30 18" fill="none" aria-hidden="true">
				<path d="M2 16 L15 3 L28 16" stroke="#515257" stroke-width="3"
					stroke-linecap="square" stroke-linejoin="miter"/>
			</svg>
		</button>
		<ul class="pselect__list" id="palette-options" role="listbox"
			aria-label="Палитра колеровки">
			<?php foreach ( $weiss_palettes as $weiss_index => $weiss_palette ) : ?>
				<li class="pselect__option" role="option" aria-selected="<?php echo 0 === $weiss_index ? 'true' : 'false'; ?>" tabindex="0"
					data-href="<?php echo esc_url( weiss_shades_url( $weiss_palette->palette ) ); ?>"><?php echo esc_html( $weiss_palette->palette ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
<?php
get_footer();
