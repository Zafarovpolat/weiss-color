<?php
/**
 * Техническое SEO (ТЗ, п.11.7): редактируемые title и description,
 * canonical, sitemap.xml, закрытые от индексации служебные страницы.
 */

/** description: своё поле, затем краткое описание товара, затем описание страницы. */
function weiss_meta_description() {
	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		$custom  = trim( (string) get_post_meta( $post_id, '_weiss_meta_description', true ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		$excerpt = trim( wp_strip_all_tags( (string) get_the_excerpt( $post_id ) ) );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		$content = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ) ) );
		if ( '' !== $content ) {
			return $content;
		}

		/* У страниц без текста описание по заголовку: иначе на всех страницах
		   повторялась одна подпись сайта (ТЗ 11.7). */
		return sprintf( '%s — интернет-магазин красок Weiss Color.', get_the_title( $post_id ) );
	}

	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->description ) ) {
			return wp_strip_all_tags( $term->description );
		}
		if ( $term && ! empty( $term->name ) ) {
			return sprintf( 'Товары в разделе «%s» — интернет-магазин Weiss Color.', $term->name );
		}
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return 'Каталог красок, лаков и пропиток Weiss Color: фасовки, цены и подбор цвета.';
	}

	return get_bloginfo( 'description' );
}

/** Служебные страницы не индексируются (ТЗ 11.7). */
function weiss_is_service_page() {
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return true;
	}
	if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) {
		return true;
	}
	if ( is_page( 'order-success' ) || is_page_template( 'page-order-success.php' ) ) {
		return true;
	}
	/* Товар-пробник — служебная позиция для конфигуратора /sampler/. */
	if ( is_singular( 'product' ) && weiss_is_sampler_product( get_queried_object_id() ) ) {
		return true;
	}
	return is_search() || is_404();
}

/** Товар, через который оформляется пробник (метка _weiss_sampler). */
function weiss_is_sampler_product( $product_id ) {
	if ( ! function_exists( 'weiss_sampler_product' ) ) {
		return false;
	}
	$sampler = weiss_sampler_product();
	return $sampler && (int) $sampler->get_id() === (int) $product_id;
}

/** Страницы, которых не должно быть в карте сайта. */
function weiss_sitemap_excluded_ids() {
	$ids = array();
	foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
		$id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( $page ) : 0;
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}

	$success = get_page_by_path( 'order-success' );
	if ( $success ) {
		$ids[] = $success->ID;
	}

	/* /color/ без параметров отвечает 404, «Образец страницы» — заготовка ядра. */
	foreach ( array( 'color', 'sample-page' ) as $path ) {
		$page = get_page_by_path( $path );
		if ( $page ) {
			$ids[] = $page->ID;
		}
	}

	$ids = array_merge( $ids, get_posts( array(
		'post_type'      => 'page',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_wp_page_template',
		'meta_value'     => 'page-order-success.php',
	) ) );

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/** Запись «Привет, мир!» — заготовка ядра, в карту сайта не нужна. */
function weiss_sitemap_excluded_post_ids() {
	$ids   = array();
	$hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
	if ( ! $hello ) {
		$first = get_post( 1 );
		if ( $first && 'post' === $first->post_type && in_array( $first->post_title, array( 'Привет, мир!', 'Hello world!' ), true ) ) {
			$hello = $first;
		}
	}
	if ( $hello ) {
		$ids[] = $hello->ID;
	}
	return $ids;
}

/** Авторских страниц нет — список пользователей в карте сайта тоже не нужен. */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );

add_action( 'wp_head', function () {
	$description = weiss_meta_description();
	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( wp_trim_words( $description, 40, '' ) ) . '">' . "\n";
	}

	if ( weiss_is_service_page() ) {
		echo '<meta name="robots" content="noindex,nofollow">' . "\n";
	}

	/* Canonical для архива товаров и категорий: ядро выводит его только
	   для одиночных записей. */
	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
		$url  = is_shop() ? get_permalink( wc_get_page_id( 'shop' ) ) : get_term_link( get_queried_object() );
		$page = max( 1, (int) get_query_var( 'paged' ) );
		if ( ! is_wp_error( $url ) ) {
			if ( $page > 1 ) {
				$url = trailingslashit( $url ) . 'page/' . $page . '/';
			}
			echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
		}
	}
}, 1 );

/** Своё SEO-название страницы заменяет <title> целиком. */
add_filter( 'document_title_parts', function ( $parts ) {
	if ( is_singular() ) {
		$custom = (string) get_post_meta( get_queried_object_id(), '_weiss_seo_title', true );
		if ( '' !== trim( $custom ) ) {
			return array( 'title' => $custom );
		}
	}
	return $parts;
} );

