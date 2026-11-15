<?php
/**
 * Подключение стилей и скриптов этапа 1 к шаблонам.
 *
 * Карта повторяет статические страницы 02_верстка: базовый набор
 * (reset → variables → base → layout → components → responsive → modals)
 * плюс файлы конкретного экрана. Версия — время изменения файла,
 * поэтому правка CSS не требует ручного сброса кэша.
 */

function weiss_file_version( $rel ) {
	$path = WEISS_DIR . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : WEISS_VERSION;
}

function weiss_enqueue_assets() {
	$base = array( 'reset', 'variables', 'base', 'layout', 'components', 'responsive', 'modals' );

	$prev = false;
	foreach ( $base as $name ) {
		$handle = 'weiss-' . $name;
		wp_enqueue_style( $handle, WEISS_URI . '/css/' . $name . '.css', $prev ? array( $prev ) : array(), weiss_file_version( 'css/' . $name . '.css' ) );
		$prev = $handle;
	}

	$screen = weiss_screen();
	$extra  = array(
		'index'            => array(),
		'404'              => array( 'cart', 'error' ),
		'about'            => array( 'cart', 'about' ),
		'cart'             => array( 'catalog', 'cart' ),
		'catalog'          => array( 'catalog' ),
		'checkout'         => array( 'cart', 'checkout' ),
		'color'            => array( 'catalog', 'color' ),
		'coloring-guide'   => array( 'coloring-guide' ),
		'contacts'         => array( 'catalog', 'checkout', 'contacts' ),
		'invoice'          => array( 'checkout', 'invoice' ),
		'legal'            => array( 'cart', 'legal' ),
		'order-success'    => array( 'cart', 'success' ),
		'palette-selector' => array( 'palette-selector' ),
		'palettes'         => array( 'catalog', 'palettes' ),
		'payment-card'     => array( 'payment-card' ),
		'payment'          => array( 'checkout', 'payment' ),
		'product'          => array( 'catalog', 'product' ),
		'sampler'          => array( 'cart', 'sampler' ),
		'shades'           => array( 'catalog', 'shades' ),
		'visualizer'       => array( 'catalog', 'visualizer', 'coloring-guide' ),
		'where-to-buy'     => array( 'cart', 'where-to-buy' ),
	);

	$files = isset( $extra[ $screen ] ) ? $extra[ $screen ] : array();

	/* Экран «Спасибо за заказ» — тот же checkout, но со своими стилями. */
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$files[] = 'success';
	}
	$last = $prev;
	foreach ( $files as $name ) {
		$last = 'weiss-' . $name;
		wp_enqueue_style( $last, WEISS_URI . '/css/' . $name . '.css', array( $prev ), weiss_file_version( 'css/' . $name . '.css' ) );
	}

	/* wp.css — только те правила, которых нет в статическом макете: cookie-панель,
	   ответы WooCommerce, поиск по оттенкам, оплата по ссылке. Подключается
	   последним, поэтому остальные css/*.css можно держать побайтовой копией
	   02_верстка/css. */
	wp_enqueue_style( 'weiss-wp', WEISS_URI . '/css/wp.css', array( $last ), weiss_file_version( 'css/wp.css' ) );

	/* Демо-масштаб и подгонка полотна по высоте окна — тот же js/zoom-mode.js,
	   который статические страницы 02_верстка подключают в <head>. Без него
	   на 2560 страница выше окна и прокручивается, тогда как на Vercel
	   ужимается до одного экрана. */
	wp_enqueue_script( 'weiss-zoom-mode', WEISS_URI . '/js/zoom-mode.js', array(), weiss_file_version( 'js/zoom-mode.js' ), false );

	/* Скрипты: общие — всем, остальные по экрану. */
	$js = array(
		'main'       => array(),
		'validation' => array(),
		'phone-mask' => array(),
		'cookie'     => array(),
	);

	$per_screen = array(
		'catalog'          => array( 'catalog', 'wc-catalog', 'wc-cart' ),
		'product'          => array( 'wc-cart', 'wc-product' ),
		'color'            => array( 'ui', 'wc-cart' ),
		'palettes'         => array( 'ui' ),
		'shades'           => array( 'ui' ),
		'visualizer'       => array( 'palette', 'ace-colors', 'visualizer', 'wc-cart' ),
		'palette-selector' => array( 'palette' ),
		'sampler'          => array( 'ui', 'wc-cart' ),
		'cart'             => array( 'wc-cart-page' ),
		'checkout'         => array( 'checkout' ),
		'contacts'         => array( 'contacts' ),
		'invoice'          => array( 'invoice' ),
		'payment-card'     => array( 'payment-card' ),
		'payment'          => array( 'payment' ),
	);

	if ( isset( $per_screen[ $screen ] ) ) {
		foreach ( $per_screen[ $screen ] as $name ) {
			$js[ $name ] = array();
		}
	}

	$order = array( 'main', 'validation', 'phone-mask', 'ui', 'catalog', 'wc-catalog', 'wc-cart', 'wc-product', 'wc-cart-page', 'palette', 'ace-colors', 'visualizer', 'cart', 'checkout', 'contacts', 'invoice', 'payment-card', 'payment', 'cookie' );
	foreach ( $order as $name ) {
		if ( ! isset( $js[ $name ] ) ) {
			continue;
		}
		$handle = 'weiss-' . $name;
		wp_enqueue_script( $handle, WEISS_URI . '/js/' . $name . '.js', array(), weiss_file_version( 'js/' . $name . '.js' ), true );
		wp_script_add_data( $handle, 'strategy', 'defer' );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	/* Оттенки для визуализатора грузятся по палитре, а не все 24 196 сразу. */
	if ( isset( $js['visualizer'] ) ) {
		wp_localize_script( 'weiss-visualizer', 'weissViz', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'weiss-colors' ),
			'palette' => 'ACE Paint Colors for Your Life',
		) );
	}

	/* Адреса экранов для переходов из скриптов (меню, селектор палитры). */
	wp_localize_script( 'weiss-main', 'weissNav', array(
		'catalog' => get_permalink( wc_get_page_id( 'shop' ) ),
		'shades'  => weiss_page_url( 'shades' ),
		'cart'    => wc_get_cart_url(),
		'wcAjax'  => add_query_arg( 'wc-ajax', 'add_to_cart', home_url( '/' ) ),
	) );

	/* Формы контактов и звонка сохраняют обращение на сервере (ТЗ, п.9.3). */
	wp_localize_script( 'weiss-main', 'weissForms', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'weiss-lead' ),
	) );

	/* Корзине нужен адрес admin-ajax и одноразовый ключ для запросов. */
	if ( isset( $js['wc-cart-page'] ) ) {
		wp_localize_script( 'weiss-wc-cart-page', 'weissCart', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'weiss-cart' ),
		) );
	}

	/* Оформление заказа отправляет форму в штатный обработчик WooCommerce. */
	if ( isset( $js['checkout'] ) ) {
		wp_localize_script( 'weiss-checkout', 'weissCheckout', array(
			'endpoint'     => add_query_arg( 'wc-ajax', 'checkout', home_url( '/' ) ),
			'noPayment'    => 'Выберите способ оплаты.',
			'placeError'   => 'Не удалось оформить заказ. Попробуйте ещё раз.',
			'submitLabel'  => 'Подтвердить заказ',
		) );
	}
}

