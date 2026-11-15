<?php
/**
 * Контакты (М14). Разметка перенесена из 02_верстка/pages/contacts.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( 'contacts-page' );
get_header();
?>
<nav class="crumbs" aria-label="Хлебные крошки">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <span aria-current="page">Контакты</span>
    </nav>

    <div class="contacts-layout">
      <div class="contacts-text">
        <section class="contacts-info" aria-labelledby="contacts-title">
          <h1 class="contacts-title" id="contacts-title">Контакты</h1>

          <p class="contacts-org">
            <span class="contacts-org__name"><?php echo esc_html( weiss_opt( 'seller_name' ) ); ?></span>
            <?php if ( weiss_opt( 'seller_address' ) ) : ?>
              <span><?php echo esc_html( weiss_opt( 'seller_address' ) ); ?></span>
            <?php endif; ?>
            <?php
            /* Реквизиты продавца (ТЗ 9.3): выводятся, когда заполнены в панели */
            $weiss_requisites = array();
            foreach ( array( 'seller_inn' => 'ИНН', 'seller_kpp' => 'КПП', 'seller_ogrn' => 'ОГРН' ) as $weiss_req_key => $weiss_req_label ) {
                $weiss_req_value = trim( (string) weiss_opt( $weiss_req_key ) );
                if ( '' !== $weiss_req_value ) {
                    $weiss_requisites[] = $weiss_req_label . ' ' . $weiss_req_value;
                }
            }
            if ( $weiss_requisites ) :
                ?>
                <span><?php echo esc_html( implode( ', ', $weiss_requisites ) ); ?></span>
            <?php endif; ?>
          </p>

          <p class="contacts-list">
            <?php $weiss_phone_html = weiss_phone_html(); ?>
            <?php if ( '' !== $weiss_phone_html ) : ?>
              <span>т. : <?php echo $weiss_phone_html; // phpcs:ignore WordPress.Security.EscapeOutput -- тег собран в content.php ?></span>
            <?php endif; ?>
            <?php $weiss_email = trim( (string) weiss_opt( 'email' ) ); ?>
            <?php if ( '' !== $weiss_email ) : ?>
              <span>e. : <a href="mailto:<?php echo esc_attr( $weiss_email ); ?>"><?php echo esc_html( $weiss_email ); ?></a></span>
            <?php endif; ?>
          </p>
        </section>

        <p class="contacts-place">
          Продукцию компании можно приобрести<br>
          у региональных представителей или на маркетплейсах
        </p>
      </div>

      <aside class="contacts-form-card" aria-labelledby="contacts-form-title">
        <h2 class="contacts-form-card__title" id="contacts-form-title">Есть вопросы?</h2>
        <p class="contacts-form-card__lead">Напишите нам и мы Вам перезвоним</p>

        <form class="contacts-form" id="contacts-form" novalidate>
          <div hidden aria-hidden="true">
            <label>Сайт<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>
          <label class="field">
            <span class="visually-hidden">Ваше имя</span>
            <input class="field__input" type="text" name="name" autocomplete="name" placeholder="Ваше имя" required>
          </label>

          <div class="field">
            <span class="visually-hidden" id="contacts-region-label">Регион</span>
            <div class="select" data-select>
              <button class="select__trigger" type="button" aria-haspopup="listbox" aria-expanded="false"
                      aria-labelledby="contacts-region-label contacts-region-value">
                <span class="select__value" id="contacts-region-value">Выберите регион</span>
                <svg class="select__chevron" viewBox="0 0 12 8" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                  <path d="M1 1.5 6 6.5l5-5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <ul class="select__list" role="listbox" aria-labelledby="contacts-region-label" hidden>
                <li class="select__option" role="option" aria-selected="false" tabindex="-1">Москва</li>
                <li class="select__option" role="option" aria-selected="false" tabindex="-1">Санкт-Петербург</li>
                <li class="select__option" role="option" aria-selected="false" tabindex="-1">Московская область</li>
                <li class="select__option" role="option" aria-selected="false" tabindex="-1">Ленинградская область</li>
                <li class="select__option" role="option" aria-selected="false" tabindex="-1">Удмуртская Республика</li>
                <li class="select__option" role="option" aria-selected="false" tabindex="-1">Другой регион</li>
              </ul>
              <input type="hidden" name="region" value="" required data-error="Выберите регион"
                     data-validate data-focus=".select__trigger">
            </div>
          </div>

          <label class="field">
            <span class="visually-hidden">Телефон</span>
            <input class="field__input" type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="+7 ( _ _ _ )_ _ _-_ _-_ _ " required>
          </label>

          <label class="field">
            <span class="visually-hidden">Сообщение</span>
            <textarea class="field__input" name="message" rows="4" maxlength="500" placeholder="Сообщение"></textarea>
          </label>

          <p class="contacts-consent">
            Оставляя свои персональные данные, Вы соглашаетесь с<br>
            <a href="<?php echo esc_url( weiss_page_url( 'legal' ) ); ?>">Политикой конфиденциальности</a><br>
            <a href="<?php echo esc_url( weiss_legal_link( 'oferta' ) ); ?>">Пользовательское соглашение</a>
          </p>

          <p class="form-error" data-form-error role="alert" hidden></p>

          <button class="contacts-submit" type="submit">Отправить</button>
        </form>
      </aside>
    </div>
<?php
get_footer();
