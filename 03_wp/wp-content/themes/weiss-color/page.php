<?php
/**
 * Обычная страница: контент из редактора в общей колонке.
 * Экраны с собственной вёрсткой получают отдельные шаблоны page-<slug>.php.
 */
$weiss_screen = weiss_screen();
weiss_set_main_class( weiss_main_class_default() );
get_header();

/* Корзина и оформление занимают всю ширину main: боковые поля задаёт
   сам экран (.cart-page / .checkout-page). */
if ( in_array( $weiss_screen, array( 'cart', 'checkout' ), true ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
} else {
	?>
	<div class="container">
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	</div>
	<?php
}

get_footer();