/**
 * Служебные страницы WooCommerce созданы ядром с английскими названиями
 * («Shop», «Cart», «My account»). У них русский заголовок вкладки (ТЗ 11.7).
 */
add_filter( 'document_title_parts', function ( $parts ) {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$parts['title'] = 'Каталог продукции';
	} elseif ( function_exists( 'is_cart' ) && is_cart() ) {
		$parts['title'] = 'Корзина';
	} elseif ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$parts['title'] = 'Личный кабинет';
	}
	return $parts;
}, 20 );

/** Тот же перевод и у заголовка на странице, а не только во вкладке. */
add_filter( 'the_title', function ( $title, $post_id = 0 ) {
	if ( is_admin() || ! function_exists( 'wc_get_page_id' ) ) {
		return $title;
	}
	if ( (int) $post_id === (int) wc_get_page_id( 'myaccount' ) && is_account_page() ) {
		return 'Личный кабинет';
	}
	return $title;
}, 10, 2 );

/**
 * Авторский архив раскрывает логин администратора (ТЗ 11.7). Закрываем его
 * и перебор /?author=N — отдаём 404, как у несуществующей страницы.
 */
add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! is_author() ) {
		return;
	}

	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_404_template();
	exit;
}, 1 );

/** ?author=N не перенаправляем на /author/… — сразу 404, без выдачи логина. */
add_filter( 'redirect_canonical', function ( $redirect, $requested ) {
	if ( isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return false;
	}
	return $redirect;
}, 10, 2 );

/* Панель: поля SEO у страниц, записей и товаров. */
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'page', 'post', 'product' ) as $type ) {
		add_meta_box( 'weiss-seo', 'SEO', function ( $post ) {
			wp_nonce_field( 'weiss_seo_save', 'weiss_seo_nonce' );
			$title = get_post_meta( $post->ID, '_weiss_seo_title', true );
			$desc  = get_post_meta( $post->ID, '_weiss_meta_description', true );
			?>
			<p>
				<label for="weiss-seo-title"><strong>Title</strong></label>
				<input class="widefat" type="text" id="weiss-seo-title" name="weiss_seo_title"
				       value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>">
			</p>
			<p>
				<label for="weiss-seo-desc"><strong>Description</strong></label>
				<textarea class="widefat" rows="3" id="weiss-seo-desc" name="weiss_meta_description" maxlength="300"><?php echo esc_textarea( $desc ); ?></textarea>
			</p>
			<p class="description">Пустые поля — берутся заголовок и краткое описание записи.</p>
			<?php
		}, $type, 'normal', 'low' );
	}
} );

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['weiss_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['weiss_seo_nonce'] ), 'weiss_seo_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, '_weiss_seo_title', sanitize_text_field( wp_unslash( $_POST['weiss_seo_title'] ?? '' ) ) );
	update_post_meta( $post_id, '_weiss_meta_description', sanitize_textarea_field( wp_unslash( $_POST['weiss_meta_description'] ?? '' ) ) );
} );

/** Заготовки ядра в карте сайта: рубрика «Без рубрики» и пустая Uncategorized. */
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	$exclude = isset( $args['exclude'] ) ? (array) $args['exclude'] : array();

	if ( 'category' === $taxonomy ) {
		$default = (int) get_option( 'default_category' );
		if ( $default > 0 ) {
			$exclude[] = $default;
		}
	}

	if ( 'product_cat' === $taxonomy ) {
		$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
		if ( $uncategorized ) {
			$exclude[] = (int) $uncategorized->term_id;
		}
	}

	if ( $exclude ) {
		$args['exclude'] = array_values( array_unique( array_map( 'intval', $exclude ) ) );
	}

	return $args;
}, 10, 2 );

/** Карта сайта ядра включена; служебные страницы в неё не попадают. */
add_filter( 'wp_sitemaps_enabled', '__return_true' );

add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$exclude = weiss_sitemap_excluded_ids();
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), $exclude );
		}
	}

	if ( 'post' === $post_type ) {
		$exclude = weiss_sitemap_excluded_post_ids();
		if ( $exclude ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), $exclude );
		}
	}

	if ( 'product' === $post_type ) {
		$sampler = function_exists( 'weiss_sampler_product' ) ? weiss_sampler_product() : null;
		if ( $sampler ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), array( $sampler->get_id() ) );
		}
	}

	return $args;
}, 10, 2 );

/**
 * ТЗ 11.7 называет адреса sitemap.xml и robots.txt. Ядро отдаёт карту по
 * /wp-sitemap.xml и не создаёт правило robots.txt, когда WordPress стоит
 * в подкаталоге. Добавляем оба адреса и один раз сбрасываем правила.
 */
