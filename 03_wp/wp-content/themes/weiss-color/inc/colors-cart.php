<?php
/**
 * Покупка с колеровкой (ТЗ, п. 6.5–6.6): палитра и код оттенка едут в позицию
 * корзины, различают позиции одного товара и сохраняются в заказе.
 */

/** Цвет из запроса на добавление в корзину. */
function weiss_cart_request_color() {
	$palette = isset( $_REQUEST['weiss_palette'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['weiss_palette'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$code    = isset( $_REQUEST['weiss_code'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['weiss_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( '' === $palette || '' === $code ) {
		return null;
	}

	return weiss_color_get( $palette, $code );
}

/** Товар-пробник 300 мл: отдельная позиция с привязкой к цвету. */
function weiss_sampler_product() {
	static $product = null;
	static $loaded  = false;

	if ( $loaded ) {
		return $product;
	}
	$loaded = true;

	$ids = wc_get_products( array(
		'status'     => 'publish',
		'limit'      => 1,
		'return'     => 'ids',
		'meta_key'   => '_weiss_sampler', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
	) );

	$product = $ids ? wc_get_product( $ids[0] ) : null;
	return $product;
}

/** Товар (и фасовка) как цель кнопки «В корзину». */
function weiss_product_cart_target( $product, $variation_id = 0 ) {
	if ( ! $product || 'publish' !== $product->get_status() ) {
		return null;
	}

	/* В карте колеровки может стоять сама фасовка (вариация): кнопка
	   должна вести на родительский товар с этой фасовкой. */
	if ( $product->is_type( 'variation' ) ) {
		$variation_id = $variation_id ? (int) $variation_id : $product->get_id();
		$parent       = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$product = $parent;
		}
	}

	$price      = (float) $product->get_price();
	$variant    = 0;
	$attributes = array();

	if ( $product->is_type( 'variable' ) && (int) $variation_id ) {
		$variant_product = wc_get_product( (int) $variation_id );
		if ( $variant_product && 'publish' === $variant_product->get_status()
			&& (int) $variant_product->get_parent_id() === $product->get_id() ) {
			$variant    = (int) $variation_id;
			$price      = (float) $variant_product->get_price();
			$attributes = $variant_product->get_variation_attributes();
		}
	}

	/* ТЗ 6.5: фасовку выбирает покупатель. Без явно выбранной фасовки
	   первая не подставляется — иначе «В корзину» молча кладёт 1,1 л.
	   Шаблоны в этом случае показывают «Выбрать фасовку» и ведут в карточку. */

	return array(
		'id'         => $product->get_id(),
		'variation'  => $variant,
		'variable'   => $product->is_type( 'variable' ),
		'attributes' => $attributes,
		'name'       => $product->get_name(),
		'short'      => preg_replace( '/^acrilux\s+/iu', '', $product->get_name() ),
		'desc'       => wp_strip_all_tags( $product->get_short_description() ),
		'price'      => $price,
		'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'medium' ),
		'url'        => get_permalink( $product->get_id() ),
	);
}

/** Первый совместимый товар для цвета — цель кнопки «В корзину». */
function weiss_color_cart_target( $color ) {
	$items = weiss_color_products( $color );
	if ( ! $items ) {
		return null;
	}

	return weiss_product_cart_target( $items[0]['product'] );
}

/** Ключ цвета для перечня пробника: палитра и код без учёта регистра. */
function weiss_sampler_color_key( $palette, $code ) {
	return mb_strtolower( trim( (string) $palette ) ) . "\x1f" . mb_strtolower( trim( (string) $code ) );
}

/** Перечень разрешённых цветов пробника (ТЗ 6.6). Пусто — разрешены все активные. */
function weiss_sampler_colors() {
	$list = get_option( 'weiss_sampler_colors', array() );
	return is_array( $list ) ? $list : array();
}

function weiss_sampler_allows( $color ) {
	if ( ! $color || empty( $color->active ) ) {
		return false;
	}

	$list = weiss_sampler_colors();
	if ( ! $list ) {
		return true;
	}

	return in_array( weiss_sampler_color_key( $color->palette, $color->code ), $list, true );
}

/** Совместим ли цвет с товаром (ТЗ, п. 5.5): пробник берёт только разрешённые цвета (п. 6.6). */
function weiss_color_fits_product( $color, $product_id, $variation_id = 0 ) {
	$product = wc_get_product( (int) $product_id );
	if ( ! $product ) {
		return false;
	}
	if ( $variation_id && 'no' === get_post_meta( (int) $variation_id, '_weiss_tinting', true ) ) {
		return false;
	}
	if ( 'yes' === get_post_meta( $product->get_id(), '_weiss_sampler', true ) ) {
		return weiss_sampler_allows( $color );
	}
	if ( $product->is_type( 'variation' ) ) {
		$product = wc_get_product( $product->get_parent_id() );
		if ( ! $product ) {
			return false;
		}
	}

	/* Применимость колеровки у товара (ТЗ, п. 4.5): если в панели сняли
	   галочку, цвет не предлагается даже при совпадении по карте. */
	if ( 'no' === get_post_meta( $product->get_id(), '_weiss_tinting', true ) ) {
		return false;
	}

	foreach ( weiss_color_products( $color ) as $item ) {
		if ( $item['product']->get_id() === $product->get_id() ) {
			return true;
		}
	}

	return false;
}

/**
 * Проверка перед добавлением: неизвестный оттенок не попадает в корзину,
 * пробник без цвета не продаётся, цвет обязан подходить товару (ТЗ, п. 6.6, 5.5).
 */
add_filter( 'woocommerce_add_to_cart_validation', function ( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
	/* Отклонённое добавление не должно терять текст ошибки: без cookie
	   WooCommerce не сохраняет сессию (save_data ждёт has_session), уведомление
	   пропадает и кнопка «В корзину» выглядит нерабочей. Заводим сессию здесь —
	   фильтр идёт и при успешной, и при отклонённой попытке. */
	if ( WC()->session && ! WC()->session->has_session() ) {
		WC()->session->set_customer_session_cookie( true );
	}

	if ( ! $passed ) {
		return $passed;
	}

	$palette = isset( $_REQUEST['weiss_palette'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['weiss_palette'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$code    = isset( $_REQUEST['weiss_code'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['weiss_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$sampler = 'yes' === get_post_meta( $product_id, '_weiss_sampler', true );

	if ( '' === $palette && '' === $code ) {
		if ( $sampler ) {
			wc_add_notice( 'Выберите цвет пробного образца в каталоге оттенков.', 'error' );
			return false;
		}
		return $passed;
	}

	$color = ( '' !== $palette && '' !== $code ) ? weiss_cart_request_color() : null;
	if ( ! $color ) {
		wc_add_notice( 'Оттенок не найден в каталоге. Выберите цвет заново.', 'error' );
		return false;
	}

	if ( $sampler && ! weiss_sampler_allows( $color ) ) {
		wc_add_notice( 'Пробник этого цвета не продаётся. Выберите цвет из перечня, утверждённого магазином.', 'error' );
		return false;
	}

	if ( ! weiss_color_fits_product( $color, $product_id, $variation_id ) ) {
		wc_add_notice( 'Этот оттенок не подходит для выбранного товара или фасовки. Выберите цвет заново.', 'error' );
		return false;
	}

	/* ТЗ 4.5: неизвестная цена конфигурации не продаётся. Пока правила
	   доплаты не заданы, покупка с колеровкой отклоняется с причиной. */
	if ( null === weiss_tint_unit_price( $product_id, $variation_id ) ) {
		wc_add_notice( weiss_tint_unknown_message( wc_get_product( $variation_id ? $variation_id : $product_id ) ), 'error' );
		return false;
	}

	return $passed;
}, 10, 5 );

/** Палитра и код в данных позиции: разные цвета дают разные позиции. */
add_filter( 'woocommerce_add_cart_item_data', function ( $cart_item_data, $product_id, $variation_id, $quantity ) {
	$color = weiss_cart_request_color();
	if ( ! $color ) {
		return $cart_item_data;
	}

	$cart_item_data['weiss_palette'] = $color->palette;
	$cart_item_data['weiss_code']    = $color->code;
	$cart_item_data['weiss_hex']     = $color->hex;

	/* Цена единицы фиксируется сервером (ТЗ, 7.2): базовая фасовка плюс
	   доплата. Браузер эти значения не передаёт. */
	$base      = weiss_tint_base_price( $product_id, $variation_id );
	$surcharge = weiss_tint_surcharge( $product_id, $variation_id );
	if ( null !== $base && null !== $surcharge ) {
		$cart_item_data['weiss_base_price'] = $base;
		$cart_item_data['weiss_surcharge']  = $surcharge;
		$cart_item_data['weiss_unit_price'] = round( $base + $surcharge, 2 );
	}

	return $cart_item_data;
}, 10, 4 );

/** Пересчёт перед итогами: доплата актуальна, даже если правила изменят. */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
	if ( ! $cart instanceof WC_Cart ) {
		return;
	}

	foreach ( $cart->get_cart() as $key => $item ) {
		if ( empty( $item['weiss_code'] ) ) {
			continue;
		}

		$product_id   = (int) $item['product_id'];
		$variation_id = (int) ( $item['variation_id'] ?? 0 );

		/* Цена фасовки берётся заново (ТЗ 7.2): если её изменили после
		   добавления в корзину, покупатель видит текущую, а не старую. */
		$base = weiss_tint_base_price( $product_id, $variation_id );
		if ( null === $base && isset( $item['weiss_base_price'] ) ) {
			$base = (float) $item['weiss_base_price'];
		}
		$surcharge = weiss_tint_surcharge( $product_id, $variation_id );

		/* Неизвестная доплата: позиция остаётся по базовой цене, а оформление
		   блокируется проверкой в woocommerce_checkout_process. */
		if ( null !== $base ) {
			$cart->cart_contents[ $key ]['weiss_base_price'] = $base;
			$cart->cart_contents[ $key ]['weiss_unit_price'] = round( $base + ( null === $surcharge ? 0 : $surcharge ), 2 );
		}
		$cart->cart_contents[ $key ]['weiss_surcharge'] = $surcharge;

		if ( isset( $cart->cart_contents[ $key ]['weiss_unit_price'] ) ) {
			$item['data']->set_price( $cart->cart_contents[ $key ]['weiss_unit_price'] );
		}
	}
}, 20 );

/** Цвет и палитра строкой под названием товара в корзине и оформлении. */
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item ) {
	if ( empty( $cart_item['weiss_code'] ) ) {
		return $item_data;
	}

	$item_data[] = array(
		'key'     => 'Цвет',
		'value'   => $cart_item['weiss_code'],
		'display' => $cart_item['weiss_code'],
	);
	$item_data[] = array(
		'key'   => 'Палитра',
		'value' => $cart_item['weiss_palette'],
	);

	$surcharge = isset( $cart_item['weiss_surcharge'] ) ? $cart_item['weiss_surcharge'] : null;
	if ( null !== $surcharge ) {
		$item_data[] = array(
			'key'     => 'Доплата за колеровку',
			'value'   => $surcharge > 0 ? weiss_price_compact( $surcharge ) : 'включена в цену',
			'display' => $surcharge > 0 ? weiss_price_compact( $surcharge ) : 'включена в цену',
		);
	}

	return $item_data;
}, 10, 2 );

/** Те же данные в позиции заказа: письма, счёт и админка. */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {
	if ( empty( $values['weiss_code'] ) ) {
		return;
	}

	$item->add_meta_data( 'Цвет', $values['weiss_code'], true );
	$item->add_meta_data( 'Палитра', $values['weiss_palette'], true );
	$item->add_meta_data( '_weiss_code', $values['weiss_code'], true );
	$item->add_meta_data( '_weiss_palette', $values['weiss_palette'], true );

	if ( isset( $values['weiss_base_price'] ) ) {
		$item->add_meta_data( '_weiss_base_price', wc_format_decimal( $values['weiss_base_price'], 2 ), true );
	}
	if ( isset( $values['weiss_surcharge'] ) && null !== $values['weiss_surcharge'] ) {
		$item->add_meta_data( '_weiss_tint_surcharge', wc_format_decimal( $values['weiss_surcharge'], 2 ), true );
		$item->add_meta_data( 'Доплата за колеровку', $values['weiss_surcharge'] > 0 ? weiss_price_compact( $values['weiss_surcharge'] ) : 'включена в цену', true );
	}
}, 10, 4 );

/**
 * Админка выводит служебные ключи позиции целиком и дублирует «Цвет» и
 * «Палитра» (замечание 78). Прячем только технические поля — выгрузка
 * и подсчёт цены по-прежнему их читают.
 */
add_filter( 'woocommerce_hidden_order_itemmeta', function ( $hidden ) {
	return array_merge(
		(array) $hidden,
		array( '_weiss_code', '_weiss_palette', '_weiss_base_price', '_weiss_tint_surcharge' )
	);
} );

/** Оформление: цвет мог быть скрыт или товар заменён — проверяем заново (п. 5.5). */
add_action( 'woocommerce_checkout_process', function () {
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( empty( $item['weiss_code'] ) ) {
			continue;
		}

		$color = weiss_color_get( $item['weiss_palette'], $item['weiss_code'] );
		if ( ! $color ) {
			wc_add_notice( sprintf( 'Оттенок %s больше недоступен. Удалите позицию и выберите цвет заново.', $item['weiss_code'] ), 'error' );
			continue;
		}

		if ( ! weiss_color_fits_product( $color, $item['product_id'], $item['variation_id'] ?? 0 ) ) {
			wc_add_notice( sprintf( 'Оттенок %s не подходит для товара «%s».', $item['weiss_code'], $item['data']->get_name() ), 'error' );
			continue;
		}

		/* Цена конфигурации подтверждается сервером (ТЗ, 7.2): если правила
		   доплаты изменились или исчезли, оплата не проводится. */
		if ( null === weiss_tint_surcharge( $item['product_id'], $item['variation_id'] ?? 0 ) ) {
			wc_add_notice( weiss_tint_unknown_message( $item['data'] ), 'error' );
		}
	}
} );
