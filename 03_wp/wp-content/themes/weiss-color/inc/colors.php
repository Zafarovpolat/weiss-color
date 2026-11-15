<?php
/**
 * Каталог цветов: 28 палитр и 24 196 записей из реестра заказчика (ТЗ, п.5).
 *
 * Хранение — отдельная таблица: цветов слишком много для записей WordPress,
 * а выборка нужна по палитре, коду и группе продуктов с поиском и страницами.
 * Импорт — XLSX/CSV из реестра; повторный импорт обновляет записи по ключу
 * «палитра + код» и не создаёт дублей.
 */

defined( 'ABSPATH' ) || exit;

const WEISS_COLORS_DB_VERSION = '1';

function weiss_colors_table() {
	global $wpdb;
	return $wpdb->prefix . 'weiss_colors';
}

function weiss_colors_install() {
	global $wpdb;

	$table   = weiss_colors_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		hash char(32) NOT NULL,
		palette varchar(190) NOT NULL,
		code varchar(100) NOT NULL,
		name varchar(190) NOT NULL DEFAULT '',
		r smallint(5) unsigned DEFAULT NULL,
		g smallint(5) unsigned DEFAULT NULL,
		b smallint(5) unsigned DEFAULT NULL,
		hex char(7) NOT NULL DEFAULT '',
		product_group varchar(190) NOT NULL DEFAULT '',
		active tinyint(1) NOT NULL DEFAULT 1,
		note varchar(255) NOT NULL DEFAULT '',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY palette_code (hash),
		KEY palette (palette(64)),
		KEY code (code(64)),
		KEY active (active)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'weiss_colors_db_version', WEISS_COLORS_DB_VERSION );
}
add_action( 'after_switch_theme', 'weiss_colors_install' );

function weiss_colors_maybe_install() {
	if ( get_option( 'weiss_colors_db_version' ) !== WEISS_COLORS_DB_VERSION ) {
		weiss_colors_install();
	}
}
add_action( 'init', 'weiss_colors_maybe_install', 1 );

/**
 * Импорт реестра. Возвращает отчёт со сверкой (ТЗ, п.5.3).
 *
 * @param string $path Путь к XLSX или CSV.
 * @return array|WP_Error
 */
function weiss_colors_import_file( $path, $ext = '' ) {
	global $wpdb;

	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'weiss_import_read', 'Файл не найден или недоступен для чтения.' );
	}

	/* У временного файла загрузки нет расширения: тип берём из имени файла,
	   иначе XLSX читается как CSV и все строки отклоняются. */
	if ( '' === $ext ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	}
	$ext = strtolower( (string) $ext );
	$php = ini_get( 'memory_limit' );
	$php = wp_convert_hr_to_bytes( $php );
	if ( $php > 0 && $php < 512 * MB_IN_BYTES ) {
		@ini_set( 'memory_limit', '512M' );
	}

	$rows = 'xlsx' === $ext ? weiss_colors_read_xlsx( $path ) : weiss_colors_read_csv( $path );
	if ( is_wp_error( $rows ) ) {
		return $rows;
	}

	$table     = weiss_colors_table();
	$now       = current_time( 'mysql' );
	$report    = array(
		'file'      => basename( $path ),
		'rows'      => count( $rows ),
		'imported'  => 0,
		'active'    => 0,
		'inactive'  => 0,
		'palettes'  => array(),
		'details'   => array(),
		'groups'    => array(),
		'rejected'  => array(),
		'incomplete'=> array(),
		'duplicates'=> array(),
	);
	$seen  = array();
	$batch = array();

	$flush = function () use ( &$batch, &$report, $table, $now, $wpdb ) {
		if ( ! $batch ) {
			return;
		}
		$values = array();
		foreach ( $batch as $row ) {
			$values[] = $wpdb->prepare(
				'(%s,%s,%s,%s,%d,%d,%d,%s,%s,%d,%s,%s)',
				$row['hash'], $row['palette'], $row['code'], $row['name'],
				$row['r'], $row['g'], $row['b'], $row['hex'],
				$row['product_group'], $row['active'], $row['note'], $now
			);
		}
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL
			"INSERT INTO {$table} (hash,palette,code,name,r,g,b,hex,product_group,active,note,updated_at)
			 VALUES " . implode( ',', $values ) . '
			 ON DUPLICATE KEY UPDATE
			   palette=VALUES(palette), code=VALUES(code), r=VALUES(r), g=VALUES(g), b=VALUES(b),
			   hex=VALUES(hex), product_group=VALUES(product_group), active=VALUES(active),
			   note=VALUES(note), updated_at=VALUES(updated_at)'
		);
		$batch = array();
	};

	foreach ( $rows as $index => $row ) {
		$palette = isset( $row['palette'] ) ? trim( (string) $row['palette'] ) : '';
		$code    = isset( $row['code'] ) ? trim( (string) $row['code'] ) : '';

		if ( '' === $palette || '' === $code ) {
			$report['rejected'][] = array(
				'row'     => $index + 2,
				'palette' => $palette,
				'code'    => $code,
				'reason'  => 'пустая палитра или код',
			);
			continue;
		}

		$hash = md5( $palette . "\x1f" . $code );
		if ( isset( $seen[ $hash ] ) ) {
			$report['duplicates'][] = array( 'row' => $index + 2, 'palette' => $palette, 'code' => $code );
			continue;
		}
		$seen[ $hash ] = true;

		$hex = strtolower( trim( (string) ( $row['hex'] ?? '' ) ) );
		if ( $hex && '#' !== $hex[0] ) {
			$hex = '#' . $hex;
		}
		$valid = preg_match( '/^#[0-9a-f]{6}$/', $hex );
		$r     = isset( $row['r'] ) && is_numeric( $row['r'] ) ? (int) $row['r'] : null;
		$g     = isset( $row['g'] ) && is_numeric( $row['g'] ) ? (int) $row['g'] : null;
		$b     = isset( $row['b'] ) && is_numeric( $row['b'] ) ? (int) $row['b'] : null;

		$note = '';
		if ( ! $valid || null === $r || null === $g || null === $b ) {
			$note = 'Неполная запись: нет RGB/HEX, цвет недоступен до уточнения.';
		}

		$item = array(
			'hash'          => $hash,
			'palette'       => $palette,
			'code'          => $code,
			'name'          => isset( $row['name'] ) ? trim( (string) $row['name'] ) : '',
			'r'             => $r,
			'g'             => $g,
			'b'             => $b,
			'hex'           => $valid ? $hex : '',
			'product_group' => isset( $row['group'] ) ? trim( (string) $row['group'] ) : '',
			'active'        => $note ? 0 : 1,
			'note'          => $note,
		);

		$batch[] = $item;
		$report['imported']++;
		$report['active'] += $item['active'];
		$report['inactive'] += $item['active'] ? 0 : 1;
		$report['palettes'][ $palette ] = ( $report['palettes'][ $palette ] ?? 0 ) + 1;

		if ( ! isset( $report['details'][ $palette ] ) ) {
			$report['details'][ $palette ] = array( 'source' => 0, 'imported' => 0, 'active' => 0, 'inactive' => 0 );
		}
		$report['details'][ $palette ]['source']++;
		$report['details'][ $palette ]['imported']++;
		$report['details'][ $palette ][ $item['active'] ? 'active' : 'inactive' ]++;

		$group = $item['product_group'];
		if ( ! isset( $report['groups'][ $group ] ) ) {
			$report['groups'][ $group ] = array( 'source' => 0, 'active' => 0 );
		}
		$report['groups'][ $group ]['source']++;
		$report['groups'][ $group ]['active'] += $item['active'];

		if ( $note ) {
			$report['incomplete'][] = array(
				'row'     => $index + 2,
				'palette' => $palette,
				'code'    => $code,
				'reason'  => $note,
			);
		}

		if ( count( $batch ) >= 200 ) {
			$flush();
		}
	}
	$flush();

	ksort( $report['palettes'], SORT_NATURAL | SORT_FLAG_CASE );
	ksort( $report['details'], SORT_NATURAL | SORT_FLAG_CASE );
	ksort( $report['groups'], SORT_NATURAL | SORT_FLAG_CASE );
	update_option( 'weiss_colors_last_import', array(
		'time'   => current_time( 'mysql' ),
		'file'   => $report['file'],
		'rows'   => $report['imported'],
		'active' => $report['active'],
	) );
	/* Отчёт хранится для сверки и выгрузки отклонённых строк (ТЗ, п.5.3). */
	update_option( 'weiss_colors_last_report', array(
		'time'       => current_time( 'mysql' ),
		'file'       => $report['file'],
		'rows'       => $report['rows'],
		'imported'   => $report['imported'],
		'active'     => $report['active'],
		'inactive'   => $report['inactive'],
		'details'    => $report['details'],
		'groups'     => $report['groups'],
		'rejected'   => $report['rejected'],
		'incomplete' => $report['incomplete'],
		'duplicates' => $report['duplicates'],
	), false );

	return $report;
}

