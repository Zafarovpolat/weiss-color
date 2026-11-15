<?php
/**
 * Интеграция WooCommerce: каркас витрины и настройки, которые отличаются
 * от дефолтных. Разметка каталога/товара — в woocommerce/*.
 */

/** Каталог: 6 товаров на страницу, 3 в ряд — как в сетке макета М15. */
add_filter( 'loop_shop_per_page', function () { return 6; } );
add_filter( 'loop_shop_columns', function () { return 3; } );

/** Заголовок и хлебные крошки на архиве рисует шаблон каталога, а не ядро. */
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
add_filter( 'woocommerce_show_page_title', '__return_false' );

/** Кнопка «В корзину» в карточке каталога — своя разметка (см. loop/content-product.php). */
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_open', 0 );

/** Обёртки карточки в каталоге рисует наш шаблон. */
remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );

/**
 * Фильтр каталога из панели «Подбор продукции»: категории и диапазон цены
 * приходят GET-параметрами (category[] и min_price/max_price).
 * Сортировку (orderby) WooCommerce обрабатывает сам — значения совпадают.
 */
function weiss_filter_catalog_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( 'product_cat' ) ) {
		return;
	}

	$cats = isset( $_GET['category'] ) ? (array) wp_unslash( $_GET['category'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$cats = array_values( array_filter( array_map( 'sanitize_title', $cats ), function ( $slug ) {
		return '' !== $slug && 'all' !== $slug;
	} ) );

	if ( $cats ) {
		$query->set( 'tax_query', array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $cats,
			),
		) );
	}
}
add_action( 'pre_get_posts', 'weiss_filter_catalog_query' );

/**
 * Пустой диапазон цены не должен доходить до штатного фильтра WooCommerce:
 * max_price=0 отсекает все товары. Свои поля тема отключает, но параметр
 * может прийти из адреса, поэтому снимаем его до обработки запроса.
 */
add_action( 'pre_get_posts', function ( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( 'product_cat' ) ) {
		return;
	}
	foreach ( array( 'min_price', 'max_price' ) as $key ) {
		if ( isset( $_GET[ $key ] ) && (float) wp_unslash( $_GET[ $key ] ) <= 0 ) { // phpcs:ignore WordPress.Security.NonceVerification
			unset( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
	}
}, 1 );

/**
 * Оформление идёт только через форму темы: она проверяет согласие,
 * реквизиты, цвет и колеровку. Маршруты Store API создавали заказ в обход
 * этих проверок, поэтому создание заказа через них закрыто (ТЗ 7.2, 11.3).
 * Store API держит несколько адресов: /wc/store/checkout, /wc/store/v1/checkout
 * и адреса конкретного заказа, поэтому закрываем по шаблону.
 */
function weiss_is_store_checkout_route( $route ) {
	return (bool) preg_match( '#/wc/store(?:/v\d+)?/checkout#', (string) $route );
}

add_filter( 'rest_endpoints', function ( $endpoints ) {
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( ! weiss_is_store_checkout_route( $route ) ) {
			continue;
		}

		foreach ( (array) $endpoints[ $route ] as $index => $handler ) {
			$methods = isset( $handler['methods'] ) ? $handler['methods'] : array();
			/* До нормализации core методы лежат строкой «GET, POST» или
			   массивом, после — ключами. Понимаем оба вида. */
			$methods = is_string( $methods ) ? explode( ',', $methods ) : (array) $methods;
			$methods = array_map( 'strtoupper', array_map( 'trim', $methods ) );
			if ( array_intersect( array( 'POST', 'PUT', 'PATCH' ), $methods ) ) {
				unset( $endpoints[ $route ][ $index ] );
			}
		}

		if ( empty( $endpoints[ $route ] ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
} );

/* Вторая линия: маршрут мог быть зарегистрирован после фильтра. */
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null !== $result ) {
		return $result;
	}

	$method = strtoupper( (string) $request->get_method() );
	if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		return $result;
	}

	if ( weiss_is_store_checkout_route( $request->get_route() ) ) {
		return new WP_Error( 'rest_no_route', 'Маршрут не найден.', array( 'status' => 404 ) );
	}

	return $result;
}, 10, 3 );

