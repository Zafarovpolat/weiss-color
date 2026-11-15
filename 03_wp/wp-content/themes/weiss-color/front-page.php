<?php
/**
 * Главная (М10, п.9.1): герой, колеровка онлайн, преимущества.
 */

$weiss_shop = get_permalink( wc_get_page_id( 'shop' ) );
$weiss_visualizer = weiss_page_url( 'visualizer' );

get_header();
?>

<section class="hero" aria-labelledby="hero-title">
	<?php
	echo weiss_media_img( 'hero_bg', 'img/hero-bg.jpg', 2565, 811, array( // phpcs:ignore WordPress.Security.EscapeOutput
		'class'           => 'hero__bg',
		'alt'             => '',
		'aria-hidden'     => 'true',
		'fetchpriority'   => 'high',
		'decoding'        => 'async',
	) );
	?>
	<div class="container hero__inner">
		<span class="hero__product-wrapper">
			<?php
			echo weiss_media_img( 'hero_can', 'img/hero-can.png', 317, 468, array( // phpcs:ignore WordPress.Security.EscapeOutput
				'class'         => 'hero__product',
				'alt'           => weiss_opt( 'hero_can_alt' ),
				'decoding'      => 'async',
				'fetchpriority' => 'high',
			) );
			?>
		</span>
		<div class="hero__copy">
			<p class="hero__eyebrow"><?php echo esc_html( weiss_opt( 'hero_eyebrow' ) ); ?></p>
			<h1 class="hero__title" id="hero-title"><?php echo esc_html( weiss_opt( 'hero_title' ) ); ?></h1>
			<p class="hero__lead"><?php echo esc_html( weiss_opt( 'hero_lead' ) ); ?></p>
			<a class="btn btn--brown" href="<?php echo esc_url( weiss_opt( 'hero_url' ) ?: $weiss_shop ); ?>">
				Подробнее
				<svg class="btn__arrow" viewBox="0 0 52 15" fill="none" aria-hidden="true"><path d="M0 6h44v3H0zM41 0l11 7.5L41 15z" fill="currentColor"/></svg>
			</a>
		</div>
	</div>
</section>

<section class="coloring" aria-labelledby="coloring-title">
	<div class="coloring__inner">
		<div class="coloring__copy">
			<h2 class="coloring__title" id="coloring-title"><?php echo esc_html( weiss_opt( 'coloring_title' ) ); ?></h2>
			<div class="coloring__body">
				<p class="coloring__text"><?php echo esc_html( weiss_opt( 'coloring_text' ) ); ?></p>
				<a class="btn btn--teal" href="<?php echo esc_url( weiss_opt( 'coloring_url' ) ?: $weiss_visualizer ); ?>">
					Подобрать цвет
					<svg class="btn__arrow" viewBox="0 0 52 15" fill="none" aria-hidden="true"><path d="M0 6h44v3H0zM41 0l11 7.5L41 15z" fill="currentColor"/></svg>
				</a>
			</div>
		</div>

		<figure class="coloring__fan">
			<picture>
				<source media="(max-width: 768px)" srcset="<?php echo esc_url( weiss_media_url( 'coloring_fan_mobile', 'img/coloring-fan-mobile.png' ) ); ?>">
				<?php
				echo weiss_media_img( 'coloring_fan', 'img/coloring-fan.png', 1137, 393, array( // phpcs:ignore WordPress.Security.EscapeOutput
					'alt'         => '',
					'aria-hidden' => 'true',
					'loading'     => 'lazy',
					'decoding'    => 'async',
				) );
				?>
			</picture>
		</figure>

		<figure class="coloring__photo">
			<?php
			echo weiss_media_img( 'coloring_scene', 'img/coloring-scene.jpg', 1038, 385, array( // phpcs:ignore WordPress.Security.EscapeOutput
				'alt'      => weiss_opt( 'coloring_scene_alt' ),
				'loading'  => 'lazy',
				'decoding' => 'async',
			) );
			?>
		</figure>
	</div>
</section>

<section class="benefits" aria-labelledby="benefits-title">
	<div class="container benefits__inner">
		<h2 class="benefits__heading" id="benefits-title"><?php echo weiss_opt_html( 'benefits_heading' ); ?></h2>

		<ul class="benefits__list">
			<li class="benefits__item">
				<span class="benefits__num" aria-hidden="true">01</span>
				<h3 class="benefits__title"><?php echo esc_html( weiss_opt( 'benefit_1_title' ) ); ?></h3>
				<p class="benefits__text"><?php echo weiss_opt_html( 'benefit_1_text' ); ?></p>
			</li>
			<li class="benefits__item">
				<span class="benefits__num" aria-hidden="true">02</span>
				<h3 class="benefits__title"><?php echo esc_html( weiss_opt( 'benefit_2_title' ) ); ?></h3>
				<p class="benefits__text"><?php echo weiss_opt_html( 'benefit_2_text' ); ?></p>
			</li>
			<li class="benefits__item">
				<span class="benefits__num" aria-hidden="true">03</span>
				<h3 class="benefits__title"><?php echo esc_html( weiss_opt( 'benefit_3_title' ) ); ?></h3>
				<p class="benefits__text"><?php echo weiss_opt_html( 'benefit_3_text' ); ?></p>
			</li>
			<li class="benefits__item">
				<span class="benefits__num" aria-hidden="true">04</span>
				<h3 class="benefits__title"><?php echo esc_html( weiss_opt( 'benefit_4_title' ) ); ?></h3>
				<p class="benefits__text"><?php echo weiss_opt_html( 'benefit_4_text' ); ?></p>
			</li>
		</ul>
	</div>
</section>

<?php
get_footer();
