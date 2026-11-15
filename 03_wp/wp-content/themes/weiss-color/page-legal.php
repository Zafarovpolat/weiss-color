<?php
/**
 * Template Name: Правовой документ (М11)
 *
 * Политика конфиденциальности и другие юридические документы (ТЗ 9.4):
 * согласие, оферта, условия оплаты, доставки и возврата. Разметка из
 * 02_верстка/pages/legal.html остаётся запасным вариантом для политики,
 * пока её текст не заполнен. Остальные документы выводят свой текст,
 * надзаголовок и вводный абзац — из полей «Правовой документ».
 */
weiss_set_main_class( '' );
get_header();

$weiss_legal_doc = trim( (string) get_post_field( 'post_content', get_the_ID() ) );

/* Разметка макета — запасной вариант только для политики конфиденциальности:
   остальные документы показывают свой текст, даже если он ещё не заполнен. */
$weiss_legal_static = ( '' === $weiss_legal_doc && 'legal' === get_post_field( 'post_name', get_the_ID() ) );

if ( ! $weiss_legal_static ) :
	$weiss_legal_eyebrow = trim( (string) get_post_meta( get_the_ID(), '_weiss_legal_eyebrow', true ) );
	$weiss_legal_lead    = trim( (string) get_post_meta( get_the_ID(), '_weiss_legal_lead', true ) );
	?>
	<header class="legal-hero">
		<nav class="crumbs crumbs--overlay" aria-label="Хлебные крошки">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <span aria-current="page"><?php the_title(); ?></span>
		</nav>
		<?php if ( '' !== $weiss_legal_eyebrow ) : ?>
			<p class="legal-hero__eyebrow"><?php echo esc_html( $weiss_legal_eyebrow ); ?></p>
		<?php endif; ?>
		<h1 class="legal-hero__title"><?php the_title(); ?></h1>
		<?php if ( '' !== $weiss_legal_lead ) : ?>
			<p class="legal-hero__lead"><?php echo wp_kses( $weiss_legal_lead, array( 'br' => array( 'class' => true ) ) ); ?></p>
		<?php endif; ?>
	</header>

	<div class="legal-body">
		<div class="legal-doc">
			<?php the_content(); ?>
		</div>
	</div>
	<?php
else :
	?>
<header class="legal-hero">
      <nav class="crumbs crumbs--overlay" aria-label="Хлебные крошки">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a> &gt; <span aria-current="page">Политика конфиденциальности</span>
      </nav>
      <p class="legal-hero__eyebrow">Ваши данные под защитой</p>
      <h1 class="legal-hero__title">Политика<br>конфиденциальности</h1>
      <p class="legal-hero__lead">
        Мы ценим ваше доверие и заботимся о защите<br class="legal-br">
        персональных данных. В этом документе мы подробно<br class="legal-br">
        рассказываем, какие данные собираем, как их используем<br class="legal-br">
        и какие у вас есть права.
      </p>
    </header>

    <div class="legal-body">
      <div class="legal-columns">
        <div class="legal-column">

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">01</span>
          <div>
            <h2 class="legal-section__title">Общие положения</h2>
            <div class="legal-section__text">
              <p>Настоящая Политика конфиденциальности определяет, как <?php echo esc_html( weiss_opt( 'seller_name' ) ); ?> (далее — «Мы», «наш сайт») обрабатывает и защищает персональные данные пользователей сайта. Мы руководствуемся требованиями Федерального закона № 152-ФЗ «О персональных данных» и стремимся обеспечить максимальную прозрачность и безопасность обработки данных.</p>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">02</span>
          <div>
            <h2 class="legal-section__title">Какие данные мы собираем</h2>
            <div class="legal-section__text">
              <p>Мы можем собирать следующие данные:</p>
              <ul>
                <li>Имя, номер телефона, адрес электронной почты;</li>
                <li>Данные, которые вы оставляете при оформлении заказа, подписке на рассылку или обращении через форму обратной связи;</li>
                <li>Технические данные: IP-адрес, тип устройства, браузер, сведения о посещении страниц;</li>
                <li>Данные, получаемые с помощью файлов cookie (подробнее в разделе 7).</li>
              </ul>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">03</span>
          <div>
            <h2 class="legal-section__title">Как мы используем данные</h2>
            <div class="legal-section__text">
              <p>Ваши данные используются для:</p>
              <ul>
                <li>Обработки заказов и доставки;</li>
                <li>Связи с вами по вопросам заказов, консультаций и сервиса;</li>
                <li>Улучшения работы сайта и качества обслуживания;</li>
                <li>Отправки информационных материалов (только с вашего согласия);</li>
                <li>Анализа посещаемости и пользовательского поведения.</li>
              </ul>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">04</span>
          <div>
            <h2 class="legal-section__title">Передача данных</h2>
            <div class="legal-section__text">
              <p>Мы не передаем ваши персональные данные третьим лицам, за исключением случаев, предусмотренных законом, или когда это необходимо для выполнения ваших заказов (например, службам доставки). Все партнеры обязаны соблюдать конфиденциальность и обеспечивать защиту данных.</p>
            </div>
          </div>
        </article>
        </div>

        <div class="legal-column">

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">05</span>
          <div>
            <h2 class="legal-section__title">Хранение данных</h2>
            <div class="legal-section__text">
              <p>Мы храним ваши данные только в течение необходимого срока для достижения целей, указанных в настоящей политике, либо в соответствии с требованиями законодательства.</p>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">06</span>
          <div>
            <h2 class="legal-section__title">Ваши права</h2>
            <div class="legal-section__text">
              <p>Вы имеете право:</p>
              <ul>
                <li>Запрашивать информацию о своих персональных данных;</li>
                <li>Требовать их уточнения, блокировки или удаления;</li>
                <li>Отозвать согласие на обработку данных;</li>
                <li>Обращаться с жалобой в уполномоченные органы.</li>
              </ul>
              <p>Для реализации своих прав вы можете связаться с нами через контактные данные, указанные ниже.</p>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">07</span>
          <div>
            <h2 class="legal-section__title">Файлы cookie</h2>
            <div class="legal-section__text">
              <p>Наш сайт использует файлы cookie для корректной работы, анализа посещаемости и персонализации контента. Вы можете в любой момент изменить настройки cookie в своем браузере.</p>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">08</span>
          <div>
            <h2 class="legal-section__title">Изменения в политике</h2>
            <div class="legal-section__text">
              <p>Мы можем обновлять настоящую Политику конфиденциальности. Актуальная версия всегда доступна на этой странице. Рекомендуем периодически проверять этот раздел.</p>
            </div>
          </div>
        </article>

        <article class="legal-section">
          <span class="legal-section__num" aria-hidden="true">09</span>
          <div>
            <h2 class="legal-section__title">Контактная информация</h2>
            <div class="legal-section__text">
              <p>Если у вас есть вопросы по обработке персональных данных, вы можете связаться с нами:</p>
              <p><?php echo esc_html( weiss_opt( 'seller_name' ) ); ?><?php echo weiss_opt( 'seller_address' ) ? ', ' . esc_html( weiss_opt( 'seller_address' ) ) : ''; ?> — <a href="mailto:<?php echo esc_attr( weiss_opt( 'email' ) ); ?>"><?php echo esc_html( weiss_opt( 'email' ) ); ?></a>, <a href="<?php echo esc_url( weiss_phone()['href'] ); ?>" translate="no"><?php echo wp_kses_post( weiss_phone()['label'] ); ?></a>.</p>
            </div>
          </div>
        </article>
        </div>
      </div>
    </div>
	<?php
endif;

get_footer();