/* ТЗ 7.2: суммы считаются сервером с точностью до копеек. В настройках
   магазина стояли целые рубли, из-за чего скидка с некруглой цены
   округлялась. Показываем копейки только когда они ненулевые. */
add_filter( 'option_woocommerce_price_num_decimals', function () {
	return 2;
} );

add_filter( 'option_woocommerce_price_trim_zeros', function () {
	return 'yes';
} );

/** Продажа — только по России: регионы и тарифы заданы для РФ (ТЗ 7.4). */
add_filter( 'option_woocommerce_allowed_countries', function () {
	return 'specific';
} );

add_filter( 'option_woocommerce_specific_allowed_countries', function () {
	return array( 'RU' );
} );

/**
 * Диапазон цены (ТЗ 4.2): товар попадает в выдачу, если хотя бы один
 * доступный вариант стоит в диапазоне, а не только минимальная цена
 * товара. Поэтому проверяем цену и у самого товара, и у его вариаций.
 */
function weiss_catalog_price_sql( $clauses, $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return $clauses;
	}
	if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( 'product_cat' ) ) {
		return $clauses;
	}

	$min = isset( $_GET['min_price'] ) ? (float) wp_unslash( $_GET['min_price'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$max = isset( $_GET['max_price'] ) ? (float) wp_unslash( $_GET['max_price'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

	if ( $min <= 0 && $max <= 0 ) {
		return $clauses;
	}

	global $wpdb;

	if ( $min > 0 && $max > 0 ) {
		$range = $wpdb->prepare( 'pm.meta_value + 0 BETWEEN %f AND %f', $min, $max );
		$range_v = $wpdb->prepare( 'pmp.meta_value + 0 BETWEEN %f AND %f', $min, $max );
	} elseif ( $min > 0 ) {
		$range   = $wpdb->prepare( 'pm.meta_value + 0 >= %f', $min );
		$range_v = $wpdb->prepare( 'pmp.meta_value + 0 >= %f', $min );
	} else {
		$range   = $wpdb->prepare( 'pm.meta_value + 0 <= %f', $max );
		$range_v = $wpdb->prepare( 'pmp.meta_value + 0 <= %f', $max );
	}

	/* «Доступный» вариант — тот, что можно купить: наличие учитываем (ТЗ 4.2). */
	$own_stock = "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pms
		WHERE pms.post_id = {$wpdb->posts}.ID AND pms.meta_key = '_stock_status' AND pms.meta_value = 'outofstock' )";
	$var_stock = "NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pmsv
		WHERE pmsv.post_id = pv.ID AND pmsv.meta_key = '_stock_status' AND pmsv.meta_value = 'outofstock' )";

	/* У товара с вариантами диапазон проверяем только по доступным вариантам:
	   собственная _price родителя может остаться от прежнего набора. */
	$own = "NOT EXISTS ( SELECT 1 FROM {$wpdb->posts} pvo
		WHERE pvo.post_parent = {$wpdb->posts}.ID AND pvo.post_type = 'product_variation'
		AND pvo.post_status = 'publish' )
		AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm
		WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_price' AND pm.meta_value <> '' AND {$range} )
		AND {$own_stock}";

	$variations = "EXISTS ( SELECT 1 FROM {$wpdb->posts} pv
		INNER JOIN {$wpdb->postmeta} pmp ON pmp.post_id = pv.ID AND pmp.meta_key = '_price'
		WHERE pv.post_parent = {$wpdb->posts}.ID AND pv.post_type = 'product_variation'
		AND pv.post_status = 'publish' AND pmp.meta_value <> '' AND {$range_v} AND {$var_stock} )";

	$clauses['where'] .= " AND ( {$own} OR {$variations} )";

	return $clauses;
}
add_filter( 'posts_clauses', 'weiss_catalog_price_sql', 99999, 2 );

