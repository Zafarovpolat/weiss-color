<?php
/**
 * Корзина (М16). Разметка повторяет 02_верстка/pages/cart.html,
 * данные — из WC()->cart. Все позиции выводятся одним списком, без
 * фиктивной пагинации (ТЗ 7.6).
 *
 * Шаблон же отдаётся в AJAX-ответе (inc/woocommerce.php), поэтому весь
 * блок обёрнут в #weiss-cart: после изменения количества клиент заменяет
 * его целиком и не считает суммы на JS.
 *
 * @package Weiss_Color
 */

defined( 'ABSPATH' ) || exit;

$weiss_cart  = WC()->cart;
$weiss_items = $weiss_cart ? $weiss_cart->get_cart() : array();
$weiss_units = $weiss_cart ? $weiss_cart->get_cart_contents_count() : 0;

/* Склонение «товар / товара / товаров». */
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

$weiss_label       = $weiss_units . ' ' . $weiss_items_word( $weiss_units );
$weiss_shop_url    = get_permalink( wc_get_page_id( 'shop' ) );
$weiss_checkout    = wc_get_checkout_url();
$weiss_delivery    = weiss_cart_delivery_info( $weiss_cart );
$weiss_discount    = $weiss_cart ? (float) $weiss_cart->get_discount_total() : 0;
$weiss_is_empty    = empty( $weiss_items );
?>

