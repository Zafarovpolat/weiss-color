<?php
/**
 * Где купить (М12). Разметка перенесена из 02_верстка/pages/where-to-buy.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( '' );
get_header();
?>
<section class="buy-hero" aria-labelledby="buy-title">
      <nav class="crumbs crumbs--overlay" aria-label="Хлебные крошки">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <span aria-current="page">Где купить</span>
      </nav>
      <h1 class="buy-hero__title" id="buy-title">Где купить продукцию WEISS</h1>
      <p class="buy-hero__lead">
        Приобретайте краски WEISS у наших официальных партнеров на ведущих маркетплейсах и в магазинах.
      </p>

      <ul class="buy-cards">
        <?php
        // ТЗ 9.2: название, описание, логотип и ссылка каждой карточки — из настроек.
        $buy_cards = array(
            1 => array( 'brand' => 'wb', 'logo' => 'img/buy-wb.svg', 'w' => 325, 'h' => 94 ),
            2 => array( 'brand' => 'ozon', 'logo' => 'img/buy-ozon.svg', 'w' => 283, 'h' => 62 ),
            3 => array( 'brand' => 'lm', 'logo' => 'img/buy-lm.svg', 'w' => 178, 'h' => 107 ),
            4 => array( 'brand' => 'ym', 'logo' => 'img/buy-ym.svg', 'w' => 345, 'h' => 82 ),
        );
        foreach ( $buy_cards as $i => $card ) :
          $name = weiss_opt( 'buy_' . $i . '_name' );
        ?>
        <li class="buy-card">
          <span class="buy-card__logo">
            <?php
            echo weiss_media_img( // phpcs:ignore WordPress.Security.EscapeOutput -- тег собран в helpers.php
              'buy_' . $i . '_logo',
              $card['logo'],
              $card['w'],
              $card['h'],
              array(
                'class'   => 'buy-logo--' . $card['brand'],
                'alt'     => 'Логотип ' . $name,
                'loading' => 'lazy',
              )
            );
            ?>
          </span>
          <h3 class="buy-card__name"><?php echo esc_html( $name ); ?></h3>
          <p class="buy-card__text"><?php echo weiss_opt_html( 'buy_' . $i . '_text' ); ?></p>
          <a class="buy-card__link buy-card__link--<?php echo esc_attr( $card['brand'] ); ?>" href="<?php echo esc_url( weiss_opt( 'buy_' . $i . '_url' ) ); ?>" target="_blank" rel="noopener noreferrer">
            Перейти
            <svg viewBox="0 0 52 15" fill="none" aria-hidden="true"><path d="M0 6h44v3H0zM41 0l11 7.5L41 15z" fill="currentColor"/></svg>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="buy-features" aria-labelledby="buy-features-title">
      <div class="buy-features__inner">
        <div class="buy-features__head">
          <h2 class="buy-features__title" id="buy-features-title">Покупайте удобно</h2>
          <p class="buy-features__lead">
            Мы сотрудничаем с надежными площадками, чтобы сделать ваши покупки еще проще
          </p>
        </div>

        <div class="buy-feature">
          <img class="buy-feature__icon buy-feature__icon--shield" src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/shield-check.svg" width="50" height="58" alt="" aria-hidden="true">
          <div>
            <h3 class="buy-feature__title">Официальная<br>продукция</h3>
            <p class="buy-feature__text">Только оригинальные краски WEISS от производителя</p>
          </div>
        </div>

        <div class="buy-feature">
          <img class="buy-feature__icon buy-feature__icon--truck" src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/truck.svg" width="90" height="46" alt="" aria-hidden="true">
          <div>
            <h3 class="buy-feature__title">Удобная<br>доставка</h3>
            <p class="buy-feature__text">Быстрая и надежная доставка по всей России</p>
          </div>
        </div>

        <div class="buy-feature">
          <img class="buy-feature__icon buy-feature__icon--people" src="<?php echo esc_url( WEISS_URI ); ?>/assets/icons/people.svg" width="78" height="37" alt="" aria-hidden="true">
          <div>
            <h3 class="buy-feature__title">Надежные<br>партнеры</h3>
            <p class="buy-feature__text">Сотрудничаем с крупнейшими<br class="d-br"> и проверенными маркетплейсами и магазинами</p>
          </div>
        </div>
      </div>
    </section>
<?php
get_footer();
