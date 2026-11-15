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

/* Ошибки проверки реквизитов приходят из woocommerce_before_pay_action:
   страница показывает их так же, как оформление заказа. */
$weiss_notice_text = '';
foreach ( wc_get_notices( 'error' ) as $weiss_notice ) {
	$weiss_notice_text .= wp_strip_all_tags( $weiss_notice['notice'] ) . ' ';
}
if ( '' !== $weiss_notice_text ) {
	wc_clear_notices();
}

$weiss_invoice_saved = (array) $order->get_meta( '_weiss_invoice' );
$weiss_field         = function ( $key, $fallback = '' ) use ( $weiss_invoice_saved ) {
	return isset( $weiss_invoice_saved[ $key ] ) ? (string) $weiss_invoice_saved[ $key ] : $fallback;
};
?>
<p class="checkout-notice" id="checkout-notice" role="alert"<?php echo '' !== $weiss_notice_text ? '' : ' hidden'; ?>><?php echo esc_html( trim( $weiss_notice_text ) ); ?></p>

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

				/* Служебные ключи с подчёркиванием скрыты: «Цвет» и «Палитра»
				   уже выведены отдельными подписями (замечание 78). */
				foreach ( $weiss_item->get_formatted_meta_data() as $weiss_meta ) {
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

		<div class="pay-invoice" id="pay-invoice" hidden>
			<h3 class="pay-invoice__title">Реквизиты для счёта</h3>
			<p class="pay-invoice__hint">Укажите данные плательщика — по ним менеджер выставит счёт (ТЗ 8.2).</p>

			<div class="form-grid">
				<label class="field field--full">
					<span class="field__label">Плательщик</span>
					<select class="field__input" name="weiss_invoice[payer]" disabled>
						<option value="legal" <?php selected( 'ip' !== $weiss_field( 'payer', 'legal' ) ); ?>>Юридическое лицо</option>
						<option value="ip" <?php selected( 'ip' === $weiss_field( 'payer', 'legal' ) ); ?>>ИП</option>
					</select>
				</label>

				<label class="field field--full">
					<span class="field__label">Название организации *</span>
					<input class="field__input" type="text" name="weiss_invoice[org]" autocomplete="organization" placeholder="Например, ООО «СтройГрупп»" value="<?php echo esc_attr( $weiss_field( 'org' ) ); ?>" required disabled>
				</label>

				<label class="field">
					<span class="field__label">ИНН *</span>
					<input class="field__input" type="text" name="weiss_invoice[inn]" inputmode="numeric" placeholder="10 цифр у юрлица, 12 у ИП" value="<?php echo esc_attr( $weiss_field( 'inn' ) ); ?>" required disabled>
				</label>

				<label class="field" data-pay-kpp>
					<span class="field__label">КПП *</span>
					<input class="field__input" type="text" name="weiss_invoice[kpp]" inputmode="numeric" placeholder="9 цифр" value="<?php echo esc_attr( $weiss_field( 'kpp' ) ); ?>" required disabled>
				</label>

				<label class="field">
					<span class="field__label">ОГРН *</span>
					<input class="field__input" type="text" name="weiss_invoice[ogrn]" inputmode="numeric" placeholder="13 цифр, ОГРНИП — 15" value="<?php echo esc_attr( $weiss_field( 'ogrn' ) ); ?>" required disabled>
				</label>

				<label class="field field--full">
					<span class="field__label">Юридический адрес *</span>
					<input class="field__input" type="text" name="weiss_invoice[address]" autocomplete="street-address" placeholder="Например, 123456, г. Москва, ул. Лесная, д. 10" value="<?php echo esc_attr( $weiss_field( 'address' ) ); ?>" required disabled>
				</label>

				<label class="field">
					<span class="field__label">E-mail для счёта *</span>
					<input class="field__input" type="email" name="weiss_invoice[email]" inputmode="email" placeholder="example@company.ru" value="<?php echo esc_attr( $weiss_field( 'email', $order->get_billing_email() ) ); ?>" required disabled>
				</label>

				<label class="field">
					<span class="field__label">Телефон *</span>
					<input class="field__input" type="tel" name="weiss_invoice[phone]" inputmode="tel" placeholder="+7 (___) ___-__-__" value="<?php echo esc_attr( $weiss_field( 'phone', $order->get_billing_phone() ) ); ?>" required disabled>
				</label>

				<label class="field field--full">
					<span class="field__label">Комментарий для бухгалтерии</span>
					<textarea class="field__input" name="weiss_invoice[comment]" rows="2" maxlength="300" placeholder="Банковские данные или пометка для менеджера" disabled><?php echo esc_textarea( $weiss_field( 'comment' ) ); ?></textarea>
				</label>
			</div>
		</div>

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
