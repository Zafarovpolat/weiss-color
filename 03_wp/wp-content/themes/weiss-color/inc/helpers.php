<?php
/**
 * Хелперы темы: адреса ассетов, ссылки на страницы, классы body/main, счётчик корзины.
 */

/**
 * Текущий экран темы. Ключи совпадают с именами CSS-файлов этапа 1
 * (catalog, product, cart, checkout, …), чтобы карта подключений была одна.
 */
function weiss_screen() {
	static $screen = null;
	if ( null !== $screen ) {
		return $screen;
	}

	if ( is_404() ) {
		$screen = '404';
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$screen = 'catalog';
	} elseif ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		$screen = 'catalog';
	} elseif ( function_exists( 'is_product' ) && is_product() ) {
		$screen = 'product';
	} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
		$screen = 'cart';
	} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$screen = 'checkout';
	} elseif ( is_front_page() ) {
		$screen = 'index';
	} elseif ( is_page() ) {
		/* Все страницы на шаблоне «Правовой документ» (ТЗ 9.4) — один экран:
		   им нужны legal.css и та же высота полотна. */
		if ( 'page-legal.php' === get_page_template_slug( get_queried_object_id() ) ) {
			$screen = 'legal';
		} else {
			$slug   = get_post_field( 'post_name', get_queried_object_id() );
			$screen = $slug ? $slug : 'page';
		}
	} else {
		$screen = 'page';
	}

	return $screen;
}

/**
 * Классы body по экрану — перенесены из статической вёрстки 1:1,
 * на них завязаны правила высоты (page-fill / page-grow / cart-body).
 */
function weiss_body_classes( $classes ) {
	$map = array(
		'index'           => array( 'page-grow' ),
		'404'             => array( 'page-fill' ),
		'about'           => array( 'page-fill' ),
		'cart'            => array( 'page-fill', 'cart-body' ),
		'catalog'         => array( 'catalog-page' ),
		'checkout'        => array( 'page-fill' ),
		'color'           => array(),
		'coloring-guide'  => array( 'page-fill' ),
		'contacts'        => array( 'page-fill' ),
		'legal'           => array( 'page-fill' ),
		'order-success'   => array( 'page-fill' ),
		'palette-selector'=> array( 'page-fill' ),
		'palettes'        => array(),
		'product'         => array( 'page-grow' ),
		'sampler'         => array( 'page-fill' ),
		'shades'          => array(),
		'visualizer'      => array( 'page-fill' ),
		'where-to-buy'    => array( 'page-fill', 'page-grow' ),
	);

	$screen = weiss_screen();
	if ( ! empty( $map[ $screen ] ) ) {
		$classes = array_merge( $classes, $map[ $screen ] );
	}

	return $classes;
}
add_filter( 'body_class', 'weiss_body_classes' );

/** Класс тега <main>: шаблон задаёт его до вызова get_header(). */
function weiss_set_main_class( $class ) {
	$GLOBALS['weiss_main_class'] = (string) $class;
}

function weiss_main_class() {
	return isset( $GLOBALS['weiss_main_class'] ) ? $GLOBALS['weiss_main_class'] : '';
}

/** Класс тега <main> по экрану — из статических страниц 02_верстка. */
function weiss_main_class_default() {
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return 'success-page';
	}

	$map = array(
		'404'              => 'error-page',
		'about'            => 'about-page',
		'cart'             => 'cart-page',
		'checkout'         => 'checkout-page',
		'color'            => 'color-page',
		'coloring-guide'   => 'guide-page',
		'contacts'         => 'contacts-page',
		'order-success'    => 'success-page',
		'palette-selector' => 'selector-page',
		'palettes'         => 'palettes-page',
		'shades'           => 'shades-page',
		'sampler'          => 'sampler-page',
		'visualizer'       => 'viz-page',
	);

	$screen = weiss_screen();
	return isset( $map[ $screen ] ) ? $map[ $screen ] : '';
}

/** URL файла темы: weiss_asset('img/logo.svg'). */
function weiss_asset( $path ) {
	return WEISS_URI . '/assets/' . ltrim( $path, '/' );
}