/** Чтение листа XLSX (первый лист, колонки A:G как в реестре). */
function weiss_colors_read_xlsx( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'weiss_import_zip', 'Расширение PHP zip не подключено: XLSX не прочитать.' );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'weiss_import_open', 'Не удалось открыть XLSX.' );
	}

	$shared = array();
	$xml    = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( $xml ) {
		$sst = simplexml_load_string( $xml );
		if ( $sst ) {
			foreach ( $sst->si as $si ) {
				if ( isset( $si->t ) ) {
					$shared[] = (string) $si->t;
				} else {
					$text = '';
					foreach ( $si->r as $run ) {
						$text .= (string) $run->t;
					}
					$shared[] = $text;
				}
			}
		}
	}

	$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
	$zip->close();

	if ( ! $sheet ) {
		return new WP_Error( 'weiss_import_sheet', 'В XLSX нет первого листа.' );
	}

	$data = simplexml_load_string( $sheet );
	if ( ! $data ) {
		return new WP_Error( 'weiss_import_xml', 'Не удалось прочитать лист XLSX.' );
	}

	$rows   = array();
	$header = null;
	foreach ( $data->sheetData->row as $row ) {
		$values = array();
		foreach ( $row->c as $cell ) {
			$ref = (string) $cell['r'];
			$col = preg_replace( '/\d+/', '', $ref );
			$type = (string) $cell['t'];
			$value = (string) $cell->v;
			if ( 's' === $type ) {
				$value = isset( $shared[ (int) $value ] ) ? $shared[ (int) $value ] : '';
			} elseif ( 'inlineStr' === $type && isset( $cell->is->t ) ) {
				$value = (string) $cell->is->t;
			}
			$values[ $col ] = trim( $value );
		}

		if ( null === $header ) {
			$header = $values;
			continue;
		}

		$rows[] = array(
			'code'    => $values['A'] ?? '',
			'palette' => $values['B'] ?? '',
			'name'    => $values['H'] ?? '',
			'r'       => $values['C'] ?? '',
			'g'       => $values['D'] ?? '',
			'b'       => $values['E'] ?? '',
			'hex'     => $values['F'] ?? '',
			'group'   => $values['G'] ?? '',
		);
	}

	return $rows;
}

/** Чтение CSV с теми же колонками; заголовок обязателен. */
function weiss_colors_read_csv( $path ) {
	$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $handle ) {
		return new WP_Error( 'weiss_import_csv', 'Не удалось открыть CSV.' );
	}

	/* Шаблон выгружается с «;» для Excel, но принимаем и «,»: разделитель
	   определяем по первой строке, иначе весь файл читался одной колонкой. */
	$first     = (string) fgets( $handle );
	rewind( $handle );
	$delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';

	$header = fgetcsv( $handle, 0, $delimiter );
	if ( ! $header ) {
		fclose( $handle );
		return new WP_Error( 'weiss_import_csv_empty', 'CSV пуст.' );
	}
	$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
	$map = array_flip( array_map( 'trim', $header ) );

	$rows = array();
	while ( false !== ( $line = fgetcsv( $handle, 0, $delimiter ) ) ) {
		if ( ! array_filter( $line, fn( $v ) => '' !== trim( (string) $v ) ) ) {
			continue;
		}
		$get = function ( $key ) use ( $map, $line ) {
			return isset( $map[ $key ], $line[ $map[ $key ] ] ) ? $line[ $map[ $key ] ] : '';
		};
		$rows[] = array(
			'code'    => $get( 'COLOURCODE' ),
			'palette' => $get( 'CARDNAME' ),
			'name'    => $get( 'NAME' ),
			'r'       => $get( 'R' ),
			'g'       => $get( 'G' ),
			'b'       => $get( 'B' ),
			'hex'     => $get( 'RGB_HTML' ),
			'group'   => $get( 'Продукт' ),
		);
	}
	fclose( $handle );

	return $rows;
}

