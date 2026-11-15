<?php
/**
 * О компании (М13). Разметка перенесена из 02_верстка/pages/about.html;
 * данные и ссылки — из WordPress.
 */
weiss_set_main_class( 'about-page' );
get_header();
?>
<nav class="crumbs" aria-label="Хлебные крошки">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>&nbsp;&nbsp; &gt;&nbsp; <span aria-current="page">О Компании</span>
    </nav>

    <h1 class="about-title"><?php the_title(); ?></h1>

    <div class="about-text">
      <?php
      /* Тексты — из «Настройки → Содержимое сайта» (ТЗ 3.1, 10.1);
         пустое поле означает текст макета, абзацы разделяются пустой строкой. */
      $weiss_about_parts = array_filter( array_map( 'trim', preg_split( '/\R{2,}/u', (string) weiss_opt( 'about_text' ) ) ) );
      foreach ( $weiss_about_parts as $weiss_about_part ) :
      ?>
        <p><?php echo weiss_opt_html_kses( $weiss_about_part ); ?></p>
      <?php endforeach; ?>
    </div>

    <ul class="about-features">
      <?php
      $weiss_about_features = array(
        array( 'shield-check.svg', 38, 44 ),
        array( 'hands-leaf.svg', 46, 50 ),
        array( 'swatches.svg', 43, 43 ),
        array( 'handshake.svg', 62, 40 ),
      );
      foreach ( $weiss_about_features as $weiss_index => $weiss_icon ) :
        $weiss_num = $weiss_index + 1;
      ?>
      <li class="about-feature">
        <img class="about-feature__icon" src="<?php echo esc_url( WEISS_URI . '/assets/icons/' . $weiss_icon[0] ); ?>" width="<?php echo (int) $weiss_icon[1]; ?>" height="<?php echo (int) $weiss_icon[2]; ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
        <div>
          <h2 class="about-feature__title"><?php echo weiss_opt_html_kses( weiss_opt( 'about_feature_' . $weiss_num . '_title' ) ); ?></h2>
          <p class="about-feature__text"><?php echo weiss_opt_html_kses( weiss_opt( 'about_feature_' . $weiss_num . '_text' ) ); ?></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <ul class="about-partners" aria-label="Партнёры компании">
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-rzd.png" width="126" height="86" alt="Логотип РЖД" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-x5.png" width="118" height="82" alt="Логотип X5 Retail Group" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-evrohim.png" width="147" height="92" alt="Логотип ЕвроХим" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-magnit.png" width="70" height="92" alt="Логотип Магнит" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-tatneft.png" width="112" height="114" alt="Логотип Татнефть" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-holz.png" width="147" height="66" alt="Логотип Holz House" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-segezha.png" width="187" height="40" alt="Логотип Segezha Group" loading="lazy"></li>
        <li><img src="<?php echo esc_url( WEISS_URI ); ?>/assets/img/partner-ultralam.png" width="174" height="59" alt="Логотип Ultralam" loading="lazy"></li>
    </ul>
<?php
get_footer();
