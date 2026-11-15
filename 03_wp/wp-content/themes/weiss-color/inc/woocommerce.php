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

/* Вторая линия: маршрут мог быть зарегистрирован после фильтра.
   Ошибку возвращаем из rest_request_before_callbacks, а не из
   rest_pre_dispatch: пакетный запрос ядра на WP_Error из pre_dispatch
   падал с фатальной ошибкой (замечание 73). */
add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {
	if ( null !== $response ) {
		return $response;
	}

	$method = strtoupper( (string) $request->get_method() );
	if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		return $response;
	}

	if ( weiss_is_store_checkout_route( $request->get_route() ) ) {
		return new WP_Error( 'rest_no_route', 'Маршрут не найден.', array( 'status' => 404 ) );
	}

	return $response;
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

/** Метрические единицы и русские тексты согласия (замечание 90). */
add_filter( 'option_woocommerce_weight_unit', function () {
	return 'kg';
} );

add_filter( 'option_woocommerce_dimension_unit', function () {
	return 'cm';
} );

add_filter( 'option_woocommerce_checkout_privacy_policy_text', function () {
	return 'Ваши данные используются для обработки заказа и связи с вами. Подробнее — в [privacy_policy].';
} );

add_filter( 'option_woocommerce_registration_privacy_policy_text', function () {
	return 'Ваши данные используются для работы личного кабинета. Подробнее — в [privacy_policy].';
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
 * Служебные отметки заказа в карточке панели: дата согласия на обработку
 * данных и признак заявки на расчёт (замечание 78). В выгрузке они были,
 * а менеджер в панели их не видел.
 */
add_action( 'woocommerce_admin_order_data_after_order_details', function ( $order ) {
	$consent = trim( (string) $order->get_meta( '_weiss_consent' ) );
	$quote   = '1' === $order->get_meta( '_weiss_shipping_quote' );

	if ( '' === $consent && ! $quote ) {
		return;
	}
	?>
	<div class="weiss-order-extra">
		<?php if ( $quote ) : ?>
			<p><strong>Заявка на расчёт доставки:</strong> да</p>
		<?php endif; ?>
		<?php if ( '' !== $consent ) : ?>
			<p><strong>Согласие на обработку данных:</strong> <?php echo esc_html( $consent ); ?></p>
		<?php endif; ?>
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

			/* Склад и ограничения WooCommerce сообщают об ошибке уведомлением.
			   check_cart_items() запускает проверку сразу: иначе при остатке 100
			   корзина принимала 101 и показывала «В наличии», а причина
			   появлялась только при следующем действии (замечание 99). */
			$cart->check_cart_items();
			$notices = wc_get_notices( 'error' );
			if ( $notices ) {
				$error = html_entity_decode( wp_strip_all_tags( $notices[0]['notice'] ), ENT_QUOTES, 'UTF-8' );
				wc_clear_notices();
			}
		}
	} elseif ( isset( $_POST['remove_coupon'] ) ) {
		$code = wc_format_coupon_code( wc_clean( wp_unslash( $_POST['remove_coupon'] ) ) );
		$cart->remove_coupon( $code );
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

/**
 * Проверка реквизитов для счёта (М22, ТЗ 8.2). Общая для оформления
 * заказа и страницы оплаты по ссылке: поле проверяется на сервере,
 * потому что окно заполняется в браузере и данные можно подменить.
 *
 * @return array Поле => сообщение об ошибке; пустой массив — всё верно.
 */
function weiss_invoice_validate( $invoice ) {
	$invoice = is_array( $invoice ) ? $invoice : array();
	$errors  = array();

	$org   = isset( $invoice['org'] ) ? trim( (string) $invoice['org'] ) : '';
	$payer = isset( $invoice['payer'] ) && 'ip' === $invoice['payer'] ? 'ip' : 'legal';
	$inn   = preg_replace( '/\D+/', '', (string) ( $invoice['inn'] ?? '' ) );
	$kpp   = preg_replace( '/\D+/', '', (string) ( $invoice['kpp'] ?? '' ) );
	$ogrn  = preg_replace( '/\D+/', '', (string) ( $invoice['ogrn'] ?? '' ) );
	$email = sanitize_email( (string) ( $invoice['email'] ?? '' ) );
	$phone = preg_replace( '/\D+/', '', (string) ( $invoice['phone'] ?? '' ) );
	$addr  = trim( (string) ( $invoice['address'] ?? '' ) );

	if ( mb_strlen( $org ) < 2 ) {
		$errors['org'] = 'Укажите название организации или ИП для счёта.';
	}
	if ( 'ip' === $payer ? 12 !== strlen( $inn ) : 10 !== strlen( $inn ) ) {
		$errors['inn'] = 'ИНН для счёта — 10 цифр у юрлица или 12 у ИП.';
	} elseif ( ! weiss_inn_valid( $inn ) ) {
		$errors['inn'] = 'ИНН не проходит проверку контрольного числа — проверьте цифры.';
	}
	if ( 'legal' === $payer && 9 !== strlen( $kpp ) ) {
		$errors['kpp'] = 'КПП для счёта — 9 цифр.';
	}
	if ( 'ip' === $payer ? 15 !== strlen( $ogrn ) : 13 !== strlen( $ogrn ) ) {
		$errors['ogrn'] = 'ip' === $payer ? 'ОГРНИП — 15 цифр.' : 'ОГРН — 13 цифр.';
	} elseif ( ! weiss_ogrn_valid( $ogrn ) ) {
		$errors['ogrn'] = 'ОГРН не проходит проверку контрольного числа — проверьте цифры.';
	}
	if ( mb_strlen( $addr ) < 5 ) {
		$errors['address'] = 'Укажите юридический адрес для счёта.';
	}
	if ( ! $email ) {
		$errors['email'] = 'Укажите e-mail для счёта.';
	}
	if ( strlen( $phone ) < 10 ) {
		$errors['phone'] = 'Укажите телефон для счёта.';
	}

	return $errors;
}

/** Согласие из макета проверяется и на сервере, не только в браузере. */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( empty( $_POST['weiss_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$errors->add( 'weiss_consent', 'Отметьте согласие на обработку персональных данных.' );
	}

	$invoice = isset( $_POST['weiss_invoice'] ) ? (array) wp_unslash( $_POST['weiss_invoice'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$bacs    = isset( $_POST['payment_method'] ) && 'bacs' === $_POST['payment_method']; // phpcs:ignore WordPress.Security.NonceVerification
	$org     = isset( $invoice['org'] ) ? trim( (string) $invoice['org'] ) : '';

	if ( ! $bacs && '' === $org ) {
		return;
	}

	foreach ( weiss_invoice_validate( $invoice ) as $field => $message ) {
		$errors->add( 'weiss_invoice_' . $field, $message );
	}
}, 10, 2 );

/**
 * Реквизиты на странице оплаты по ссылке (ТЗ 8.2, 7.5): при выборе
 * «По счёту» покупатель заполняет их прямо здесь, иначе заказ юрлица
 * приходил менеджеру без названия и ИНН.
 */
add_action( 'woocommerce_before_pay_action', function ( $order ) {
	$method = isset( $_POST['payment_method'] ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'bacs' !== $method ) {
		return;
	}

	$invoice = isset( $_POST['weiss_invoice'] ) ? (array) wp_unslash( $_POST['weiss_invoice'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$errors  = weiss_invoice_validate( $invoice );

	foreach ( $errors as $message ) {
		wc_add_notice( $message, 'error' );
	}

	if ( $errors ) {
		return;
	}

	$order->update_meta_data( '_weiss_invoice', array_map( 'wc_clean', $invoice ) );
	$order->add_order_note( 'Покупатель указал реквизиты для счёта на странице оплаты.' );
	$order->save();
} );

/**
 * Сообщение для страницы оплаты с неверной или уже ненужной ссылкой.
 * В этом случае form-pay.php не выводится, а полосу уведомлений
 * WooCommerce на оформлении тема скрывает — страница оставалась пустой
 * (замечание 100).
 */
function weiss_pay_endpoint_message() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
		return '';
	}

	$order_id = absint( get_query_var( 'order-pay' ) );
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( ! $order || ! hash_equals( (string) $order->get_order_key(), (string) $key ) ) {
		return 'Ссылка на оплату недействительна. Проверьте адрес или запросите новую ссылку у менеджера.';
	}
	if ( ! $order->needs_payment() ) {
		return 'Этот заказ уже оплачен или отменён — повторная оплата недоступна.';
	}

	return '';
}

/**
 * На оформлении нет полосы уведомлений WooCommerce: в макете её нет,
 * а «товар добавлен в корзину» сдвигает крошки и заголовок. Ошибки
 * шаблон показывает сам в .checkout-notice.
 */
add_action( 'template_redirect', function () {
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		remove_action( 'woocommerce_before_checkout_form_cart_notices', 'woocommerce_output_all_notices', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );
		/* На оплате по ссылке ошибки проверки реквизитов собирает form-pay.php;
		   полоса WooCommerce напечатала бы их раньше и забрала текст. */
		remove_action( 'before_woocommerce_pay', 'woocommerce_output_all_notices', 10 );
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
	   на расчёт форма всё равно присылает пустой набор полей. Пустой набор
	   не пишем: иначе у заявки физлица в карточке появлялся блок
	   «Реквизиты для счёта: Юридическое лицо» без данных (замечание 100). */
	if ( ! empty( $post['weiss_invoice'] ) && is_array( $post['weiss_invoice'] )
		&& in_array( $order->get_payment_method(), array( 'bacs', 'weiss_quote' ), true ) ) {
		$invoice = array_filter(
			array_map( 'wc_clean', $post['weiss_invoice'] ),
			function ( $value ) {
				return '' !== trim( (string) $value );
			}
		);
		if ( ! empty( $invoice['org'] ) || ! empty( $invoice['inn'] ) ) {
			$order->update_meta_data( '_weiss_invoice', $invoice );
		}
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
 * Значение читаем и пишем напрямую в таблице: get_option() проходит через
 * фильтр выше и всегда возвращает 2, а update_option() из-за этого считает
 * значение неизменным и в базу не пишет (замечание 90).
 */
add_action( 'init', function () {
	if ( '2' !== (string) weiss_option_raw( 'woocommerce_price_num_decimals' ) ) {
		weiss_set_option_raw( 'woocommerce_price_num_decimals', '2' );
	}
}, 5 );

add_filter( 'formatted_woocommerce_price', function ( $formatted, $price, $decimals, $decimal_separator, $thousand_separator ) {
	if ( 2 === (int) $decimals && abs( (float) $price - round( (float) $price ) ) < 0.005 ) {
		return number_format( round( (float) $price ), 0, $decimal_separator, $thousand_separator );
	}
	return $formatted;
}, 10, 5 );

/** Значение настройки прямо из таблицы, минуя фильтры option_* темы. */
function weiss_option_raw( $name ) {
	global $wpdb;

	return $wpdb->get_var( $wpdb->prepare(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
		$name
	) );
}

/** Запись настройки в базу в обход фильтров option_* темы и общего кэша. */
function weiss_set_option_raw( $name, $value ) {
	global $wpdb;

	$value = (string) $value;
	if ( null === weiss_option_raw( $name ) ) {
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => $name,
				'option_value' => $value,
				'autoload'     => 'yes',
			)
		);
	} else {
		$wpdb->update(
			$wpdb->options,
			array( 'option_value' => $value ),
			array( 'option_name' => $name )
		);
	}

	wp_cache_delete( $name, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

/**
 * Заводские значения WooCommerce → метрические и русские (замечание 90).
 * Фильтры выше уже отдают нужное на витрине, но в панели и в выгрузке
 * настроек оставались фунты, дюймы и английский текст о данных. Пишем
 * в базу один раз и только точные заводские значения, чтобы не затереть
 * выбор администратора.
 */
add_action( 'init', function () {
	if ( '1' === get_option( 'weiss_wc_settings_cleanup' ) ) {
		return;
	}

	$values = array(
		'woocommerce_weight_unit'    => 'kg',
		'woocommerce_dimension_unit' => 'cm',
	);
	foreach ( $values as $name => $value ) {
		if ( (string) weiss_option_raw( $name ) !== $value ) {
			weiss_set_option_raw( $name, $value );
		}
	}

	$texts = array(
		'woocommerce_checkout_privacy_policy_text'     => array(
			'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].',
			'Ваши данные используются для обработки заказа и связи с вами. Подробнее — в [privacy_policy].',
		),
		'woocommerce_registration_privacy_policy_text' => array(
			'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our [privacy_policy].',
			'Ваши данные используются для работы личного кабинета. Подробнее — в [privacy_policy].',
		),
	);
	foreach ( $texts as $name => $pair ) {
		if ( $pair[0] === trim( (string) weiss_option_raw( $name ) ) ) {
			weiss_set_option_raw( $name, $pair[1] );
		}
	}

	update_option( 'weiss_wc_settings_cleanup', '1', false );
}, 7 );