/**
 * ТЗ 4.2: на карточке цена «от» — минимальная доступная фасовка. Сортировка
 * «по убыванию цены» должна опираться на то же число. WooCommerce для DESC
 * берёт максимальную цену, поэтому товар с дорогой крупной фасовкой вставал
 * первым, хотя на витрине у него «от 3 500».
 */
function weiss_catalog_price_desc_sql( $clauses, $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return $clauses;
	}
	if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( 'product_cat' ) ) {
		return $clauses;
	}

	$requested = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'price-desc' !== $requested ) {
		return $clauses;
	}

	global $wpdb;

	$variation_min = "( SELECT MIN( pmp.meta_value + 0 ) FROM {$wpdb->posts} pv
		INNER JOIN {$wpdb->postmeta} pmp ON pmp.post_id = pv.ID AND pmp.meta_key = '_price'
		WHERE pv.post_parent = {$wpdb->posts}.ID AND pv.post_type = 'product_variation'
		AND pv.post_status = 'publish' AND pmp.meta_value <> ''
		AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pmsv
			WHERE pmsv.post_id = pv.ID AND pmsv.meta_key = '_stock_status' AND pmsv.meta_value = 'outofstock' ) )";

	$own_price = "( SELECT pm.meta_value + 0 FROM {$wpdb->postmeta} pm
		WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_price' LIMIT 1 )";

	$clauses['orderby'] = "COALESCE( {$variation_min}, {$own_price}, 0 ) DESC, {$wpdb->posts}.ID DESC";

	return $clauses;
}
add_filter( 'posts_clauses', 'weiss_catalog_price_desc_sql', 99999, 2 );

/**
 * Сортировка «по популярности» (ТЗ 4.2): сначала редактируемый приоритет
 * товара, при равенстве — количество проданных единиц.
 */
function weiss_catalog_popularity_sql( $clauses, $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return $clauses;
	}
	if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( 'product_cat' ) ) {
		return $clauses;
	}

	/* Каталог по умолчанию — «по популярности», как подписана сортировка
	   в шаблоне (ТЗ 4.2). Явный выбор другого порядка не трогаем. */
	$requested = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $requested && 'popularity' !== $requested ) {
		return $clauses;
	}

	global $wpdb;

	$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} wprio
		ON ( {$wpdb->posts}.ID = wprio.post_id AND wprio.meta_key = '_weiss_priority' )
		LEFT JOIN {$wpdb->prefix}wc_product_meta_lookup wlookup
		ON ( wlookup.product_id = {$wpdb->posts}.ID )";

	$clauses['orderby'] = "CAST( COALESCE( wprio.meta_value, 0 ) AS SIGNED ) DESC,
		CAST( COALESCE( wlookup.total_sales, 0 ) AS SIGNED ) DESC,
		{$wpdb->posts}.post_date DESC";

	return $clauses;
}
add_filter( 'posts_clauses', 'weiss_catalog_popularity_sql', 99999, 2 );

/**
 * Цена «от» — минимальная цена доступного к покупке варианта (ТЗ 4.2),
 * а не самого дешёвого в прайсе, если он распродан.
 */
function weiss_product_price_from( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}
	if ( ! $product->is_type( 'variable' ) ) {
		return $product->get_price();
	}

	$prices = array();
	foreach ( $product->get_children() as $child_id ) {
		$child = wc_get_product( $child_id );
		if ( $child && $child->is_purchasable() && $child->is_in_stock() && '' !== $child->get_price() ) {
			$prices[] = (float) $child->get_price();
		}
	}

	return $prices ? min( $prices ) : '';
}

/** Поле «Приоритет в каталоге» в карточке товара (ТЗ 4.2). */
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_text_input( array(
		'id'          => '_weiss_priority',
		'label'       => 'Приоритет в каталоге',
		'description' => 'Чем больше число, тем выше товар в сортировке «По популярности». При равенстве учитывается число продаж.',
		'desc_tip'    => true,
		'type'        => 'number',
		'custom_attributes' => array( 'step' => '1' ),
	) );
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	$priority = isset( $_POST['_weiss_priority'] ) ? (int) $_POST['_weiss_priority'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $post_id, '_weiss_priority', $priority );
} );

