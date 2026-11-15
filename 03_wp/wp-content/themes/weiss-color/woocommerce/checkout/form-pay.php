<?php
/**
 * Оплата заказа по ссылке (ТЗ 7.5). Штатный шаблон WooCommerce заменён
 * разметкой оформления заказа: слева состав и итог, справа способ оплаты.
 *
 * @package Weiss_Color
 */

defined( 'ABSPATH' ) || exit;

$weiss_totals = $order->get_order_item_totals();
$weiss_first  = true;

/* Карта первой — как в оформлении заказа, даже если в настройках
   WooCommerce счёт стоит выше. Шлюз ищем по классу, не по id. */
$weiss_card_gateway = weiss_card_gateway( $available_gateways );
$weiss_card_id      = $weiss_card_gateway ? $weiss_card_gateway->id : '';
$weiss_order        = $weiss_card_id ? array( $weiss_card_id => 0, 'bacs' => 1 ) : array( 'bacs' => 0 );

uksort( $available_gateways, function ( $a, $b ) use ( $weiss_order ) {
	$left  = isset( $weiss_order[ $a ] ) ? $weiss_order[ $a ] : 9;
	$right = isset( $weiss_order[ $b ] ) ? $weiss_order[ $b ] : 9;
	return $left <=> $right;
} );

$weiss_icons = array( 'bacs' => array( 'icons/document.svg', 40, 51, 'С НДС или без НДС' ) );
if ( $weiss_card_id ) {
	$weiss_icons[ $weiss_card_id ] = array( 'icons/card.svg', 51, 32, 'Visa, MasterCard, Мир' );
}
?>
<form id="order_review" method="post" class="checkout-layout pay-form">

	<div class="form-card pay-summary">
		<div class="checkout-panel__head">
			<h2 class="checkout-panel__title">Заказ № <?php echo esc_html( $order->get_order_number() ); ?></h2>
			<span class="checkout-panel__count"><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'd.m.Y' ) ); ?></span>
		</div>

		<ul class="checkout-items">
			<?php foreach ( $order->get_items() as $weiss_item ) : ?>
				<?php
				if ( ! apply_filters( 'woocommerce_order_item_visible', true, $weiss_item ) ) {
					continue;
				}
				$weiss_product = $weiss_item->get_product();
				$weiss_specs   = array();
				$weiss_qty     = (int) $weiss_item->get_quantity();
				$weiss_unit    = $weiss_qty ? (float) $weiss_item->get_subtotal() / $weiss_qty : 0;

				foreach ( $weiss_item->get_formatted_meta_data( '' ) as $weiss_meta ) {
					$weiss_specs[] = wp_strip_all_tags( $weiss_meta->display_key ) . ' ' . wp_strip_all_tags( $weiss_meta->display_value );
				}
				?>
				<li class="checkout-item">
					<?php if ( $weiss_product ) : ?>
						<?php echo wp_get_attachment_image( $weiss_product->get_image_id(), 'weiss-card', false, array( 'class' => 'checkout-item__img', 'alt' => $weiss_item->get_name(), 'width' => 68, 'height' => 100 ) ); ?>
					<?php endif; ?>
					<div>
						<h3 class="checkout-item__name"><?php echo esc_html( $weiss_item->get_name() ); ?></h3>
						<?php if ( $weiss_specs ) : ?>
							<p class="checkout-item__spec"><?php echo wp_kses_post( implode( ' &nbsp;|&nbsp; ', array_map( 'esc_html', $weiss_specs ) ) ); ?></p>
						<?php endif; ?>
						<p class="checkout-item__qty"><?php echo esc_html( $weiss_qty ); ?> &nbsp;×&nbsp; <?php echo wp_kses_post( wc_price( $weiss_unit, array( 'decimals' => 0 ) ) ); ?></p>
					</div>
					<p class="checkout-item__sum"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $weiss_item ) ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php foreach ( $weiss_totals as $weiss_key => $weiss_total ) : ?>
			<?php if ( 'order_total' === $weiss_key ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<p class="checkout-delivery">
				<?php echo esc_html( rtrim( wp_strip_all_tags( $weiss_total['label'] ), ':' ) ); ?>
				<span class="checkout-delivery__value"><?php echo wp_kses_post( $weiss_total['value'] ); ?></span>
			</p>
		<?php endforeach; ?>

		<dl class="checkout-total">
			<dt>Итого</dt>
			<dd><?php echo wp_kses_post( $weiss_totals['order_total']['value'] ); ?></dd>
		</dl>
	</div>

	<section class="form-card pay-payment" aria-labelledby="pay-method-title">
		<h2 class="form-card__title" id="pay-method-title">Способ оплаты</h2>

		<div class="choice-row choice-row--payment" role="radiogroup" aria-labelledby="pay-method-title">
			<?php foreach ( $available_gateways as $weiss_gateway ) : ?>
				<?php $weiss_icon = isset( $weiss_icons[ $weiss_gateway->id ] ) ? $weiss_icons[ $weiss_gateway->id ] : null; ?>
				<label class="choice">
					<input class="choice__input" type="radio" name="payment_method" value="<?php echo esc_attr( $weiss_gateway->id ); ?>" <?php checked( $weiss_first ); ?>>
					<span class="choice__radio" aria-hidden="true">
						<svg class="choice__check" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" aria-hidden="true">
							<path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<?php if ( $weiss_icon ) : ?>
						<img class="choice__icon" src="<?php echo esc_url( weiss_asset( $weiss_icon[0] ) ); ?>" width="<?php echo esc_attr( $weiss_icon[1] ); ?>" height="<?php echo esc_attr( $weiss_icon[2] ); ?>" alt="" aria-hidden="true">
					<?php endif; ?>
					<span>
						<span class="choice__title"><?php echo esc_html( $weiss_gateway->get_title() ); ?></span>
						<?php if ( $weiss_icon ) : ?>
							<span class="choice__note"><?php echo esc_html( $weiss_icon[3] ); ?></span>
						<?php endif; ?>
					</span>
				</label>
				<?php $weiss_first = false; ?>
			<?php endforeach; ?>
		</div>

		<p class="pay-note pay-note--card">
			Оплата проходит на защищённой странице банка. Номер карты и CVV на сайте не вводятся и не сохраняются.
		</p>
		<p class="pay-note pay-note--bacs">
			После подтверждения заказа мы выставим счёт и пришлём его на e-mail. Заказ резервируется сразу.
		</p>

		<p class="pay-consent">
			Нажимая «Оплатить заказ», вы соглашаетесь с
			<a href="<?php echo esc_url( weiss_page_url( 'legal' ) ); ?>">политикой конфиденциальности</a>
			и обработкой персональных данных.
		</p>

		<div class="checkout-actions">
			<button type="submit" class="checkout-actions__submit" id="place_order" name="place_order" value="<?php echo esc_attr( $order_button_text ); ?>"><?php echo esc_html( $order_button_text ); ?></button>
			<a class="checkout-actions__back" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Вернуться в каталог</a>
		</div>

		<input type="hidden" name="woocommerce_pay" value="1">
		<?php wp_nonce_field( 'woocommerce-pay', 'woocommerce-pay-nonce' ); ?>
	</section>
</form>
