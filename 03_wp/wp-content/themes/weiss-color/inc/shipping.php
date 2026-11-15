<?php
/**
 * Доставка по регионам (ТЗ, п.7.4–7.5). Тарифы ведутся одной таблицей
 * в панели: регион | стоимость | срок. Стоимость известна до оплаты;
 * если тарифа для региона нет, заказ оформляется как заявка на расчёт —
 * деньги не списываются, менеджер присылает итог и ссылку на оплату.
 */

/** Тарифная таблица по умолчанию — из макета М01 и согласованных условий. */
function weiss_delivery_tariffs_default() {
	return implode( "\n", array(
		'Москва и Московская область|0|1–2 дня',
		'Санкт-Петербург и Ленинградская область|300|2–4 дня',
		'Тверская область|400|3–5 дней',
		'Тульская область|400|3–5 дней',
		'Другой регион||уточняется',
	) );
}

/**
 * Разобранная таблица: «Регион» => array( cost => float|null, term => строка ).
 * Пустая стоимость означает «рассчитает менеджер», а не бесплатную доставку.
 */
function weiss_delivery_tariffs() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}

	$raw = (string) get_option( 'weiss_delivery_tariffs', '' );
	if ( '' === trim( $raw ) ) {
		$raw = weiss_delivery_tariffs_default();
	}

	$map = array();
	foreach ( preg_split( '/\R/u', $raw ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( '' === $parts[0] ) {
			continue;
		}
		$cost = isset( $parts[1] ) ? trim( $parts[1] ) : '';
		$free = isset( $parts[3] ) ? trim( str_replace( ',', '.', $parts[3] ) ) : '';
		$map[ $parts[0] ] = array(
			'cost'      => '' === $cost ? null : (float) str_replace( ',', '.', $cost ),
			'term'      => isset( $parts[2] ) ? $parts[2] : '',
			'free_from' => '' === $free ? null : (float) $free,
		);
	}

	return $map;
}

/** Тариф региона; неизвестный регион считается регионом без тарифа. */
function weiss_delivery_tariff( $region ) {
	$tariffs = weiss_delivery_tariffs();
	return isset( $tariffs[ $region ] ) ? $tariffs[ $region ] : array( 'cost' => null, 'term' => '', 'free_from' => null );
}

/**
 * Доставка в корзине (ТЗ 7.2): до оформления регион может быть неизвестен.
 * Тогда честно показываем «рассчитывается при оформлении», а не бесплатную
 * доставку. Порог и срок берутся из таблицы тарифов, а не из шаблона.
 */
function weiss_cart_delivery_info( $cart = null ) {
	$cart = $cart ? $cart : ( function_exists( 'WC' ) ? WC()->cart : null );

	$region = '';
	if ( function_exists( 'WC' ) && WC()->customer ) {
		$region = (string) ( WC()->customer->get_shipping_state() ?: WC()->customer->get_billing_state() );
	}

	$subtotal = $cart ? (float) $cart->get_subtotal() : 0;
	$discount = $cart ? (float) $cart->get_discount_total() : 0;
	$goods    = max( 0, $subtotal - $discount );

	$info = array(
		'region'    => $region,
		'label'     => 'Рассчитывается при оформлении',
		'term'      => 'Стоимость и срок доставки зависят от региона — точные условия появятся при оформлении заказа.',
		'free_from' => null,
		'free_left' => null,
	);

	if ( '' === $region ) {
		$shipping = $cart ? (float) $cart->get_shipping_total() : 0;
		if ( $shipping > 0 ) {
			$info['label'] = weiss_price_number( $shipping ) . ' ₽';
		}
		return $info;
	}

	$tariff = weiss_delivery_tariff( $region );

	if ( $tariff['term'] ) {
		$info['term'] = 'Доставка: ' . $tariff['term'] . '.';
	}

	if ( $tariff['free_from'] ) {
		$info['free_from'] = (float) $tariff['free_from'];
		$info['free_left'] = max( 0, $info['free_from'] - $goods );
	}

	if ( null === $tariff['cost'] ) {
		$info['label'] = 'Рассчитывается менеджером';
	} elseif ( 0.0 === (float) $tariff['cost'] || ( $info['free_from'] && $goods >= $info['free_from'] ) ) {
		$info['label'] = 'Бесплатно';
	} else {
		$info['label'] = weiss_price_number( (float) $tariff['cost'] ) . ' ₽';
	}

	return $info;
}

