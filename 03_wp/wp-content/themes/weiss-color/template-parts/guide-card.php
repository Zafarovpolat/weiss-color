<?php
/**
 * Карточка «Как это работает?» (М21). Одна разметка на страницу-инструкцию
 * и на модальное окно визуализатора; различаются только заголовок и CTA.
 *
 * Аргументы:
 *   viz_url     — ссылка «Выбрать цвет» (на странице-инструкции);
 *   sampler_url — ссылка «Заказать пробный образец»;
 *   close_cta   — в окне визуализатора «Выбрать цвет» просто закрывает окно;
 *   h2          — в модальном окне заголовок второго уровня.
 *
 * @package Weiss_Color
 */

defined( 'ABSPATH' ) || exit;

$weiss_viz_url     = $args['viz_url'] ?? weiss_page_url( 'visualizer' );
$weiss_sampler_url = $args['sampler_url'] ?? weiss_page_url( 'sampler' );
$weiss_close_cta   = ! empty( $args['close_cta'] );
$weiss_heading     = ! empty( $args['h2'] ) ? 'h2' : 'h1';
?>
<article class="guide-card">
      <?php if ( $weiss_close_cta ) : ?>
        <button class="mcard__close" type="button" data-modal-close aria-label="Закрыть окно">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
            <path d="M3 3l18 18M21 3 3 21" stroke-linecap="round"/>
          </svg>
        </button>
      <?php endif; ?>
      <<?php echo $weiss_heading; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="guide-title" id="guide-title">Как это работает?</<?php echo $weiss_heading; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
      <p class="guide-sub">Выберите цвет, и мы заколеруем краску специально для вас</p>

      <ol class="guide-steps">
        <li class="guide-step">
          <div class="guide-step__head">
            <span class="guide-step__num" aria-hidden="true"></span>
            <h2 class="guide-step__title">Выберите каталог</h2>
          </div>
          <img class="guide-step__img" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/guide-step1.png" width="326" height="390" alt="Веер образцов каталогов цветов Weiss" loading="lazy">
          <p class="guide-step__text">Выберите каталог цветов, <br>доступный для колеровки: <br>например ACE, NCS, RAL, <br>Pantone или Tikkurila.</p>
        </li>

        <li class="guide-step">
          <div class="guide-step__head">
            <span class="guide-step__num" aria-hidden="true"></span>
            <h2 class="guide-step__title">Найдите нужный оттенок</h2>
          </div>
          <img class="guide-step__img" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/guide-step2.png" width="325" height="387" alt="Сетка оттенков с выбранным цветом" loading="lazy">
          <p class="guide-step__text">Просматривайте палитру и <br>выберите понравившийся <br>цвет. Каждый оттенок <br>отмечен кодом каталога.</p>
        </li>

        <li class="guide-step">
          <div class="guide-step__head">
            <span class="guide-step__num" aria-hidden="true"></span>
            <h2 class="guide-step__title">Проверьте на интерьере</h2>
          </div>
          <img class="guide-step__img" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/guide-step3.png" width="326" height="388" alt="Интерьер гостиной, окрашенной в выбранный оттенок" loading="lazy">
          <p class="guide-step__text">Используйте визуализацию, <br>чтобы посмотреть, как цвет <br>будет выглядеть в готовом <br>интерьере. Доступны <br>гостиная, ванная, кухня <br>и детская.</p>
        </li>

        <li class="guide-step">
          <div class="guide-step__head">
            <span class="guide-step__num" aria-hidden="true"></span>
            <h2 class="guide-step__title">Мы заколеруем краску</h2>
          </div>
          <img class="guide-step__img" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/guide-step4.png" width="330" height="391" alt="Банка краски ACRILUX с мазком выбранного цвета" loading="lazy">
          <p class="guide-step__text">После оформления заказа <br>мы заколеруем краску <br>на профессиональном <br>оборудовании точно <br>в выбранный цвет.</p>
        </li>

        <li class="guide-step">
          <div class="guide-step__head">
            <span class="guide-step__num" aria-hidden="true"></span>
            <h2 class="guide-step__title">Получите готовый продукт</h2>
          </div>
          <img class="guide-step__img" src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/guide-step5.png" width="328" height="386" alt="Упаковка заказа Weiss Color" loading="lazy">
          <p class="guide-step__text">Вы получаете краску, готовую к применению, с указанием цвета и кода на этикетке.</p>
        </li>
      </ol>

      <div class="guide-cta">
        <?php if ( $weiss_close_cta ) : ?>
          <button class="guide-cta__primary" type="button" data-modal-close>Выбрать цвет</button>
        <?php else : ?>
          <a class="guide-cta__primary" href="<?php echo esc_url( $weiss_viz_url ); ?>">Выбрать цвет</a>
        <?php endif; ?>
        <a class="guide-cta__secondary" href="<?php echo esc_url( $weiss_sampler_url ); ?>">Заказать пробный образец</a>
      </div>
    </article>
