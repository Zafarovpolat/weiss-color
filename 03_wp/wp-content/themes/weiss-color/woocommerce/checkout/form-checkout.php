<?php
/**
 * Оформление заказа (М01). Разметка повторяет 02_верстка/pages/checkout.html,
 * данные — из WC()->cart и настроек доставки/оплаты. Форма уходит в штатный
 * обработчик WooCommerce (?wc-ajax=checkout), поэтому заказ создаётся
 * средствами магазина, а не демонстрационным JS.
 *
 * @package Weiss_Color
 */

defined( 'ABSPATH' ) || exit;

$weiss_cart  = WC()->cart;
$weiss_items = $weiss_cart->get_cart();
$weiss_units = $weiss_cart->get_cart_contents_count();

$weiss_items_word = static function ( $n ) {
	$mod10  = $n % 10;
	$mod100 = $n % 100;
	if ( 1 === $mod10 && 11 !== $mod100 ) {
		return 'товар';
	}
	if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
		return 'товара';
	}
	return 'товаров';
};

$weiss_money = static function ( $value ) {
	return weiss_price_number( $value ) . ' ₽';
};

$weiss_pickup  = weiss_shipping_method_option( 'local_pickup' );
$weiss_courier = weiss_shipping_method_option( 'flat_rate' );
$weiss_pickup_cost  = $weiss_pickup ? $weiss_pickup['cost'] : 0;
$weiss_courier_cost = $weiss_courier ? $weiss_courier['cost'] : 0;

/* Итого без доставки: сумма позиций минус скидки по промокодам. */
$weiss_base_total = (float) $weiss_cart->total - (float) $weiss_cart->get_shipping_total() - (float) $weiss_cart->get_shipping_tax();

$weiss_gateways = WC()->payment_gateways->get_available_payment_gateways();
$weiss_card     = weiss_card_gateway( $weiss_gateways );
$weiss_bacs     = isset( $weiss_gateways['bacs'] ) ? $weiss_gateways['bacs'] : null;

$weiss_shop_url = get_permalink( wc_get_page_id( 'shop' ) );
$weiss_cart_url = wc_get_cart_url();

/* Ошибки WooCommerce (склад, промокод) — в блок над колонками. */
$weiss_notice_text = '';
foreach ( wc_get_notices( 'error' ) as $weiss_notice ) {
	$weiss_notice_text .= wp_strip_all_tags( $weiss_notice['notice'] ) . ' ';
}
if ( '' !== $weiss_notice_text ) {
	wc_clear_notices();
}
?>

<nav class="crumbs crumbs--checkout" aria-label="Хлебные крошки">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <a href="<?php echo esc_url( $weiss_cart_url ); ?>">Корзина</a> &gt;
	<span aria-current="page">Оформление заказа</span>
</nav>

<h1 class="checkout-title">Оформление заказа</h1>

<p class="checkout-notice" id="checkout-notice" role="alert"<?php echo '' !== $weiss_notice_text ? '' : ' hidden'; ?>><?php echo esc_html( trim( $weiss_notice_text ) ); ?></p>