/**
 * Порядок палитр по таблице ТЗ, п.5.1. Наименования — значения поля CARDNAME.
 * Единый порядок для обзора М13, селектора М19, инструкции М21 и панели.
 */
function weiss_palette_order() {
	return array(
		'Benjamin Moore Color Preview',
		'Benjamin Moore Historical Colors',
		'Benjamin Moore Off-White Colors',
		'Caparol 3D PLUS',
		'Dulux Trade Colour Palette full',
		'Farrow&Ball Colours',
		'Little Greene Colour Scales',
		'Little Greene Colours of England',
		'NCS Index 2050',
		'PANTONE FHI Color Guide',
		'RAL Design D2',
		'RAL 840HR',
		'Ral Effect',
		'Sherwin-Williams COLOR',
		'Tikkurila Furniture Paints',
		'Kreate Color Grain',
		'Tikkurila Interior Lacquer 2004',
		'Tikkurila Wood Finishes 2005',
		'Ace Clark Kensington Color Collection',
		'ACE Paint Colors for Your Life',
		'ACE Accent & Lightest White Colors',
		'Caparol Alpinacolor',
		'Caparol SCHWARZ',
		'Swiss Lake',
		'Tikkurila Pro Grey 2010',
		'Tikkurila Facade 2002',
		'Tikkurila Facade 760',
		'Tikkurila Symphony',
	);
}

/** Список палитр с числом записей — в порядке таблицы ТЗ (п.5.1). */
function weiss_color_palettes() {
	global $wpdb;
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$table = weiss_colors_table();
	$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
		"SELECT palette, COUNT(*) AS total, SUM(active = 1) AS active
		   FROM {$table}
		  GROUP BY palette"
	);
	$rows  = $rows ? $rows : array();

	$order = array_flip( weiss_palette_order() );
	usort(
		$rows,
		function ( $a, $b ) use ( $order ) {
			$left  = $order[ $a->palette ] ?? PHP_INT_MAX;
			$right = $order[ $b->palette ] ?? PHP_INT_MAX;
			if ( $left === $right ) {
				return strcasecmp( $a->palette, $b->palette );
			}
			return $left <=> $right;
		}
	);

	$cache = $rows;
	return $cache;
}

/**
 * Палитры с видимыми оттенками для витрины: скрытая целиком палитра
 * не предлагается в обзоре и селекторе. $group ограничивает совместимостью
 * с выбранным товаром (ТЗ, п.5.4–5.5).
 */
function weiss_color_palettes_active( $group = '' ) {
	global $wpdb;
	$table = weiss_colors_table();
	$where = 'active = 1';
	if ( 'wood' === $group ) {
		$where .= $wpdb->prepare( ' AND product_group LIKE %s', '%Лаки%' );
	} elseif ( 'paint' === $group ) {
		$where .= $wpdb->prepare( ' AND product_group NOT LIKE %s', '%Лаки%' );
	}

	$rows = $wpdb->get_results( "SELECT palette, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY palette" ); // phpcs:ignore WordPress.DB
	$rows = $rows ? $rows : array();

	$order = array_flip( weiss_palette_order() );
	usort( $rows, function ( $a, $b ) use ( $order ) {
		return ( $order[ $a->palette ] ?? PHP_INT_MAX ) <=> ( $order[ $b->palette ] ?? PHP_INT_MAX );
	} );

	return $rows;
}

/** Короткое имя каталога для заголовка и селекта визуализатора. */
function weiss_palette_brand( $palette ) {
	$brands = array(
		'ACE '                => 'ACE PAINT',
		'Ace '                => 'ACE PAINT',
		'Benjamin Moore '     => 'BENJAMIN MOORE',
		'Caparol '            => 'CAPAROL',
		'Dulux '              => 'DULUX',
		'Farrow&Ball '        => 'FARROW & BALL',
		'Kreate '             => 'KREATE',
		'Little Greene '      => 'LITTLE GREENE',
		'NCS '                => 'NCS',
		'PANTONE '            => 'PANTONE',
		'RAL '                => 'RAL',
		'Ral '                => 'RAL',
		'Sherwin-Williams '   => 'SHERWIN-WILLIAMS',
		'Swiss Lake'          => 'SWISS LAKE',
		'Tikkurila '          => 'TIKKURILA',
	);

	foreach ( $brands as $prefix => $brand ) {
		if ( 0 === strpos( $palette, $prefix ) ) {
			return $brand;
		}
	}

	return mb_strtoupper( strtok( $palette, ' ' ) );
}

/** Записи палитры: поиск по коду/названию, фильтр продукта, страницы. */
function weiss_colors_query( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'palette'          => '',
		'search'           => '',
		'material'         => '',
		'group'            => '',
		'page'             => 1,
		'per_page'         => 24,
		'all'              => false,
		'include_inactive' => false,
	) );

	$where  = $args['include_inactive'] ? array( '1 = 1' ) : array( 'active = 1' );
	$params = array();

	if ( $args['palette'] ) {
		$where[]  = 'palette = %s';
		$params[] = $args['palette'];
	}
	if ( '' !== $args['search'] ) {
		$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]  = '(code LIKE %s OR name LIKE %s)';
		$params[] = $like;
		$params[] = $like;
	}
	if ( $args['material'] ) {
		$like     = '%' . $wpdb->esc_like( $args['material'] ) . '%';
		$where[]  = 'product_group LIKE %s';
		$params[] = $like;
	}
	if ( 'wood' === $args['group'] ) {
		$where[]  = 'product_group LIKE %s';
		$params[] = '%Лаки%';
	} elseif ( 'paint' === $args['group'] ) {
		$where[]  = 'product_group NOT LIKE %s';
		$params[] = '%Лаки%';
	}

	$table = weiss_colors_table();
	$sql   = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where );
	$count = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

	$total_sql = $params ? $wpdb->prepare( $count, $params ) : $count; // phpcs:ignore WordPress.DB
	$total     = (int) $wpdb->get_var( $total_sql ); // phpcs:ignore WordPress.DB

	if ( $args['all'] ) {
		$limit_sql = ' ORDER BY palette ASC, code ASC';
	} else {
		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$sql     .= ' ORDER BY code ASC LIMIT ' . $per_page . ' OFFSET ' . $offset;
	}

	$query = $params ? $wpdb->prepare( $sql, $params ) : $sql; // phpcs:ignore WordPress.DB
	$items = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB

	return array(
		'items'     => $items ? $items : array(),
		'total'     => $total,
		'per_page'  => (int) $args['per_page'],
		'page'      => max( 1, (int) $args['page'] ),
		'max_pages' => $args['all'] ? 1 : max( 1, (int) ceil( $total / max( 1, (int) $args['per_page'] ) ) ),
	);
}

