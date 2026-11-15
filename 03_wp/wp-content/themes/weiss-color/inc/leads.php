<?php
/**
 * Обращения с форм «Контакты» и «Заказать звонок» (ТЗ, п.9.3).
 * Сохраняются в панели (тип записи weiss_lead), уходят на согласованный
 * e-mail; ошибка отправки видна администратору.
 */

add_action( 'init', function () {
	register_post_type( 'weiss_lead', array(
		'labels'          => array(
			'name'          => 'Обращения',
			'singular_name' => 'Обращение',
			'menu_name'     => 'Обращения',
			'all_items'     => 'Все обращения',
			'edit_item'     => 'Обращение',
			'view_item'     => 'Обращение',
			'search_items'  => 'Искать обращения',
			'not_found'     => 'Обращений пока нет',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_position'   => 26,
		'menu_icon'       => 'dashicons-email-alt',
		'supports'        => array( 'title' ),
		'capability_type' => array( 'weiss_lead', 'weiss_leads' ),
		'map_meta_cap'    => true,
		'has_archive'     => false,
		'rewrite'         => false,
		'query_var'       => false,
	) );
} );

/** Адрес для уведомлений; задаётся в «Настройки → Формы сайта». */
function weiss_lead_email() {
	$email = sanitize_email( get_option( 'weiss_lead_email' ) );
	return $email ? $email : get_option( 'admin_email' );
}

/** IP посетителя для ограничения частоты. */
function weiss_lead_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

function weiss_lead_fail( $message ) {
	wp_send_json_error( array( 'message' => $message ), 400 );
}

/**
 * Приём формы: защита одноразовым ключом, ловушкой для ботов и лимитом
 * частоты; обязательные поля проверяются повторно на сервере.
 */
function weiss_lead_ajax() {
	check_ajax_referer( 'weiss-lead', 'nonce' );

	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	if ( ! in_array( $type, array( 'callback', 'contact' ), true ) ) {
		weiss_lead_fail( 'Не удалось определить форму. Обновите страницу и попробуйте снова.' );
	}

	if ( ! empty( $_POST['website'] ) ) {
		weiss_lead_fail( 'Не удалось отправить обращение. Попробуйте ещё раз.' );
	}

	$ip  = weiss_lead_ip();
	$key = 'weiss_lead_' . md5( $ip ? $ip : 'unknown' );
	if ( $ip && get_transient( $key ) ) {
		weiss_lead_fail( 'Обращение уже отправлено. Следующее можно отправить через минуту.' );
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$region  = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	$name_length = mb_strlen( trim( $name ) );
	if ( $name_length < 2 || $name_length > 100 ) {
		weiss_lead_fail( 'Укажите имя — от 2 до 100 символов.' );
	}

	$digits = preg_replace( '/\D+/', '', $phone );
	if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
		weiss_lead_fail( 'Введите телефон в формате +7 (999) 123-45-67.' );
	}

	if ( 'contact' === $type && '' === trim( $region ) ) {
		weiss_lead_fail( 'Выберите регион.' );
	}

	if ( mb_strlen( $message ) > 2000 ) {
		weiss_lead_fail( 'Сообщение слишком длинное — не больше 2000 символов.' );
	}

	$page = isset( $_POST['page'] ) ? esc_url_raw( wp_unslash( $_POST['page'] ) ) : '';
	$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	$label = 'callback' === $type ? 'Заказать звонок' : 'Контакты';
	$post  = wp_insert_post( array(
		'post_type'   => 'weiss_lead',
		'post_status' => 'publish',
		'post_title'  => $label . ' — ' . $name . ', ' . current_time( 'd.m.Y H:i' ),
		'meta_input'  => array(
			'_weiss_type'    => $type,
			'_weiss_name'    => $name,
			'_weiss_phone'   => $phone,
			'_weiss_region'  => $region,
			'_weiss_message' => $message,
			'_weiss_page'    => $page,
			'_weiss_ip'      => $ip,
			'_weiss_ua'      => $ua,
		),
	), true );

	if ( is_wp_error( $post ) ) {
		weiss_lead_fail( 'Не удалось сохранить обращение. Попробуйте ещё раз.' );
	}

	if ( $ip ) {
		set_transient( $key, 1, MINUTE_IN_SECONDS );
	}

	$body = array(
		'Тип: ' . $label,
		'Имя: ' . $name,
		'Телефон: ' . $phone,
		'Регион: ' . ( $region ? $region : '—' ),
		'Сообщение: ' . ( $message ? $message : '—' ),
		'Страница: ' . ( $page ? $page : '—' ),
		'IP: ' . ( $ip ? $ip : '—' ),
	);
	$sent = wp_mail(
		weiss_lead_email(),
		'Обращение с сайта: ' . $label . ' — ' . $name,
		implode( "\n", $body )
	);
	update_post_meta( $post, '_weiss_mail', $sent ? 'sent' : 'failed' );

	wp_send_json_success( array( 'id' => $post ) );
}
add_action( 'wp_ajax_weiss_lead', 'weiss_lead_ajax' );
add_action( 'wp_ajax_nopriv_weiss_lead', 'weiss_lead_ajax' );

/* -------------------------------------------------------------------------
 * Администратор: список, карточка, настройка адреса.
 * ---------------------------------------------------------------------- */

add_filter( 'manage_weiss_lead_posts_columns', function ( $columns ) {
	return array(
		'cb'          => $columns['cb'],
		'title'       => 'Обращение',
		'weiss_type'  => 'Форма',
		'weiss_phone' => 'Телефон',
		'weiss_region'=> 'Регион',
		'weiss_mail'  => 'Письмо',
		'date'        => 'Дата',
	);
} );

add_action( 'manage_weiss_lead_posts_custom_column', function ( $column, $post_id ) {
	if ( 'weiss_type' === $column ) {
		echo esc_html( 'callback' === get_post_meta( $post_id, '_weiss_type', true ) ? 'Звонок' : 'Контакты' );
	} elseif ( 'weiss_phone' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_weiss_phone', true ) );
	} elseif ( 'weiss_region' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_weiss_region', true ) ?: '—' );
	} elseif ( 'weiss_mail' === $column ) {
		$mail = get_post_meta( $post_id, '_weiss_mail', true );
		echo 'sent' === $mail
			? '<span style="color:#2e6b4f">отправлено</span>'
			: '<span style="color:#d0433a">не отправлено</span>';
	}
}, 10, 2 );

add_action( 'add_meta_boxes_weiss_lead', function () {
	add_meta_box( 'weiss-lead-details', 'Данные обращения', function ( $post ) {
		$rows = array(
			'Форма'      => 'callback' === get_post_meta( $post->ID, '_weiss_type', true ) ? 'Заказать звонок' : 'Контакты',
			'Имя'        => get_post_meta( $post->ID, '_weiss_name', true ),
			'Телефон'    => get_post_meta( $post->ID, '_weiss_phone', true ),
			'Регион'     => get_post_meta( $post->ID, '_weiss_region', true ),
			'Сообщение'  => get_post_meta( $post->ID, '_weiss_message', true ),
			'Страница'   => get_post_meta( $post->ID, '_weiss_page', true ),
			'IP'         => get_post_meta( $post->ID, '_weiss_ip', true ),
			'Письмо'     => 'sent' === get_post_meta( $post->ID, '_weiss_mail', true ) ? 'отправлено' : 'не отправлено',
		);
		echo '<table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th style="width:140px">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}, 'weiss_lead', 'normal', 'high' );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Формы сайта', 'Формы сайта', 'manage_options', 'weiss-forms', function () {
		?>
		<div class="wrap">
			<h1>Формы сайта</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'weiss_forms' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="weiss-lead-email">E-mail для обращений</label></th>
						<td>
							<input type="email" class="regular-text" id="weiss-lead-email" name="weiss_lead_email"
							       value="<?php echo esc_attr( get_option( 'weiss_lead_email' ) ); ?>"
							       placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
							<p class="description">Если поле пустое, уведомления идут на e-mail администратора. Обращения сохраняются в разделе «Обращения» в любом случае.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	} );
} );

add_action( 'admin_init', function () {
	register_setting( 'weiss_forms', 'weiss_lead_email', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_email',
		'default'           => '',
	) );
} );
