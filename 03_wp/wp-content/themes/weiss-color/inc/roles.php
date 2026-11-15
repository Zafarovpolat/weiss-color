<?php
/**
 * Роли и права (ТЗ, п.10.1): администратор и менеджер магазина.
 * Менеджер ведёт торговлю (товары, заказы, промокоды, обращения, письма),
 * но не получает доступ к настройкам сайта, темам, плагинам и пользователям.
 */

define( 'WEISS_ROLES_VERSION', '4' );

/** Права на обращения: у типа записи собственный набор (см. inc/leads.php). */
function weiss_lead_caps() {
	return array(
		'edit_weiss_lead',
		'read_weiss_lead',
		'delete_weiss_lead',
		'edit_weiss_leads',
		'edit_others_weiss_leads',
		'publish_weiss_leads',
		'read_private_weiss_leads',
		'delete_weiss_leads',
		'delete_private_weiss_leads',
		'delete_published_weiss_leads',
		'delete_others_weiss_leads',
		'edit_private_weiss_leads',
		'edit_published_weiss_leads',
		'create_weiss_leads',
	);
}

/**
 * Права, которые роли WooCommerce «Менеджер магазина» не нужны.
 * Страницы и записи сайта — зона администратора (ТЗ 10.1: у менеджера
 * только торговые функции), поэтому права на них снимаются полностью,
 * включая публикацию, удаление и чтение чужих черновиков.
 */
function weiss_manager_extra_caps() {
	$content = array();
	foreach ( array( 'posts', 'pages' ) as $type ) {
		$content = array_merge( $content, array(
			'edit_' . $type,
			'edit_others_' . $type,
			'edit_published_' . $type,
			'edit_private_' . $type,
			'publish_' . $type,
			'delete_' . $type,
			'delete_others_' . $type,
			'delete_published_' . $type,
			'delete_private_' . $type,
			'read_private_' . $type,
		) );
	}

	return array_merge( $content, array(
		'edit_theme_options',
		/* ТЗ 10.1: пользователи — не торговая функция; скрытого пункта меню мало,
		   право нельзя оставлять, иначе оно доступно по прямому адресу */
		'list_users',
		'edit_users',
		'create_users',
		'delete_users',
		'promote_users',
		'remove_users',
		'manage_links',
		'moderate_comments',
		'manage_categories',
		'import',
		'export',
	) );
}

/**
 * Инструмент WooCommerce «Сбросить возможности» пересоздаёт роль с заводскими
 * правами. Одной проверки версии мало: она не менялась, и ограничения терялись.
 * Поэтому права снимаем при каждом расхождении, а не только при смене версии,
 * и дополнительно держим запрет фильтром (см. weiss_manager_caps_filter).
 */
function weiss_roles_sync() {
    $dirty = get_option( 'weiss_roles_version' ) !== WEISS_ROLES_VERSION;

    $manager = get_role( 'shop_manager' );
    if ( $manager ) {
        foreach ( weiss_manager_extra_caps() as $cap ) {
            if ( $manager->has_cap( $cap ) ) {
                $manager->remove_cap( $cap );
                $dirty = true;
            }
        }
        foreach ( weiss_lead_caps() as $cap ) {
            if ( ! $manager->has_cap( $cap ) ) {
                $manager->add_cap( $cap );
                $dirty = true;
            }
        }
    }

    $admin = get_role( 'administrator' );
    if ( $admin ) {
        foreach ( weiss_lead_caps() as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
                $dirty = true;
            }
        }
    }

    if ( $dirty ) {
        update_option( 'weiss_roles_version', WEISS_ROLES_VERSION, false );
    }
}
add_action( 'init', 'weiss_roles_sync', 1 );

/**
 * Вторая линия защиты: даже если роль снова получила лишние права, проверка
 * прав её не отпустит. Обращения остаются доступны менеджеру, остальное — нет.
 */
function weiss_manager_deny_caps( $caps ) {
    foreach ( weiss_manager_extra_caps() as $cap ) {
        $caps[ $cap ] = false;
    }
    return $caps;
}

