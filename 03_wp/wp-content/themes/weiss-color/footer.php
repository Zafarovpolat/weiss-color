<?php
/**
 * Подвал: навигация, правовые ссылки, кнопка звонка (п.3.1) + окна,
 * которые нужны на всех экранах.
 */
?>
</main>

<footer class="site-footer">
	<div class="footer__inner">
		<a class="footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Weiss Color — на главную">
			<img src="<?php echo esc_url( weiss_asset( 'img/logo-white.svg' ) ); ?>" width="186" height="52" alt="Weiss Color">
		</a>

		<nav class="footer__nav" aria-label="Навигация в подвале">
			<?php /* ТЗ 10.1: меню из панели; без назначенного меню — разметка макета. */ ?>
			<?php $weiss_footer_nav = weiss_menu_items( 'footer' ); ?>
			<?php if ( $weiss_footer_nav ) : ?>
				<?php foreach ( $weiss_footer_nav as $weiss_item ) : ?>
					<a href="<?php echo esc_url( $weiss_item['url'] ); ?>"<?php echo $weiss_item['target'] ? ' target="' . esc_attr( $weiss_item['target'] ) . '" rel="noopener"' : ''; ?>><?php echo esc_html( $weiss_item['title'] ); ?></a>
				<?php endforeach; ?>
			<?php else : ?>
				<a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>">Каталог</a>
				<a href="<?php echo esc_url( weiss_page_url( 'where-to-buy' ) ); ?>">Где купить</a>
				<a href="<?php echo esc_url( weiss_page_url( 'about' ) ); ?>">О компании</a>
				<a href="<?php echo esc_url( weiss_page_url( 'contacts' ) ); ?>">Контакты</a>
				<?php /* в статике coloring-guide.html в подвале только четыре пункта */ ?>
				<?php if ( 'coloring-guide' !== weiss_screen() ) : ?>
					<a href="<?php echo esc_url( weiss_page_url( 'palettes' ) ); ?>">Колеровка цвета</a>
				<?php endif; ?>
			<?php endif; ?>
		</nav>

		<div class="footer__bottom">
			<div class="footer__legal">
				<?php if ( 'coloring-guide' === weiss_screen() ) : ?>
					<a class="footer__copy" href="<?php echo esc_url( weiss_page_url( 'legal' ) ); ?>">© Weiss Color, 2020–2025. Все права защищены.</a>
				<?php else : ?>
					<a class="footer__copy" href="<?php echo esc_url( weiss_page_url( 'legal' ) ); ?>">
						Политика конфиденциальности и обработки персональных данных
					</a>
				<?php endif; ?>

				<?php /* ТЗ 9.4: остальные правовые документы появляются в подвале,
				         когда Заказчик опубликует их текст. */ ?>
				<?php $weiss_legal_docs = weiss_legal_documents(); ?>
				<?php if ( $weiss_legal_docs ) : ?>
					<nav class="footer__docs" aria-label="Правовые документы">
						<?php foreach ( $weiss_legal_docs as $weiss_doc ) : ?>
							<a class="footer__copy" href="<?php echo esc_url( $weiss_doc['url'] ); ?>"><?php echo esc_html( $weiss_doc['title'] ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>

				<?php /* п.9.5: настройки cookie открываются из подвала после первого выбора.
				         До выбора кнопка скрыта, чтобы подвал совпадал с макетом. Вторая
				         строка под «Политикой»: в одну строку ссылка на узких ширинах
				         наезжала на меню подвала. */ ?>
				<button class="footer__cookie" type="button" data-cookie-open hidden>Настройки cookie</button>
			</div>

			<button class="btn btn--ghost footer__cta" type="button" data-modal-open="callback-dialog">
				Заказать звонок
			</button>
		</div>
	</div>
</footer>

<?php get_template_part( 'template-parts/modals/callback' ); ?>

<?php /* М20: уведомление о cookie (п.9.5). Показ и сохранение выбора — js/cookie.js */ ?>
<div class="cookie-panel" data-cookie-panel hidden>
	<aside class="cookie-bar" id="cookie-bar" role="region" aria-labelledby="cookie-title">
		<img class="cookie-bar__img" src="<?php echo esc_url( weiss_asset( 'img/cookie.png' ) ); ?>" width="261" height="229" alt="">

		<div class="cookie-bar__content">
			<h2 class="cookie-bar__title" id="cookie-title">Мы используем cookie</h2>
			<p class="cookie-bar__text">
				Файлы cookie помогают нам делать сайт удобнее для вас:
				запоминать настройки, показывать актуальные предложения и анализировать посещаемость.
			</p>
			<a class="cookie-bar__link" href="<?php echo esc_url( weiss_page_url( 'legal' ) ); ?>">
				<span class="cookie-bar__label">Подробнее в Политике конфиденциальности</span>
				<svg viewBox="0 0 26 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
					<path d="M1 8h23M18 2l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</a>
		</div>

		<div class="cookie-bar__actions">
			<button class="btn btn--brown" type="button" data-cookie="accept">Принять все</button>
			<button class="btn btn--outline" type="button" data-cookie="settings">Настроить</button>
		</div>

		<button class="cookie-bar__close" type="button" data-cookie="close" aria-label="Закрыть уведомление о cookie">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
				<path d="M5 5l14 14M19 5L5 19" stroke-linecap="round"/>
			</svg>
		</button>

		<div class="cookie-settings" id="cookie-settings" hidden>
			<p class="cookie-settings__lead">Выберите, какие файлы cookie можно использовать.</p>

			<label class="cookie-option">
				<input type="checkbox" data-cookie-category="necessary" checked disabled>
				<span class="cookie-option__box" aria-hidden="true"></span>
				<span class="cookie-option__body">
					<span class="cookie-option__name">Необходимые</span>
					<span class="cookie-option__text">Без них сайт не работает: корзина, оформление заказа, выбранный оттенок. Отключить нельзя.</span>
				</span>
			</label>

			<label class="cookie-option">
				<input type="checkbox" data-cookie-category="analytics">
				<span class="cookie-option__box" aria-hidden="true"></span>
				<span class="cookie-option__body">
					<span class="cookie-option__name">Аналитика</span>
					<span class="cookie-option__text">Помогает понять, какими разделами пользуются чаще, и исправлять неудобные места.</span>
				</span>
			</label>

			<label class="cookie-option">
				<input type="checkbox" data-cookie-category="ads">
				<span class="cookie-option__box" aria-hidden="true"></span>
				<span class="cookie-option__body">
					<span class="cookie-option__name">Реклама</span>
					<span class="cookie-option__text">Показывает актуальные предложения и не повторяет одну и ту же рекламу.</span>
				</span>
			</label>

			<button class="btn btn--brown cookie-settings__save" type="button" data-cookie="save">Сохранить выбор</button>
		</div>

		<img class="cookie-bar__leaf" src="<?php echo esc_url( weiss_asset( 'img/cookie-leaf.png' ) ); ?>" width="210" height="216" alt="">
	</aside>
</div>

<?php wp_footer(); ?>
</body>
</html>
