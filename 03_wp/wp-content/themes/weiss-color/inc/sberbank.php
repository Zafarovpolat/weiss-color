<?php
/**
 * Интернет-эквайринг Сбербанка (ТЗ 8.3–8.6).
 *
 * Карта вводится только на защищённой странице банка: магазин создаёт
 * платёж, получает адрес платёжной формы и проверяет статус на стороне
 * банка. Успешный возврат покупателя сам по себе оплатой не считается —
 * статус всегда подтверждается запросом getOrderStatusExtended.
 */

defined( 'ABSPATH' ) || exit;

class Weiss_Gateway_Sberbank extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'weiss_sberbank';
		$this->method_title       = 'Банковской картой онлайн (Сбербанк)';
		$this->method_description = 'Оплата на защищённой странице Сбербанка. Номер карты, срок и CVV на сайте не вводятся и не сохраняются.';
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', 'Банковской картой онлайн' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled', 'no' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => 'Включить',
				'type'    => 'checkbox',
				'label'   => 'Показывать оплату картой на оформлении',
				'default' => 'no',
			),
			'title'       => array(
				'title'   => 'Название способа',
				'type'    => 'text',
				'default' => 'Банковской картой онлайн',
			),
			'description' => array(
				'title'   => 'Описание',
				'type'    => 'textarea',
				'default' => 'Переход на защищённую платёжную страницу Сбербанка.',
			),
			'test_mode'   => array(
				'title'   => 'Тестовая среда',
				'type'    => 'checkbox',
				'label'   => 'Использовать тестовый контур Сбербанка',
				'default' => 'yes',
			),
			'username'    => array(
				'title'   => 'Логин эквайринга',
				'type'    => 'text',
				'default' => '',
			),
			'password'    => array(
				'title'   => 'Пароль эквайринга',
				'type'    => 'password',
				'default' => '',
			),
			'send_receipt' => array(
				'title'       => 'Передавать чек в банк (54-ФЗ)',
				'type'        => 'checkbox',
				'label'       => 'Включать состав заказа и НДС в запрос register.do',
				'description' => 'Включайте после того, как Заказчик назовёт систему налогообложения и ставку НДС: без этих данных чек будет неверным.',
				'default'     => 'no',
			),
			'tax_system'  => array(
				'title'   => 'Система налогообложения',
				'type'    => 'select',
				'default' => '0',
				'options' => array(
					'0' => 'Общая',
					'1' => 'УСН, доход',
					'2' => 'УСН, доход минус расход',
					'4' => 'ЕСХН',
					'5' => 'Патент',
				),
			),
			'vat'         => array(
				'title'   => 'НДС в чеке',
				'type'    => 'select',
				'default' => '',
				'options' => array(
					''   => 'Без НДС',
					'0'  => 'НДС 0%',
					'5'  => 'НДС 5% (УСН)',
					'7'  => 'НДС 7% (УСН)',
					'10' => 'НДС 10%',
					'20' => 'НДС 20%',
					'22' => 'НДС 22%',
				),
				'description' => 'Коды taxType для 5 %, 7 % и 22 % банк назначает отдельно: до ответа банка чек с этими ставками не передаётся. Код задаётся фильтром weiss_sber_tax_type_codes.',
			),
		);
	}

	/** Без логина и пароля способ не показывается: демонстрационной оплаты нет. */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		return '' !== trim( (string) $this->get_option( 'username' ) )
			&& '' !== trim( (string) $this->get_option( 'password' ) );
	}

	private function api_url( $method ) {
		$host = 'yes' === $this->get_option( 'test_mode', 'yes' )
			? 'https://3dsec.sberbank.ru'
			: 'https://securepayments.sberbank.ru';

		return $host . '/payment/rest/' . $method;
	}

	private function request( $method, $args ) {
		$args = array_merge(
			array(
				'userName' => trim( (string) $this->get_option( 'username' ) ),
				'password' => trim( (string) $this->get_option( 'password' ) ),
			),
			$args
		);

		$response = wp_remote_post(
			$this->api_url( $method ),
			array(
				'timeout' => 30,
				'body'    => $args,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'weiss_sber_json', 'Банк вернул ответ в неизвестном формате.' );
		}

		return $body;
	}

	/**
	 * Состав чека для банка (orderBundle). Ставка НДС одна на заказ: в
	 * магазине одна ставка, отдельные налоговые классы не ведутся.
	 *
	 * Коды taxType по документации банка: 0 — без НДС, 1 — 0 %, 2 — 10 %,
	 * 6 — 20 %. Код 3 — это 18 %, а не 20 % (замечание 65). Ставки 5 %, 7 %
	 * и 22 % банк кодирует отдельно; их коды задаются фильтром, а пока код
	 * не задан, чек не передаётся — лучше без чека, чем с неверной ставкой.
	 */
	private function order_bundle( $order ) {
		$vat   = (string) $this->get_option( 'vat', '' );
		$codes = apply_filters( 'weiss_sber_tax_type_codes', array(
			''   => 0,
			'0'  => 1,
			'10' => 2,
			'20' => 6,
		) );

		if ( ! isset( $codes[ $vat ] ) ) {
			$order->add_order_note( sprintf(
				'Чек 54-ФЗ не передан: для ставки «%s» не задан код taxType у банка. Укажите код фильтром weiss_sber_tax_type_codes.',
				'' === $vat ? 'без НДС' : $vat . ' %'
			) );
			return '';
		}

		$type     = (int) $codes[ $vat ];
		$items    = array();
		$position = 0;

		$add = function ( $name, $qty, $sum, $code ) use ( &$items, &$position, $vat, $type ) {
			$sum = (float) $sum;
			if ( $qty <= 0 || $sum < 0 ) {
				return;
			}
			$position++;
			/* itemCode обязателен; itemPrice не передаём: банк требует
			   itemAmount = itemPrice × quantity, а округление единичной
			   цены давало 99,99 вместо 100,00 (замечание 65). */
			$row = array(
				'positionId' => $position,
				'name'       => mb_substr( wp_strip_all_tags( (string) $name ), 0, 128 ),
				'itemCode'   => mb_substr( (string) $code, 0, 64 ),
				'quantity'   => array(
					'value'   => (string) wc_format_decimal( $qty, 2 ),
					'measure' => 'шт.',
				),
				'itemAmount' => (int) round( $sum * 100 ),
			);

			if ( '' !== $vat ) {
				$tax_sum = (float) $vat > 0 ? $sum * (float) $vat / ( 100 + (float) $vat ) : 0;
				$row['tax'] = array(
					'taxType' => $type,
					'taxSum'  => (int) round( $tax_sum * 100 ),
				);
			}

			$items[] = $row;
		};

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$code    = $product && $product->get_sku() ? $product->get_sku() : (string) $item->get_product_id();
			$add( $item->get_name(), (float) $item->get_quantity(), (float) $item->get_total() + (float) $item->get_total_tax(), $code );
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$add( 'Доставка', 1, (float) $item->get_total() + (float) $item->get_total_tax(), 'delivery' );
		}

		return wp_json_encode( array( 'cartItems' => array( 'items' => $items ) ) );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( 'Заказ не найден.', 'error' );
			return array( 'result' => 'failure' );
		}

		if ( $order->is_paid() ) {
			wc_add_notice( 'Этот заказ уже оплачен. Повторное списание не выполняется.', 'error' );
			return array( 'result' => 'failure' );
		}

		$amount = (int) round( (float) $order->get_total() * 100 );
		if ( $amount <= 0 ) {
			wc_add_notice( 'Сумма заказа должна быть больше нуля.', 'error' );
			return array( 'result' => 'failure' );
		}

		/* orderNumber в Сбербанке должен быть уникальным для каждой попытки. */
		$number = $order->get_id() . '-' . gmdate( 'YmdHis' );

		$args = array(
			'orderNumber' => $number,
			'amount'      => $amount,
			'currency'    => '643',
			'language'    => 'ru',
			'description' => 'Заказ № ' . $order->get_order_number() . ' на сайте Weiss Color',
			'returnUrl'   => $order->get_checkout_order_received_url(),
			'failUrl'     => $order->get_checkout_payment_url(),
			'email'       => (string) $order->get_billing_email(),
			'phone'       => preg_replace( '/\D+/', '', (string) $order->get_billing_phone() ),
		);

		/* Чек по 54-ФЗ (ТЗ 8.5): состав заказа и НДС передаются только когда
		   Заказчик подтвердил систему налогообложения и ставку. Пустая строка
		   значит, что для выбранной ставки нет кода taxType у банка. */
		if ( 'yes' === $this->get_option( 'send_receipt', 'no' ) ) {
			$bundle = $this->order_bundle( $order );
			if ( '' !== $bundle ) {
				$args['orderBundle'] = $bundle;
				$args['taxSystem']   = (int) $this->get_option( 'tax_system', '0' );
			}
		}

		$response = $this->request( 'register.do', $args );

		if ( is_wp_error( $response ) ) {
			$order->add_order_note( 'Сбербанк: платёж не создан — ' . $response->get_error_message() );
			wc_add_notice( 'Не удалось перейти к оплате. Попробуйте ещё раз или выберите оплату по счёту.', 'error' );
			return array( 'result' => 'failure' );
		}

		if ( ! empty( $response['errorCode'] ) || empty( $response['formUrl'] ) ) {
			$message = isset( $response['errorMessage'] ) ? (string) $response['errorMessage'] : 'банк не вернул адрес платёжной формы';
			$order->add_order_note( 'Сбербанк: ошибка создания платежа — ' . $message . ' (код ' . (int) ( $response['errorCode'] ?? 0 ) . ').' );
			$order->update_meta_data( '_weiss_sber_error', $message );
			$order->save();
			wc_add_notice( 'Банк не принял платёж: ' . esc_html( $message ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_weiss_sber_order_id', sanitize_text_field( $response['orderId'] ) );

		/* Номер каждой попытки: уведомление по предыдущей попытке тоже
		   должно найти заказ, а не только по последней. */
		$attempts   = (array) $order->get_meta( '_weiss_sber_order_ids' );
		$attempts[] = sanitize_text_field( $response['orderId'] );
		$order->update_meta_data( '_weiss_sber_order_ids', array_values( array_unique( $attempts ) ) );

		$order->update_meta_data( '_weiss_sber_order_number', $number );
		$order->update_meta_data( '_weiss_sber_amount', $amount );
		$order->delete_meta_data( '_weiss_sber_error' );
		$order->save();

		$order->update_status( 'pending', 'Платёж создан в Сбербанке, ожидается оплата на стороне банка.' );

		return array(
			'result'   => 'success',
			'redirect' => esc_url_raw( $response['formUrl'] ),
		);
	}

	/**
	 * Проверка статуса на стороне банка. Вызывается из уведомления и при
	 * возврате покупателя; повторный вызов безопасен.
	 */
	public function sync_order_status( $order, $order_id = '' ) {
		$bank_order_id = $order_id ? $order_id : (string) $order->get_meta( '_weiss_sber_order_id' );
		if ( '' === $bank_order_id ) {
			return false;
		}

		$response = $this->request( 'getOrderStatusExtended.do', array( 'orderId' => $bank_order_id ) );
		if ( is_wp_error( $response ) || ! empty( $response['errorCode'] ) ) {
			return false;
		}

		$status = isset( $response['orderStatus'] ) ? (int) $response['orderStatus'] : -1;
		/* 2 — деньги списаны. 1 — только заблокированы: заказ не оплачен. */
		$paid   = 2 === $status;
		$amount = isset( $response['amount'] ) ? (int) $response['amount'] : 0;

		if ( 1 === $status && ! $order->is_paid() ) {
			/* Заметка о блокировке пишется один раз на платёж: возврат
			   покупателя вызывает проверку повторно. */
			if ( (string) $order->get_meta( '_weiss_sber_lock_note' ) !== $bank_order_id ) {
				$order->add_order_note( 'Сбербанк: сумма заблокирована, но ещё не списана (статус 1). Ожидается подтверждение платежа.' );
				$order->update_meta_data( '_weiss_sber_lock_note', $bank_order_id );
				$order->save();
			}
			return false;
		}

		if ( $paid ) {
			if ( $order->is_paid() ) {
				return true;
			}

			/* Сумма от банка должна совпадать с заказом. Пустая или нулевая
			   сумма — не подтверждение: оплату автоматически не закрываем. */
			$expected = (int) round( (float) $order->get_total() * 100 );
			if ( $amount <= 0 || $amount !== $expected ) {
				$order->add_order_note( 'Сбербанк: сумма платежа ' . wc_price( $amount / 100 ) . ' не совпадает с заказом ' . wc_price( $expected / 100 ) . '. Оплата не подтверждена автоматически.' );
				return false;
			}

			$order->payment_complete( $bank_order_id );
			$order->update_meta_data( '_weiss_sber_paid_order_id', $bank_order_id );
			$order->save();
			$order->add_order_note( 'Оплата подтверждена Сбербанком. Идентификатор платежа банка: ' . $bank_order_id . '.' );

			/* Номер фискального чека банк возвращает в статусе платежа (ТЗ 8.5). */
			$receipt = isset( $response['fiscalReceiptNumber'] ) ? sanitize_text_field( (string) $response['fiscalReceiptNumber'] ) : '';
			if ( '' !== $receipt && (string) $order->get_meta( '_weiss_sber_receipt' ) !== $receipt ) {
				$order->update_meta_data( '_weiss_sber_receipt', $receipt );
				$order->save();
				$order->add_order_note( 'Сбербанк: чек зарегистрирован, номер ' . $receipt . '.' );
			}

			return true;
		}

		if ( in_array( $status, array( 3, 6 ), true ) && ! $order->is_paid() ) {
			$order->update_status( 'failed', 'Сбербанк: оплата отклонена (статус ' . $status . ').' );
		}

		return false;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() ) {
			return new WP_Error( 'weiss_sber_refund', 'Нет подтверждённого платежа банка для возврата.' );
		}

		/* Возврат идёт по тому платежу, которым заказ оплачен: последняя
		   попытка могла остаться неуспешной (ТЗ 8.6). */
		$bank_order_id = (string) $order->get_meta( '_weiss_sber_paid_order_id' );
		if ( '' === $bank_order_id ) {
			$bank_order_id = (string) $order->get_meta( '_weiss_sber_order_id' );
		}
		if ( '' === $bank_order_id ) {
			return new WP_Error( 'weiss_sber_refund', 'Нет подтверждённого платежа банка для возврата.' );
		}

		$response = $this->request(
			'refund.do',
			array(
				'orderId' => $bank_order_id,
				'amount'  => (int) round( (float) $amount * 100 ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! empty( $response['errorCode'] ) ) {
			return new WP_Error( 'weiss_sber_refund', isset( $response['errorMessage'] ) ? $response['errorMessage'] : 'Банк отклонил возврат.' );
		}

		$order->add_order_note( 'Сбербанк: возврат ' . wc_price( $amount ) . ' принят банком' . ( $reason ? ' (' . $reason . ')' : '' ) . '.' );
		return true;
	}
}

add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
	$gateways[] = 'Weiss_Gateway_Sberbank';
	return $gateways;
} );

