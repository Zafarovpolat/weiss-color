<?php
/**
 * Страница оплаты по ссылке из письма (ТЗ 7.5): крошки и заголовок
 * над формой form-pay.php, итог без устаревшего способа оплаты.
 */

/* Заголовок ставим до проверки доступа WooCommerce: он нужен и форме
   оплаты, и подтверждению e-mail, которое магазин показывает гостю. */
add_action( 'before_woocommerce_pay', function () {
	global $wp;

	$order_id = isset( $wp->query_vars['order-pay'] ) ? absint( $wp->query_vars['order-pay'] ) : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : null;
	?>
	<nav class="crumbs crumbs--checkout crumbs--pay" aria-label="Хлебные крошки">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
		<span aria-current="page">Оплата заказа</span>
	</nav>
	<h1 class="checkout-title pay-title">
		<?php
		echo $order
			? 'Оплата заказа № ' . esc_html( $order->get_order_number() )
			: 'Оплата заказа';
		?>
	</h1>
	<?php
} );

/* Ссылка на оплату и страница «спасибо» открываются по секретному ключу
   заказа из письма. Повторное подтверждение e-mail на этих страницах
   WooCommerce только мешает гостю оплатить заказ (ТЗ 7.5). */
add_filter( 'woocommerce_order_email_verification_required', function ( $required, $order, $context ) {
	if ( in_array( $context, array( 'order-pay', 'order-received' ), true ) ) {
		return false;
	}

	return $required;
}, 10, 3 );

/* После расчёта доставки заказ ещё не оплачен: строка «Способ оплаты»
   с прежним способом («оплата после расчёта») в итоге только путает. */
add_filter( 'woocommerce_get_order_item_totals', function ( $totals, $order ) {
	if ( ! is_admin() && $order->get_meta( '_weiss_quote_resolved' ) && ! $order->is_paid() ) {
		unset( $totals['payment_method'] );
	}

	return $totals;
}, 10, 2 );
