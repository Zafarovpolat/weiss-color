<?php
/**
 * Тема Weiss Color: точка входа.
 */

defined( 'ABSPATH' ) || exit;

define( 'WEISS_VERSION', '0.1.0' );
define( 'WEISS_DIR', get_template_directory() );
define( 'WEISS_URI', get_template_directory_uri() );

require WEISS_DIR . '/inc/helpers.php';
require WEISS_DIR . '/inc/setup.php';
require WEISS_DIR . '/inc/enqueue.php';
require WEISS_DIR . '/inc/woocommerce.php';
require WEISS_DIR . '/inc/sberbank.php';
require WEISS_DIR . '/inc/colors.php';
require WEISS_DIR . '/inc/tinting.php';
require WEISS_DIR . '/inc/colors-cart.php';
require WEISS_DIR . '/inc/leads.php';
require WEISS_DIR . '/inc/shipping.php';
require WEISS_DIR . '/inc/mail-log.php';
require WEISS_DIR . '/inc/pay.php';
require WEISS_DIR . '/inc/roles.php';
require WEISS_DIR . '/inc/export.php';
require WEISS_DIR . '/inc/content.php';
require WEISS_DIR . '/inc/legal.php';
require WEISS_DIR . '/inc/seo.php';
