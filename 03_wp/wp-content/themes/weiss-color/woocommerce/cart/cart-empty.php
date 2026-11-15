<?php
/**
 * Пустая корзина (М16). WooCommerce подключает этот шаблон вместо
 * woocommerce/cart/cart.php, когда корзина пуста, поэтому шапку экрана
 * и пустое состояние рисуем здесь же — иначе страница оставалась
 * с английским текстом WooCommerce без заголовка и хлебных крошек.
 *
 * @package Weiss_Color
 */

defined( 'ABSPATH' ) || exit;

$weiss_shop_url = get_permalink( wc_get_page_id( 'shop' ) );
?>

<div id="weiss-cart" data-cart-region>
	<div class="cart-head">
		<nav class="crumbs" aria-label="Хлебные крошки">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <span aria-current="page">Моя корзина</span>
		</nav>
	</div>

	<div class="cart-head__row">
		<h1 class="cart-title">Корзина</h1>
		<a class="cart-back" href="<?php echo esc_url( $weiss_shop_url ); ?>">
			<svg viewBox="0 0 512 243.58" aria-hidden="true" focusable="false">
				<path fill="currentColor" fill-rule="nonzero" transform="translate(512 0) scale(-1 1)"
					d="M373.57 0 512 120.75 371.53 243.58l-20.92-23.91 94.93-83L0 137.09v-31.75l445.55-.41-92.89-81.02z"/>
			</svg>
			<span class="cart-back__text">Продолжить покупки</span>
		</a>
	</div>

	<div class="cart-empty">
		<p class="cart-empty__title">В корзине пока нет товаров</p>
		<p class="cart-empty__text">Загляните в каталог — подберём краску и оттенок.</p>
		<a class="cart-empty__link" href="<?php echo esc_url( $weiss_shop_url ); ?>">Перейти в каталог</a>
	</div>
</div>
