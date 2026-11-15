<?php
/**
 * Шапка: логотип, меню, телефон, мессенджеры, кнопка звонка, корзина (М10, п.3.1).
 * Открывает <main>: шаблон задаёт класс через weiss_set_main_class().
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">К основному содержанию</a>

<header class="site-header">
	<div class="header__inner">
		<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Weiss Color — на главную">
			<?php
			/* Логотип меняется в «Внешний вид → Настроить → Свойства сайта»;
			   без выбранного файла — логотип темы из макета. */
			$weiss_logo_id  = (int) get_theme_mod( 'custom_logo' );
			$weiss_logo_src = $weiss_logo_id ? wp_get_attachment_image_url( $weiss_logo_id, 'full' ) : '';
			?>
			<img src="<?php echo esc_url( $weiss_logo_src ? $weiss_logo_src : weiss_asset( 'img/logo.svg' ) ); ?>" width="186" height="52" alt="Weiss Color">
		</a>

		<button class="burger" type="button" aria-expanded="false" aria-controls="site-menu">
			<span class="visually-hidden">Показать меню</span>
			<span class="burger__line" aria-hidden="true"></span>
			<span class="burger__line" aria-hidden="true"></span>
			<span class="burger__line" aria-hidden="true"></span>
		</button>

		<nav class="site-nav" id="site-menu" aria-label="Основная навигация">
			<?php /* ТЗ 10.1: меню из панели; без назначенного меню — разметка макета. */ ?>
			<?php $weiss_nav = weiss_menu_items( 'primary' ); ?>
			<ul class="site-nav__list">
				<?php if ( $weiss_nav ) : ?>
					<?php foreach ( $weiss_nav as $weiss_item ) : ?>
						<li><a href="<?php echo esc_url( $weiss_item['url'] ); ?>"<?php echo $weiss_item['target'] ? ' target="' . esc_attr( $weiss_item['target'] ) . '" rel="noopener"' : ''; ?>><?php echo esc_html( $weiss_item['title'] ); ?></a></li>
					<?php endforeach; ?>
				<?php else : ?>
					<li><a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Каталог</a></li>
					<li><a href="<?php echo esc_url( weiss_page_url( 'where-to-buy' ) ); ?>">Где купить</a></li>
					<li><a href="<?php echo esc_url( weiss_page_url( 'about' ) ); ?>">О компании</a></li>
					<li><a href="<?php echo esc_url( weiss_page_url( 'contacts' ) ); ?>">Контакты</a></li>
					<li><a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a></li>
				<?php endif; ?>
			</ul>

			<div class="header__contacts">
				<?php $weiss_phone = weiss_phone(); ?>
				<a class="header__phone" href="<?php echo esc_url( $weiss_phone['href'] ); ?>" translate="no"><?php echo wp_kses_post( $weiss_phone['label'] ); ?></a>

				<ul class="socials" aria-label="Связаться в мессенджере">
					<?php
					$weiss_socials = array(
						'max'      => array( 'social_max', 'MAX' ),
						'telegram' => array( 'social_telegram', 'Telegram' ),
						'whatsapp' => array( 'social_whatsapp', 'WhatsApp' ),
					);
					foreach ( $weiss_socials as $weiss_icon => $weiss_channel ) :
						$weiss_url = weiss_social( $weiss_channel[0] );
						if ( '' === $weiss_url ) {
							continue;
						}
						?>
						<li>
							<a href="<?php echo esc_url( $weiss_url ); ?>" aria-label="<?php echo esc_attr( $weiss_channel[1] ); ?>" rel="noopener">
								<span class="socials__icon socials__icon--<?php echo esc_attr( $weiss_icon ); ?>" aria-hidden="true"></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>

				<button class="btn btn--outline header__cta" type="button" data-modal-open="callback-dialog">
					Заказать звонок
				</button>
			</div>
		</nav>

		<?php $weiss_count = weiss_cart_count(); ?>
		<a class="cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="Корзина, товаров: <?php echo esc_attr( $weiss_count ); ?>">
			<span class="cart__icon" aria-hidden="true"></span>
			<span class="cart__count tabular" aria-hidden="true"><?php echo esc_html( $weiss_count ); ?></span>
		</a>
	</div>
</header>

<main id="main" class="<?php echo esc_attr( weiss_main_class() ); ?>">
<?php
/* На оформлении и оплате заказа ошибки печатает сам шаблон в .checkout-notice:
   если полоса здесь напечатает их первой, шаблон уже не увидит текст. */
$weiss_wc_notice_band = ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' );
if ( $weiss_wc_notice_band && function_exists( 'wc_print_notices' ) ) :
	?>
	<div class="container"><div class="wc-notices"><?php wc_print_notices(); ?></div></div>
<?php endif; ?>
