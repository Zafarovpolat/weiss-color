<?php
/**
 * Экспорт данных (ТЗ, п.10.2): заказы, товары и цвета в CSV.
 * Файл открывается в Excel и LibreOffice без настроек: UTF-8 с BOM,
 * разделитель «;». Экспорт не зависит от подрядчика и не требует плагинов.
 */

function weiss_export_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'Экспорт данных',
		'Экспорт данных',
		'manage_woocommerce',
		'weiss-export',
		'weiss_export_page'
	);
}
add_action( 'admin_menu', 'weiss_export_admin_menu' );

function weiss_export_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	?>
	<div class="wrap">
		<h1>Экспорт данных</h1>
		<p>Файлы в формате CSV (UTF-8, разделитель «;») открываются в Excel и LibreOffice. Данные выгружаются целиком, без ограничения числа строк.</p>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr>
					<td><strong>Заказы</strong><br><span class="description">Номер, статус, оплата, позиции с фасовкой и цветом, суммы, доставка, контакты, реквизиты для счёта.</span></td>
					<td style="width:180px"><a class="button button-primary" href="<?php echo esc_url( weiss_export_url( 'orders' ) ); ?>">Скачать CSV</a></td>
				</tr>
				<tr>
					<td><strong>Товары</strong><br><span class="description">Товары и варианты: артикул, фасовка, цена, остаток, категории, характеристики, приоритет.</span></td>
					<td><a class="button button-primary" href="<?php echo esc_url( weiss_export_url( 'products' ) ); ?>">Скачать CSV</a></td>
				</tr>
				<tr>
					<td><strong>Палитры и цвета</strong><br><span class="description">Код, RGB/HEX, группа продукта, статус неполной записи и совместимые товары.</span></td>
					<td><a class="button button-primary" href="<?php echo esc_url( weiss_export_url( 'colors' ) ); ?>">Скачать CSV</a></td>
				</tr>
				<tr>
					<td><strong>Обращения</strong><br><span class="description">Заявки с форм «Контакты» и «Заказать звонок».</span></td>
					<td><a class="button button-primary" href="<?php echo esc_url( weiss_export_url( 'leads' ) ); ?>">Скачать CSV</a></td>
				</tr>
				<tr>
					<td><strong>Шаблон реестра цветов</strong><br><span class="description">Пустая таблица с колонками исходного файла для последующих обновлений.</span></td>
					<td><a class="button" href="<?php echo esc_url( weiss_export_url( 'colors-template' ) ); ?>">Скачать CSV</a></td>
				</tr>
				<tr>
					<td><strong>Отклонённые строки импорта</strong><br><span class="description">Номера строк, палитры, коды и причины из последнего импорта.</span></td>
					<td><a class="button" href="<?php echo esc_url( weiss_export_url( 'colors-rejected' ) ); ?>">Скачать CSV</a></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}

function weiss_export_url( $kind ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'weiss_export',
				'kind'   => $kind,
			),
			admin_url( 'admin-post.php' )
		),
		'weiss_export_' . $kind
	);
}

add_action( 'admin_post_weiss_export', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Недостаточно прав.' );
	}

	$kind = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
	check_admin_referer( 'weiss_export_' . $kind );

	$kinds = array(
		'orders'          => array( 'orders', 'weiss_export_orders' ),
		'products'        => array( 'products', 'weiss_export_products' ),
		'colors'          => array( 'colors', 'weiss_export_colors' ),
		'colors-template' => array( 'colors-template', 'weiss_export_colors_template' ),
		'colors-rejected' => array( 'colors-rejected', 'weiss_export_colors_rejected' ),
		'leads'           => array( 'leads', 'weiss_export_leads' ),
	);
	if ( ! isset( $kinds[ $kind ] ) ) {
		wp_die( 'Неизвестный тип экспорта.' );
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="weiss-' . $kinds[ $kind ][0] . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

	/* Файл собирается целиком и отправляется с Content-Length: обрыв
	   ответа хостингом (замечание 71) теперь виден клиенту как ошибка
	   загрузки, а не как файл, обрезанный посреди строки. */
	@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	ignore_user_abort( true );

	$buffer = fopen( 'php://temp/maxmemory:33554432', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $buffer, "\xEF\xBB\xBF" );

	call_user_func( $kinds[ $kind ][1], $buffer );

	$size = ftell( $buffer );
	rewind( $buffer );
	header( 'Content-Length: ' . $size );
	fpassthru( $buffer );
	fclose( $buffer );
	exit;
} );

