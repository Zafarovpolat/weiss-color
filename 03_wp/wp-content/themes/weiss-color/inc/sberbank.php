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

		$response = $this->request(
			'register.do',
			array(
				'orderNumber' => $number,
				'amount'      => $amount,
				'currency'    => '643',
				'language'    => 'ru',
				'description' => 'Заказ № ' . $order->get_order_number() . ' на сайте Weiss Color',
				'returnUrl'   => $order->get_checkout_order_received_url(),
				'failUrl'     => $order->get_checkout_payment_url(),
				'email'       => (string) $order->get_billing_email(),
				'phone'       => preg_replace( '/\D+/', '', (string) $order->get_billing_phone() ),
			)
		);

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
		$paid   = in_array( $status, array( 1, 2 ), true );
		$amount = isset( $response['amount'] ) ? (int) $response['amount'] : 0;

		if ( $paid ) {
			if ( $order->is_paid() ) {
				return true;
			}

			/* Сумма от банка должна совпадать с заказом. */
			$expected = (int) round( (float) $order->get_total() * 100 );
			if ( $amount && $amount !== $expected ) {
				$order->add_order_note( 'Сбербанк: сумма платежа ' . wc_price( $amount / 100 ) . ' не совпадает с заказом ' . wc_price( $expected / 100 ) . '. Оплата не подтверждена автоматически.' );
				return false;
			}

			$order->payment_complete( $bank_order_id );
			$order->add_order_note( 'Оплата подтверждена Сбербанком. Идентификатор платежа банка: ' . $bank_order_id . '.' );
			return true;
		}

		if ( in_array( $status, array( 3, 6 ), true ) && ! $order->is_paid() ) {
			$order->update_status( 'failed', 'Сбербанк: оплата отклонена (статус ' . $status . ').' );
		}

		return false;
	}

	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		$bank_order_id = $order ? (string) $order->get_meta( '_weiss_sber_order_id' ) : '';

		if ( ! $order || '' === $bank_order_id || ! $order->is_paid() ) {
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

/** Уведомление банка: статус подтверждается отдельным запросом к Сбербанку. */
add_action( 'woocommerce_api_weiss_sberbank', function () {
	$bank_order_id = isset( $_POST['mdOrder'] ) ? sanitize_text_field( wp_unslash( $_POST['mdOrder'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

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
 */
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || 'weiss_sberbank' !== $order->get_payment_method() || $order->is_paid() ) {
		return;
	}

	$gateway = new Weiss_Gateway_Sberbank();
	$gateway->sync_order_status( $order );
}, 5 );
