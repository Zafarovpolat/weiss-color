<?php
/**
 * Запасной шаблон: используется, когда для запроса нет своего файла.
 */
get_header();
?>
<div class="container">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1>Ничего не найдено</h1>
	<?php endif; ?>
</div>
<?php
get_footer();