/** Один цвет по палитре и коду (только активные). */
function weiss_color_get( $palette, $code ) {
	global $wpdb;
	$table = weiss_colors_table();
	return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"SELECT * FROM {$table} WHERE palette = %s AND code = %s AND active = 1 LIMIT 1",
		$palette,
		$code
	) );
}

/** Первые цвета палитры — для плитки на обзоре палитр. */
function weiss_color_preview_hexes( $palette, $limit = 6 ) {
	global $wpdb;
	$table = weiss_colors_table();
	$rows  = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"SELECT hex FROM {$table} WHERE palette = %s AND active = 1 AND hex <> '' ORDER BY id ASC LIMIT %d",
		$palette,
		max( 1, (int) $limit )
	) );

	return $rows ? $rows : array();
}

/** Адрес списка оттенков: палитра и страница. */
function weiss_shades_url( $palette = '', $page = 1, $search = '' ) {
	$args = array();
	if ( $palette ) {
		/* Названия палитр содержат «&» (Farrow&Ball): без кодирования
		   ссылка распадается на два параметра и открывает чужую палитру. */
		$args['palette'] = rawurlencode( $palette );
	}
	if ( $page > 1 ) {
		$args['shade_page'] = (int) $page;
	}
	if ( '' !== $search ) {
		$args['q'] = rawurlencode( $search );
	}
	return add_query_arg( $args, weiss_page_url( 'shades' ) );
}

/** Адрес страницы цвета. */
function weiss_color_url( $palette, $code ) {
	return add_query_arg(
		array(
			'palette' => rawurlencode( $palette ),
			'code'    => rawurlencode( $code ),
		),
		weiss_page_url( 'color' )
	);
}

/** Подписи групп продуктов из реестра для фильтров материалов. */
function weiss_color_materials() {
	return array(
		'all'       => array( 'label' => 'Все', 'like' => '' ),
		'walls'     => array( 'label' => 'Краски для стен', 'like' => 'стены' ),
		'kitchen'   => array( 'label' => 'Краски для кухни и ванной', 'like' => 'кухня' ),
		'facade'    => array( 'label' => 'Краски для фасадов', 'like' => 'фасадная' ),
		'furniture' => array( 'label' => 'Краски для мебели', 'like' => 'мебель' ),
		'lacquer'   => array( 'label' => 'Лаки', 'like' => 'лаки' ),
		'bath'      => array( 'label' => 'Пропитка для бани', 'like' => 'пропитка' ),
		'stain'     => array( 'label' => 'Состав для дерева', 'like' => 'состав для дерева' ),
		'oil'       => array( 'label' => 'Масло', 'like' => 'масло' ),
	);
}

/** Число с правильной формой слова: 1 оттенок, 2 оттенка, 5 оттенков. */
function weiss_colors_word( $number, $forms ) {
	$number = abs( (int) $number ) % 100;
	$last   = $number % 10;
	if ( $number > 10 && $number < 20 ) {
		return $forms[2];
	}
	if ( $last > 1 && $last < 5 ) {
		return $forms[1];
	}
	if ( 1 === $last ) {
		return $forms[0];
	}
	return $forms[2];
}

/**
 * Заводское соответствие «поле Продукт → материалы → товары» (ТЗ, п.5.5).
 * Артикулы меняются в панели: WooCommerce → Колеровка → Совместимость.
 */
function weiss_color_map_defaults() {
	return array(
		'paint' => array(
			'walls'     => array( 21 ),
			'kitchen'   => array( 25 ),
			'facade'    => array( 26 ),
			'furniture' => array( 27 ),
		),
		/* Группа Б (ТЗ, п.5.5): категории по согласованному ассортименту.
		   «Состав для дерева» без подтверждённого артикула остаётся пустым —
		   произвольным товаром не подменяется. */
		'wood'  => array(
			'lacquer' => array( 28, 29, 30 ),
			'bath'    => array( 31 ),
			'stain'   => array(),
			'oil'     => array( 32 ),
		),
	);
}

/** Соответствие материалов товарам с учётом правок из панели. */
function weiss_color_product_map() {
	$saved = get_option( 'weiss_color_map' );
	if ( ! is_array( $saved ) || ! $saved ) {
		return weiss_color_map_defaults();
	}

	$map = array();
	foreach ( weiss_color_map_defaults() as $group => $materials ) {
		foreach ( $materials as $material => $default ) {
			$ids = $saved[ $group ][ $material ] ?? $default;
			$map[ $group ][ $material ] = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		}
	}

	return $map;
}

/** Первая палитра с оттенками совместимой группы — стартовый каталог товара. */
function weiss_palette_first_compatible( $group ) {
	if ( '' === $group ) {
		return '';
	}

	global $wpdb;
	$table = weiss_colors_table();
	$where = 'wood' === $group ? 'product_group LIKE %s' : 'product_group NOT LIKE %s';
	$rows  = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"SELECT DISTINCT palette FROM {$table} WHERE active = 1 AND {$where}",
		'%Лаки%'
	) );
	if ( ! $rows ) {
		return '';
	}

	foreach ( weiss_color_palettes() as $palette ) {
		if ( in_array( $palette->palette, $rows, true ) ) {
			return $palette->palette;
		}
	}

	return $rows[0];
}

/** Группа совместимых цветов товара: paint/wood, '' — любой (пробник). */
function weiss_product_color_group( $product_id ) {
	$product = wc_get_product( (int) $product_id );
	if ( ! $product ) {
		return '';
	}
	if ( 'yes' === get_post_meta( $product->get_id(), '_weiss_sampler', true ) ) {
		return '';
	}
	if ( $product->is_type( 'variation' ) ) {
		$product = wc_get_product( $product->get_parent_id() );
		if ( ! $product ) {
			return '';
		}
	}

	foreach ( weiss_color_product_map() as $group => $materials ) {
		foreach ( $materials as $ids ) {
			if ( in_array( $product->get_id(), $ids, true ) ) {
				return $group;
			}
		}
	}

	return '';
}