/** Колеровка, пробник и доплата за колеровку в карточке товара (ТЗ 4.5, 6.6). */
add_action( 'woocommerce_product_options_general_product_data', function () {
	woocommerce_wp_text_input( array(
		'id'                => '_weiss_tint_surcharge',
		'label'             => 'Доплата за колеровку, ₽',
		'description'       => 'Своя доплата для этого товара. Пусто — общая ставка группы из WooCommerce → Колеровка; 0 — колеровка без доплаты.',
		'desc_tip'          => true,
		'type'              => 'number',
		'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
	) );

	woocommerce_wp_select( array(
		'id'          => '_weiss_tinting',
		'label'       => 'Колеровка по каталогу',
		'description' => '«Нет» — товар продаётся только в базовом цвете, каталог оттенков для него закрыт.',
		'desc_tip'    => true,
		'options'     => array( '' => 'По карте совместимости', 'yes' => 'Да', 'no' => 'Нет' ),
	) );

	woocommerce_wp_select( array(
		'id'          => '_weiss_sampler',
		'label'       => 'Пробный образец 300 мл',
		'description' => 'Пробник покупается только с выбранным цветом; цена и наличие задаются как у обычного товара.',
		'desc_tip'    => true,
		'options'     => array( '' => 'Нет', 'yes' => 'Да' ),
	) );
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	$surcharge = isset( $_POST['_weiss_tint_surcharge'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['_weiss_tint_surcharge'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $post_id, '_weiss_tint_surcharge', ( '' !== $surcharge && is_numeric( $surcharge ) ) ? (string) max( 0, round( (float) $surcharge, 2 ) ) : '' );

	$tinting = sanitize_key( wp_unslash( $_POST['_weiss_tinting'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $post_id, '_weiss_tinting', in_array( $tinting, array( 'yes', 'no' ), true ) ? $tinting : '' );

	$sampler = 'yes' === ( $_POST['_weiss_sampler'] ?? '' ) ? 'yes' : ''; // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $post_id, '_weiss_sampler', $sampler );
} );

/** Та же доплата у каждой фасовки: своё значение важнее товара. */
add_action( 'woocommerce_product_after_variable_attributes', function ( $loop, $variation_data, $variation ) {
	$value = get_post_meta( $variation->ID, '_weiss_tint_surcharge', true );
	?>
	<div class="form-row form-row-full">
		<label for="weiss_tint_surcharge_<?php echo esc_attr( $loop ); ?>">
			Доплата за колеровку, ₽
			<input type="number" min="0" step="0.01" class="short"
				id="weiss_tint_surcharge_<?php echo esc_attr( $loop ); ?>"
				name="weiss_tint_surcharge[<?php echo esc_attr( $loop ); ?>]"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="Как у товара">
		</label>
		<p class="description" style="margin:4px 0 0">Пусто — как у товара или общая ставка группы.</p>
	</div>
	<div class="form-row form-row-full">
		<label for="weiss_tinting_<?php echo esc_attr( $loop ); ?>">
			Колеровка по каталогу
			<select id="weiss_tinting_<?php echo esc_attr( $loop ); ?>" class="short" name="weiss_tinting_variation[<?php echo esc_attr( $loop ); ?>]">
				<?php $tinting = get_post_meta( $variation->ID, '_weiss_tinting', true ); ?>
				<option value=""<?php selected( '', $tinting ); ?>>Как у товара</option>
				<option value="yes"<?php selected( 'yes', $tinting ); ?>>Да</option>
				<option value="no"<?php selected( 'no', $tinting ); ?>>Нет</option>
			</select>
		</label>
	</div>
	<?php
}, 10, 3 );

add_action( 'woocommerce_save_product_variation', function ( $variation_id, $loop ) {
	$raw = trim( sanitize_text_field( wp_unslash( $_POST['weiss_tint_surcharge'][ $loop ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $variation_id, '_weiss_tint_surcharge', ( '' !== $raw && is_numeric( $raw ) ) ? (string) max( 0, round( (float) $raw, 2 ) ) : '' );

	$tinting = sanitize_key( wp_unslash( $_POST['weiss_tinting_variation'][ $loop ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_post_meta( $variation_id, '_weiss_tinting', in_array( $tinting, array( 'yes', 'no' ), true ) ? $tinting : '' );
}, 10, 2 );

/** Счётчик в шапке обновляется после AJAX-добавления в корзину. */
function weiss_cart_count_fragment( $fragments ) {
	ob_start();
	?>
	<span class="cart__count tabular" aria-hidden="true"><?php echo esc_html( weiss_cart_count() ); ?></span>
	<?php
	$fragments['.cart__count'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'weiss_cart_count_fragment' );

/** Последний заказ в сессии: страница /order-success/ находит его даже без ссылки. */
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( $order && WC()->session ) {
		WC()->session->set( 'weiss_last_order_id', $order->get_id() );
		WC()->session->set( 'weiss_last_order_key', $order->get_order_key() );
	}
} );

/**
 * Реквизиты для счёта в карточке заказа (ТЗ 8.2): менеджер выставляет счёт
 * по этим данным и видит, что покупатель указал ИП или юрлицо.
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	$invoice = (array) $order->get_meta( '_weiss_invoice' );
	if ( ! $invoice ) {
		return;
	}

	$labels = array(
		'payer'   => 'Плательщик',
		'org'     => 'Организация',
		'inn'     => 'ИНН',
		'kpp'     => 'КПП',
		'ogrn'    => 'ОГРН/ОГРНИП',
		'address' => 'Юридический адрес',
		'email'   => 'E-mail для счёта',
		'phone'   => 'Телефон для счёта',
		'comment' => 'Дополнительно',
	);
	?>
	<div class="weiss-invoice">
		<h3>Реквизиты для счёта</h3>
		<p>
			<?php
			foreach ( $labels as $key => $label ) {
				$value = trim( (string) ( $invoice[ $key ] ?? '' ) );
				if ( '' === $value ) {
					continue;
				}
				if ( 'payer' === $key ) {
					$value = 'ip' === $value ? 'ИП' : 'Юридическое лицо';
				}
				echo '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '<br>';
			}
			?>
		</p>
	</div>
	<?php
} );

/**
 * Корзина без перезагрузки: количество, удаление позиции и промокод
 * уходят на admin-ajax и возвращают заново отрисованный блок #weiss-cart.
 * Суммы считает WooCommerce, JS только подставляет готовую разметку.
 */
function weiss_cart_region_html() {
	ob_start();
	wc_get_template( 'cart/cart.php', array(), '', WEISS_DIR . '/woocommerce/' );
	return ob_get_clean();
}

function weiss_cart_ajax_update() {
	check_ajax_referer( 'weiss-cart', 'nonce' );

	if ( ! WC()->cart ) {
		wp_send_json_error();
	}

	$cart  = WC()->cart;
	$key   = isset( $_POST['key'] ) ? wc_clean( wp_unslash( $_POST['key'] ) ) : '';
	$error = '';

	if ( isset( $_POST['remove'] ) && $key ) {
		$cart->remove_cart_item( $key );
	} elseif ( $key && isset( $_POST['quantity'] ) ) {
		$quantity = (int) $_POST['quantity'];

		if ( $quantity < 1 ) {
			$error = 'Количество должно быть не меньше 1. Чтобы убрать товар, удалите позицию.';
		} else {
			$cart->set_quantity( $key, $quantity, true );

			/* Склад и ограничения WooCommerce сообщают об ошибке уведомлением:
			   без вывода в корзине количество молча возвращалось к прежнему. */
			$notices = wc_get_notices( 'error' );
			if ( $notices ) {
				$error = html_entity_decode( wp_strip_all_tags( $notices[0]['notice'] ), ENT_QUOTES, 'UTF-8' );
				wc_clear_notices();
			}
		}
	} elseif ( isset( $_POST['coupon'] ) ) {
		$code = wc_format_coupon_code( wc_clean( wp_unslash( $_POST['coupon'] ) ) );
		$cart->apply_coupon( $code );

		$notices = wc_get_notices( 'error' );
		if ( $notices ) {
			$error = html_entity_decode( wp_strip_all_tags( $notices[0]['notice'] ), ENT_QUOTES, 'UTF-8' );
			wc_clear_notices();
		}
	}

	$cart->calculate_totals();

	wp_send_json_success( array(
		'html'  => weiss_cart_region_html(),
		'count' => $cart->get_cart_contents_count(),
		'error' => $error,
	) );
}
add_action( 'wp_ajax_weiss_cart_update', 'weiss_cart_ajax_update' );
add_action( 'wp_ajax_nopriv_weiss_cart_update', 'weiss_cart_ajax_update' );

/**
 * Способ доставки из зоны WooCommerce в том виде, в каком его ждёт
 * оформление заказа: «flat_rate:2» + цена. Экран М01 показывает
 * «Бесплатно» и «от 300₽» — эти цифры берутся из настроек зоны,
 * а не дублируются в шаблоне.
 */
function weiss_shipping_method_option( $method_id ) {
	static $cache = array();

	if ( isset( $cache[ $method_id ] ) ) {
		return $cache[ $method_id ];
	}

	$result = null;
	foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
		foreach ( $zone['shipping_methods'] as $instance_id => $method ) {
			if ( $method->id !== $method_id || 'yes' !== $method->enabled ) {
				continue;
			}
			$result = array(
				'value' => $method->id . ':' . $instance_id,
				'cost'  => isset( $method->cost ) ? (float) $method->cost : 0.0,
			);
			break 2;
		}
	}

	$cache[ $method_id ] = $result;
	return $result;
}

/**
 * Оформление заказа повторяет макет М01: одно поле «Ваше имя», индекс
 * и адрес второй строкой необязательны. Остальные требования WooCommerce
 * (телефон, e-mail, страна, город, улица) остаются включёнными.
 */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$fields['billing']['billing_last_name']['required'] = false;
	$fields['billing']['billing_company']['required']   = false;
	$fields['billing']['billing_postcode']['required']  = false;
	return $fields;
} );

/** Самовывоз: адрес не обязателен и на сервере (ТЗ, п. 7.6). */
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$methods = isset( $_POST['shipping_method'] ) ? (array) wp_unslash( $_POST['shipping_method'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$method  = isset( $methods[0] ) ? wc_clean( $methods[0] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( 0 === strpos( $method, 'local_pickup' ) ) {
		foreach ( array( 'billing_city', 'billing_state', 'billing_address_1', 'billing_address_2', 'billing_postcode' ) as $key ) {
			if ( isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ]['required'] = false;
			}
		}
	}

	return $fields;
}, 20 );

/** Контрольные числа ИНН (10 цифр — юрлицо, 12 — ИП) и ОГРН/ОГРНИП. */
function weiss_inn_valid( $inn ) {
	if ( ! preg_match( '/^\d{10}$|^\d{12}$/', $inn ) ) {
		return false;
	}

	$digits = array_map( 'intval', str_split( $inn ) );
	$check  = function ( $weights ) use ( $digits ) {
		$sum = 0;
		foreach ( $weights as $i => $weight ) {
			$sum += $digits[ $i ] * $weight;
		}
		return $sum % 11 % 10;
	};

	if ( 10 === strlen( $inn ) ) {
		return $check( array( 2, 4, 10, 3, 5, 9, 4, 6, 8 ) ) === $digits[9];
	}

	return $check( array( 7, 2, 4, 10, 3, 5, 9, 4, 6, 8 ) ) === $digits[10]
		&& $check( array( 3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8 ) ) === $digits[11];
}

function weiss_ogrn_valid( $ogrn ) {
	if ( ! preg_match( '/^\d{13}$|^\d{15}$/', $ogrn ) ) {
		return false;
	}

	$divisor = 13 === strlen( $ogrn ) ? 11 : 13;
	$base    = (int) substr( $ogrn, 0, -1 );
	$control = $base % $divisor % 10;

	return $control === (int) substr( $ogrn, -1 );
}

/** Согласие из макета проверяется и на сервере, не только в браузере. */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( empty( $_POST['weiss_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$errors->add( 'weiss_consent', 'Отметьте согласие на обработку персональных данных.' );
	}

	/* Реквизиты для счёта (М22, ТЗ 8.2): проверка на сервере, потому что
	   окно заполняется в браузере и его данные можно подменить. */
	$invoice = isset( $_POST['weiss_invoice'] ) ? (array) wp_unslash( $_POST['weiss_invoice'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$bacs    = isset( $_POST['payment_method'] ) && 'bacs' === $_POST['payment_method']; // phpcs:ignore WordPress.Security.NonceVerification
	$org     = isset( $invoice['org'] ) ? trim( (string) $invoice['org'] ) : '';

	if ( ! $bacs && '' === $org ) {
		return;
	}

	$payer = isset( $invoice['payer'] ) && 'ip' === $invoice['payer'] ? 'ip' : 'legal';
	$inn   = preg_replace( '/\D+/', '', (string) ( $invoice['inn'] ?? '' ) );
	$kpp   = preg_replace( '/\D+/', '', (string) ( $invoice['kpp'] ?? '' ) );
	$ogrn  = preg_replace( '/\D+/', '', (string) ( $invoice['ogrn'] ?? '' ) );
	$email = sanitize_email( (string) ( $invoice['email'] ?? '' ) );
	$phone = preg_replace( '/\D+/', '', (string) ( $invoice['phone'] ?? '' ) );

	if ( mb_strlen( $org ) < 2 ) {
		$errors->add( 'weiss_invoice_org', 'Укажите название организации или ИП для счёта.' );
	}
	if ( 'ip' === $payer ? 12 !== strlen( $inn ) : 10 !== strlen( $inn ) ) {
		$errors->add( 'weiss_invoice_inn', 'ИНН для счёта — 10 цифр у юрлица или 12 у ИП.' );
	} elseif ( ! weiss_inn_valid( $inn ) ) {
		$errors->add( 'weiss_invoice_inn', 'ИНН не проходит проверку контрольного числа — проверьте цифры.' );
	}
	if ( 'legal' === $payer && 9 !== strlen( $kpp ) ) {
		$errors->add( 'weiss_invoice_kpp', 'КПП для счёта — 9 цифр.' );
	}
	if ( 'ip' === $payer ? 15 !== strlen( $ogrn ) : 13 !== strlen( $ogrn ) ) {
		$errors->add( 'weiss_invoice_ogrn', 'ip' === $payer ? 'ОГРНИП — 15 цифр.' : 'ОГРН — 13 цифр.' );
	} elseif ( ! weiss_ogrn_valid( $ogrn ) ) {
		$errors->add( 'weiss_invoice_ogrn', 'ОГРН не проходит проверку контрольного числа — проверьте цифры.' );
	}
	/* ТЗ 8.2: юридический адрес сохраняется для счёта у юрлица и у ИП */
	$address = trim( (string) ( $invoice['address'] ?? '' ) );
	if ( mb_strlen( $address ) < 5 ) {
		$errors->add( 'weiss_invoice_address', 'Укажите юридический адрес для счёта.' );
	}
	if ( ! $email ) {
		$errors->add( 'weiss_invoice_email', 'Укажите e-mail для счёта.' );
	}
	if ( strlen( $phone ) < 10 ) {
		$errors->add( 'weiss_invoice_phone', 'Укажите телефон для счёта.' );
	}
}, 10, 2 );

/**
 * На оформлении нет полосы уведомлений WooCommerce: в макете её нет,
 * а «товар добавлен в корзину» сдвигает крошки и заголовок. Ошибки
 * шаблон показывает сам в .checkout-notice.
 */
add_action( 'template_redirect', function () {
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		remove_action( 'woocommerce_before_checkout_form_cart_notices', 'woocommerce_output_all_notices', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );
	}
} );

/**
 * Поля, которых нет в стандартном адресе WooCommerce: регион списком
 * из макета, дом и квартира, реквизиты юрлица для счёта.
 */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification

	$region = isset( $post['billing_state'] ) ? wc_clean( $post['billing_state'] ) : '';
	$house  = isset( $post['weiss_house'] ) ? wc_clean( $post['weiss_house'] ) : '';
	$flat   = isset( $post['weiss_flat'] ) ? wc_clean( $post['weiss_flat'] ) : '';

	if ( $region ) {
		$order->set_shipping_state( $region );
	}

	if ( $house || $flat ) {
		$address_2 = $house ? 'д. ' . $house : '';
		if ( $flat ) {
			$address_2 .= ( $address_2 ? ', ' : '' ) . 'кв./офис ' . $flat;
		}
		$order->set_billing_address_2( $address_2 );
		$order->set_shipping_address_2( $address_2 );
	}

	$order->update_meta_data( '_weiss_region', $region );
	$order->update_meta_data( '_weiss_house', $house );
	$order->update_meta_data( '_weiss_flat', $flat );

	if ( ! empty( $_POST['weiss_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$order->update_meta_data( '_weiss_consent', current_time( 'mysql' ) );
	}

	/* Реквизиты сохраняем только для оплаты по счёту: у карты и заявки
	   на расчёт форма всё равно присылает пустой набор полей. */
	if ( ! empty( $post['weiss_invoice'] ) && is_array( $post['weiss_invoice'] )
		&& in_array( $order->get_payment_method(), array( 'bacs', 'weiss_quote' ), true ) ) {
		$order->update_meta_data( '_weiss_invoice', array_map( 'wc_clean', $post['weiss_invoice'] ) );
	}
}, 10, 2 );

/**
 * Корзина сохраняется 7 дней (ТЗ 7.1). Стандартные 48 часов WooCommerce
 * заменяем на неделю; после этого состав пересчитывается заново.
 */
add_filter( 'wc_session_expiring', function () {
	return 7 * DAY_IN_SECONDS - HOUR_IN_SECONDS;
} );

add_filter( 'wc_session_expiration', function () {
	return 7 * DAY_IN_SECONDS;
} );

/**
 * Одновременно действует один промокод (ТЗ 7.3): новый код заменяет
 * предыдущий, а не суммируется с ним. Скидка не распространяется на
 * доставку — это поведение WooCommerce по умолчанию.
 */
add_action( 'woocommerce_applied_coupon', function ( $code ) {
	if ( ! WC()->cart ) {
		return;
	}

	$code = wc_format_coupon_code( $code );
	foreach ( WC()->cart->get_applied_coupons() as $applied ) {
		if ( $applied !== $code ) {
			WC()->cart->remove_coupon( $applied );
		}
	}
}, 20 );

/**
 * Точность денег — до копеек (ТЗ 7.2). WooCommerce хранит и умножает суммы
 * с точностью настройки, поэтому она обязана быть 2; нулевые копейки
 * в выводе не показываем, чтобы цены остались как в макете.
 */
add_action( 'init', function () {
	if ( '2' !== (string) get_option( 'woocommerce_price_num_decimals' ) ) {
		update_option( 'woocommerce_price_num_decimals', 2 );
	}
}, 5 );

add_filter( 'formatted_woocommerce_price', function ( $formatted, $price, $decimals, $decimal_separator, $thousand_separator ) {
	if ( 2 === (int) $decimals && abs( (float) $price - round( (float) $price ) ) < 0.005 ) {
		return number_format( round( (float) $price ), 0, $decimal_separator, $thousand_separator );
	}
	return $formatted;
}, 10, 5 );