/** Регион из отправленной формы или из адреса покупателя WooCommerce. */
function weiss_checkout_region() {
	if ( isset( $_POST['billing_state'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return wc_clean( wp_unslash( $_POST['billing_state'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( function_exists( 'WC' ) && WC()->customer ) {
		return (string) WC()->customer->get_shipping_state();
	}
	return '';
}

/**
 * Заявка на расчёт: выбран курьер, а тарифа для региона нет. При
 * самовывозе расчёт не нужен — адрес и стоимость не меняются.
 */
function weiss_is_quote_checkout() {
	$methods = isset( $_POST['shipping_method'] ) ? (array) wp_unslash( $_POST['shipping_method'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$chosen  = $methods ? (string) reset( $methods ) : '';

	/* проверяем только отправку оформления: на просмотре заказа в сессии
	   мог остаться регион из прошлой заявки */
	if ( ! $methods || 0 === strpos( $chosen, 'local_pickup' ) ) {
		return false;
	}

	$region = weiss_checkout_region();
	if ( '' === $region ) {
		return false;
	}

	return null === weiss_delivery_tariff( $region )['cost'];
}

/**
 * Стоимость доставки в корзине и оформлении (ТЗ 7.2, 7.4). Без этого
 * фильтра зона WooCommerce показывала 300 ₽ для любого региона, а верная
 * сумма появлялась только в момент создания заказа.
 */
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
	$region = isset( $package['destination']['state'] ) ? (string) $package['destination']['state'] : '';

	$goods = 0;
	foreach ( $package['contents'] as $item ) {
		$goods += (float) $item['line_total'];
	}

	foreach ( $rates as $rate ) {
		if ( 0 === strpos( $rate->get_method_id(), 'local_pickup' ) ) {
			continue;
		}

		if ( '' === $region ) {
			$rate->set_cost( 0 );
			$rate->set_label( 'Курьерская доставка — рассчитывается при оформлении' );
			continue;
		}

		$tariff = weiss_delivery_tariff( $region );
		if ( null === $tariff['cost'] ) {
			$rate->set_cost( 0 );
			$rate->set_label( 'Курьерская доставка — расчёт после оформления' );
			continue;
		}

		$cost = (float) $tariff['cost'];
		if ( $tariff['free_from'] && $goods >= $tariff['free_from'] ) {
			$cost = 0;
		}

		$rate->set_cost( $cost );
		$rate->set_label( $cost > 0 ? 'Курьерская доставка' : 'Курьерская доставка — бесплатно' );
	}

	return $rates;
}, 20, 2 );

/**
 * Стоимость доставки заказа: цена из таблицы или 0 для заявки на расчёт.
 * Вызывается при создании заказа, чтобы сумма не зависела от браузера.
 */
add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	$methods = isset( $data['shipping_method'] ) ? (array) $data['shipping_method'] : array();
	$chosen  = $methods ? (string) reset( $methods ) : '';

	if ( 0 === strpos( $chosen, 'local_pickup' ) ) {
		$order->update_meta_data( '_weiss_shipping_quote', '0' );
		return;
	}

	$region = isset( $data['billing_state'] ) ? wc_clean( $data['billing_state'] ) : '';
	$tariff = weiss_delivery_tariff( $region );
	$quote  = null === $tariff['cost'];
	$cost   = $quote ? 0 : (float) $tariff['cost'];

	/* Порог бесплатной доставки считается от суммы товаров после скидки (ТЗ 7.3). */
	$goods = WC()->cart ? (float) WC()->cart->get_subtotal() - (float) WC()->cart->get_discount_total() : 0;
	$free  = ! $quote && $tariff['free_from'] && $goods >= $tariff['free_from'];
	if ( $free ) {
		$cost = 0;
	}

	foreach ( $order->get_items( 'shipping' ) as $item ) {
		$item->set_total( $cost );
		$item->set_name( $quote ? 'Курьерская доставка — расчёт после оформления' : 'Курьерская доставка' );
		$item->save();
	}

	$order->update_meta_data( '_weiss_delivery_region', $region );
	$order->update_meta_data( '_weiss_delivery_term', $tariff['term'] );
	$order->update_meta_data( '_weiss_shipping_quote', $quote ? '1' : '0' );
	$order->update_meta_data( '_weiss_shipping_free', $free ? '1' : '0' );
	$order->update_meta_data( '_weiss_ship_cost', $quote ? '' : $cost );

	$order->calculate_totals( false );
}, 5, 2 );

/**
 * «Оплата после расчёта доставки»: платёжный способ для заявки. Он не
 * показывается в обычном оформлении, а в заявке заменяет карту и счёт:
 * оплата открывается ссылкой после того, как менеджер впишет стоимость.
 */
class Weiss_Gateway_Quote extends WC_Payment_Gateway {
	public function __construct() {
		$this->id                 = 'weiss_quote';
		$this->method_title       = 'Оплата после расчёта доставки';
		$this->method_description = 'Заявка на расчёт доставки: деньги не списываются, покупатель получает ссылку на оплату после подтверждения стоимости.';
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_settings();
		$this->title   = $this->get_option( 'title', 'Оплата после расчёта доставки' );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_weiss_shipping_quote', '1' );
		$order->save();
		$order->update_status( 'on-hold', 'Заявка на расчёт доставки: оплата не списана. Менеджеру нужно указать стоимость доставки и отправить покупателю ссылку на оплату.' );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		);
	}
}

add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
	$gateways[] = 'Weiss_Gateway_Quote';
	return $gateways;
} );

/** В обычном оформлении заявки нет; в заявке нет карты и счёта. */
add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $gateways;
	}

	if ( weiss_is_quote_checkout() ) {
		return isset( $gateways['weiss_quote'] ) ? array( 'weiss_quote' => $gateways['weiss_quote'] ) : array();
	}

	unset( $gateways['weiss_quote'] );
	return $gateways;
} );