/**
 * Пункты меню, назначенного на область темы (ТЗ 10.1). Пустой массив —
 * меню не назначено, шаблон печатает разметку из макета.
 */
function weiss_menu_items( $location ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return array();
	}

	$items = wp_get_nav_menu_items( $locations[ $location ] );
	if ( ! $items ) {
		return array();
	}

	$links = array();
	foreach ( $items as $item ) {
		if ( (int) $item->menu_item_parent ) {
			continue;
		}
		$links[] = array(
			'url'    => $item->url,
			'title'  => $item->title,
			'target' => $item->target,
		);
	}

	/* Меню собирает администратор в панели (ТЗ 3.1, 10.1): выводим любое
	   непустое меню, даже если пунктов меньше пяти. Кодовая навигация
	   нужна только когда меню не назначено вовсе. */
	if ( ! $links ) {
		return array();
	}

	return $links;
}

/** URL картинки из настроек: вложение или файл темы (ТЗ 9.1). */
function weiss_media_url( $key, $default_path ) {
	$id = absint( weiss_opt( $key ) );
	if ( $id ) {
		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( $url ) {
			return $url;
		}
	}

	return weiss_asset( $default_path );
}

/**
 * Картинка главной из настроек (ТЗ 9.1). Выбрано вложение — отдаём его,
 * иначе файл темы с размерами макета, чтобы разметка осталась прежней.
 *
 * @param string $key          Ключ настройки weiss_content (ID вложения).
 * @param string $default_path Путь к файлу темы.
 * @param int    $width        Ширина файла темы.
 * @param int    $height       Высота файла темы.
 * @param array  $attrs        Атрибуты тега img.
 */
function weiss_media_img( $key, $default_path, $width, $height, $attrs = array() ) {
	$id = absint( weiss_opt( $key ) );
	if ( $id && wp_attachment_is_image( $id ) ) {
		return wp_get_attachment_image( $id, 'full', false, $attrs );
	}

	$out = '<img src="' . esc_url( weiss_asset( $default_path ) ) . '" width="' . (int) $width . '" height="' . (int) $height . '"';
	foreach ( $attrs as $name => $value ) {
		if ( false === $value || null === $value ) {
			continue;
		}
		$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}

	return $out . '>';
}

/**
 * Адрес страницы по slug. Пока страница не создана — возвращает
 * предполагаемый адрес, чтобы ссылки не превращались в «#».
 */
function weiss_page_url( $slug ) {
	static $cache = array();
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$page = get_page_by_path( $slug );
	$url  = $page ? get_permalink( $page ) : home_url( '/' . trim( $slug, '/' ) . '/' );

	$cache[ $slug ] = $url;
	return $url;
}

/**
 * Демо-товар для ссылок, которые в макете ведут на карточку (М05):
 * на этапе 2 открываем первый опубликованный товар каталога.
 */
function weiss_demo_product_url() {
	static $url = null;
	if ( null !== $url ) {
		return $url;
	}

	$products = wc_get_products( array(
		'status'  => 'publish',
		'limit'   => 1,
		'orderby' => 'menu_order',
		'order'   => 'ASC',
	) );

	$url = $products ? get_permalink( $products[0]->get_id() ) : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) );
	return $url;
}

/**
 * Картинка карточки каталога: в макете банка 136×200, файл отдаётся 2x
 * (274×400), поэтому width/height заданы по макету, а srcset — по retina.
 */
function weiss_product_card_image( $product ) {
	$image_id = $product->get_image_id();
	$alt      = $product->get_name();

	if ( ! $image_id ) {
		return '<img src="' . esc_url( wc_placeholder_img_src( 'weiss-card' ) ) . '" width="137" height="200" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
	}

	$src  = wp_get_attachment_image_src( $image_id, 'weiss-card' );
	$src2 = wp_get_attachment_image_src( $image_id, 'weiss-card-2x' );
	if ( ! $src ) {
		return '';
	}

	$srcset = $src2 ? ' srcset="' . esc_url( $src[0] ) . ' 1x, ' . esc_url( $src2[0] ) . ' 2x"' : '';

	return '<img src="' . esc_url( $src[0] ) . '"' . $srcset . ' width="137" height="200" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
}