/**
 * Текст покупателя не должен выполняться как формула при открытии CSV
 * в Excel (ТЗ 11.3). Значения с «=», «+», «-», «@» экранируем апострофом.
 */
function weiss_export_cell( $value ) {
	$value = (string) $value;
	if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		return "'" . $value;
	}
	return $value;
}

function weiss_export_csv_row( $out, $row ) {
	fputcsv( $out, array_map( 'weiss_export_cell', $row ), ';', '"', '' );
}

function weiss_export_orders( $out ) {
	weiss_export_csv_row( $out, array(
		'Номер', 'Дата', 'Статус', 'Оплата', 'Метод оплаты', 'Транзакция',
		'Товар', 'Артикул', 'Фасовка', 'Палитра', 'Код цвета',
		'Количество', 'Цена единицы', 'Сумма строки',
		'Скидка', 'Доставка', 'Итого', 'Валюта',
		'Имя', 'Телефон', 'E-mail', 'Регион', 'Адрес',
		'Плательщик', 'Организация', 'ИНН', 'КПП', 'ОГРН/ОГРНИП',
		'Юридический адрес', 'Телефон для счёта', 'E-mail для счёта', 'Реквизиты: комментарий',
		'Чек', 'Комментарий', 'Согласие', 'Письмо покупателю',
	) );

	/* Заказы выгружаем порциями: на большом магазине весь список в памяти
	   не помещается и экспорт падает. */
	$page  = 1;
	$limit = 200;

	do {
		$orders = wc_get_orders( array(
			'limit'   => $limit,
			'page'    => $page,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
		) );

		foreach ( $orders as $order ) {
			$invoice = (array) $order->get_meta( '_weiss_invoice' );
			/* «Юрлицо» только когда реквизиты действительно заполнены:
			   пустой массив остаётся у заявки и у физлица (замечание 72). */
			$org   = isset( $invoice['org'] ) ? trim( (string) $invoice['org'] ) : '';
			$payer = '' === $org ? '' : ( ( $invoice['payer'] ?? '' ) === 'ip' ? 'ИП' : 'Юрлицо' );
			$base    = array(
				$order->get_order_number(),
				$order->get_date_created() ? $order->get_date_created()->date( 'd.m.Y H:i' ) : '',
				wc_get_order_status_name( $order->get_status() ),
				$order->is_paid() ? 'оплачен' : 'не оплачен',
				$order->get_payment_method_title(),
				$order->get_transaction_id(),
			);
			$tail = array(
				$order->get_formatted_billing_full_name(),
				$order->get_billing_phone(),
				$order->get_billing_email(),
				$order->get_meta( '_weiss_region' ) ?: $order->get_billing_state(),
				trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() ),
				$payer,
				$invoice['org'] ?? '',
				$invoice['inn'] ?? '',
				$invoice['kpp'] ?? '',
				$invoice['ogrn'] ?? '',
				$invoice['address'] ?? '',
				$invoice['phone'] ?? '',
				$invoice['email'] ?? '',
				$invoice['comment'] ?? '',
				(string) $order->get_meta( '_weiss_sber_receipt' ),
				$order->get_customer_note(),
				$order->get_meta( '_weiss_consent' ) ? 'да, ' . $order->get_meta( '_weiss_consent' ) : 'нет',
				$order->get_meta( '_weiss_mail_sent' ) === 'no' ? 'ошибка' : ( $order->get_meta( '_weiss_mail_sent' ) === 'yes' ? 'отправлено' : '—' ),
			);

			$items = $order->get_items();
			if ( ! $items ) {
				$items = array( null );
			}

			foreach ( $items as $item ) {
				$line = array( '', '', '', 0, 0, 0 );
				if ( $item ) {
					$product = $item->get_product();
					$line    = array(
						$item->get_name(),
						$product ? $product->get_sku() : '',
						weiss_export_item_packaging( $item ),
						(string) ( $item->get_meta( '_weiss_palette' ) ?: $item->get_meta( 'Палитра' ) ),
						(string) ( $item->get_meta( '_weiss_code' ) ?: $item->get_meta( 'Цвет' ) ),
						$item->get_quantity(),
						wc_format_decimal( $order->get_item_subtotal( $item, false, false ), 2 ),
						wc_format_decimal( $item->get_total(), 2 ),
					);
				}

				weiss_export_csv_row( $out, array_merge(
					$base,
					$line,
					array(
						wc_format_decimal( $order->get_discount_total(), 2 ),
						wc_format_decimal( $order->get_shipping_total(), 2 ),
						wc_format_decimal( $order->get_total(), 2 ),
						$order->get_currency(),
					),
					$tail
				) );
			}
		}

		$page++;
	} while ( count( $orders ) === $limit );
}

