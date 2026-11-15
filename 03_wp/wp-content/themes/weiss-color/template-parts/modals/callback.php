<?php
/**
 * Окно «Заказать звонок» (М25, п.9.3). Подключается в подвале на всех страницах,
 * потому что кнопка звонка есть в шапке и подвале каждого экрана.
 */
?>
<dialog class="mcard mcard-callback" id="callback-dialog" aria-labelledby="callback-title">
	<button class="mcard__close" type="button" data-modal-close aria-label="Закрыть окно">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
			<path d="M3 3l18 18M21 3 3 21" stroke-linecap="round"/>
		</svg>
	</button>

	<div class="callback-photo">
		<img class="callback-photo__logo" src="<?php echo esc_url( weiss_asset( 'img/logo-wordmark.svg' ) ); ?>" width="192" height="54" alt="Weiss Color">
		<hr class="callback-photo__rule">
		<p class="callback-photo__slogan">Вдохновляем<br>на красивые<br>интерьеры</p>
	</div>

	<form class="callback-form" id="callback-modal-form" novalidate>
		<div hidden aria-hidden="true">
			<label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		</div>
		<p class="callback-form__eyebrow">Заказать звонок</p>
		<h2 class="callback-form__title" id="callback-title">Мы свяжемся<br>с вами</h2>
		<p class="callback-form__text">
			Оставьте номер телефона и наш специалист перезвонит вам в ближайшее время,<br class="callback-form__break">
			чтобы ответить на все вопросы.
		</p>

		<label class="callback-field">
			<span class="visually-hidden">Ваше имя</span>
			<svg viewBox="0 0 32 33" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<circle cx="16" cy="10" r="6.5"/>
				<path d="M3 31c0-7.2 5.8-13 13-13s13 5.8 13 13" stroke-linecap="round"/>
			</svg>
			<input type="text" name="name" autocomplete="name" placeholder="Ваше имя" required>
		</label>

		<label class="callback-field">
			<span class="visually-hidden">Телефон</span>
			<svg viewBox="0 0 32 33" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M8 3h5l3 7-3.5 2.5a16 16 0 0 0 8 8L22 17l7 3v5c0 2.2-1.8 4-4 4C13.6 29 4 19.4 4 8c0-2.2 1.8-5 4-5Z" stroke-linejoin="round"/>
			</svg>
			<input type="tel" name="phone" autocomplete="tel" inputmode="tel"
			       placeholder="+7 ( _ _ _ )_ _ _-_ _-_ _" required>
		</label>

		<p class="form-error" data-form-error role="alert" hidden></p>

		<button class="btn btn--brown" type="submit">
			Заказать звонок
			<svg class="btn__arrow" viewBox="0 0 52 15" fill="none" aria-hidden="true">
				<path d="M0 6h44v3H0zM41 0l11 7.5L41 15z" fill="currentColor"/>
			</svg>
		</button>

		<p class="callback-form__note">
			<svg viewBox="0 0 17 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<rect x="1" y="10" width="15" height="13" rx="2"/>
				<path d="M4 10V7a4.5 4.5 0 0 1 9 0v3"/>
			</svg>
			Ваши данные защищены и не будут переданы третьим лицам
		</p>
	</form>
</dialog>