<div class="checkout-layout">
	<form class="checkout-form" id="checkout-form" method="post" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" novalidate>
		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
		<input type="hidden" name="billing_country" value="RU">

		<section class="form-card" aria-labelledby="contact-title">
			<h2 class="form-card__title" id="contact-title">Контактные данные</h2>

			<div class="form-grid">
				<label class="field">
					<span class="field__label">Ваше имя*</span>
					<input class="field__input" type="text" name="billing_first_name" autocomplete="name" placeholder="Иванов Иван" data-validate-name required>
				</label>

				<label class="field">
					<span class="field__label">Телефон*</span>
					<input class="field__input" type="tel" name="billing_phone" autocomplete="tel" inputmode="tel" placeholder="+7 (___) ___-__-__" required>
				</label>

				<label class="field">
					<span class="field__label">E-mail*</span>
					<input class="field__input" type="email" name="billing_email" autocomplete="email" inputmode="email" placeholder="info@ooonort.ru" required>
				</label>
			</div>
		</section>

		<section class="form-card" aria-labelledby="delivery-title">
			<h2 class="form-card__title" id="delivery-title">Способ доставки</h2>

			<div class="choice-row" role="radiogroup" aria-labelledby="delivery-title">
				<label class="choice">
					<input class="choice__input" type="radio" name="shipping_method[0]" value="<?php echo esc_attr( $weiss_pickup ? $weiss_pickup['value'] : 'local_pickup' ); ?>" data-cost="<?php echo esc_attr( $weiss_pickup_cost ); ?>" checked>
					<span class="choice__radio" aria-hidden="true">
						<svg class="choice__check" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" aria-hidden="true">
							<path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<img class="choice__icon" src="<?php echo esc_url( weiss_asset( 'icons/house.svg' ) ); ?>" width="51" height="49" alt="" aria-hidden="true">
					<span>
						<span class="choice__title">Самовывоз</span>
						<span class="choice__note"><?php echo $weiss_pickup_cost > 0 ? esc_html( weiss_price_compact( $weiss_pickup_cost ) ) : 'Бесплатно'; ?></span>
						<?php
						/* Адрес и часы точки самовывоза (ТЗ 7.6) — из «Тарифов доставки» */
						$weiss_point = weiss_pickup_point();
						if ( $weiss_point['address'] || $weiss_point['hours'] ) :
							?>
							<span class="choice__point">
								<?php if ( $weiss_point['address'] ) : ?>
									<span>Адрес: <?php echo esc_html( $weiss_point['address'] ); ?></span>
								<?php endif; ?>
								<?php if ( $weiss_point['hours'] ) : ?>
									<span>Часы самовывоза: <?php echo esc_html( $weiss_point['hours'] ); ?></span>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</span>
				</label>

				<label class="choice">
					<input class="choice__input" type="radio" name="shipping_method[0]" value="<?php echo esc_attr( $weiss_courier ? $weiss_courier['value'] : 'flat_rate' ); ?>" data-cost="<?php echo esc_attr( $weiss_courier_cost ); ?>">
					<span class="choice__radio" aria-hidden="true">
						<svg class="choice__check" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" aria-hidden="true">
							<path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</span>
					<img class="choice__icon" src="<?php echo esc_url( weiss_asset( 'icons/truck-courier.svg' ) ); ?>" width="48" height="35" alt="" aria-hidden="true">
					<span>
						<span class="choice__title">Курьерская доставка</span>
						<span class="choice__note" id="courier-note"><?php echo esc_html( weiss_price_compact( $weiss_courier_cost ) ); ?></span>
					</span>
				</label>
			</div>
		</section>

		<section class="form-card form-card--address" aria-labelledby="address-title">
			<h2 class="form-card__title" id="address-title">Адрес доставки</h2>
			<p class="visually-hidden" id="address-hint">Адрес нужен для доставки заказа — заполните все поля со звёздочкой.</p>

			<div class="form-grid">
				<div class="field">
					<span class="field__label" id="region-label">Регион<span class="field__req">*</span></span>
					<div class="select" data-select>
						<button class="select__trigger" type="button" aria-haspopup="listbox" aria-expanded="false"
								aria-labelledby="region-label region-value">
							<span class="select__value" id="region-value">Выберите регион</span>
							<svg class="select__chevron" viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
								<path d="M1 1.5 6 6.5l5-5" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</button>
						<ul class="select__list" role="listbox" aria-labelledby="region-label" hidden>
							<?php foreach ( weiss_delivery_tariffs() as $weiss_region => $weiss_tariff ) : ?>
								<li class="select__option" role="option" aria-selected="false" tabindex="-1"
									data-cost="<?php echo esc_attr( null === $weiss_tariff['cost'] ? '' : $weiss_tariff['cost'] ); ?>"
									data-term="<?php echo esc_attr( $weiss_tariff['term'] ); ?>"
									data-free-from="<?php echo esc_attr( $weiss_tariff['free_from'] ); ?>"><?php echo esc_html( $weiss_region ); ?></li>
							<?php endforeach; ?>
						</ul>
						<input type="hidden" name="billing_state" value="" required data-error="Выберите регион"
							   data-validate data-focus=".select__trigger">
					</div>
				</div>

				<label class="field">
					<span class="field__label">Город<span class="field__req">*</span></span>
					<input class="field__input" type="text" name="billing_city" autocomplete="address-level2" placeholder="Например, Казань" required>
				</label>

				<label class="field">
					<span class="field__label">Улица<span class="field__req">*</span></span>
					<input class="field__input" type="text" name="billing_address_1" autocomplete="address-line1" placeholder="Выберите улицу" required>
				</label>

				<label class="field">
					<span class="field__label">Дом<span class="field__req">*</span></span>
					<input class="field__input" type="text" name="weiss_house" placeholder="Например, 10" required>
				</label>

				<label class="field">
					<span class="field__label">Квартира / офис</span>
					<input class="field__input" type="text" name="weiss_flat" maxlength="10" placeholder="Например, 25 или 12А">
				</label>

				<label class="field">
					<span class="field__label">Индекс</span>
					<input class="field__input" type="text" name="billing_postcode" inputmode="numeric" autocomplete="postal-code" placeholder="Например, 123456" pattern="[0-9]{6}" maxlength="6" data-error="Индекс — 6 цифр">
				</label>
			</div>
		</section>

		<section class="form-card" aria-labelledby="payment-title">
			<h2 class="form-card__title" id="payment-title">Способ оплаты</h2>

			<div class="choice-row choice-row--payment" role="radiogroup" aria-labelledby="payment-title">
				<?php if ( $weiss_card ) : ?>
					<label class="choice">
						<input class="choice__input" type="radio" name="payment_method" value="<?php echo esc_attr( $weiss_card->id ); ?>" data-card="1">
						<span class="choice__radio" aria-hidden="true">
							<svg class="choice__check" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" aria-hidden="true">
								<path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
						<img class="choice__icon" src="<?php echo esc_url( weiss_asset( 'icons/card.svg' ) ); ?>" width="51" height="32" alt="" aria-hidden="true">
						<span>
							<span class="choice__title">Банковской картой онлайн</span>
							<span class="choice__note">Visa, MasterCard, Мир</span>
						</span>
					</label>
				<?php endif; ?>

				<?php if ( $weiss_bacs ) : ?>
					<label class="choice">
						<input class="choice__input" type="radio" name="payment_method" value="<?php echo esc_attr( $weiss_bacs->id ); ?>">
						<span class="choice__radio" aria-hidden="true">
							<svg class="choice__check" viewBox="0 0 20 20" fill="none" stroke="#fff" stroke-width="2.2" aria-hidden="true">
								<path d="m4 10.5 4 4 8-9" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</span>
						<img class="choice__icon" src="<?php echo esc_url( weiss_asset( 'icons/document.svg' ) ); ?>" width="40" height="51" alt="" aria-hidden="true">
						<span>
							<span class="choice__title">По счету для юр. лиц</span>
							<span class="choice__note">С НДС или без НДС</span>
						</span>
					</label>
				<?php endif; ?>
			</div>

			<p class="pay-note" id="pay-note" role="status">
				Оплата проходит на защищённой странице Сбербанка. Номер карты и CVV на сайте не вводятся и не сохраняются — после подтверждения заказа вы перейдёте в банковскую форму.
			</p>

			<p class="checkout-quote-note" id="checkout-quote-note" role="status" hidden>
				Для выбранного региона стоимость доставки рассчитает менеджер. Деньги не списываются: после расчёта мы пришлём итог и ссылку на оплату.
			</p>

			<?php /* Заявка на расчёт (ТЗ 7.5): оплата открывается позже, карта не нужна */ ?>
			<input type="hidden" name="payment_method" id="quote-payment" value="weiss_quote" disabled>

			<button class="btn btn--outline invoice-dialog__open" type="button" id="invoice-open" hidden>
				Указать реквизиты для счёта
			</button>

			<input type="hidden" name="weiss_invoice[payer]" value="legal">
			<input type="hidden" name="weiss_invoice[org]" value="">
			<input type="hidden" name="weiss_invoice[inn]" value="">
			<input type="hidden" name="weiss_invoice[kpp]" value="">
			<input type="hidden" name="weiss_invoice[ogrn]" value="">
			<input type="hidden" name="weiss_invoice[address]" value="">
			<input type="hidden" name="weiss_invoice[email]" value="">
			<input type="hidden" name="weiss_invoice[phone]" value="">
			<input type="hidden" name="weiss_invoice[comment]" value="">
		</section>

		<section class="form-card" aria-labelledby="comment-title">
			<h2 class="form-card__title" id="comment-title">Комментарий к заказу</h2>

			<label class="field comment-field">
				<span class="visually-hidden">Комментарий к заказу</span>
				<textarea class="field__input" name="order_comments" rows="2" maxlength="300" placeholder="Напишите комментарий, если нужно (например, удобное время доставки)"></textarea>
			</label>

			<label class="consent">
				<input type="checkbox" name="weiss_consent" id="consent" value="1" required aria-describedby="consent-error">
				<span>
					Я согласен с <a href="<?php echo esc_url( weiss_page_url( 'legal' ) ); ?>">политикой конфиденциальности</a> и обработкой персональных данных
				</span>
			</label>
			<p class="consent__error" id="consent-error" role="alert">Отметьте согласие на обработку персональных данных.</p>
		</section>
	</form>

	<aside class="checkout-panel" aria-labelledby="checkout-panel-title">
		<div class="checkout-panel__head">
			<h2 class="checkout-panel__title" id="checkout-panel-title">Ваш заказ</h2>
			<span class="checkout-panel__count"><?php echo esc_html( $weiss_units . ' ' . $weiss_items_word( $weiss_units ) ); ?></span>
		</div>

		<ul class="checkout-items">
			<?php foreach ( $weiss_items as $weiss_item ) : ?>
				<?php
				$weiss_product = $weiss_item['data'];
				$weiss_parent  = $weiss_product->get_parent_id() ? wc_get_product( $weiss_product->get_parent_id() ) : $weiss_product;
				$weiss_name    = $weiss_parent ? $weiss_parent->get_name() : $weiss_product->get_name();
				$weiss_price   = (float) $weiss_product->get_price();
				$weiss_specs   = array();

				foreach ( (array) $weiss_item['variation'] as $weiss_attr => $weiss_value ) {
					if ( '' === $weiss_value ) {
						continue;
					}
					$weiss_slug       = urldecode( str_replace( 'attribute_', '', $weiss_attr ) );
					$weiss_attr_label = '';
					if ( $weiss_parent ) {
						foreach ( $weiss_parent->get_attributes() as $weiss_attr_key => $weiss_attribute ) {
							if ( urldecode( $weiss_attr_key ) === $weiss_slug || sanitize_title( $weiss_attribute->get_name() ) === $weiss_slug ) {
								$weiss_attr_label = $weiss_attribute->get_name();
								break;
							}
						}
					}
					if ( '' === $weiss_attr_label ) {
						$weiss_attr_label = mb_strtoupper( mb_substr( $weiss_slug, 0, 1 ) ) . mb_substr( $weiss_slug, 1 );
					}
					$weiss_specs[] = $weiss_attr_label . ' ' . $weiss_value;
				}

				if ( ! empty( $weiss_item['weiss_code'] ) ) {
					$weiss_specs[] = 'Цвет ' . $weiss_item['weiss_code'];
					$weiss_specs[] = $weiss_item['weiss_palette'];
				}
				?>
				<li class="checkout-item">
					<?php
					echo wp_get_attachment_image(
						$weiss_product->get_image_id(),
						'weiss-card',
						false,
						array(
							'class'  => 'checkout-item__img',
							'alt'    => $weiss_name,
							'width'  => 68,
							'height' => 100,
						)
					);
					?>
					<div>
						<h3 class="checkout-item__name"><?php echo esc_html( $weiss_name ); ?></h3>
						<?php if ( $weiss_specs ) : ?>
							<p class="checkout-item__spec"><?php echo wp_kses_post( implode( ' &nbsp;|&nbsp; ', array_map( 'esc_html', $weiss_specs ) ) ); ?></p>
						<?php endif; ?>
						<p class="checkout-item__qty"><?php echo esc_html( $weiss_item['quantity'] ); ?> &nbsp;×&nbsp; <?php echo esc_html( $weiss_money( $weiss_price ) ); ?></p>
					</div>
					<p class="checkout-item__sum"><?php echo esc_html( $weiss_money( $weiss_price * $weiss_item['quantity'] ) ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>

		<p class="checkout-delivery">
			Доставка
			<span class="tip">
				<button class="tip__btn" type="button" aria-label="Подробнее о сроках доставки" aria-describedby="delivery-tip">?</button>
				<span class="tip__pop" id="delivery-tip" role="tooltip" data-default="Срок доставки зависит от региона — точные сроки подтвердим при оформлении">Срок доставки зависит от региона — точные сроки подтвердим при оформлении</span>
			</span>
			<span class="checkout-delivery__value" id="delivery-value" data-pickup-cost="<?php echo esc_attr( $weiss_pickup_cost ); ?>" data-courier-cost="<?php echo esc_attr( $weiss_courier_cost ); ?>"><?php echo $weiss_pickup_cost > 0 ? esc_html( $weiss_money( $weiss_pickup_cost ) ) : 'Бесплатно'; ?></span>
		</p>

		<dl class="checkout-total">
			<dt>Итого</dt>
			<dd data-base-total="<?php echo esc_attr( $weiss_base_total ); ?>"><?php echo esc_html( $weiss_money( $weiss_base_total + $weiss_pickup_cost ) ); ?></dd>
		</dl>

		<div class="checkout-actions">
			<button class="checkout-actions__submit" type="submit" form="checkout-form">Подтвердить заказ</button>
			<a class="checkout-actions__back" href="<?php echo esc_url( $weiss_cart_url ); ?>">Вернуться в корзину</a>
		</div>

		<p class="checkout-note">
			<svg viewBox="0 0 20 22" aria-hidden="true" focusable="false">
				<path fill="currentColor" fill-rule="evenodd"
					  d="M2 8.7h16a1.4 1.4 0 0 1 1.4 1.4v9.9a1.4 1.4 0 0 1-1.4 1.4H2a1.4 1.4 0 0 1-1.4-1.4v-9.9A1.4 1.4 0 0 1 2 8.7Zm8 2.9a2 2 0 0 0-1 3.7v3.4a1 1 0 0 0 2 0v-3.4a2 2 0 0 0-1-3.7Z"/>
				<path fill="none" stroke="currentColor" stroke-width="2.2"
					  d="M5.7 9.2V7.3a4.3 4.3 0 0 1 8.6 0v1.9"/>
			</svg>
			Ваши данные защищены и не будут переданы третьим лицам
		</p>
	</aside>
</div>

<dialog class="invoice-dialog" id="invoice-dialog" aria-labelledby="invoice-title">
	<form class="invoice-dialog__body" id="invoice-form" method="dialog" novalidate>
		<h2 class="invoice-dialog__title" id="invoice-title">Реквизиты юридического лица</h2>
		<p class="invoice-dialog__hint">Заполните данные компании для выставления счета</p>

		<fieldset class="invoice-payer">
			<legend class="invoice-payer__legend">Плательщик</legend>
			<label class="invoice-payer__option">
				<input type="radio" name="payer" value="legal" checked>
				<span>Юридическое лицо</span>
			</label>
			<label class="invoice-payer__option">
				<input type="radio" name="payer" value="ip">
				<span>ИП</span>
			</label>
		</fieldset>

		<div class="invoice-dialog__grid">
			<label class="field field--full">
				<span class="field__label">Название организации *</span>
				<input class="field__input" type="text" name="org" autocomplete="organization" placeholder="Например, ООО «СтройГрупп»" required>
			</label>

			<label class="field">
				<span class="field__label">ИНН *</span>
				<input class="field__input" type="text" name="inn" inputmode="numeric" pattern="[0-9]{10}|[0-9]{12}" placeholder="Например, 7701234567" data-error="ИНН — 10 цифр у юрлица или 12 у ИП" required>
			</label>

			<label class="field" data-field="kpp">
				<span class="field__label">КПП *</span>
				<input class="field__input" type="text" name="kpp" inputmode="numeric" pattern="[0-9]{9}" placeholder="Например, 770101001" data-error="КПП — 9 цифр">
			</label>

			<label class="field">
				<span class="field__label" data-ogrn-label>ОГРН *</span>
				<input class="field__input" type="text" name="ogrn" inputmode="numeric" pattern="[0-9]{13}|[0-9]{15}" placeholder="Например, 1027700132195" data-error="ОГРН — 13 цифр, ОГРНИП — 15" required>
			</label>

			<label class="field">
				<span class="field__label">Юридический адрес *</span>
				<input class="field__input" type="text" name="legal-address" autocomplete="street-address" placeholder="Например, 123456, г. Москва, ул. Лесная, д. 10" required>
			</label>

			<label class="field">
				<span class="field__label">E-mail для счета *</span>
				<input class="field__input" type="email" name="invoice-email" inputmode="email" placeholder="example@company.ru" required>
			</label>

			<label class="field">
				<span class="field__label">Телефон*</span>
				<input class="field__input" type="tel" name="invoice-phone" inputmode="tel" placeholder="+7 (___) ___-__-__" required>
			</label>

			<label class="field field--full">
				<span class="field__label">Дополнительные реквизиты / комментарий</span>
				<textarea class="field__input" name="invoice-comment" rows="2" maxlength="300" placeholder="Укажите дополнительные реквизиты, банковские данные или комментарий для менеджера"></textarea>
			</label>
		</div>

		<p class="invoice-dialog__note">
			Указанные данные будут использованы для формирования и отправки счета. Счёт выставляет менеджер после проверки заказа.
		</p>

		<div class="invoice-dialog__actions">
			<button class="invoice-dialog__btn invoice-dialog__btn--ghost" type="submit" value="cancel">Отменить</button>
			<button class="invoice-dialog__btn invoice-dialog__btn--primary" type="submit" value="save">Сохранить</button>
		</div>
	</form>
</dialog>