/** Количество товаров в корзине (0, если WooCommerce не активен). */function weiss_cart_count() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0;
	}
	return (int) WC()->cart->get_cart_contents_count();
}

/** Число в денежном виде макета: 3 500 (пробел как разделитель тысяч).
    Копейки показываются только когда они есть: ТЗ 7.2 требует точности
    до копеек, а макет рисует круглые суммы без дробной части. */
function weiss_price_number( $value ) {
	$value = (float) $value;
	$decimals = abs( $value - round( $value ) ) > 0.004 ? 2 : 0;
	return number_format( $value, $decimals, ',', ' ' );
}

/** Цена в карточке каталога: «от 3 500 руб.» (макет М15). */
function weiss_price_text( $value, $from = false ) {
	return ( $from ? 'от ' : '' ) . weiss_price_number( $value ) . ' руб.';
}

/** Цена на карточке товара: «3 900₽» без пробела (макет М05). */
function weiss_price_compact( $value ) {
	return weiss_price_number( $value ) . '₽';
}

/**
 * Минимум и максимум цены по товарам, которые видно в каталоге, — границы
 * ползунка фильтра. Цену товара с фасовками задают фасовки: собственная
 * _price родителя хранит минимум и верхнюю границу не показывает. Товары,
 * исключённые из каталога (пробник), в шкалу не попадают.
 */
function weiss_price_bounds() {
	static $bounds = null;
	if ( null !== $bounds ) {
		return $bounds;
	}

	global $wpdb;

	$hidden = "EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} wtr
		INNER JOIN {$wpdb->term_taxonomy} wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
		INNER JOIN {$wpdb->terms} wte ON wte.term_id = wtt.term_id
		WHERE wtr.object_id = p.ID AND wtt.taxonomy = 'product_visibility'
		AND wte.slug IN ( 'exclude-from-catalog', 'exclude-from-search' ) )";

	$own = "SELECT pm.meta_value + 0 AS price
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_price'
		WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value <> ''
		AND NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} pv
			WHERE pv.post_parent = p.ID AND pv.post_type = 'product_variation' AND pv.post_status = 'publish' )
		AND NOT {$hidden}";

	$variations = "SELECT vp.meta_value + 0 AS price
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->posts} pv ON pv.post_parent = p.ID AND pv.post_type = 'product_variation' AND pv.post_status = 'publish'
		INNER JOIN {$wpdb->postmeta} vp ON vp.post_id = pv.ID AND vp.meta_key = '_price'
		WHERE p.post_type = 'product' AND p.post_status = 'publish' AND vp.meta_value <> ''
		AND NOT {$hidden}";

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT MIN( price ) AS min_price, MAX( price ) AS max_price
		   FROM ( {$own} UNION ALL {$variations} ) wprices"
	);

	$min = $row && null !== $row->min_price ? (float) $row->min_price : 0;
	$max = $row && null !== $row->max_price ? (float) $row->max_price : 20000;

	$bounds = array(
		'min' => (int) ( floor( $min / 100 ) * 100 ),
		'max' => (int) ( ceil( $max / 100 ) * 100 ),
	);

	return $bounds;
}

/**
 * Пагинация каталога в разметке этапа 1:
 * <nav class="pagination pagination--catalog"> с ячейками .pagination__item.
 */
function weiss_pagination() {
	global $wp_query;

	if ( $wp_query->max_num_pages < 2 ) {
		return;
	}

	$links = paginate_links( array(
		'total'     => $wp_query->max_num_pages,
		'current'   => max( 1, get_query_var( 'paged' ) ),
		'mid_size'  => 2,
		'end_size'  => 1,
		'type'      => 'array',
		'prev_text' => 'Назад',
		'next_text' => 'Вперед',
	) );

	if ( empty( $links ) ) {
		return;
	}

	echo '<nav class="pagination pagination--catalog container" aria-label="Страницы каталога">';
	foreach ( $links as $link ) {
		echo str_replace( 'page-numbers', 'pagination__item', $link ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</nav>';
}
