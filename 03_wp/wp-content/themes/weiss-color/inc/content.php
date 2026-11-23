<?php
/**
 * Содержимое сайта (ТЗ, п.3.1, 9.1, 9.2, 10.1): телефон, каналы связи,
 * карточки маркетплейсов и тексты главной меняются из панели без правки кода.
 * Значения по умолчанию совпадают с макетами, поэтому внешний вид не меняется.
 */

function weiss_content_defaults() {
	return array(
		'phone_label'     => '+7(950)&nbsp;255-30-15',
		'phone_href'      => 'tel:+79502553015',
		'email'           => 'info@ooonort.ru',
		'seller_name'     => 'ООО «Фаер Гвард»',
		'seller_address'  => '',
		'seller_inn'      => '',
		'seller_ogrn'     => '',
		'seller_kpp'      => '',
		'social_max'      => 'https://max.ru',
		'social_telegram' => 'https://t.me/weisscolor',
		'social_whatsapp' => 'https://wa.me/79502553015',
		'buy_1_name'      => 'Wildberries',
		'buy_1_text'      => 'Широкий ассортимент красок<br>WEISS с удобной доставкой<br>по всей России.',
		'buy_1_logo'      => '',
		'buy_1_url'       => 'https://www.wildberries.ru/',
		'buy_2_name'      => 'Ozon',
		'buy_2_text'      => 'Официальная продукция WEISS<br>на маркетплейсе Ozon.<br>Быстро и удобно.',
		'buy_2_logo'      => '',
		'buy_2_url'       => 'https://www.ozon.ru/',
		'buy_3_name'      => 'Леруа Мерлен',
		'buy_3_text'      => 'Краски WEISS в магазинах<br>и онлайн-каталоге Леруа Мерлен<br>рядом с вами.',
		'buy_3_logo'      => '',
		'buy_3_url'       => 'https://leroymerlin.ru/',
		'buy_4_name'      => 'Яндекс Маркет',
		'buy_4_text'      => 'Покупайте продукцию WEISS<br>на Яндекс Маркете с быстрой<br>доставкой и выгодными<br>предложениями.',
		'buy_4_logo'      => '',
		'buy_4_url'       => 'https://market.yandex.ru/',
		'hero_eyebrow'    => 'Идеальное решение',
		'hero_title'      => 'Для вашего интерьера',
		'hero_lead'       => 'Краска для стен и потолков интерьерная, матовая',
		'hero_url'        => '',
		'hero_bg'         => '',
		'hero_can'        => '',
		'hero_can_alt'    => 'Банка краски Acrilux — краска для стен и потолков интерьерная, матовая',
		'coloring_title'  => 'Колеровка онлайн',
		'coloring_text'   => 'Подберите идеальный цвет для вашего интерьера с помощью каталога ведущих производителей красок',
		'coloring_url'    => '',
		'coloring_fan'    => '',
		'coloring_fan_mobile' => '',
		'coloring_scene'  => '',
		'coloring_scene_alt' => 'Интерьер с окрашенной стеной из каталога цветов Weiss Color',
		'benefits_heading' => 'Почему стоит<br>обратиться<br><span class="benefits__heading-accent">к профессионалам</span>',
		'benefit_1_title' => 'Оригинальная продукция',
		'benefit_1_text'  => 'Мы предоставляем только<br class="d-br"> оригинальную продукцию<br class="d-br"> от ведущих производителей,<br class="d-br"> гарантируем соответствие<br class="d-br"> стандартам качества',
		'benefit_2_title' => 'Индивидуальный подход',
		'benefit_2_text'  => 'Наши менеджеры<br class="d-br"> и цвет-эксперты помогают<br class="d-br"> в подборе цвета, предлагая<br class="d-br"> индивидуальные решения,<br class="d-br"> разработанные с учетом ваших<br class="d-br"> вкусов и потребностей',
		'benefit_3_title' => 'Широкий ассортимент',
		'benefit_3_text'  => 'Предлагаем краски и лаки<br class="d-br"> на водной и органической<br class="d-br"> основе, наборы из большого<br class="d-br"> каталога ведущих брендов<br class="d-br"> нашего ассортимента',
		'benefit_4_title' => 'Профессиональная консультация',
		'benefit_4_text'  => 'Наши эксперты всегда готовы<br class="d-br"> предоставить профессиональный совет<br class="d-br"> и помочь при выборе, сделать правильный выбор',
		'about_text'      => "WEISS COLOR — российский производитель лакокрасочной продукции для профессионального и бытового применения.\n\n"
			. "Мы создаём высококачественные краски и декоративные покрытия, сочетающие современные технологии, экологичность и эстетику.\n\n"
			. "Наша миссия — делать мир ярче и безопаснее, предлагая материалы, которые вдохновляют на создание красивых и долговечных интерьеров.\n\n"
			. "Производство организовано на современном оборудовании с многоступенчатым контролем качества<br class=\"about-br\">на всех этапах — от отбора сырья до упаковки готовой продукции.\n\n"
			. "Мы постоянно развиваемся, расширяем ассортимент и совершенствуем формулы, чтобы соответствовать<br class=\"about-br\">потребностям наших клиентов и высоким стандартам рынка.\n\n"
			. "Продукция WEISS COLOR используется в жилых и коммерческих интерьерах, рекомендована<br class=\"about-br\">профессиональными малярами, дизайнерами и архитекторами.",
		'about_feature_1_title' => 'Качество<br>и безопасность',
		'about_feature_1_text'  => 'Сырьё от проверенных<br class="about-br"> поставщиков и строгий<br class="about-br"> контроль качества',
		'about_feature_2_title' => 'Экологичность',
		'about_feature_2_text'  => 'Продукция соответствует<br class="about-br"> экологическим<br class="about-br"> стандартам и безопасна<br class="about-br"> для здоровья',
		'about_feature_3_title' => 'Широкий ассортимент',
		'about_feature_3_text'  => 'Краски для стен и потолков,<br class="about-br"> декоративные покрытия,<br class="about-br"> составы и сопутствующие<br class="about-br"> товары',
		'about_feature_4_title' => 'Поддержка клиентов',
		'about_feature_4_text'  => 'Профессиональные<br class="about-br"> консультации и помощь<br class="about-br"> на всех этапах выбора',
		'redirects'       => '',
		'orders_email'    => '',
	);
}