/**
 * Ссылка на оплату для заявки: действие в карточке заказа. Менеджер
 * указывает доставку, сохраняет заказ и отправляет покупателю итог
 * со ссылкой на защищённую платёжную страницу.
 */
add_filter( 'woocommerce_order_actions', function ( $actions, $order ) {
	if ( $order && '1' === $order->get_meta( '_weiss_shipping_quote' ) ) {
		$actions['weiss_send_payment_link'] = 'Отправить покупателю ссылку на оплату';
	}
	return $actions;
}, 10, 2 );

add_action( 'woocommerce_order_action_weiss_send_payment_link', function ( $order ) {
	$email = $order->get_billing_email();

	if ( $order->get_total() <= 0 || $order->get_shipping_total() <= 0 || ! $email ) {
		$order->add_order_note( 'Ссылка на оплату не отправлена: укажите стоимость доставки (итог заказа больше нуля) и e-mail покупателя.' );
		return;
	}

	/* заявка становится обычным неоплаченным заказом: страница оплаты
	   открывается только для «Ожидает оплаты», а не для «На удержании» */
	$order->update_meta_data( '_weiss_shipping_quote', '0' );
	$order->update_meta_data( '_weiss_quote_resolved', current_time( 'mysql' ) );
	$order->update_status( 'pending', 'Стоимость доставки рассчитана: ' . wp_strip_all_tags( wc_price( $order->get_shipping_total() ) ) . '. Покупателю отправляется ссылка на оплату.' );

	$url     = $order->get_checkout_payment_url();
	$subject = 'Заказ № ' . $order->get_order_number() . ' — итог и ссылка на оплату';
	$message = implode( "\n", array(
		'Здравствуйте, ' . $order->get_billing_first_name() . '!',
		'',
		'Мы рассчитали стоимость доставки по вашему заказу № ' . $order->get_order_number() . '.',
		'Итог заказа с доставкой: ' . wp_strip_all_tags( $order->get_formatted_order_total() ) . '.',
		'Оплатить заказ: ' . $url,
		'',
		'Если данные не совпадают с ожидаемыми, ответьте на это письмо или позвоните нам.',
	) );

	$sent = wp_mail( $email, $subject, $message );

	$order->add_order_note( $sent
		? 'Покупателю отправлена ссылка на оплату: ' . $email . '.'
		: 'Не удалось отправить ссылку на оплату на ' . $email . '. Ошибка почты зафиксирована в разделе «Письма».' );

	if ( $sent ) {
		$order->update_meta_data( '_weiss_pay_link_sent', current_time( 'mysql' ) );
		$order->save();
	}
} );

