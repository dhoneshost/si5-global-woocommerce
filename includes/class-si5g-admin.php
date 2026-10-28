<?php
defined( 'ABSPATH' ) || exit;

/**
 * Telas do painel: configuracao (WooCommerce › SI5 Global), campos NCM/CEST no produto, caixa "SI5 Global" com o
 * botao "Buscar dados agora" e a acao em massa na lista de produtos.
 */
class SI5G_Admin {

	public static function iniciar(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'registrar' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'scripts' ) );
		add_action( 'admin_post_si5g_varredura', array( __CLASS__, 'acao_varredura' ) );
		add_action( 'wp_ajax_si5g_testar', array( __CLASS__, 'ajax_testar' ) );
		add_action( 'wp_ajax_si5g_buscar', array( __CLASS__, 'ajax_buscar' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'campos_fiscais' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'salvar_campos_fiscais' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'caixa' ) );
		add_filter( 'bulk_actions-edit-product', array( __CLASS__, 'acao_em_massa' ) );
		add_filter( 'handle_bulk_actions-edit-product', array( __CLASS__, 'tratar_em_massa' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'avisos' ) );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', 'SI5 Global', 'SI5 Global', 'manage_woocommerce', 'si5-global', array( __CLASS__, 'pagina' ) );
	}

	public static function registrar(): void {
		register_setting( 'si5g', SI5G_Opcoes::CHAVE, array( 'sanitize_callback' => array( SI5G_Opcoes::class, 'sanitizar' ) ) );
	}

	public static function scripts( $tela ): void {
		$t = get_current_screen();
		if ( ! $t || ! in_array( $t->id, array( 'woocommerce_page_si5-global', 'product' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'si5g-admin', SI5G_URL . 'assets/admin.js', array( 'jquery' ), SI5G_VERSAO, true );
		wp_localize_script( 'si5g-admin', 'SI5G', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'si5g' ),
			'txt'   => array(
				'buscando'  => __( 'Consultando o SI5 Global…', 'si5-global-woocommerce' ),
				'recarregar'=> __( 'Pronto. A página vai recarregar para mostrar os dados.', 'si5-global-woocommerce' ),
				'salvar'    => __( 'Salve as alterações do produto antes: a página será recarregada.', 'si5-global-woocommerce' ),
			),
		) );
	}

	public static function pagina(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o = SI5G_Opcoes::todas();
		$e = SI5G_Fila::estado();
		$n = SI5G_Opcoes::CHAVE;
		$marcar = function ( $campo, $rotulo, $ajuda = '' ) use ( $o, $n ) {
			printf( '<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>%s<br>', esc_attr( $n ), esc_attr( $campo ), checked( ! empty( $o[ $campo ] ), true, false ), esc_html( $rotulo ), $ajuda ? ' <span class="description">' . esc_html( $ajuda ) . '</span>' : '' );
		};
		?>
		<div class="wrap">
			<h1>SI5 Global</h1>
			<p><?php esc_html_e( 'Completa os produtos pelo código de barras com o catálogo SI5 Global: nome, foto, marca, NCM e CEST. Só preenche o que está vazio.', 'si5-global-woocommerce' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'si5g' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="si5g-token"><?php esc_html_e( 'Token da API', 'si5-global-woocommerce' ); ?></label></th>
						<td>
							<input type="password" id="si5g-token" class="regular-text" name="<?php echo esc_attr( $n ); ?>[token]" value="<?php echo esc_attr( $o['token'] ); ?>" autocomplete="off">
							<button type="button" class="button" id="si5g-testar"><?php esc_html_e( 'Testar conexão', 'si5-global-woocommerce' ); ?></button>
							<span id="si5g-teste"></span>
							<p class="description"><?php
								printf(
									/* translators: %s: link */
									esc_html__( 'Crie o token em %s (plano gratuito: 100 consultas por dia).', 'si5-global-woocommerce' ),
									'<a href="https://global.si5.com.br/" target="_blank" rel="noopener">global.si5.com.br</a>'
								); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'O que preencher', 'si5-global-woocommerce' ); ?></th>
						<td>
							<label><?php esc_html_e( 'Nome do produto:', 'si5-global-woocommerce' ); ?>
								<select name="<?php echo esc_attr( $n ); ?>[titulo]">
									<option value="vazio" <?php selected( $o['titulo'], 'vazio' ); ?>><?php esc_html_e( 'só quando estiver vazio (ou for o próprio código)', 'si5-global-woocommerce' ); ?></option>
									<option value="nunca" <?php selected( $o['titulo'], 'nunca' ); ?>><?php esc_html_e( 'nunca', 'si5-global-woocommerce' ); ?></option>
								</select></label><br>
							<?php
							$marcar( 'imagem', __( 'Foto, quando o produto não tiver imagem', 'si5-global-woocommerce' ) );
							$marcar( 'descricao', __( 'Ingredientes e alérgicos na descrição, quando ela estiver vazia', 'si5-global-woocommerce' ) );
							$marcar( 'marca', __( 'Marca, quando o produto não tiver marca', 'si5-global-woocommerce' ), __( '(Produtos › Marcas do WooCommerce)', 'si5-global-woocommerce' ) );
							$marcar( 'ncm', 'NCM' );
							$marcar( 'cest', 'CEST' );
							$marcar( 'ncm_sobrescrever', __( 'Trocar NCM/CEST que já estiverem preenchidos', 'si5-global-woocommerce' ), __( '(desligado: só preenche os vazios)', 'si5-global-woocommerce' ) );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Código de barras', 'si5-global-woocommerce' ); ?></th>
						<td>
							<?php
							$marcar( 'usar_sku', __( 'Usar o SKU quando ele for um código de barras válido', 'si5-global-woocommerce' ) );
							$marcar( 'gtin_no_produto', __( 'Gravar o código achado no campo GTIN/EAN do WooCommerce', 'si5-global-woocommerce' ) );
							?>
							<p><label><?php esc_html_e( 'Outros campos onde a loja guarda o código (separados por vírgula):', 'si5-global-woocommerce' ); ?><br>
							<input type="text" class="large-text code" name="<?php echo esc_attr( $n ); ?>[metas_gtin]" value="<?php echo esc_attr( $o['metas_gtin'] ); ?>"></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin de nota fiscal', 'si5-global-woocommerce' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'O NCM e o CEST ficam nos campos do produto (aba Geral). Se o seu plugin de NF-e lê de outro campo, informe o nome dele aqui para receber o mesmo valor.', 'si5-global-woocommerce' ); ?></p>
							<p><label>NCM: <input type="text" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[metas_ncm_extra]" value="<?php echo esc_attr( $o['metas_ncm_extra'] ); ?>" placeholder="_campo_ncm_do_plugin"></label></p>
							<p><label>CEST: <input type="text" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[metas_cest_extra]" value="<?php echo esc_attr( $o['metas_cest_extra'] ); ?>" placeholder="_campo_cest_do_plugin"></label></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Completar todos os produtos sem NCM', 'si5-global-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Roda em segundo plano, 25 produtos por vez. Se o limite diário do plano acabar, continua sozinho quando ele renovar.', 'si5-global-woocommerce' ); ?></p>
			<?php if ( ! SI5G_Fila::disponivel() ) : ?>
				<p><strong><?php esc_html_e( 'O agendador do WooCommerce (Action Scheduler) não está disponível.', 'si5-global-woocommerce' ); ?></strong></p>
			<?php else : ?>
				<p>
					<?php
					printf(
						/* translators: 1-5: contagens */
						esc_html__( 'Consultados: %1$d · completados: %2$d · fora do catálogo: %3$d · sem código de barras: %4$d · erros: %5$d', 'si5-global-woocommerce' ),
						(int) $e['vistos'], (int) $e['ok'], (int) $e['nao_encontrado'], (int) $e['sem_gtin'], (int) $e['erro']
					);
					if ( $e['ativa'] ) {
						echo ' — <strong>' . esc_html__( 'em andamento', 'si5-global-woocommerce' ) . '</strong>';
						if ( $e['pausada_ate'] > time() ) {
							echo ' (' . esc_html( sprintf( __( 'aguardando o limite renovar às %s', 'si5-global-woocommerce' ), wp_date( 'd/m H:i', (int) $e['pausada_ate'] ) ) ) . ')';
						}
					}
					if ( $e['mensagem'] ) {
						echo '<br><em>' . esc_html( $e['mensagem'] ) . '</em>';
					}
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="si5g_varredura">
					<?php wp_nonce_field( 'si5g_varredura' ); ?>
					<?php if ( $e['ativa'] ) : ?>
						<button class="button" name="o" value="parar"><?php esc_html_e( 'Parar', 'si5-global-woocommerce' ); ?></button>
					<?php else : ?>
						<button class="button button-primary" name="o" value="iniciar"><?php esc_html_e( 'Completar agora', 'si5-global-woocommerce' ); ?></button>
					<?php endif; ?>
				</form>
			<?php endif; ?>
			<p class="description" style="margin-top:2em"><?php esc_html_e( 'NCM e CEST são sugestões do catálogo, com a origem registrada. Confirme com seu contador antes de emitir notas.', 'si5-global-woocommerce' ); ?></p>
		</div>
		<?php
	}

	public static function acao_varredura(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'si5g_varredura' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'si5-global-woocommerce' ) );
		}
		if ( SI5G_Fila::disponivel() ) {
			'parar' === ( $_POST['o'] ?? '' ) ? SI5G_Fila::parar_varredura() : SI5G_Fila::iniciar_varredura();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=si5-global' ) );
		exit;
	}

	public static function ajax_testar(): void {
		check_ajax_referer( 'si5g', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Sem permissão.', 'si5-global-woocommerce' ) );
		}
		// testa com o token digitado (antes de salvar), sem gravar nada
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$r     = SI5G_API::consultar( '7891000100103', '' !== $token ? $token : null );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( sprintf( __( 'Conectado. Exemplo: %1$s — NCM %2$s', 'si5-global-woocommerce' ), $r['produto'] ?? '?', $r['ncm'] ?? '-' ) );
	}

	public static function ajax_buscar(): void {
		check_ajax_referer( 'si5g', 'nonce' );
		$id = absint( $_POST['produto'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_product', $id ) ) {
			wp_send_json_error( __( 'Sem permissão.', 'si5-global-woocommerce' ) );
		}
		list( $status, $mensagem ) = SI5G_Aplicador::processar( $id, true );
		'ok' === $status ? wp_send_json_success( $mensagem ) : wp_send_json_error( $mensagem );
	}

	public static function campos_fiscais(): void {
		echo '<div class="options_group">';
		woocommerce_wp_text_input( array(
			'id'                => SI5G_Aplicador::META_NCM,
			'label'             => 'NCM',
			'placeholder'       => '00000000',
			'desc_tip'          => true,
			'description'       => __( '8 dígitos. Preenchido pelo SI5 Global quando vazio; confirme com seu contador.', 'si5-global-woocommerce' ),
			'custom_attributes' => array( 'maxlength' => 10, 'inputmode' => 'numeric' ),
		) );
		woocommerce_wp_text_input( array(
			'id'                => SI5G_Aplicador::META_CEST,
			'label'             => 'CEST',
			'placeholder'       => '0000000',
			'desc_tip'          => true,
			'description'       => __( '7 dígitos, quando o produto está na substituição tributária.', 'si5-global-woocommerce' ),
			'custom_attributes' => array( 'maxlength' => 9, 'inputmode' => 'numeric' ),
		) );
		echo '</div>';
	}

	public static function salvar_campos_fiscais( WC_Product $produto ): void {
		foreach ( array( SI5G_Aplicador::META_NCM => 8, SI5G_Aplicador::META_CEST => 7 ) as $meta => $tam ) {
			if ( isset( $_POST[ $meta ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- o WooCommerce ja conferiu o nonce do formulario
				$valor = preg_replace( '/\D/', '', wp_unslash( $_POST[ $meta ] ) );
				$produto->update_meta_data( $meta, strlen( $valor ) === $tam ? $valor : '' );
			}
		}
	}

	public static function caixa(): void {
		add_meta_box( 'si5g-caixa', 'SI5 Global', array( __CLASS__, 'caixa_html' ), 'product', 'side', 'default' );
	}

	public static function caixa_html( WP_Post $post ): void {
		$produto = wc_get_product( $post->ID );
		if ( ! $produto ) {
			return;
		}
		list( $gtin, $origem ) = SI5G_Gtin::do_produto( $produto );
		$status = (string) $produto->get_meta( SI5G_Aplicador::META_STATUS, true );
		$quando = (int) $produto->get_meta( SI5G_Aplicador::META_QUANDO, true );
		$rotulos = array(
			'ok'             => __( 'completado', 'si5-global-woocommerce' ),
			'nao_encontrado' => __( 'código fora do catálogo', 'si5-global-woocommerce' ),
			'sem_gtin'       => __( 'sem código de barras', 'si5-global-woocommerce' ),
			'erro'           => __( 'erro na última consulta', 'si5-global-woocommerce' ),
		);
		echo '<p>' . esc_html__( 'Código de barras:', 'si5-global-woocommerce' ) . ' <strong>' . ( $gtin ? esc_html( $gtin ) : '—' ) . '</strong>';
		if ( $gtin && 'produto' !== $origem ) {
			echo ' <span class="description">(' . esc_html( $origem ) . ')</span>';
		}
		echo '</p>';
		if ( $status ) {
			echo '<p>' . esc_html( ( $rotulos[ $status ] ?? $status ) . ( $quando ? ' · ' . wp_date( 'd/m/Y H:i', $quando ) : '' ) ) . '</p>';
		}
		if ( $gtin ) {
			echo '<p><a href="' . esc_url( 'https://global.si5.com.br/produtos/' . $gtin ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver no SI5 Global', 'si5-global-woocommerce' ) . '</a></p>';
		}
		echo '<p><button type="button" class="button" id="si5g-buscar" data-produto="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Buscar dados agora', 'si5-global-woocommerce' ) . '</button></p>';
		echo '<p id="si5g-resultado" class="description"></p>';
	}

	public static function acao_em_massa( array $acoes ): array {
		if ( SI5G_Fila::disponivel() ) {
			$acoes['si5g_completar'] = __( 'Completar com SI5 Global', 'si5-global-woocommerce' );
		}
		return $acoes;
	}

	public static function tratar_em_massa( string $volta, string $acao, array $ids ): string {
		if ( 'si5g_completar' !== $acao || ! current_user_can( 'edit_products' ) ) {
			return $volta;
		}
		// confere a permissao de cada produto (ex.: autor que so edita os proprios), nao so a geral
		$ids = array_values( array_filter( array_map( 'absint', $ids ), function ( $id ) {
			return $id && current_user_can( 'edit_product', $id );
		} ) );
		$n = $ids ? SI5G_Fila::enfileirar( $ids ) : 0;
		return add_query_arg( 'si5g_enfileirados', $n, $volta );
	}

	public static function avisos(): void {
		if ( isset( $_GET['si5g_enfileirados'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$n = absint( $_GET['si5g_enfileirados'] );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d produto enviado para o SI5 Global. Os dados aparecem em alguns minutos.', '%d produtos enviados para o SI5 Global. Os dados aparecem em alguns minutos.', $n, 'si5-global-woocommerce' ), $n ) ) . '</p></div>';
		}
		$t = get_current_screen();
		if ( $t && 'woocommerce_page_si5-global' !== $t->id && current_user_can( 'manage_woocommerce' ) && '' === trim( (string) SI5G_Opcoes::get( 'token' ) ) && in_array( $t->id, array( 'edit-product', 'product', 'plugins' ), true ) ) {
			echo '<div class="notice notice-info"><p>' . wp_kses_post( sprintf( __( 'SI5 Global: informe o token da API em <a href="%s">WooCommerce › SI5 Global</a> para completar os produtos.', 'si5-global-woocommerce' ), esc_url( admin_url( 'admin.php?page=si5-global' ) ) ) ) . '</p></div>';
		}
	}
}