/**
 * Значение настройки. Пока поле ни разу не сохраняли — значение из макета;
 * после сохранения берётся ровно то, что записано, включая пустое: очищенное
 * поле скрывает элемент, а не возвращает подпись из макета (замечание 80).
 */
function weiss_opt( $key ) {
	$values = get_option( 'weiss_content', array() );
	if ( is_array( $values ) && array_key_exists( $key, $values ) ) {
		return $values[ $key ];
	}

	$defaults = weiss_content_defaults();
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/**
 * Пока пустое значение означало «как в макете», сохранённые пустые строки
 * ни на что не влияли. Теперь они скрывают элемент, поэтому старые пустые
 * значения убираем один раз: иначе после обновления исчезли бы телефон,
 * кнопки мессенджеров и карточки «Где купить».
 */
add_action( 'init', function () {
	if ( '1' === get_option( 'weiss_content_empty_migrated' ) ) {
		return;
	}

	$values = get_option( 'weiss_content', null );
	if ( is_array( $values ) ) {
		$values = array_filter( $values, function ( $value ) {
			return '' !== trim( (string) $value );
		} );
		if ( $values ) {
			update_option( 'weiss_content', $values );
		} else {
			delete_option( 'weiss_content' );
		}
	}

	update_option( 'weiss_content_empty_migrated', '1', false );
}, 9 );

/**
 * Переносы в карточке 04 «Почему стоит обратиться» не совпадали с макетом:
 * из-за них текст ломался на 6 строк вместо 5. Если сохранённая настройка
 * хранит старую разбивку, заменяем её на макетную один раз.
 */
add_action( 'init', function () {
	if ( '1' === get_option( 'weiss_benefit4_br_migrated' ) ) {
		return;
	}

	$old      = 'Наши эксперты всегда готовы<br class="d-br"> предоставить профессиональный<br class="d-br"> совет и помочь при выборе, сделать<br class="d-br"> правильный выбор';
	$new      = 'Наши эксперты всегда готовы<br class="d-br"> предоставить профессиональный совет<br class="d-br"> и помочь при выборе, сделать правильный выбор';
	$values   = get_option( 'weiss_content', null );

	if ( is_array( $values ) && isset( $values['benefit_4_text'] ) && $old === $values['benefit_4_text'] ) {
		$values['benefit_4_text'] = $new;
		update_option( 'weiss_content', $values );
	}

	update_option( 'weiss_benefit4_br_migrated', '1', false );
}, 9 );

/** Текст с переносами макета: разрешены только <br> и <span> с классами. */
function weiss_opt_html_kses( $value ) {
	return wp_kses( (string) $value, array(
		'br'   => array( 'class' => true ),
		'span' => array( 'class' => true ),
	) );
}

function weiss_opt_html( $key ) {
	return weiss_opt_html_kses( weiss_opt( $key ) );
}

function weiss_phone() {
	return array(
		'href'  => weiss_opt( 'phone_href' ),
		'label' => weiss_opt( 'phone_label' ),
	);
}

/**
 * Телефон со ссылкой: очищенная подпись скрывает его целиком, подпись без
 * ссылки выводится текстом — на сайте не должно быть ссылок с пустым href.
 */
function weiss_phone_html( $class = '' ) {
	$phone = weiss_phone();
	$label = trim( (string) $phone['label'] );
	if ( '' === $label ) {
		return '';
	}

	$attr = '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '';
	$href = trim( (string) $phone['href'] );
	if ( '' === $href ) {
		return '<span' . $attr . ' translate="no">' . wp_kses_post( $label ) . '</span>';
	}

	return '<a' . $attr . ' href="' . esc_url( $href ) . '" translate="no">' . wp_kses_post( $label ) . '</a>';
}

/**
 * Канал связи: сохранённое пустое поле скрывает кнопку, а значение по
 * умолчанию подставляется только пока поле ни разу не сохраняли (ТЗ 3.1).
 */
function weiss_social( $key ) {
	$values = get_option( 'weiss_content', array() );
	if ( is_array( $values ) && array_key_exists( $key, $values ) ) {
		return trim( (string) $values[ $key ] );
	}

	$defaults = weiss_content_defaults();
	return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
}

/** Адрес для уведомлений о заказах: менеджер, а не администратор сайта. */
function weiss_orders_email() {
	$email = sanitize_email( weiss_opt( 'orders_email' ) );
	return $email ? $email : get_option( 'admin_email' );
}

add_filter( 'woocommerce_email_recipient_new_order', function ( $recipient, $order ) {
	return weiss_orders_email();
}, 10, 2 );

add_filter( 'woocommerce_email_recipient_cancelled_order', function ( $recipient, $order ) {
	return weiss_orders_email();
}, 10, 2 );

add_filter( 'woocommerce_email_recipient_failed_order', function ( $recipient, $order ) {
	return weiss_orders_email();
}, 10, 2 );

/* -------------------------------------------------------------------------
 * Настройки → Содержимое сайта
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page( 'Содержимое сайта', 'Содержимое сайта', 'manage_options', 'weiss-content', 'weiss_content_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'weiss_content', 'weiss_content', array(
		'type'              => 'array',
		'sanitize_callback' => 'weiss_content_sanitize',
		'default'           => array(),
	) );
} );

function weiss_content_sanitize( $input ) {
	$urls   = array( 'phone_href', 'social_max', 'social_telegram', 'social_whatsapp', 'buy_1_url', 'buy_2_url', 'buy_3_url', 'buy_4_url', 'hero_url', 'coloring_url' );
	$emails = array( 'email', 'orders_email' );
	$html   = array( 'benefits_heading', 'benefit_1_text', 'benefit_2_text', 'benefit_3_text', 'benefit_4_text', 'buy_1_text', 'buy_2_text', 'buy_3_text', 'buy_4_text', 'about_text', 'about_feature_1_title', 'about_feature_1_text', 'about_feature_2_title', 'about_feature_2_text', 'about_feature_3_title', 'about_feature_3_text', 'about_feature_4_title', 'about_feature_4_text' );
	$texts  = array( 'redirects' );
	$images = array( 'hero_bg', 'hero_can', 'coloring_fan', 'coloring_fan_mobile', 'coloring_scene', 'buy_1_logo', 'buy_2_logo', 'buy_3_logo', 'buy_4_logo' );

	$existing = (array) get_option( 'weiss_content', array() );
	/* WordPress вызывает колбэк дважды за сохранение (update_option и
	   add_option), поэтому защита от повтора должна жить весь запрос. */
	static $reported = array();

	$clean = array();
	foreach ( (array) $input as $key => $value ) {
		$key = sanitize_key( $key );

		if ( in_array( $key, $images, true ) ) {
			$clean[ $key ] = absint( $value );
		} elseif ( in_array( $key, $urls, true ) ) {
			$clean[ $key ] = 'phone_href' === $key ? sanitize_text_field( $value ) : esc_url_raw( $value );
		} elseif ( in_array( $key, $emails, true ) ) {
			$clean[ $key ] = sanitize_email( $value );
			if ( '' !== trim( (string) $value ) && '' === $clean[ $key ] ) {
				/* Неверный адрес не стирает прежний: он оставался на сайте,
				   а покупателю писали уже не туда (замечание 101). */
				$clean[ $key ] = isset( $existing[ $key ] ) ? (string) $existing[ $key ] : '';

				/* Колбэк регистрации настройки WordPress вызывает дважды за
				   сохранение — сообщение не должно дублироваться. */
				if ( ! isset( $reported[ $key ] ) ) {
					$reported[ $key ] = true;
					$labels = array(
						'email'        => 'E-mail магазина',
						'orders_email' => 'E-mail менеджера для заказов',
					);
					add_settings_error(
						'weiss_content',
						'weiss_content_' . $key,
						sprintf(
							'Поле «%s»: адрес не изменён — проверьте формат.%s',
							isset( $labels[ $key ] ) ? $labels[ $key ] : $key,
							'' !== $clean[ $key ] ? ' Сохранён прежний адрес.' : ''
						),
						'error'
					);
				}
			}
		} elseif ( in_array( $key, $texts, true ) ) {
			$clean[ $key ] = sanitize_textarea_field( $value );
		} elseif ( in_array( $key, $html, true ) ) {
			$clean[ $key ] = wp_kses( (string) $value, array(
				'br'   => array( 'class' => true ),
				'span' => array( 'class' => true ),
			) );
		} else {
			$clean[ $key ] = sanitize_text_field( $value );
		}
	}

	return $clean;
}

