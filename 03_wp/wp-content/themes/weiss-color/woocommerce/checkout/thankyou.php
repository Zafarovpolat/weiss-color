<?php
/**
 * Спасибо за заказ (М07): экран после оформления — номер заказа из WooCommerce,
 * остальная разметка перенесена из 02_верстка/pages/order-success.html.
 */

defined( 'ABSPATH' ) || exit;

/* Заказ не найден или ключ в ссылке неверный. WooCommerce показывает
   такой экран пустым, поэтому объясняем, что произошло (замечание 100). */
if ( ! $order ) {
	?>
	<article class="success-card">
		<nav class="crumbs" aria-label="Хлебные крошки">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
			<span aria-current="page">Заказ не найден</span>
		</nav>

		<h1 class="success-title">Заказ не найден</h1>
		<p class="success-lead">
			Ссылка на заказ недействительна или устарела.<br>
			Проверьте адрес или запросите новую ссылку у менеджера.
		</p>

		<div class="success-actions">
			<a class="success-actions__primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Продолжить покупки</a>
			<a class="success-actions__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a>
		</div>
	</article>
	<?php
	return;
}

/* Шаблон рисует страницу сам, но события WooCommerce нужны: на них
   платёжные шлюзы проверяют статус платежа при возврате покупателя.
   Разметку темы вывод шлюзов не заменяет, поэтому он отбрасывается. */
ob_start();
do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
do_action( 'woocommerce_thankyou', $order->get_id() );
ob_end_clean();

$weiss_phone = weiss_phone();
$weiss_items = $order->get_items();

