<?php
/**
 * Шаблон «Правовой документ (М11)»: надзаголовок и вводный абзац полосы
 * (ТЗ 9.4). Сам текст документа — обычное содержимое страницы, поэтому
 * политика, согласие, оферта, оплата, доставка и возврат редактируются
 * без правки кода.
 */

/**
 * Ссылка на юридический документ по слагу (ТЗ 9.4). Пока документ не
 * опубликован, ссылка ведёт на политику конфиденциальности, а не в 404.
 */
function weiss_legal_link( $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}

	return weiss_page_url( 'legal' );
}

/**
 * Опубликованные правовые документы для подвала (ТЗ 3.1, 9.4). Черновики
 * не выводятся: пустой документ не должен открываться по ссылке.
 */
function weiss_legal_documents() {
	$slugs = array( 'oferta', 'payment-terms', 'delivery-return', 'consent' );
	$docs  = array();

	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			$docs[ $slug ] = array(
				'title' => get_the_title( $page ),
				'url'   => get_permalink( $page ),
			);
		}
	}

	return $docs;
}

function weiss_is_legal_page( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}

	$template = get_page_template_slug( $post );
	if ( $template ) {
		return 'page-legal.php' === $template;
	}

	// Страница «legal» получает шаблон по иерархии page-{slug}.php.
	return 'legal' === $post->post_name;
}

add_action( 'add_meta_boxes_page', function ( $post ) {
	if ( ! weiss_is_legal_page( $post ) ) {
		return;
	}

	add_meta_box( 'weiss-legal', 'Правовой документ: верхняя полоса', 'weiss_legal_meta_box', 'page', 'normal', 'high' );
} );

function weiss_legal_meta_box( $post ) {
	wp_nonce_field( 'weiss_legal_meta', 'weiss_legal_nonce' );

	$eyebrow = get_post_meta( $post->ID, '_weiss_legal_eyebrow', true );
	$lead    = get_post_meta( $post->ID, '_weiss_legal_lead', true );
	?>
	<p>
		<label for="weiss-legal-eyebrow"><strong>Надзаголовок</strong></label><br>
		<input type="text" class="large-text" id="weiss-legal-eyebrow" name="weiss_legal_eyebrow"
		       value="<?php echo esc_attr( $eyebrow ); ?>" placeholder="Ваши данные под защитой">
	</p>
	<p>
		<label for="weiss-legal-lead"><strong>Вводный абзац</strong></label><br>
		<textarea class="large-text" rows="3" id="weiss-legal-lead" name="weiss_legal_lead" placeholder="Короткое вступление под заголовком"><?php echo esc_textarea( $lead ); ?></textarea>
	</p>
	<p class="description">
		Полоса показывается, когда у страницы есть текст. Заголовок — название страницы.
		Перенос строки в абзаце — тег <code>&lt;br class="legal-br"&gt;</code>.
	</p>
	<?php
}

add_action( 'save_post_page', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['weiss_legal_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weiss_legal_nonce'] ) ), 'weiss_legal_meta' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$eyebrow = isset( $_POST['weiss_legal_eyebrow'] ) ? sanitize_text_field( wp_unslash( $_POST['weiss_legal_eyebrow'] ) ) : '';
	$lead    = isset( $_POST['weiss_legal_lead'] ) ? wp_kses( wp_unslash( $_POST['weiss_legal_lead'] ), array( 'br' => array( 'class' => true ) ) ) : '';

	foreach ( array( '_weiss_legal_eyebrow' => $eyebrow, '_weiss_legal_lead' => $lead ) as $meta_key => $value ) {
		if ( '' === trim( $value ) ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
} );