function weiss_content_page() {
	$d             = weiss_content_defaults();
	$weiss_values  = get_option( 'weiss_content', array() );
	wp_enqueue_media();
	?>
	<div class="wrap">
		<h1>Содержимое сайта</h1>
		<p class="description">В полях — действующие значения. Очистите поле и сохраните, чтобы скрыть элемент на сайте.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'weiss_content' ); ?>

			<h2>Контакты и продавец</h2>
			<table class="form-table" role="presentation">
				<?php
				weiss_content_row( 'phone_label', 'Телефон на сайте', $d['phone_label'] );
				weiss_content_row( 'phone_href', 'Ссылка телефона (tel:)', $d['phone_href'] );
				weiss_content_row( 'email', 'E-mail магазина', $d['email'], 'email' );
				weiss_content_row( 'seller_name', 'Наименование продавца', $d['seller_name'] );
				weiss_content_row( 'seller_address', 'Адрес продавца', $d['seller_address'], 'text', 'Юридический адрес: показывается на «Контактах» и в записи о продавце.' );
				weiss_content_row( 'seller_inn', 'ИНН', $d['seller_inn'] );
				weiss_content_row( 'seller_ogrn', 'ОГРН / ОГРНИП', $d['seller_ogrn'] );
				weiss_content_row( 'seller_kpp', 'КПП', $d['seller_kpp'] );
				weiss_content_row( 'orders_email', 'E-mail менеджера для заказов', $d['orders_email'], 'email', 'На этот адрес приходят уведомления о новых заказах. Пусто — адрес администратора сайта.' );
				?>
			</table>

			<h2>Каналы связи</h2>
			<table class="form-table" role="presentation">
				<?php
				weiss_content_row( 'social_max', 'Ссылка MAX', $d['social_max'], 'url' );
				weiss_content_row( 'social_telegram', 'Ссылка Telegram', $d['social_telegram'], 'url' );
				weiss_content_row( 'social_whatsapp', 'Ссылка WhatsApp', $d['social_whatsapp'], 'url' );
				?>
			</table>

			<h2>Где купить</h2>
			<table class="form-table" role="presentation">
				<?php
				$buy_logos = array( 1 => 'img/buy-wb.svg', 2 => 'img/buy-ozon.svg', 3 => 'img/buy-lm.svg', 4 => 'img/buy-ym.svg' );
				for ( $i = 1; $i <= 4; $i++ ) {
					weiss_content_row( 'buy_' . $i . '_name', 'Площадка ' . $i . ' — название', $d[ 'buy_' . $i . '_name' ] );
					weiss_content_image_row( 'buy_' . $i . '_logo', 'Площадка ' . $i . ' — логотип', $buy_logos[ $i ] );
					weiss_content_row( 'buy_' . $i . '_text', 'Площадка ' . $i . ' — описание', $d[ 'buy_' . $i . '_text' ], 'textarea', 'Перенос строки — <br>.' );
					weiss_content_row( 'buy_' . $i . '_url', 'Площадка ' . $i . ' — ссылка', $d[ 'buy_' . $i . '_url' ], 'url' );
				}
				?>
			</table>

			<h2>Главная страница: изображения</h2>
			<table class="form-table" role="presentation">
				<?php
				weiss_content_image_row( 'hero_bg', 'Баннер: фоновое фото', 'img/hero-bg.jpg' );
				weiss_content_image_row( 'hero_can', 'Баннер: банка краски', 'img/hero-can.png' );
				weiss_content_row( 'hero_can_alt', 'Баннер: подпись банки (alt)', $d['hero_can_alt'] );
				weiss_content_image_row( 'coloring_fan', 'Колеровка: веер (десктоп)', 'img/coloring-fan.png' );
				weiss_content_image_row( 'coloring_fan_mobile', 'Колеровка: веер (мобильный)', 'img/coloring-fan-mobile.png' );
				weiss_content_image_row( 'coloring_scene', 'Колеровка: интерьер', 'img/coloring-scene.jpg' );
				weiss_content_row( 'coloring_scene_alt', 'Колеровка: подпись интерьера (alt)', $d['coloring_scene_alt'] );
				?>
			</table>

			<h2>Главная страница: тексты</h2>
			<table class="form-table" role="presentation">
				<?php
				weiss_content_row( 'hero_eyebrow', 'Баннер: надзаголовок', $d['hero_eyebrow'] );
				weiss_content_row( 'hero_title', 'Баннер: заголовок', $d['hero_title'] );
				weiss_content_row( 'hero_lead', 'Баннер: строка под заголовком', $d['hero_lead'] );
				weiss_content_row( 'hero_url', 'Баннер: ссылка кнопки «Подробнее»', $d['hero_url'], 'url', 'Пусто — ссылка на каталог.' );
				weiss_content_row( 'coloring_title', 'Блок колеровки: заголовок', $d['coloring_title'] );
				weiss_content_row( 'coloring_text', 'Блок колеровки: текст', $d['coloring_text'] );
				weiss_content_row( 'coloring_url', 'Блок колеровки: ссылка кнопки', $d['coloring_url'], 'url', 'Пусто — ссылка на визуализатор.' );
				weiss_content_row( 'benefits_heading', 'Заголовок блока преимуществ (можно <br>)', $d['benefits_heading'], 'textarea' );
				for ( $i = 1; $i <= 4; $i++ ) {
					weiss_content_row( 'benefit_' . $i . '_title', 'Преимущество ' . $i . ' — заголовок', $d[ 'benefit_' . $i . '_title' ] );
					weiss_content_row( 'benefit_' . $i . '_text', 'Преимущество ' . $i . ' — текст (перенос — <br class="d-br">)', $d[ 'benefit_' . $i . '_text' ], 'textarea' );
				}
				?>
			</table>

			<h2>О компании</h2>
			<table class="form-table" role="presentation">
				<?php
				weiss_content_row( 'about_text', 'Текст страницы (абзацы через пустую строку)', $d['about_text'], 'textarea', 'Перевод строки внутри абзаца — <br class="about-br">.' );
				for ( $i = 1; $i <= 4; $i++ ) {
					weiss_content_row( 'about_feature_' . $i . '_title', 'Преимущество ' . $i . ' — заголовок', $d[ 'about_feature_' . $i . '_title' ], 'textarea' );
					weiss_content_row( 'about_feature_' . $i . '_text', 'Преимущество ' . $i . ' — текст (перенос — <br class="about-br">)', $d[ 'about_feature_' . $i . '_text' ], 'textarea' );
				}
				?>
			</table>

			<h2>SEO и перенаправления</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="weiss-content-redirects">Перенаправления 301</label></th>
					<td>
						<textarea class="large-text code" rows="4" id="weiss-content-redirects" name="weiss_content[redirects]" placeholder="/staryj-adres/ → /novyj-adres/"><?php echo esc_textarea( isset( $weiss_values['redirects'] ) ? $weiss_values['redirects'] : '' ); ?></textarea>
						<p class="description">По одному адресу в строке: старый путь, стрелка или «;», новый адрес. Реестр старых адресов передаёт Заказчик; пустое поле — перенаправлений нет.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Сохранить содержимое' ); ?>
		</form>
	</div>
	<?php
	weiss_content_media_script();
}