/**
 * Трекинг источника заказа WooCommerce ставит cookie sourcebuster сразу
 * при заходе, до согласия на аналитику (ТЗ, п.9.5). Отключаем его:
 * необязательные cookie включаются только выбором посетителя.
 */
function weiss_dequeue_attribution() {
	wp_dequeue_script( 'sourcebuster-js' );
	wp_dequeue_script( 'wc-order-attribution' );
}
add_action( 'wp_enqueue_scripts', 'weiss_dequeue_attribution', 20 );

/**
 * Своя логика формы вместо checkout.js WooCommerce: скрипт ядра ищет
 * разметку блоков и перерисовывает итоги в собственном каркасе.
 */
function weiss_dequeue_checkout_scripts() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}
	wp_dequeue_script( 'wc-checkout' );
	wp_dequeue_script( 'wc-address-i18n' );
	wp_dequeue_script( 'wc-country-select' );
}
add_action( 'wp_enqueue_scripts', 'weiss_dequeue_checkout_scripts', 20 );
add_action( 'wp_enqueue_scripts', 'weiss_enqueue_assets' );

/** Предзагрузка двух начертаний, которыми набран первый экран. */
function weiss_preload_fonts() {
	?>
	<meta name="theme-color" content="#ffffff">
	<link rel="preload" as="font" type="font/woff2" href="<?php echo esc_url( WEISS_URI . '/assets/fonts/montserrat-cyrillic-600-normal.woff2' ); ?>" crossorigin>
	<link rel="preload" as="font" type="font/woff2" href="<?php echo esc_url( WEISS_URI . '/assets/fonts/montserrat-cyrillic-400-normal.woff2' ); ?>" crossorigin>
	<?php
}
add_action( 'wp_head', 'weiss_preload_fonts', 1 );
