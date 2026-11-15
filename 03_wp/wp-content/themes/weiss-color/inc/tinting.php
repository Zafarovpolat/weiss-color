<?php
/**
 * Цена конфигурации с колеровкой (ТЗ, п. 4.5, 6.5, 7.2): сервер считает
 * стоимость выбранной фасовки и согласованной доплаты за колеровку.
 * Пока правила доплаты не заданы, цена конфигурации неизвестна и такая
 * позиция к оплате не допускается — покупатель видит причину и действие.
 *
 * Источники доплаты по убыванию: поле фасовки, поле товара, общая ставка
 * группы в WooCommerce → Колеровка. Пустая строка — «не согласовано».
 */

/** Вариант, у которого поле доплаты не заполнено: 0 — без колеровки. */
function weiss_tint_meta_value( $post_id ) {
	$value = get_post_meta( (int) $post_id, '_weiss_tint_surcharge', true );
	if ( '' === $value || null === $value || false === $value ) {
		return null;
	}

	return round( (float) $value, 2 );
}

/** Общая доплата группы paint/wood из настроек «Колеровка». */
function weiss_tint_group_surcharge( $group ) {
	$saved = get_option( 'weiss_tint_surcharge', array() );
	if ( ! is_array( $saved ) || ! isset( $saved[ $group ] ) ) {
		return null;
	}

	$value = trim( (string) $saved[ $group ] );
	if ( '' === $value || ! is_numeric( $value ) ) {
		return null;
	}

	return round( (float) $value, 2 );
}

/**
 * Доплата за колеровку для товара и фасовки. null — правила доплаты не
 * согласованы; тогда цену конфигурации назвать нельзя.
 */
function weiss_tint_surcharge( $product_id, $variation_id = 0 ) {
	foreach ( array( (int) $variation_id, (int) $product_id ) as $post_id ) {
		if ( ! $post_id ) {
			continue;
		}
		$value = weiss_tint_meta_value( $post_id );
		if ( null !== $value ) {
			return max( 0, $value );
		}
	}

	$group = weiss_product_color_group( $product_id );
	if ( '' === $group ) {
		/* Пробник и товары вне карты совместимости: цена позиции задана
		   самим товаром, отдельной доплаты за колеровку нет. */
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( $product && 'yes' === get_post_meta( $product->get_id(), '_weiss_sampler', true ) ) {
			return 0.0;
		}
		return null;
	}

	return weiss_tint_group_surcharge( $group );
}

/** Цена фасовки без колеровки; null — цена товара не задана. */
function weiss_tint_base_price( $product_id, $variation_id = 0 ) {
	$product = wc_get_product( $variation_id ? $variation_id : $product_id );
	if ( ! $product ) {
		return null;
	}

	$price = $product->get_price();
	if ( '' === $price || null === $price || ! is_numeric( $price ) ) {
		return null;
	}

	return round( (float) $price, 2 );
}

/** Цена единицы с колеровкой: базовая фасовка + согласованная доплата. */
function weiss_tint_unit_price( $product_id, $variation_id = 0 ) {
	$base = weiss_tint_base_price( $product_id, $variation_id );
	if ( null === $base ) {
		return null;
	}

	$surcharge = weiss_tint_surcharge( $product_id, $variation_id );
	if ( null === $surcharge ) {
		return null;
	}

	return round( $base + $surcharge, 2 );
}

/** Текст причины, когда цену конфигурации назвать нельзя (ТЗ, п. 4.5). */
function weiss_tint_unknown_message( $product = null ) {
	$phone = '';
	if ( function_exists( 'weiss_phone' ) ) {
		$phone = str_replace( "\xC2\xA0", ' ', html_entity_decode( wp_strip_all_tags( weiss_phone()['label'] ), ENT_QUOTES, 'UTF-8' ) );
	}
	$name  = $product ? $product->get_name() : '';

	return sprintf(
		'Стоимость колеровки для%s ещё не согласована, поэтому оформить заказ с колеровкой сейчас нельзя. Добавьте базовый цвет без колеровки или свяжитесь с нами%s.',
		$name ? ' «' . $name . '»' : ' этого объёма',
		$phone ? ' по телефону ' . $phone : ''
	);
}
