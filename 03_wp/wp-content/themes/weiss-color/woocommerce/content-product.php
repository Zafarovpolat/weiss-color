<?php
/**
 * Карточка товара в сетке каталога (М15).
 * Разметка совпадает с этапом 1: .product > .product__media + .product__body.
 */

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

$permalink = get_permalink( $product->get_id() );
$image     = weiss_product_card_image( $product );

$is_variable = $product->is_type( 'variable' );
$price       = weiss_product_price_from( $product );
?>
<li <?php wc_product_class( '', $product ); ?>>
	<a class="product__media" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_get_attachment_image уже безопасен ?>
	</a>

	<div class="product__body">
		<h3 class="product__title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>

		<?php if ( $product->get_short_description() ) : ?>
			<p class="product__desc"><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $price ) : ?>
			<p class="product__price tabular"><?php echo esc_html( weiss_price_text( (float) $price, $is_variable ) ); ?></p>
		<?php endif; ?>

		<?php if ( $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) : ?>
			<a class="btn product__btn" href="<?php echo esc_url( $permalink ); ?>"
			   data-weiss-add="<?php echo esc_attr( $product->get_id() ); ?>"
			   rel="nofollow">В корзину</a>
		<?php else : ?>
			<a class="btn product__btn" href="<?php echo esc_url( $permalink ); ?>">Выбрать</a>
		<?php endif; ?>
	</div>
</li>