/**
 * Совместимые материалы цвета: группа А — краски Acrilux, группа Б — лаки
 * и составы по дереву. Соответствие «поле Продукт → товар» фиксируется здесь
 * до согласования артикулов (ТЗ, п.5.5).
 */
function weiss_color_products( $color ) {
	$group = strtolower( (string) ( $color->product_group ?? '' ) );
	$wood  = false !== mb_strpos( $group, 'лаки' );

	$groups = weiss_color_product_map();
	$map    = $wood ? $groups['wood'] : $groups['paint'];

	$items = array();
	$seen  = array();
	foreach ( $map as $material => $ids ) {
		foreach ( $ids as $id ) {
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			$seen[ $id ] = true;
			$items[] = array(
				'product'  => $product,
				'material' => $material,
			);
		}
	}

	return $items;
}

/** Пагинация списка: те же классы, что в макете. */
function weiss_colors_pagination( $current, $max, $url_for_page, $label ) {
	if ( $max < 2 ) {
		return;
	}

	$items = array();
	$items[] = array( 'label' => 'Назад', 'page' => max( 1, $current - 1 ), 'disabled' => $current <= 1 );

	$start = max( 1, $current - 3 );
	$end   = min( $max, $start + 6 );
	$start = max( 1, $end - 6 );
	for ( $page = $start; $page <= $end; $page++ ) {
		$items[] = array( 'label' => (string) $page, 'page' => $page, 'current' => $page === $current );
	}

	$items[] = array( 'label' => 'Вперед', 'page' => min( $max, $current + 1 ), 'disabled' => $current >= $max );

	echo '<nav class="pagination pagination--compact" aria-label="' . esc_attr( $label ) . '">';
	foreach ( $items as $item ) {
		$attrs = '';
		if ( ! empty( $item['current'] ) ) {
			$attrs = ' aria-current="page"';
		} elseif ( ! empty( $item['disabled'] ) ) {
			$attrs = ' aria-disabled="true"';
		}
		$href = ( ! empty( $item['disabled'] ) || ! empty( $item['current'] ) ) ? '#' : $url_for_page( $item['page'] );
		echo '<a class="pagination__item" href="' . esc_url( $href ) . '" aria-label="' . esc_attr( 'Назад' === $item['label'] ? 'Предыдущая страница' : ( 'Вперед' === $item['label'] ? 'Следующая страница' : 'Страница ' . $item['label'] ) ) . '"' . $attrs . '>' . esc_html( $item['label'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</nav>';
}

/**
 * Пагинация визуализатора: те же подписи и окно из 7 страниц, что рисует
 * visualizer.js; ссылки работают и без JS (?viz_page=). Счётчик страниц —
 * фактический по загруженному набору (ТЗ, п.5.4).
 */
function weiss_viz_pagination( $current, $max, $url_for_page ) {
	if ( $max < 2 ) {
		return;
	}

	$start = max( 1, min( $current - 3, $max - 6 ) );
	$end   = min( $max, $start + 6 );

	echo '<nav class="pagination pagination--compact viz-pagination" aria-label="Страницы оттенков">';
	$prev = max( 1, $current - 1 );
	$next = min( $max, $current + 1 );
	echo '<a class="pagination__item" href="' . esc_url( $url_for_page( $prev ) ) . '" aria-label="Предыдущая страница"' . ( $current <= 1 ? ' aria-disabled="true"' : '' ) . '>Назад</a>';
	for ( $page = $start; $page <= $end; $page++ ) {
		echo '<a class="pagination__item" href="' . esc_url( $url_for_page( $page ) ) . '" aria-label="' . esc_attr( 'Страница ' . $page ) . '"' . ( $page === $current ? ' aria-current="page"' : '' ) . '>' . (int) $page . '</a>';
	}
	echo '<a class="pagination__item" href="' . esc_url( $url_for_page( $next ) ) . '" aria-label="Следующая страница"' . ( $current >= $max ? ' aria-disabled="true"' : '' ) . '>Вперед</a>';
	echo '</nav>';
}

/* -------------------------------------------------------------------------
 * Оттенки по запросу для визуализатора: текущая палитра, страница, поиск.
 * ---------------------------------------------------------------------- */

function weiss_colors_ajax() {
	check_ajax_referer( 'weiss-colors', 'nonce' );

	$palette  = isset( $_GET['palette'] ) ? sanitize_text_field( wp_unslash( $_GET['palette'] ) ) : '';
	$search   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );
	$product_id = isset( $_GET['weiss_product'] ) ? absint( $_GET['weiss_product'] ) : 0;
	$variation  = isset( $_GET['weiss_variation'] ) ? absint( $_GET['weiss_variation'] ) : 0;
	$per_page   = 18;

	$result = weiss_colors_query( array(
		'palette'  => $palette,
		'search'   => $search,
		'group'    => $product_id ? weiss_product_color_group( $product_id ) : '',
		'page'     => $page,
		'per_page' => $per_page,
	) );

	$items   = array();
	$product = $product_id ? weiss_product_cart_target( wc_get_product( $product_id ), $variation ) : null;
	foreach ( $result['items'] as $color ) {
		$items[] = array(
			'code' => $color->code,
			'hex'  => $color->hex,
		);
	}
	if ( ! $product && $result['items'] ) {
		$product = weiss_color_cart_target( $result['items'][0] );
	}

	wp_send_json_success( array(
		'items'     => $items,
		'page'      => $result['page'],
		'max_pages' => $result['max_pages'],
		'total'     => $result['total'],
		'product'   => $product,
	) );
}
add_action( 'wp_ajax_weiss_colors', 'weiss_colors_ajax' );
add_action( 'wp_ajax_nopriv_weiss_colors', 'weiss_colors_ajax' );

/* -------------------------------------------------------------------------
 * Администратор: сводка, импорт, скрытие цветов (ТЗ, п.5.3).
 * ---------------------------------------------------------------------- */

function weiss_colors_admin_menu() {
	add_submenu_page(
		'woocommerce',
		'Колеровка',
		'Колеровка',
		'manage_woocommerce',
		'weiss-colors',
		'weiss_colors_admin_page'
	);
}
add_action( 'admin_menu', 'weiss_colors_admin_menu' );

/** Товары для выбора совместимости: родители и варианты, подписи как в каталоге. */
function weiss_colors_admin_product_choices() {
	$choices = array();
	$parents = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );
	foreach ( $parents as $product ) {
		$choices[ $product->get_id() ] = $product->get_name() . ' (#' . $product->get_id() . ')';
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( $child_id );
				if ( $child ) {
					$choices[ $child_id ] = '— ' . $child->get_name() . ' (#' . $child_id . ')';
				}
			}
		}
	}
	return $choices;
}

