<?php
/**
 * Спасибо за заказ (М08).
 */
$weiss_order = null;

/* Заказ из адреса (ссылка из письма) или из сессии после оформления. */
$weiss_order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$weiss_key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

if ( $weiss_order_id ) {
	$weiss_candidate = wc_get_order( $weiss_order_id );
	if ( $weiss_candidate && ( '' === $weiss_key || hash_equals( $weiss_candidate->get_order_key(), $weiss_key ) ) ) {
		$weiss_order = $weiss_candidate;
	}
}

if ( ! $weiss_order && function_exists( 'WC' ) && WC()->session ) {
	$weiss_session_id  = absint( WC()->session->get( 'weiss_last_order_id' ) );
	$weiss_session_key = (string) WC()->session->get( 'weiss_last_order_key' );

	if ( $weiss_session_id ) {
		$weiss_candidate = wc_get_order( $weiss_session_id );
		if ( $weiss_candidate && ( '' === $weiss_session_key || hash_equals( $weiss_candidate->get_order_key(), $weiss_session_key ) ) ) {
			$weiss_order = $weiss_candidate;
		}
	}
}

if ( $weiss_order ) {
	wp_safe_redirect( $weiss_order->get_checkout_order_received_url() );
	exit;
}

wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
exit;