/**
 * Шлюз оплаты картой среди доступных способов. Формы темы ищут его по
 * классу, а не по идентификатору: id менялся, и карта пропадала из
 * оформления, хотя шлюз был включён.
 */
function weiss_card_gateway( $gateways = null ) {
	if ( null === $gateways ) {
		$gateways = WC()->payment_gateways ? WC()->payment_gateways->get_available_payment_gateways() : array();
	}

	foreach ( (array) $gateways as $gateway ) {
		if ( $gateway instanceof Weiss_Gateway_Sberbank ) {
			return $gateway;
		}
	}

	return null;
}

/** Уведомление банка: статус подтверждается отдельным запросом к Сбербанку. */
add_action( 'woocommerce_api_weiss_sberbank', function () {
	/* Банк присылает уведомление POST, но при проверке из панели бывает GET:
	   статус всё равно перезапрашивается у банка, поэтому способ не важен. */
	$bank_order_id = isset( $_REQUEST['mdOrder'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['mdOrder'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( '' === $bank_order_id ) {
		status_header( 400 );
		exit( 'mdOrder is required' );
	}

	$orders = wc_get_orders( array(
		'limit'      => 1,
		'meta_key'   => '_weiss_sber_order_id', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value' => $bank_order_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		'status'     => array_keys( wc_get_order_statuses() ),
	) );

	/* Платёж мог быть создан в предыдущей попытке: ищем по всем номерам. */
	if ( ! $orders ) {
		$orders = wc_get_orders( array(
			'limit'      => 1,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_weiss_sber_order_ids',
					'value'   => '"' . $bank_order_id . '"',
					'compare' => 'LIKE',
				),
			),
			'status'     => array( 'pending', 'on-hold', 'failed' ),
		) );
	}

	if ( ! $orders ) {
		status_header( 404 );
		exit( 'order not found' );
	}

	$gateway = new Weiss_Gateway_Sberbank();
	$gateway->sync_order_status( $orders[0], $bank_order_id );

	status_header( 200 );
	exit( 'OK' );
} );

/**
 * Возврат покупателя: проверяем статус по идентификатору платежа.
 * Открытие страницы успеха само по себе оплату не подтверждает (ТЗ 8.4).
 * Шаблон «Спасибо за заказ» вызывает это событие сам, поэтому проверка
 * срабатывает и без уведомления банка.
 */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'weiss_sberbank' !== $order->get_payment_method() || $order->is_paid() ) {
		return;
	}

	$gateway = new Weiss_Gateway_Sberbank();
	$gateway->sync_order_status( $order );
}, 5 );