function weiss_colors_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Недостаточно прав.' );
	}

	$notice = '';

	if ( isset( $_POST['weiss_colors_action'] ) && check_admin_referer( 'weiss_colors_admin' ) ) {
		$action = sanitize_key( $_POST['weiss_colors_action'] );

		if ( 'import' === $action && ! empty( $_FILES['weiss_colors_file']['tmp_name'] ) ) {
			$file = $_FILES['weiss_colors_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
				$notice = 'Поддерживаются XLSX и CSV.';
			} else {
				$report = weiss_colors_import_file( $file['tmp_name'], $ext );
				if ( is_wp_error( $report ) ) {
					$notice = $report->get_error_message();
				} else {
					$notice = sprintf(
						'Импорт: строк %d, активно %d, неполных %d, отклонено %d, дублей %d, палитр %d. Сверка — ниже.',
						$report['imported'],
						$report['active'],
						$report['inactive'],
						count( $report['rejected'] ),
						count( $report['duplicates'] ),
						count( $report['palettes'] )
					);
				}
			}
		}

		if ( 'toggle' === $action && ! empty( $_POST['color_id'] ) ) {
			global $wpdb;
			$id = (int) $_POST['color_id'];
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . weiss_colors_table() . ' SET active = 1 - active WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB
			$notice = 'Статус цвета изменён.';
		}

		if ( 'rename' === $action && ! empty( $_POST['color_id'] ) ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
				'UPDATE ' . weiss_colors_table() . ' SET name = %s WHERE id = %d',
				sanitize_text_field( wp_unslash( $_POST['color_name'] ?? '' ) ),
				(int) $_POST['color_id']
			) );
			$notice = 'Название сохранено.';
		}

		if ( 'palette_toggle' === $action && ! empty( $_POST['palette'] ) ) {
			global $wpdb;
			$palette = sanitize_text_field( wp_unslash( $_POST['palette'] ) );
			$state   = 'show' === ( $_POST['state'] ?? '' ) ? 1 : 0;
			$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
				'UPDATE ' . weiss_colors_table() . ' SET active = %d WHERE palette = %s',
				$state,
				$palette
			) );
			$notice = $state ? 'Палитра «' . $palette . '» показана.' : 'Палитра «' . $palette . '» скрыта.';
		}

		if ( 'add_color' === $action ) {
			global $wpdb;
			$palette = sanitize_text_field( wp_unslash( $_POST['new_palette'] ?? '' ) );
			$code    = sanitize_text_field( wp_unslash( $_POST['new_code'] ?? '' ) );
			$name    = sanitize_text_field( wp_unslash( $_POST['new_name'] ?? '' ) );
			$group   = sanitize_text_field( wp_unslash( $_POST['new_group'] ?? '' ) );
			$hex     = strtolower( sanitize_text_field( wp_unslash( $_POST['new_hex'] ?? '' ) ) );
			$active  = empty( $_POST['new_inactive'] ) ? 1 : 0;
			if ( $hex && '#' !== $hex[0] ) {
				$hex = '#' . $hex;
			}

			if ( '' === $palette || '' === $code ) {
				$notice = 'Для цвета нужны палитра и код.';
			} elseif ( ! preg_match( '/^#[0-9a-f]{6}$/', $hex ) ) {
				$notice = 'HEX-цвет в формате #RRGGBB.';
			} else {
				$r = hexdec( substr( $hex, 1, 2 ) );
				$g = hexdec( substr( $hex, 3, 2 ) );
				$b = hexdec( substr( $hex, 5, 2 ) );
				$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
					'INSERT INTO ' . weiss_colors_table() . ' (hash,palette,code,name,r,g,b,hex,product_group,active,note,updated_at)
					 VALUES (%s,%s,%s,%s,%d,%d,%d,%s,%s,%d,%s,%s)
					 ON DUPLICATE KEY UPDATE name=VALUES(name), r=VALUES(r), g=VALUES(g), b=VALUES(b),
					   hex=VALUES(hex), product_group=VALUES(product_group), active=VALUES(active),
					   note=VALUES(note), updated_at=VALUES(updated_at)',
					md5( $palette . "\x1f" . $code ),
					$palette,
					$code,
					$name,
					$r,
					$g,
					$b,
					$hex,
					$group,
					$active,
					'',
					current_time( 'mysql' )
				) );
				$notice = 'Цвет «' . $palette . ' / ' . $code . '» сохранён.';
			}
		}

		if ( 'map' === $action ) {
			$defaults = weiss_color_map_defaults();
			$saved    = array();
			foreach ( $defaults as $group => $materials ) {
				foreach ( $materials as $material => $ids ) {
					$raw = $_POST['map'][ $group ][ $material ] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$saved[ $group ][ $material ] = array_values( array_filter( array_map( 'absint', (array) $raw ) ) );
				}
			}
			update_option( 'weiss_color_map', $saved, false );
			$notice = 'Совместимость материалов сохранена.';
		}

		if ( 'tint' === $action ) {
			$rates = array();
			foreach ( array( 'paint', 'wood' ) as $group ) {
				$raw = trim( sanitize_text_field( wp_unslash( $_POST['tint'][ $group ] ?? '' ) ) );
				if ( '' === $raw ) {
					$rates[ $group ] = '';
				} elseif ( ! is_numeric( $raw ) || (float) $raw < 0 ) {
					$notice = 'Доплата за колеровку — неотрицательное число в рублях или пустое поле.';
					$rates   = null;
					break;
				} else {
					$rates[ $group ] = (string) round( (float) $raw, 2 );
				}
			}
			if ( is_array( $rates ) ) {
				update_option( 'weiss_tint_surcharge', $rates, false );
				$notice = 'Доплата за колеровку сохранена.';
			}
		}
	}

	$palettes = weiss_color_palettes();
	$last     = get_option( 'weiss_colors_last_import' );

	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$paged  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$list   = $search ? weiss_colors_query( array( 'search' => $search, 'page' => $paged, 'per_page' => 50, 'include_inactive' => true ) ) : null;
	?>
	<div class="wrap">
		<h1>Колеровка: палитры и цвета</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<p>
			Палитр: <strong><?php echo count( $palettes ); ?></strong>,
			записей: <strong><?php echo array_sum( wp_list_pluck( $palettes, 'total' ) ); ?></strong>,
			активных: <strong><?php echo array_sum( wp_list_pluck( $palettes, 'active' ) ); ?></strong>.
			<?php if ( $last ) : ?>
				Последний импорт: <?php echo esc_html( $last['time'] ); ?> (<?php echo esc_html( $last['file'] ); ?>).
			<?php endif; ?>
		</p>

		<h2>Импорт реестра</h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
			<input type="hidden" name="weiss_colors_action" value="import">
			<input type="file" name="weiss_colors_file" accept=".xlsx,.csv" required>
			<button class="button button-primary" type="submit">Импортировать</button>
			<p class="description">Повторный импорт обновляет записи по ключу «палитра + код». Неполные строки сохраняются скрытыми.</p>
			<p>
				<a class="button" href="<?php echo esc_url( weiss_export_url( 'colors-template' ) ); ?>">Скачать шаблон CSV</a>
				<a class="button" href="<?php echo esc_url( weiss_export_url( 'colors-rejected' ) ); ?>">Отклонённые строки (CSV)</a>
			</p>
		</form>

		<?php $weiss_report = get_option( 'weiss_colors_last_report' ); ?>
		<?php if ( is_array( $weiss_report ) ) : ?>
			<h2>Сверка последнего импорта</h2>
			<p class="description">
				Файл <?php echo esc_html( $weiss_report['file'] ); ?>, <?php echo esc_html( $weiss_report['time'] ); ?>.
				Исходных строк: <?php echo (int) $weiss_report['rows']; ?>,
				загружено: <?php echo (int) $weiss_report['imported']; ?>,
				активно: <?php echo (int) $weiss_report['active']; ?>,
				неполных: <?php echo count( $weiss_report['incomplete'] ); ?>,
				отклонено: <?php echo count( $weiss_report['rejected'] ); ?>,
				дублей: <?php echo count( $weiss_report['duplicates'] ); ?>.
			</p>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Палитра</th><th>Исходных</th><th>Загружено</th><th>Активно</th><th>Неполных</th></tr></thead>
				<tbody>
				<?php foreach ( (array) $weiss_report['details'] as $weiss_name => $weiss_row ) : ?>
					<tr>
						<td><?php echo esc_html( $weiss_name ); ?></td>
						<td><?php echo (int) $weiss_row['source']; ?></td>
						<td><?php echo (int) $weiss_row['imported']; ?></td>
						<td><?php echo (int) $weiss_row['active']; ?></td>
						<td><?php echo (int) $weiss_row['inactive']; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $weiss_report['groups'] ) : ?>
				<h3>По группам продуктов</h3>
				<table class="widefat striped" style="max-width:600px">
					<thead><tr><th>Поле «Продукт»</th><th>Записей</th><th>Активно</th></tr></thead>
					<tbody>
					<?php foreach ( $weiss_report['groups'] as $weiss_group => $weiss_row ) : ?>
						<tr>
							<td><?php echo esc_html( '' !== $weiss_group ? $weiss_group : '— краски (пусто)' ); ?></td>
							<td><?php echo (int) $weiss_row['source']; ?></td>
							<td><?php echo (int) $weiss_row['active']; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php
			$weiss_problems = array_merge(
				(array) $weiss_report['rejected'],
				(array) $weiss_report['incomplete'],
				(array) $weiss_report['duplicates']
			);
			?>
			<?php if ( $weiss_problems ) : ?>
				<h3>Строки, требующие внимания</h3>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Строка</th><th>Палитра</th><th>Код</th><th>Причина</th></tr></thead>
					<tbody>
					<?php foreach ( $weiss_problems as $weiss_row ) : ?>
						<tr>
							<td><?php echo (int) ( $weiss_row['row'] ?? 0 ); ?></td>
							<td><?php echo esc_html( $weiss_row['palette'] ?? '' ); ?></td>
							<td><?php echo esc_html( $weiss_row['code'] ?? '' ); ?></td>
							<td><?php echo esc_html( $weiss_row['reason'] ?? 'повтор ключа «палитра + код»' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		<?php endif; ?>

		<h2>Палитры</h2>
		<table class="widefat striped">
			<thead><tr><th>Палитра</th><th>Записей</th><th>Активных</th><th>Показ</th></tr></thead>
			<tbody>
			<?php foreach ( $palettes as $palette ) : ?>
				<tr>
					<td><?php echo esc_html( $palette->palette ); ?></td>
					<td><?php echo (int) $palette->total; ?></td>
					<td><?php echo (int) $palette->active; ?></td>
					<td>
						<form method="post">
							<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
							<input type="hidden" name="weiss_colors_action" value="palette_toggle">
							<input type="hidden" name="palette" value="<?php echo esc_attr( $palette->palette ); ?>">
							<input type="hidden" name="state" value="<?php echo $palette->active ? 'hide' : 'show'; ?>">
							<button class="button button-small" type="submit"><?php echo $palette->active ? 'Скрыть' : 'Показать'; ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php $weiss_map = weiss_color_product_map(); $weiss_choices = weiss_colors_admin_product_choices(); ?>
		<h2>Совместимость цветов с товарами</h2>
		<p class="description">
			На странице оттенка покупатель видит совместимые товары выбранной группы.
			Материалы «Краски для стен / кухни и ванной / фасадов / мебели» берут цвета с полем «Продукт», кроме лаков;
			«Краски для дерева» — цвета лаков.
		</p>
		<form method="post">
			<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
			<input type="hidden" name="weiss_colors_action" value="map">
			<table class="widefat striped" style="max-width:760px">
				<thead><tr><th>Материал</th><th>Товары</th></tr></thead>
				<tbody>
				<?php foreach ( $weiss_map as $weiss_group => $weiss_materials ) : ?>
					<?php foreach ( $weiss_materials as $weiss_material => $weiss_ids ) : ?>
						<tr>
							<td><?php echo esc_html( weiss_color_materials()[ $weiss_material ]['label'] ?? $weiss_material ); ?></td>
							<td>
								<select name="map[<?php echo esc_attr( $weiss_group ); ?>][<?php echo esc_attr( $weiss_material ); ?>][]" multiple size="4" style="min-width:420px">
									<?php foreach ( $weiss_choices as $weiss_id => $weiss_label ) : ?>
										<option value="<?php echo (int) $weiss_id; ?>"<?php selected( in_array( $weiss_id, $weiss_ids, true ) ); ?>><?php echo esc_html( $weiss_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary" type="submit">Сохранить совместимость</button></p>
		</form>

		<?php $weiss_rates = get_option( 'weiss_tint_surcharge', array() ); ?>
		<h2>Доплата за колеровку</h2>
		<p class="description">
			Цена позиции = цена фасовки + доплата за колеровку. Ставка действует на всю группу,
			если у товара или фасовки не задано своё значение в карточке товара (там 0 — без доплаты).
			Пока поле пустое, цена конфигурации с колеровкой неизвестна и такие заказы не оформляются —
			покупатель видит причину и может купить базовый цвет без колеровки.
		</p>
		<form method="post">
			<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
			<input type="hidden" name="weiss_colors_action" value="tint">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="weiss-tint-paint">Краски (группа А), ₽</label></th>
					<td>
						<input id="weiss-tint-paint" type="number" min="0" step="0.01" name="tint[paint]" value="<?php echo esc_attr( $weiss_rates['paint'] ?? '' ); ?>">
						<?php if ( null === weiss_tint_group_surcharge( 'paint' ) ) : ?>
							<p class="description" style="color:#b32d2e">Не согласована — колеровка красок не продаётся.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="weiss-tint-wood">Лаки и составы по дереву (группа Б), ₽</label></th>
					<td>
						<input id="weiss-tint-wood" type="number" min="0" step="0.01" name="tint[wood]" value="<?php echo esc_attr( $weiss_rates['wood'] ?? '' ); ?>">
						<?php if ( null === weiss_tint_group_surcharge( 'wood' ) ) : ?>
							<p class="description" style="color:#b32d2e">Не согласована — колеровка лаков не продаётся.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p><button class="button button-primary" type="submit">Сохранить доплату</button></p>
		</form>

		<h2>Добавить цвет</h2>
		<form method="post">
			<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
			<input type="hidden" name="weiss_colors_action" value="add_color">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="weiss-new-palette">Палитра</label></th>
					<td>
						<input id="weiss-new-palette" type="text" name="new_palette" list="weiss-palette-list" required>
						<datalist id="weiss-palette-list">
							<?php foreach ( $palettes as $palette ) : ?>
								<option value="<?php echo esc_attr( $palette->palette ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
						<p class="description">Новое название создаст новую палитру; существующее — добавит цвет в неё.</p>
					</td>
				</tr>
				<tr>
					<th><label for="weiss-new-code">Код</label></th>
					<td><input id="weiss-new-code" type="text" name="new_code" required></td>
				</tr>
				<tr>
					<th><label for="weiss-new-name">Название</label></th>
					<td><input id="weiss-new-name" type="text" name="new_name"></td>
				</tr>
				<tr>
					<th><label for="weiss-new-hex">Цвет HEX</label></th>
					<td><input id="weiss-new-hex" type="text" name="new_hex" placeholder="#A17A4F" pattern="#?[0-9A-Fa-f]{6}" required></td>
				</tr>
				<tr>
					<th><label for="weiss-new-group">Поле «Продукт»</label></th>
					<td>
						<input id="weiss-new-group" type="text" name="new_group" class="regular-text">
						<p class="description">Пусто — краска; слово «Лаки» в названии относит цвет к группе по дереву.</p>
					</td>
				</tr>
				<tr>
					<th></th>
					<td><label><input type="checkbox" name="new_inactive" value="1"> Сохранить скрытым</label></td>
				</tr>
			</table>
			<p>
				<button class="button button-primary" type="submit">Сохранить цвет</button>
				<a class="button" href="<?php echo esc_url( weiss_export_url( 'colors' ) ); ?>">Экспорт цветов в CSV</a>
			</p>
		</form>

		<h2>Цвета</h2>
		<form method="get">
			<input type="hidden" name="page" value="weiss-colors">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Код или название">
			<button class="button" type="submit">Найти</button>
		</form>

		<?php if ( $list ) : ?>
			<table class="widefat striped">
				<thead><tr><th>Палитра</th><th>Код</th><th>Название</th><th>Цвет</th><th>Статус</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $list['items'] as $color ) : ?>
					<tr>
						<td><?php echo esc_html( $color->palette ); ?></td>
						<td><?php echo esc_html( $color->code ); ?></td>
						<td>
							<form method="post" style="display:flex;gap:4px">
								<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
								<input type="hidden" name="weiss_colors_action" value="rename">
								<input type="hidden" name="color_id" value="<?php echo (int) $color->id; ?>">
								<input type="text" name="color_name" value="<?php echo esc_attr( $color->name ); ?>">
								<button class="button button-small" type="submit">Сохранить</button>
							</form>
						</td>
						<td><?php echo $color->hex ? '<span style="display:inline-block;width:28px;height:18px;border:1px solid #ccc;background:' . esc_attr( $color->hex ) . '"></span> ' . esc_html( $color->hex ) : '—'; ?></td>
						<td><?php echo $color->active ? 'активен' : 'скрыт'; ?><?php echo $color->note ? '<br><small>' . esc_html( $color->note ) . '</small>' : ''; ?></td>
						<td>
							<form method="post">
								<?php wp_nonce_field( 'weiss_colors_admin' ); ?>
								<input type="hidden" name="weiss_colors_action" value="toggle">
								<input type="hidden" name="color_id" value="<?php echo (int) $color->id; ?>">
								<button class="button button-small" type="submit"><?php echo $color->active ? 'Скрыть' : 'Показать'; ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $list['max_pages'],
				'prev_text' => 'Назад',
				'next_text' => 'Вперёд',
			) );
			?>
		<?php endif; ?>
	</div>
	<?php
}