<div id="weiss-cart" data-cart-region>
	<div class="cart-head">
		<nav class="crumbs" aria-label="Хлебные крошки">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <span aria-current="page">Моя корзина</span>
		</nav>
	</div>

	<div class="cart-head__row">
		<h1 class="cart-title">Корзина</h1>
		<?php if ( ! $weiss_is_empty ) : ?>
			<span class="cart-count"><?php echo esc_html( $weiss_label ); ?></span>
		<?php endif; ?>
		<a class="cart-back" href="<?php echo esc_url( $weiss_shop_url ); ?>">
			<svg viewBox="0 0 512 243.58" aria-hidden="true" focusable="false">
				<path fill="currentColor" fill-rule="nonzero" transform="translate(512 0) scale(-1 1)"
					d="M373.57 0 512 120.75 371.53 243.58l-20.92-23.91 94.93-83L0 137.09v-31.75l445.55-.41-92.89-81.02z"/>
			</svg>
			<span class="cart-back__text">Продолжить покупки</span>
		</a>
	</div>

	<?php if ( $weiss_is_empty ) : ?>
		<div class="cart-empty">
			<p class="cart-empty__title">В корзине пока нет товаров</p>
			<p class="cart-empty__text">Загляните в каталог — подберём краску и оттенок.</p>
			<a class="cart-empty__link" href="<?php echo esc_url( $weiss_shop_url ); ?>">Перейти в каталог</a>
		</div>
	<?php else : ?>
		<div class="cart-layout">
			<ul class="cart-list">
				<?php foreach ( $weiss_items as $weiss_key => $weiss_item ) : ?>
					<?php
					$weiss_variation = $weiss_item['data'];
					$weiss_parent    = $weiss_variation->get_parent_id() ? wc_get_product( $weiss_variation->get_parent_id() ) : $weiss_variation;
					$weiss_name      = $weiss_parent ? $weiss_parent->get_name() : $weiss_variation->get_name();
					$weiss_price     = (float) $weiss_variation->get_price();
					$weiss_sum       = $weiss_price * (int) $weiss_item['quantity'];
					$weiss_specs     = array();

					foreach ( (array) $weiss_item['variation'] as $weiss_attr => $weiss_value ) {
						if ( '' === $weiss_value ) {
							continue;
						}

						/* Ключи нелатинских атрибутов WooCommerce хранит urlencoded. */
						$weiss_slug  = urldecode( str_replace( 'attribute_', '', $weiss_attr ) );
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
					?>
					<li class="cart-item" data-price="<?php echo esc_attr( $weiss_price ); ?>" data-cart-key="<?php echo esc_attr( $weiss_key ); ?>">
						<?php
						echo wp_get_attachment_image(
							$weiss_variation->get_image_id(),
							'weiss-card',
							false,
							array(
								'class' => 'cart-item__img',
								'alt'   => $weiss_name,
							)
						);
						?>

						<div class="cart-item__body">
							<h2 class="cart-item__name"><?php echo esc_html( $weiss_name ); ?></h2>
							<?php if ( $weiss_specs || ! empty( $weiss_item['weiss_code'] ) ) : ?>
								<div class="cart-item__meta">
									<?php foreach ( $weiss_specs as $weiss_spec ) : ?>
										<p class="cart-item__spec"><?php echo esc_html( $weiss_spec ); ?></p>
									<?php endforeach; ?>
									<?php if ( ! empty( $weiss_item['weiss_code'] ) ) : ?>
										<p class="cart-item__spec">Цвет <?php echo esc_html( $weiss_item['weiss_code'] ); ?></p>
										<p class="cart-item__spec">Палитра <?php echo esc_html( $weiss_item['weiss_palette'] ); ?></p>
										<?php if ( ! empty( $weiss_item['weiss_surcharge'] ) ) : ?>
											<p class="cart-item__spec">Колеровка +<?php echo esc_html( $weiss_money( $weiss_item['weiss_surcharge'] ) ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<p class="cart-item__stock"><?php echo $weiss_variation->is_in_stock() ? 'В наличии' : 'Нет в наличии'; ?></p>
						</div>

						<div class="cart-item__cols">
							<div>
								<p class="cart-item__col-title">Цена за шт.</p>
								<p class="cart-item__price"><?php echo esc_html( $weiss_money( $weiss_price ) ); ?></p>
								<div class="cart-item__qty">
									<div class="qty">
										<button class="qty__btn qty__btn--minus" type="button" data-qty="dec"
											aria-label="Уменьшить количество" data-cart-key="<?php echo esc_attr( $weiss_key ); ?>">−</button>
										<input class="qty__input" type="number" inputmode="numeric" min="1" step="1"
											value="<?php echo esc_attr( $weiss_item['quantity'] ); ?>"
											data-cart-key="<?php echo esc_attr( $weiss_key ); ?>"
											aria-label="Количество, шт.">
										<button class="qty__btn" type="button" data-qty="inc"
											aria-label="Увеличить количество" data-cart-key="<?php echo esc_attr( $weiss_key ); ?>">+</button>
									</div>
								</div>
							</div>

							<div>
								<p class="cart-item__col-title">Количество</p>
							</div>

							<div>
								<p class="cart-item__col-title">Сумма</p>
								<p class="cart-item__total"><?php echo esc_html( $weiss_money( $weiss_sum ) ); ?></p>
							</div>
						</div>

						<button class="cart-item__del" type="button" data-cart-remove data-cart-key="<?php echo esc_attr( $weiss_key ); ?>"
							aria-label="Удалить позицию: <?php echo esc_attr( $weiss_name ); ?>">
							<img src="<?php echo esc_url( weiss_asset( 'icons/trash.svg' ) ); ?>" width="37" height="44" alt="">
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<aside class="order-panel" aria-labelledby="order-panel-title">
				<div class="order-panel__head">
					<h2 class="order-panel__title" id="order-panel-title">Ваш заказ</h2>
					<span class="order-panel__count"><?php echo esc_html( $weiss_label ); ?></span>
				</div>

				<div class="order-panel__rows">
					<p class="order-panel__row">
						<span class="order-panel__goods">Товары (<?php echo esc_html( $weiss_units ); ?> шт.&nbsp;)</span>
						<span class="order-panel__value"><?php echo esc_html( $weiss_money( $weiss_cart->get_subtotal() ) ); ?></span>
					</p>
					<?php if ( $weiss_discount > 0 ) : ?>
						<p class="order-panel__row">
							Скидка
							<span class="order-panel__value">−<?php echo esc_html( $weiss_money( $weiss_discount ) ); ?></span>
						</p>
					<?php endif; ?>
					<p class="order-panel__row">
						Доставка
						<span class="tip">
							<button class="tip__btn" type="button" aria-label="Подробнее о сроках доставки" aria-describedby="delivery-tip">?</button>
							<span class="tip__pop" id="delivery-tip" role="tooltip">
								<?php echo esc_html( $weiss_delivery['term'] ); ?>
							</span>
						</span>
						<span class="order-panel__value"><?php echo esc_html( $weiss_delivery['label'] ); ?></span>
					</p>
				</div>

				<?php if ( $weiss_delivery['free_from'] ) : ?>
					<div class="order-panel__info">
						<img src="<?php echo esc_url( weiss_asset( 'icons/truck.svg' ) ); ?>" width="67" height="35" alt="" aria-hidden="true">
						<?php if ( $weiss_delivery['free_left'] <= 0 ) : ?>
							<p><strong>Бесплатная доставка при заказе от <?php echo esc_html( weiss_price_number( $weiss_delivery['free_from'] ) ); ?>₽</strong>В вашем заказе бесплатная доставка!</p>
						<?php else : ?>
							<p><strong>Бесплатная доставка при заказе от <?php echo esc_html( weiss_price_number( $weiss_delivery['free_from'] ) ); ?>₽</strong>До бесплатной доставки не хватает <?php echo esc_html( $weiss_money( $weiss_delivery['free_left'] ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="promo">
					<label class="promo__label" for="promo-code">Промокод</label>
					<div class="promo__row">
						<input class="promo__input" id="promo-code" name="promo" type="text" maxlength="24"
							pattern="[A-Za-z0-9-]{2,24}" data-error="Промокод — латинские буквы и цифры"
							placeholder="Введите промокод" autocomplete="off">
						<button class="promo__btn" type="button">Применить</button>
					</div>
					<?php foreach ( $weiss_cart->get_applied_coupons() as $weiss_coupon ) : ?>
						<span class="form-success">Промокод <?php echo esc_html( $weiss_coupon ); ?> применён</span>
					<?php endforeach; ?>
				</div>

				<dl class="order-panel__sum">
					<dt>Итого:</dt>
					<dd><?php echo esc_html( $weiss_money( $weiss_cart->total ) ); ?></dd>
				</dl>

				<div class="order-panel__actions">
					<a class="order-panel__checkout" href="<?php echo esc_url( $weiss_checkout ); ?>">Перейти к оформлению</a>
					<a class="order-panel__continue" href="<?php echo esc_url( $weiss_shop_url ); ?>">Продолжить покупки</a>
				</div>
			</aside>
		</div>

	<?php endif; ?>
</div>