function weiss_seo_rewrites() {
	add_rewrite_rule( '^sitemap\.xml$', 'index.php?sitemap=index', 'top' );
	add_rewrite_rule( '^robots\.txt$', 'index.php?robots=1', 'top' );

	if ( get_option( 'weiss_seo_rewrite_version' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'weiss_seo_rewrite_version', '1', false );
	}
}
add_action( 'init', 'weiss_seo_rewrites', 5 );

/** В robots.txt карта сайта — по адресу из ТЗ. */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( $public ) {
		$output = str_replace( home_url( 'wp-sitemap.xml' ), home_url( 'sitemap.xml' ), $output );
	}
	return $output;
}, 20, 2 );

/** Не уводим /sitemap.xml на /wp-sitemap.xml: карта отдаётся по адресу из ТЗ. */
add_filter( 'redirect_canonical', function ( $redirect, $requested ) {
	if ( ! get_query_var( 'sitemap' ) ) {
		return $redirect;
	}
	$path = wp_parse_url( $requested, PHP_URL_PATH );
	if ( trailingslashit( (string) $path ) === trailingslashit( wp_parse_url( home_url( '/sitemap.xml' ), PHP_URL_PATH ) ) ) {
		return false;
	}
	return $redirect;
}, 10, 2 );

/**
 * Тестовый стенд закрыт от индексации (ТЗ 11.7). Настройка WordPress может
 * сброситься при переносе базы, поэтому для стенда она принудительно
 * выключена: ядро само отдаёт noindex и закрывает robots.txt.
 */
function weiss_is_staging_host() {
	$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	return 'weisscolor.ruso.agency' === $host
		|| 'localhost' === $host
		|| '127.0.0.1' === $host
		|| (bool) preg_match( '/\.(local|test|localhost)$/', $host );
}

add_filter( 'pre_option_blog_public', function ( $value ) {
	return weiss_is_staging_host() ? '0' : $value;
} );

/**
 * Запрет индексации хранится и в базе: иначе после смены адреса стенда
 * настройка «Разрешить поисковикам» открыла бы сайт (ТЗ 11.7). На боевом
 * домене флаг не трогаем — его включает администратор при запуске.
 */
add_action( 'init', function () {
	if ( ! weiss_is_staging_host() ) {
		return;
	}

	global $wpdb;
	$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'blog_public' ) ); // phpcs:ignore WordPress.DB
	if ( '0' !== (string) $stored ) {
		update_option( 'blog_public', '0' );
	}
}, 5 );

/**
 * Перенаправления 301 по реестру Заказчика (ТЗ 11.7). Реестр вводится в
 * «Настройки → Содержимое сайта», по строке на адрес: «старый → новый».
 * До передачи реестра список пуст и ничего не перенаправляется.
 */
function weiss_redirect_path( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	$path = wp_parse_url( $value, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		$path = $value;
	}

	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$home = '/' === $home ? '' : untrailingslashit( $home );
	if ( '' !== $home && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}

	return untrailingslashit( '/' . ltrim( $path, '/' ) );
}

function weiss_redirect_map() {
	$map = array();
	foreach ( preg_split( '/\R/', (string) weiss_opt( 'redirects' ) ) as $line ) {
		if ( ! preg_match( '/^(.+?)\s*(?:=>|->|→|\||;)\s*(.+)$/u', trim( $line ), $match ) ) {
			continue;
		}
		$from = weiss_redirect_path( $match[1] );
		$to   = trim( $match[2] );
		if ( '' !== $from && '' !== $to && $from !== weiss_redirect_path( $to ) ) {
			$map[ $from ] = $to;
		}
	}
	return $map;
}

add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true ) ) {
		return;
	}

	$map = weiss_redirect_map();
	if ( ! $map ) {
		return;
	}

	$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path    = weiss_redirect_path( (string) wp_parse_url( $request, PHP_URL_PATH ) );

	if ( isset( $map[ $path ] ) ) {
		$to = $map[ $path ];
		/* Путь без домена дополняем адресом сайта: при установке в подкаталоге
		   «/new/» иначе увёл бы на корень домена. */
		if ( 0 === strpos( $to, '/' ) && '//' !== substr( $to, 0, 2 ) ) {
			$to = home_url( $to );
		}

		/* Абсолютный адрес старого домена: wp_safe_redirect() без списка
		   разрешённых хостов уводил в /wp-admin/ (замечание 69). */
		$scheme = strtolower( (string) wp_parse_url( $to, PHP_URL_SCHEME ) );
		if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return;
		}
		$host = strtolower( (string) wp_parse_url( $to, PHP_URL_HOST ) );
		if ( '' !== $host ) {
			add_filter( 'allowed_redirect_hosts', function ( $hosts ) use ( $host ) {
				$hosts[] = $host;
				return $hosts;
			} );
		}

		wp_safe_redirect( $to, 301, 'Weiss 301' );
		exit;
	}
}, 1 );
