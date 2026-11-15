<?php
/**
 * Роли и права (ТЗ, п.10.1): администратор и менеджер магазина.
 * Менеджер ведёт торговлю (товары, заказы, промокоды, обращения, письма),
 * но не получает доступ к настройкам сайта, темам, плагинам и пользователям.
 */

define( 'WEISS_ROLES_VERSION', '3' );

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

function weiss_roles_sync() {
	if ( get_option( 'weiss_roles_version' ) === WEISS_ROLES_VERSION ) {
		return;
	}

	$manager = get_role( 'shop_manager' );
	if ( $manager ) {
		foreach ( weiss_manager_extra_caps() as $cap ) {
			$manager->remove_cap( $cap );
		}
		foreach ( weiss_lead_caps() as $cap ) {
			$manager->add_cap( $cap );
		}
	}

	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( weiss_lead_caps() as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	update_option( 'weiss_roles_version', WEISS_ROLES_VERSION, false );
}
add_action( 'init', 'weiss_roles_sync', 20 );

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
}, 99 );

/** Стартовый экран менеджера — заказы, а не «Консоль». */
add_filter( 'login_redirect', function ( $redirect, $request, $user ) {
	if ( $user instanceof WP_User && in_array( 'shop_manager', (array) $user->roles, true ) ) {
		return admin_url( 'admin.php?page=wc-orders' );
	}
	return $redirect;
}, 10, 3 );
