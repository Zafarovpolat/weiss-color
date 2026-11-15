<?php
/**
 * Базовая настройка темы: поддержка WordPress и WooCommerce, чистка лишнего
 * вывода ядра, чтобы вёрстка этапа 1 оставалась 1:1 с макетами.
 */

function weiss_setup() {
	load_theme_textdomain( 'weiss', WEISS_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 52,
		'width'       => 186,
		'flex-height' => false,
		'flex-width'  => false,
	) );

	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 640,
		'single_image_width'    => 1200,
		'product_grid'          => array(
			'default_rows'    => 3,
			'min_rows'        => 1,
			'default_columns' => 3,
			'min_columns'     => 1,
			'max_columns'     => 4,
		),
	) );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary' => 'Основная навигация',
		'footer'  => 'Навигация в подвале',
	) );

	/* Карточка каталога: в макете банка 136×200 px. Отдаём два файла —
	   1x 137×200 и 2x 274×400: при srcset с дескриптором 1x ширина
	   картинки в layout считается по 1x-файлу, иначе она растягивается. */
	add_image_size( 'weiss-card', 137, 200, true );
	add_image_size( 'weiss-card-2x', 274, 400, true );
	add_image_size( 'weiss-hero', 1920, 1080, false );
}
add_action( 'after_setup_theme', 'weiss_setup' );

/** Тема не использует редактор блоков — не тянем его стили на фронт. */
function weiss_dequeue_block_assets() {
	if ( is_admin() ) {
		return;
	}
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'weiss_dequeue_block_assets', 20 );

/* Панель администратора занимает 32px над страницей: у залогиненного
   сравнение с макетом сразу расходится, а страница перестаёт влезать
   в один экран. На фронте её не выводим — в админку ведёт /wp-admin/. */
add_filter( 'show_admin_bar', '__return_false' );

/**
 * Чистка тестовых значений, оставшихся от проверок: неполное меню и
 * демонстрационные контакты. Выполняется один раз и только для точных
 * совпадений, поэтому реальные настройки не затрагивает.
 */
function weiss_cleanup_test_content() {
	if ( '1' === get_option( 'weiss_content_cleanup' ) ) {
		return;
	}

	$locations = get_nav_menu_locations();
	$changed   = false;
	foreach ( $locations as $location => $menu_id ) {
		if ( ! $menu_id ) {
			continue;
		}
		$items = wp_get_nav_menu_items( $menu_id );
		if ( is_array( $items ) && count( $items ) < 4 ) {
			$locations[ $location ] = 0;
			$changed                = true;
		}
	}
	if ( $changed ) {
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	$junk = array(
		'phone_label'     => array( '+79991112233' ),
		'email'           => array( 'atuin59354081@gmail.com' ),
		'seller_name'     => array( 'ыфыыф' ),
		'seller_address'  => array( 'Tashkent' ),
		'social_max'      => array( 'https://m.yandex.ru/video/preview/8120127023709832939' ),
		'social_telegram' => array( 'https://m.yandex.ru/video/preview/8120127023709832939' ),
		'social_whatsapp' => array( 'https://m.yandex.ru/video/preview/8120127023709832939' ),
	);

	$values = get_option( 'weiss_content', array() );
	if ( is_array( $values ) ) {
		$dirty = false;
		foreach ( $junk as $key => $matches ) {
			if ( isset( $values[ $key ] ) && in_array( (string) $values[ $key ], $matches, true ) ) {
				unset( $values[ $key ] );
				$dirty = true;
			}
		}
		if ( $dirty ) {
			update_option( 'weiss_content', $values );
		}
	}

	update_option( 'weiss_content_cleanup', '1', false );
}
add_action( 'init', 'weiss_cleanup_test_content', 3 );

/** Эмодзи-скрипты ядра не нужны и весят лишние килобайты. */
function weiss_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'weiss_disable_emoji' );

/** Убираем generator и короткие ссылки из <head>. */
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

/** WooCommerce ставит свои обёртки — у нас собственный каркас страниц. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/** Свои стили вместо стилей WooCommerce: файлы этапа 1 уже описывают вид. */
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