/** Фасовка позиции: значение атрибута «объём» или мета корзины. */
function weiss_export_item_packaging( $item ) {
	foreach ( (array) $item->get_meta_data() as $meta ) {
		$key = mb_strtolower( (string) $meta->key );
		if ( false !== mb_strpos( $key, 'объ' ) || false !== mb_strpos( $key, 'фасов' ) ) {
			return (string) $meta->value;
		}
	}
	$product = $item->get_product();
	if ( $product && $product->is_type( 'variation' ) ) {
		$attrs = $product->get_variation_attributes();
		return implode( ', ', array_filter( $attrs ) );
	}
	return '';
}

function weiss_export_products( $out ) {
	weiss_export_csv_row( $out, array(
		'ID', 'Артикул', 'Название', 'Тип', 'Категории', 'Цена', 'Цена от', 'Остаток',
		'Статус', 'Приоритет', 'Характеристики', 'Родительский ID',
	) );

	$products = wc_get_products( array(
		'limit'   => -1,
		'status'  => array( 'publish', 'draft', 'private', 'pending' ),
		'type'    => array( 'simple', 'variable', 'variation' ),
		'orderby' => 'ID',
		'order'   => 'ASC',
	) );

	foreach ( $products as $product ) {
		$categories = wp_get_post_terms( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$specs      = array();

		if ( $product->is_type( 'variation' ) ) {
			/* У варианта get_attributes() возвращает пары «имя => значение». */
			foreach ( (array) $product->get_attributes() as $name => $value ) {
				$specs[] = wc_attribute_label( $name, $product ) . ': ' . $value;
			}
			$price_from = '';
		} else {
			foreach ( $product->get_attributes() as $attribute ) {
				if ( $attribute->get_variation() ) {
					$specs[] = wc_attribute_label( $attribute->get_name(), $product ) . ': ' . implode( ', ', $attribute->get_options() );
				} elseif ( $attribute->is_taxonomy() ) {
					$specs[] = wc_attribute_label( $attribute->get_name(), $product ) . ': ' . implode( ', ', wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) ) );
				} else {
					$specs[] = wc_attribute_label( $attribute->get_name(), $product ) . ': ' . implode( ', ', $attribute->get_options() );
				}
			}
			$price_from = $product->is_type( 'variable' ) ? wc_format_decimal( $product->get_variation_price( 'min', true ), 2 ) : '';
		}

		weiss_export_csv_row( $out, array(
			$product->get_id(),
			$product->get_sku(),
			$product->get_name(),
			$product->get_type(),
			is_wp_error( $categories ) ? '' : implode( ', ', $categories ),
			wc_format_decimal( $product->get_price(), 2 ),
			$price_from,
			$product->get_stock_quantity() === null ? $product->get_stock_status() : $product->get_stock_quantity(),
			$product->get_status(),
			(int) get_post_meta( $product->get_id(), '_weiss_priority', true ),
			implode( '; ', $specs ),
			$product->get_parent_id(),
		) );
	}
}