/** Строка выбора картинки из медиатеки (ТЗ 9.1). Пусто — файл темы. */
function weiss_content_image_row( $key, $label, $default_path ) {
	$values  = get_option( 'weiss_content', array() );
	$id      = isset( $values[ $key ] ) ? absint( $values[ $key ] ) : 0;
	$default = weiss_asset( $default_path );
	$src     = $id ? wp_get_attachment_image_url( $id, 'medium' ) : $default;
	$field   = 'weiss-content-' . str_replace( '_', '-', $key );
	?>
	<tr class="weiss-media" data-default="<?php echo esc_url( $default ); ?>">
		<th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<img class="weiss-media-preview" src="<?php echo esc_url( $src ); ?>" alt=""
			     style="display:block;max-width:240px;max-height:160px;width:auto;height:auto;margin-bottom:8px;background:#f0f0f1;object-fit:contain;">
			<input type="hidden" id="<?php echo esc_attr( $field ); ?>" name="weiss_content[<?php echo esc_attr( $key ); ?>]" value="<?php echo $id ? (int) $id : ''; ?>">
			<button class="button weiss-media-select" type="button">Выбрать изображение</button>
			<button class="button weiss-media-remove" type="button"<?php echo $id ? '' : ' style="display:none"'; ?>>Вернуть из макета</button>
			<p class="description">Пусто — картинка из макета.</p>
		</td>
	</tr>
	<?php
}