/* Самовывоз: подсказка о сроке курьерской доставки не показывается (ТЗ 7.6). */
$weiss_pickup = false;
foreach ( $order->get_shipping_methods() as $weiss_shipping ) {
	if ( 0 === strpos( (string) $weiss_shipping->get_method_id(), 'local_pickup' ) ) {
		$weiss_pickup = true;
	}
}
?>
<article class="success-card">
	<nav class="crumbs" aria-label="Хлебные крошки">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt;
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>">Корзина</a> &gt;
		<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>">Оформление заказа</a> &gt;
		<span aria-current="page">Спасибо за заказ</span>
	</nav>

	<div class="success-badge" aria-hidden="true">
		<svg viewBox="0 0 72 51" fill="none" stroke="currentColor" stroke-width="8" aria-hidden="true">
			<path d="M4 27 26 47 68 4" stroke-linecap="round" stroke-linejoin="round"/>
		</svg>
	</div>

	<?php
	/* Отменённый и возвращённый заказ — не «спасибо за заказ»: экран
	   показывает фактическое состояние (замечание 85). */
	$weiss_cancelled = $order->has_status( 'cancelled' );
	$weiss_refunded  = $order->has_status( 'refunded' );

	if ( $weiss_cancelled ) {
		$weiss_title = 'Заказ отменён';
	} elseif ( $weiss_refunded ) {
		$weiss_title = 'Заказ возвращён';
	} elseif ( $order->has_status( 'failed' ) ) {
		$weiss_title = 'Заказ не оплачен';
	} else {
		$weiss_title = 'Спасибо за заказ';
	}
	?>

	<h1 class="success-title"><?php echo esc_html( $weiss_title ); ?></h1>

	<?php if ( $weiss_cancelled ) : ?>
		<p class="success-lead">
			Заказ отменён. Если оплата проходила, деньги вернутся тем же способом.<br>
			Оформить заказ заново можно в каталоге или по телефону.
		</p>
	<?php elseif ( $weiss_refunded ) : ?>
		<p class="success-lead">
			По заказу выполнен возврат. Деньги вернутся тем же способом,<br>
			которым проходила оплата.
		</p>
	<?php elseif ( $order->has_status( 'failed' ) ) : ?>
		<p class="success-lead">
			Не удалось оплатить заказ.<br>
			Попробуйте ещё раз или свяжитесь с нами по телефону.
		</p>
	<?php else : ?>
		<p class="success-lead">
			Мы получили ваш заказ и уже начали его обработку.<br>
			В ближайшее время с вами свяжется наш специалист<br>
			для подтверждения деталей
		</p>
	<?php endif; ?>

	<p class="success-number">
		Номер заказа
		<strong>№ <?php echo esc_html( $order->get_order_number() ); ?></strong>
	</p>

	<?php
	/* ТЗ 8.7: экран показывает действительный статус оплаты. Заявка на
	   расчёт доставки (ТЗ 7.5) оплатой ещё не является. */
	$weiss_quote = '1' === $order->get_meta( '_weiss_shipping_quote' );

	if ( $weiss_cancelled ) {
		$weiss_pay_status = 'Заказ отменён';
	} elseif ( $weiss_refunded ) {
		$weiss_pay_status = 'Возврат выполнен';
	} elseif ( $order->is_paid() ) {
		$weiss_pay_status = 'Оплата подтверждена банком';
	} elseif ( $order->has_status( 'failed' ) ) {
		$weiss_pay_status = 'Оплата не прошла';
	} elseif ( $weiss_quote ) {
		$weiss_pay_status = 'Ожидает расчёта доставки';
	} elseif ( $order->has_status( 'on-hold' ) ) {
		$weiss_pay_status = 'Ожидает подтверждения оплаты';
	} else {
		$weiss_pay_status = 'Ожидает оплаты';
	}
	?>
	<p class="success-status">Статус оплаты: <strong><?php echo esc_html( $weiss_pay_status ); ?></strong></p>

	<?php if ( $weiss_quote && ! $order->is_paid() ) : ?>
		<p class="success-quote">
			Стоимость доставки для вашего региона рассчитает менеджер. Деньги не списаны:
			после расчёта мы пришлём итог и ссылку на оплату.
		</p>
	<?php endif; ?>

	<?php
	/* ТЗ 7.6: при самовывозе показываем адрес и часы точки. */
	$weiss_point = $weiss_pickup ? weiss_pickup_point() : array( 'address' => '', 'hours' => '' );
	if ( $weiss_point['address'] || $weiss_point['hours'] ) :
		?>
		<p class="success-pickup">
			<strong>Самовывоз:</strong>
			<?php if ( $weiss_point['address'] ) : ?>
				<?php echo esc_html( $weiss_point['address'] ); ?>
			<?php endif; ?>
			<?php if ( $weiss_point['hours'] ) : ?>
				<?php echo $weiss_point['address'] ? '<br>' : ''; ?>
				Часы самовывоза: <?php echo esc_html( $weiss_point['hours'] ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( $weiss_items ) : ?>
		<section class="success-summary" aria-labelledby="success-summary-title">
			<h2 class="success-summary__title" id="success-summary-title">Состав заказа</h2>

			<ul class="success-summary__items">
				<?php foreach ( $weiss_items as $weiss_item ) : ?>
					<?php
					$weiss_meta = wc_display_item_meta( $weiss_item, array(
						'echo'      => false,
						'before'    => '<div class="success-summary__meta">',
						'after'     => '</div>',
						'separator' => '<br>',
					) );
					?>
					<li class="success-summary__item">
						<div class="success-summary__info">
							<p class="success-summary__name"><?php echo esc_html( $weiss_item->get_name() ); ?></p>
							<?php echo $weiss_meta; // phpcs:ignore WordPress.Security.EscapeOutput -- WooCommerce экранирует метаданные ?>
						</div>
						<p class="success-summary__qty"><?php echo esc_html( $weiss_item->get_quantity() . ' шт.' ); ?></p>
						<?php /* Сумма строки — до скидки: ниже отдельной строкой идёт
						         «Товары» без скидки и «Скидка», и строки сходятся
						         с итогом (замечание 85). */ ?>
						<p class="success-summary__sum"><?php echo esc_html( weiss_price_number( $weiss_item->get_subtotal() ) . ' ₽' ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>

			<dl class="success-summary__totals">
				<div>
					<dt>Товары</dt>
					<dd><?php echo esc_html( weiss_price_number( $order->get_subtotal() ) . ' ₽' ); ?></dd>
				</div>
				<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
					<div>
						<dt>Скидка</dt>
						<dd>−<?php echo esc_html( weiss_price_number( $order->get_discount_total() ) . ' ₽' ); ?></dd>
					</div>
				<?php endif; ?>
				<div>
					<dt>Доставка</dt>
					<dd>
						<?php
						if ( $weiss_quote ) {
							echo 'Рассчитывается менеджером';
						} elseif ( (float) $order->get_shipping_total() > 0 ) {
							echo esc_html( weiss_price_number( $order->get_shipping_total() ) . ' ₽' );
						} else {
							echo 'Бесплатно';
						}
						?>
					</dd>
				</div>
				<div class="success-summary__total">
					<dt>Итого</dt>
					<dd><?php echo esc_html( weiss_price_number( $order->get_total() ) . ' ₽' ); ?></dd>
				</div>
			</dl>
		</section>
	<?php endif; ?>

	<?php
	$weiss_invoice = (array) $order->get_meta( '_weiss_invoice' );
	/* Реквизиты относятся только к оплате по счёту: у карты и заявки на
	   расчёт этого блока нет, даже если поля формы пришли пустыми (ТЗ 8.2).
	   Пустой набор не показываем: у заявки физлица оставался заголовок
	   с плательщиком «Юридическое лицо» без данных (замечание 100). */
	$weiss_invoice_filled = '' !== trim( (string) ( $weiss_invoice['org'] ?? '' ) ) || '' !== trim( (string) ( $weiss_invoice['inn'] ?? '' ) );
	if ( $weiss_invoice_filled && in_array( $order->get_payment_method(), array( 'bacs', 'weiss_quote' ), true ) ) :
		$weiss_invoice_labels = array(
			'payer'   => 'Плательщик',
			'org'     => 'Организация',
			'inn'     => 'ИНН',
			'kpp'     => 'КПП',
			'ogrn'    => 'ОГРН/ОГРНИП',
			'address' => 'Юридический адрес',
			'email'   => 'E-mail для счёта',
			'phone'   => 'Телефон',
			'comment' => 'Дополнительные реквизиты',
		);
		?>
		<section class="success-invoice" aria-labelledby="success-invoice-title">
			<h2 class="success-invoice__title" id="success-invoice-title">Реквизиты для счёта</h2>
			<dl class="success-invoice__list">
				<?php foreach ( $weiss_invoice_labels as $weiss_key => $weiss_label ) : ?>
					<?php
					$weiss_value = trim( (string) ( $weiss_invoice[ $weiss_key ] ?? '' ) );
					if ( '' === $weiss_value ) {
						continue;
					}
					if ( 'payer' === $weiss_key ) {
						$weiss_value = 'ip' === $weiss_value ? 'ИП' : 'Юридическое лицо';
					}
					?>
					<div>
						<dt><?php echo esc_html( $weiss_label ); ?></dt>
						<dd><?php echo esc_html( $weiss_value ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
			<p class="success-invoice__note">Если реквизиты нужно исправить, сообщите менеджеру — он выставит счёт по обновлённым данным.</p>
		</section>
	<?php endif; ?>

	<hr class="success-divider">

	<ul class="success-tips">
		<li class="success-tip">
			<span class="success-tip__icon" aria-hidden="true">
				<svg viewBox="0 0 52 35" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
					<rect x="1.5" y="1.5" width="49" height="32" rx="2"/>
					<path d="m2 3 24 18L50 3" stroke-linejoin="round"/>
				</svg>
			</span>
			<div>
				<?php if ( 'no' === $order->get_meta( '_weiss_mail_sent' ) ) : ?>
					<p class="success-tip__title">Письмо с подтверждением не удалось отправить</p>
					<p class="success-tip__text">Мы свяжемся с вами по телефону, чтобы подтвердить заказ.</p>
				<?php elseif ( 'yes' === $order->get_meta( '_weiss_mail_sent' ) ) : ?>
					<p class="success-tip__title">Письмо с подтверждением отправлено на e-mail</p>
					<p class="success-tip__text">Мы выслали детали заказа на указанный вами адрес.</p>
				<?php else : ?>
					<p class="success-tip__title">Детали заказа сохранены</p>
					<p class="success-tip__text">Если письмо не пришло, свяжитесь с нами — проверим отправку.</p>
				<?php endif; ?>
			</div>
		</li>

		<li class="success-tip">
			<span class="success-tip__icon" aria-hidden="true">
				<svg viewBox="0 0 42 42" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
					<path d="M11 2h7l3 9-4 3a24 24 0 0 0 11 11l3-4 9 3v7c0 2-2 4-4 4A36 36 0 0 1 7 6c0-2 2-4 4-4Z" stroke-linejoin="round"/>
				</svg>
			</span>
			<div>
				<p class="success-tip__title">Статус заказа можно уточнить по телефону</p>
				<p class="success-tip__text"><?php echo wp_kses_post( $weiss_phone['label'] ); ?> — с 9:00 до 18:00 (МСК).</p>
			</div>
		</li>

		<?php if ( ! $weiss_pickup ) : ?>
		<li class="success-tip">
			<span class="success-tip__icon" aria-hidden="true">
				<svg viewBox="0 0 41 40" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
					<rect x="1.5" y="1.5" width="38" height="37" rx="3"/>
					<path d="M1.5 10h38M8 6v6M33 6v6"/>
					<g fill="currentColor" stroke="none">
						<circle cx="10" cy="18" r="1.7"/><circle cx="17" cy="18" r="1.7"/><circle cx="24" cy="18" r="1.7"/><circle cx="31" cy="18" r="1.7"/>
						<circle cx="10" cy="25" r="1.7"/><circle cx="17" cy="25" r="1.7"/><circle cx="24" cy="25" r="1.7"/><circle cx="31" cy="25" r="1.7"/>
						<circle cx="10" cy="32" r="1.7"/><circle cx="17" cy="32" r="1.7"/><circle cx="24" cy="32" r="1.7"/>
					</g>
				</svg>
			</span>
			<div>
				<p class="success-tip__title">Срок доставки зависит от вашего региона</p>
				<p class="success-tip__text">Мы сообщим точные сроки при подтверждении заказа.</p>
			</div>
		</li>
		<?php endif; ?>
	</ul>

	<div class="success-actions">
		<?php if ( ! $weiss_cancelled && ! $weiss_refunded && $order->needs_payment() ) : ?>
			<a class="success-actions__primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php echo $order->has_status( 'failed' ) ? 'Оплатить ещё раз' : 'Оплатить заказ'; ?></a>
			<a class="success-actions__link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Продолжить покупки</a>
		<?php else : ?>
			<a class="success-actions__primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Продолжить покупки</a>
			<a class="success-actions__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a>
		<?php endif; ?>
	</div>
</article>