function weiss_export_colors( $out ) {
	global $wpdb;

	/* Заголовки файла — как у импорта (COLOURCODE/CARDNAME/NAME/RGB_HTML):
	   выгрузку можно загрузить обратно без переименования колонок. */
	weiss_export_csv_row( $out, array(
		'COLOURCODE', 'CARDNAME', 'NAME', 'R', 'G', 'B', 'RGB_HTML', 'Продукт',
		'Статус', 'Примечание', 'Совместимые товары',
	) );

	$table = weiss_colors_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY palette ASC, code ASC" ); // phpcs:ignore WordPress.DB

	/* Если запрос вернул меньше строк, чем в таблице, повторяем один раз.
	   Строка-маркер в конце позволяет проверить полноту файла: импорт
	   пропускает строки, начинающиеся с «#» (замечание 71). */
	if ( count( $rows ) !== $total ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY palette ASC, code ASC" ); // phpcs:ignore WordPress.DB
	}

	$written = 0;
	foreach ( $rows as $color ) {
		$compatible = array();
		foreach ( weiss_color_products( $color ) as $item ) {
			$compatible[] = $item['product']->get_name();
		}

		weiss_export_csv_row( $out, array(
			$color->code,
			$color->palette,
			$color->name,
			$color->r,
			$color->g,
			$color->b,
			$color->hex,
			$color->product_group,
			$color->active ? 'активен' : 'неполный/скрыт',
			$color->note,
			implode( ', ', array_unique( $compatible ) ),
		) );
		$written++;
	}

	$complete = count( $rows ) === $total && $written === $total;
	weiss_export_csv_row( $out, array(
		$complete ? '#КОНЕЦ ФАЙЛА' : '#ФАЙЛ НЕПОЛНЫЙ',
		'цветов',
		$written,
		'в базе',
		$total,
		gmdate( 'Y-m-d H:i' ) . ' UTC',
	) );
}

/**
 * Шаблон CSV для последующих обновлений реестра (ТЗ, п.5.3), колонки как
 * в исходном файле (п.5.2). Строки-примеры не пишем, чтобы их не импортировали.
 */
function weiss_export_colors_template( $out ) {
	weiss_export_csv_row( $out, array( 'COLOURCODE', 'CARDNAME', 'NAME', 'R', 'G', 'B', 'RGB_HTML', 'Продукт' ) );
}

/** Строки последнего импорта, требующие исправления: номер, палитра, код, причина. */
function weiss_export_colors_rejected( $out ) {
	weiss_export_csv_row( $out, array( 'Строка', 'Палитра', 'Код', 'Причина' ) );

	$report = get_option( 'weiss_colors_last_report' );
	if ( ! is_array( $report ) ) {
		return;
	}

	foreach ( array( 'rejected', 'incomplete', 'duplicates' ) as $kind ) {
		foreach ( (array) ( $report[ $kind ] ?? array() ) as $row ) {
			weiss_export_csv_row( $out, array(
				(int) ( $row['row'] ?? 0 ),
				$row['palette'] ?? '',
				$row['code'] ?? '',
				$row['reason'] ?? 'повтор ключа «палитра + код»',
			) );
		}
	}
}

function weiss_export_leads( $out ) {
	weiss_export_csv_row( $out, array(
		'Дата', 'Тип', 'Имя', 'Телефон', 'Регион', 'Сообщение', 'Страница', 'IP', 'Письмо',
	) );

	$leads = get_posts( array(
		'post_type'      => 'weiss_lead',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	foreach ( $leads as $lead ) {
		weiss_export_csv_row( $out, array(
			get_the_date( 'd.m.Y H:i', $lead ),
			'callback' === get_post_meta( $lead->ID, '_weiss_type', true ) ? 'Заказать звонок' : 'Контакты',
			get_post_meta( $lead->ID, '_weiss_name', true ),
			get_post_meta( $lead->ID, '_weiss_phone', true ),
			get_post_meta( $lead->ID, '_weiss_region', true ),
			get_post_meta( $lead->ID, '_weiss_message', true ),
			get_post_meta( $lead->ID, '_weiss_page', true ),
			get_post_meta( $lead->ID, '_weiss_ip', true ),
			'sent' === get_post_meta( $lead->ID, '_weiss_mail', true ) ? 'отправлено' : 'ошибка',
		) );
	}
}