add_filter( 'user_has_cap', function ( $allcaps, $caps, $args, $user ) {
    if ( ! $user instanceof WP_User || empty( $user->roles ) ) {
        return $allcaps;
    }
    if ( ! in_array( 'shop_manager', (array) $user->roles, true ) ) {
        return $allcaps;
    }
    if ( ! empty( $allcaps['manage_options'] ) ) {
        return $allcaps;
    }

    $allcaps = weiss_manager_deny_caps( $allcaps );
    foreach ( weiss_lead_caps() as $cap ) {
        $allcaps[ $cap ] = true;
    }

    return $allcaps;
}, 999, 4 );

/**
 * Менеджеру не показываем чужие для торговли разделы, даже если плагин
 * выдал права: сайт, внешний вид, инструменты, пользователи.
 */
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
		return;
	}

	remove_menu_page( 'index.php' );
	remove_menu_page( 'edit.php' );
	remove_menu_page( 'upload.php' );
	remove_menu_page( 'edit.php?post_type=page' );
	remove_menu_page( 'edit-comments.php' );
	remove_menu_page( 'themes.php' );
	remove_menu_page( 'plugins.php' );
	remove_menu_page( 'tools.php' );
	remove_menu_page( 'options-general.php' );
	remove_menu_page( 'users.php' );

	/* ТЗ 10.1: настройки магазина, статус и инструменты — не торговые
	   функции. Меню прячем, прямой адрес и REST закрываем ниже. */
	remove_submenu_page( 'woocommerce', 'wc-settings' );
	remove_submenu_page( 'woocommerce', 'wc-status' );
	remove_submenu_page( 'woocommerce', 'wc-settings&tab=checkout' );
	remove_submenu_page( 'woocommerce', 'wc-settings&tab=shipping' );
}, 99 );

/** Прямой заход на настройки и статус WooCommerce менеджеру закрыт. */
add_action( 'current_screen', function ( $screen ) {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! in_array( 'shop_manager', (array) wp_get_current_user()->roles, true ) ) {
		return;
	}

	$blocked = array( 'woocommerce_page_wc-settings', 'woocommerce_page_wc-status' );
	if ( in_array( $screen->id, $blocked, true ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}
} );

/**
 * REST-маршруты WooCommerce: менеджеру оставлены только торговые разделы.
 * Запрет по списку запрещённого оставлял открытыми платёжные шлюзы с
 * логином и паролем эквайринга, зоны доставки, налоги и вебхуки
 * (замечание 95), поэтому здесь список разрешённого: всё остальное —
 * настройки магазина и инструменты, они только у администратора.
 * Ставки налогов и способы доставки менеджер может читать (справочник
 * нужен в карточке товара и заказа), но не менять.
 * Фильтр стоит на rest_request_before_callbacks: возврат ошибки из
 * rest_pre_dispatch ломал пакетные запросы ядра (замечание 73).
 */
function weiss_wc_manager_route_allowed( $route, $method ) {
	$route  = (string) $route;
	$method = strtoupper( (string) $method );

	/* Служебные пространства WooCommerce Admin: /wc-admin/options меняет
	   настройки магазина, рядом — установка плагинов, онбординг и
	   аналитика. Менеджеру они не нужны (замечание 95). */
	if ( preg_match( '#^/(wc-admin|wc-admin-email|wc-analytics|wc-telemetry|wc-push-notifications|wccom-site|wc/private)(/|$)#', $route ) ) {
		return false;
	}
	if ( ! preg_match( '#^/wc/v\d+/([a-z_]+)#', $route, $match ) ) {
		return true;
	}

	$allowed = array( 'orders', 'products', 'coupons', 'customers', 'reports', 'data' );
	if ( in_array( $match[1], $allowed, true ) ) {
		return true;
	}

	if ( 'GET' === $method && in_array( $match[1], array( 'taxes', 'shipping' ), true ) ) {
		return true;
	}

	return false;
}

add_filter( 'rest_request_before_callbacks', function ( $response, $handler, $request ) {
	if ( null !== $response ) {
		return $response;
	}
	if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
		return $response;
	}
	if ( ! weiss_wc_manager_route_allowed( $request->get_route(), $request->get_method() ) ) {
		return new WP_Error( 'weiss_forbidden', 'Недостаточно прав.', array( 'status' => 403 ) );
	}
	return $response;
}, 10, 3 );

/** Стартовый экран менеджера — заказы, а не «Консоль». */
add_filter( 'login_redirect', function ( $redirect, $request, $user ) {
	if ( $user instanceof WP_User && in_array( 'shop_manager', (array) $user->roles, true ) ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}
	return $redirect;
}, 10, 3 );