function weiss_content_media_script() {
	?>
	<script>
	jQuery( function ( $ ) {
		$( document ).on( 'click', '.weiss-media-select', function ( event ) {
			event.preventDefault();
			var field = $( this ).closest( '.weiss-media' );
			var frame = wp.media( {
				title: 'Выберите изображение',
				button: { text: 'Использовать' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				var src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				field.find( 'input[type=hidden]' ).val( attachment.id );
				field.find( '.weiss-media-preview' ).attr( 'src', src );
				field.find( '.weiss-media-remove' ).show();
			} );
			frame.open();
		} );

		$( document ).on( 'click', '.weiss-media-remove', function ( event ) {
			event.preventDefault();
			var field = $( this ).closest( '.weiss-media' );
			field.find( 'input[type=hidden]' ).val( '' );
			field.find( '.weiss-media-preview' ).attr( 'src', field.data( 'default' ) );
			$( this ).hide();
		} );
	} );
	</script>
	<?php
}

function weiss_content_row( $key, $label, $default, $type = 'text', $note = '' ) {
	$values = get_option( 'weiss_content', array() );
	/* Показываем действующее значение, а не пустое поле с подсказкой:
	   иначе первое сохранение записывало пустоту и убирало кнопки
	   мессенджеров, телефоны и карточки «Где купить» (замечание 68). */
	$value  = ( is_array( $values ) && array_key_exists( $key, $values ) ) ? $values[ $key ] : $default;
	$id     = 'weiss-content-' . str_replace( '_', '-', $key );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<?php if ( 'textarea' === $type ) : ?>
				<textarea class="large-text code" rows="3" id="<?php echo esc_attr( $id ); ?>" name="weiss_content[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input class="regular-text" type="<?php echo 'email' === $type ? 'email' : ( 'url' === $type ? 'url' : 'text' ); ?>"
				       id="<?php echo esc_attr( $id ); ?>" name="weiss_content[<?php echo esc_attr( $key ); ?>]"
				       value="<?php echo esc_attr( $value ); ?>">
			<?php endif; ?>
			<?php if ( $note ) : ?>
				<p class="description"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}