/**
 * Колонка «Доставка» в списке заказов: менеджер сразу видит заявки на
 * расчёт и регион с точной стоимостью (ТЗ 7.4–7.5).
 */
function weiss_orders_delivery_column( $order ) {
	if ( '1' === $order->get_meta( '_weiss_shipping_quote' ) ) {
		echo '<span style="color:#d0433a">Заявка на расчёт</span>';
		return;
	}

	$region = (string) $order->get_meta( '_weiss_delivery_region' );
	$cost   = (float) $order->get_shipping_total();

	echo esc_html( $region ? $region : '—' );
	if ( $cost > 0 ) {
		echo '<br><small>' . wp_kses_post( wc_price( $cost ) ) . '</small>';
	}
}

add_filter( 'woocommerce_shop_order_list_table_columns', function ( $columns ) {
	$columns['weiss_delivery'] = 'Доставка';
	return $columns;
} );
add_action( 'woocommerce_shop_order_list_table_custom_column', function ( $column, $order ) {
	if ( 'weiss_delivery' === $column ) {
		weiss_orders_delivery_column( $order );
	}
}, 10, 2 );

/* запасной путь для установок без HPOS */
add_filter( 'manage_edit-shop_order_columns', function ( $columns ) {
	$columns['weiss_delivery'] = 'Доставка';
	return $columns;
} );
add_action( 'manage_shop_order_posts_custom_column', function ( $column, $post_id ) {
	if ( 'weiss_delivery' === $column ) {
		$order = wc_get_order( $post_id );
		if ( $order ) {
			weiss_orders_delivery_column( $order );
		}
	}
}, 10, 2 );

/** Таблица тарифов: «WooCommerce → Тарифы доставки». */add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		'Тарифы доставки',
		'Тарифы доставки',
		'manage_woocommerce',
		'weiss-delivery',
		'weiss_delivery_admin_page'
	);
} );

function weiss_delivery_admin_page() {
	$raw = (string) get_option( 'weiss_delivery_tariffs', '' );
	if ( '' === trim( $raw ) ) {
		$raw = weiss_delivery_tariffs_default();
	}
	?>
	<div class="wrap">
		<h1>Тарифы доставки</h1>
		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Тарифы сохранены.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="weiss_delivery_save">
			<?php wp_nonce_field( 'weiss_delivery_save' ); ?>
			<p>По одной строке на регион: <code>Регион | Стоимость, ₽ | Срок | Бесплатно от, ₽</code>. Список регионов на оформлении заказа строится из этой таблицы.</p>
			<p>Четвёртое поле — порог бесплатной доставки: он сравнивается с суммой товаров после скидки. Пустое поле — бесплатной доставки в регионе нет.</p>
			<p>Пустая стоимость — регион без тарифа: заказ оформляется как заявка на расчёт, деньги не списываются, менеджер присылает итог и ссылку на оплату.</p>
			<textarea name="weiss_delivery_tariffs" rows="10" class="large-text code"><?php echo esc_textarea( $raw ); ?></textarea>
			<?php submit_button( 'Сохранить тарифы' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_init', function () {
	register_setting( 'weiss_delivery', 'weiss_delivery_tariffs', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_textarea_field',
		'default'           => weiss_delivery_tariffs_default(),
	) );
} );

/**
 * Тарифы правит менеджер: у него есть manage_woocommerce, а options.php
 * требует manage_options и отклонял сохранение. Поэтому своя обработка.
 */
add_action( 'admin_post_weiss_delivery_save', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Недостаточно прав для изменения тарифов.' );
	}

	check_admin_referer( 'weiss_delivery_save' );

	update_option( 'weiss_delivery_tariffs', sanitize_textarea_field( wp_unslash( $_POST['weiss_delivery_tariffs'] ?? '' ) ) );

	wp_safe_redirect( add_query_arg( array(
		'page'    => 'weiss-delivery',
		'updated' => '1',
	), admin_url( 'admin.php' ) ) );
	exit;
} );
