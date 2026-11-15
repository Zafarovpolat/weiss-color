<?php
/**
 * Почта сайта (ТЗ, п.8.7): письма покупателю и менеджеру отправляет
 * WooCommerce. Если транспорт не отправил письмо, ошибка сохраняется
 * здесь и её можно отправить повторно из панели управления.
 */

/** Последние неудачные отправки, новые сверху. */
function weiss_mail_failures() {
	$log = get_option( 'weiss_mail_failures', array() );
	return is_array( $log ) ? $log : array();
}

function weiss_mail_failures_save( $log ) {
	update_option( 'weiss_mail_failures', array_slice( array_values( $log ), 0, 50 ), false );
}

function weiss_mail_recipients( $to ) {
	if ( is_array( $to ) ) {
		return implode( ', ', array_map( 'sanitize_text_field', $to ) );
	}
	return (string) $to;
}

/**
 * Результат штатных писем WooCommerce по заказу: экран «Спасибо за заказ»
 * не должен утверждать, что письмо отправлено, если транспорт его отклонил.
 */
add_action( 'woocommerce_email_sent', function ( $sent, $email_id, $email ) {
	if ( ! $email instanceof WC_Email || ! $email->object instanceof WC_Order ) {
		return;
	}

	$customer_emails = array(
		'customer_processing_order',
		'customer_on_hold_order',
		'customer_completed_order',
		'customer_invoice',
		'customer_failed_order',
	);

	if ( in_array( $email_id, $customer_emails, true ) ) {
		$email->object->update_meta_data( '_weiss_mail_sent', $sent ? 'yes' : 'no' );
		$email->object->save();
	}
}, 10, 3 );

add_action( 'wp_mail_failed', function ( $error ) {	/* повторная отправка из панели не должна плодить дубли в журнале */
	if ( ! empty( $GLOBALS['weiss_resending_mail'] ) ) {
		return;
	}

	$args = $error->get_error_data();
	if ( ! is_array( $args ) ) {
		$args = array();
	}

	$log   = weiss_mail_failures();
	$log[] = array(
		'id'          => wp_generate_uuid4(),
		'time'        => current_time( 'mysql' ),
		'to'          => isset( $args['to'] ) ? weiss_mail_recipients( $args['to'] ) : '',
		'subject'     => isset( $args['subject'] ) ? (string) $args['subject'] : '',
		'message'     => isset( $args['message'] ) ? (string) $args['message'] : '',
		'headers'     => isset( $args['headers'] ) ? $args['headers'] : array(),
		'attachments' => isset( $args['attachments'] ) ? $args['attachments'] : array(),
		'error'       => $error->get_error_message(),
	);
	weiss_mail_failures_save( $log );
} );

/** Раздел «WooCommerce → Письма»: журнал ошибок и повторная отправка. */
add_action( 'admin_menu', function () {
	$count = count( weiss_mail_failures() );
	$label = $count ? sprintf( 'Письма <span class="update-plugins count-%d"><span class="plugin-count">%d</span></span>', $count, $count ) : 'Письма';

	add_submenu_page(
		'woocommerce',
		'Письма и ошибки почты',
		$label,
		'manage_woocommerce',
		'weiss-mail-log',
		'weiss_mail_log_page'
	);
} );

function weiss_mail_log_page() {
	$log    = weiss_mail_failures();
	$notice = isset( $_GET['weiss_mail_notice'] ) ? sanitize_key( wp_unslash( $_GET['weiss_mail_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="wrap">
		<h1>Письма и ошибки почты</h1>

		<?php if ( 'resent' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p>Письмо отправлено повторно.</p></div>
		<?php elseif ( 'failed' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p>Отправить письмо снова не удалось — ошибка осталась в журнале.</p></div>
		<?php endif; ?>

		<p>Письма покупателю и уведомления менеджеру отправляет WooCommerce. Если отправка не удалась, запись остаётся здесь: проверьте адрес и настройки почты хостинга, затем отправьте письмо повторно.</p>

		<?php if ( ! $log ) : ?>
			<p><strong>Ошибок отправки нет.</strong> Все письма ушли без ошибок.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:150px">Когда</th>
						<th style="width:220px">Кому</th>
						<th>Тема и ошибка</th>
						<th style="width:170px">Действие</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ); ?></td>
							<td><?php echo esc_html( $entry['to'] ? $entry['to'] : '—' ); ?></td>
							<td>
								<strong><?php echo esc_html( $entry['subject'] ? $entry['subject'] : 'Без темы' ); ?></strong><br>
								<span style="color:#d0433a"><?php echo esc_html( $entry['error'] ); ?></span><br>
								<details>
									<summary>Текст письма</summary>
									<pre style="white-space:pre-wrap;max-height:220px;overflow:auto"><?php echo esc_html( $entry['message'] ); ?></pre>
								</details>
							</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'weiss_resend_mail' ); ?>
									<input type="hidden" name="action" value="weiss_resend_mail">
									<input type="hidden" name="mail" value="<?php echo esc_attr( $entry['id'] ); ?>">
									<?php submit_button( 'Отправить повторно', 'secondary', 'submit', false ); ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<p class="description">На рабочем хостинге для писем на внешние адреса нужен настроенный транспорт (SMTP или sendmail). Пароли и адреса почты в журнал не попадают.</p>
	</div>
	<?php
}

add_action( 'admin_post_weiss_resend_mail', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	check_admin_referer( 'weiss_resend_mail' );

	$id = isset( $_POST['mail'] ) ? sanitize_text_field( wp_unslash( $_POST['mail'] ) ) : '';
	if ( '' === $id ) {
		wp_safe_redirect( admin_url( 'admin.php?page=weiss-mail-log' ) );
		exit;
	}

	$log   = weiss_mail_failures();
	$entry = null;

	foreach ( $log as $item ) {
		if ( $item['id'] === $id ) {
			$entry = $item;
			break;
		}
	}

	if ( ! $entry ) {
		wp_safe_redirect( admin_url( 'admin.php?page=weiss-mail-log' ) );
		exit;
	}

	$GLOBALS['weiss_resending_mail'] = true;
	$sent = wp_mail( $entry['to'], $entry['subject'], $entry['message'], $entry['headers'], $entry['attachments'] );
	$GLOBALS['weiss_resending_mail'] = false;

	/* запись убираем только после успешной отправки — иначе ошибка остаётся видимой */
	if ( $sent ) {
		$log = array_filter( $log, function ( $item ) use ( $id ) {
			return $item['id'] !== $id;
		} );
		weiss_mail_failures_save( $log );
	}

	wp_safe_redirect( add_query_arg(
		array(
			'page'             => 'weiss-mail-log',
			'weiss_mail_notice' => $sent ? 'resent' : 'failed',
		),
		admin_url( 'admin.php' )
	) );
	exit;
} );
