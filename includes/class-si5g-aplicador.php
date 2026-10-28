<?php
defined( 'ABSPATH' ) || exit;

/**
 * Aplica os dados do SI5 Global num produto. Regra de ouro: so preenche o que esta vazio - nunca troca o nome,
 * a descricao ou a foto que a loja ja cadastrou. NCM/CEST so sao trocados se a opcao "sobrescrever" estiver ligada
 * ou se o usuario pediu na mao (botao "Buscar dados agora").
 */
class SI5G_Aplicador {
	const META_NCM    = '_si5g_ncm';
	const META_CEST   = '_si5g_cest';
	const META_STATUS = '_si5g_status';     // ok | nao_encontrado | sem_gtin | erro
	const META_QUANDO = '_si5g_consultado_em';
	const META_GTIN   = '_si5g_gtin';

	/**
	 * Consulta e aplica. Devolve [status, mensagem, campos alterados] - status 'limite' quando o plano estourou
	 * (quem chamou deve parar a fila).
	 *
	 * @return array{0: string, 1: string, 2: string[]}
	 */
	public static function processar( int $produto_id, bool $manual = false ): array {
		$produto = wc_get_product( $produto_id );
		if ( ! $produto ) {
			return array( 'erro', __( 'Produto não encontrado.', 'si5-global-woocommerce' ), array() );
		}
		list( $gtin, $origem ) = SI5G_Gtin::do_produto( $produto );
		if ( ! $gtin ) {
			self::marcar( $produto, 'sem_gtin', null );
			return array( 'sem_gtin', __( 'Produto sem código de barras válido (GTIN/EAN no campo GTIN, em um campo extra ou no SKU).', 'si5-global-woocommerce' ), array() );
		}

		$dados = SI5G_API::consultar( $gtin );
		if ( is_wp_error( $dados ) ) {
			if ( 'si5g_limite' === $dados->get_error_code() ) {
				return array( 'limite', $dados->get_error_message(), array() );
			}
			self::marcar( $produto, 'erro', $gtin );
			return array( 'erro', $dados->get_error_message(), array() );
		}
		if ( null === $dados ) {
			self::marcar( $produto, 'nao_encontrado', $gtin );
			return array( 'nao_encontrado', sprintf( __( 'O código %s ainda não está no catálogo SI5 Global.', 'si5-global-woocommerce' ), $gtin ), array() );
		}

		$alterados = self::aplicar( $produto, $dados, $gtin, $origem, $manual );
		self::marcar( $produto, 'ok', $gtin );
		return array( 'ok', $alterados ? sprintf( __( 'Atualizado: %s.', 'si5-global-woocommerce' ), implode( ', ', $alterados ) ) : __( 'Nada a preencher - o produto já tinha esses dados.', 'si5-global-woocommerce' ), $alterados );
	}

	/** @return string[] campos alterados */
	public static function aplicar( WC_Product $produto, array $d, string $gtin, string $origem, bool $manual ): array {
		$o         = SI5G_Opcoes::todas();
		$alterados = array();

		// nome: so quando vazio, igual ao codigo ou "Produto"/rascunho automatico
		$nome = trim( (string) $produto->get_name() );
		if ( 'vazio' === $o['titulo'] && ! empty( $d['produto'] ) && ( '' === $nome || preg_replace( '/\D/', '', $nome ) === $gtin || in_array( mb_strtolower( $nome ), array( 'produto', 'product', 'auto rascunho', 'auto draft', 'rascunho automático' ), true ) ) ) {
			$produto->set_name( $d['produto'] );
			$alterados[] = __( 'nome', 'si5-global-woocommerce' );
		}

		// descricao: ingredientes/alergenos (alimentos), so com a descricao vazia
		if ( $o['descricao'] && '' === trim( wp_strip_all_tags( (string) $produto->get_description() ) ) ) {
			$partes = array();
			foreach ( array( 'ingredientes' => __( 'Ingredientes', 'si5-global-woocommerce' ), 'alergenos' => __( 'Alérgicos', 'si5-global-woocommerce' ), 'pode_conter' => __( 'Pode conter', 'si5-global-woocommerce' ) ) as $campo => $rotulo ) {
				if ( ! empty( $d[ $campo ] ) && is_string( $d[ $campo ] ) ) {
					$partes[] = '<p><strong>' . esc_html( $rotulo ) . ':</strong> ' . esc_html( $d[ $campo ] ) . '</p>';
				}
			}
			if ( $partes ) {
				$produto->set_description( implode( "\n", $partes ) );
				$alterados[] = __( 'descrição', 'si5-global-woocommerce' );
			}
		}

		// GTIN no campo proprio do WooCommerce quando veio do SKU ou de outro campo
		if ( $o['gtin_no_produto'] && 'produto' !== $origem && 0 !== strpos( $origem, 'variacao:' ) && method_exists( $produto, 'set_global_unique_id' ) && '' === (string) $produto->get_global_unique_id() ) {
			try {
				$produto->set_global_unique_id( $gtin );
				$alterados[] = 'GTIN';
			} catch ( Exception $e ) {
				// outro produto ja usa esse GTIN - deixa como esta
			}
		}

		// NCM e CEST (so digitos, como a nota fiscal pede)
		$sobrescrever = $manual || $o['ncm_sobrescrever'];
		$ncm          = preg_replace( '/\D/', '', (string) ( $d['ncm'] ?? '' ) );
		$cest         = preg_replace( '/\D/', '', (string) ( $d['cest_codigo'] ?? '' ) );
		if ( $o['ncm'] && 8 === strlen( $ncm ) && ( $sobrescrever || '' === (string) $produto->get_meta( self::META_NCM, true ) ) ) {
			foreach ( array_merge( array( self::META_NCM ), SI5G_Opcoes::lista( 'metas_ncm_extra' ) ) as $meta ) {
				if ( $sobrescrever || '' === (string) $produto->get_meta( $meta, true ) ) {
					$produto->update_meta_data( $meta, $ncm );
				}
			}
			$alterados[] = 'NCM';
		}
		if ( $o['cest'] && 7 === strlen( $cest ) && ( $sobrescrever || '' === (string) $produto->get_meta( self::META_CEST, true ) ) ) {
			foreach ( array_merge( array( self::META_CEST ), SI5G_Opcoes::lista( 'metas_cest_extra' ) ) as $meta ) {
				if ( $sobrescrever || '' === (string) $produto->get_meta( $meta, true ) ) {
					$produto->update_meta_data( $meta, $cest );
				}
			}
			$alterados[] = 'CEST';
		}

		$produto->save();

		// foto: so quando o produto nao tem imagem
		if ( $o['imagem'] && ! $produto->get_image_id() && ! empty( $d['imagem_url'] ) ) {
			$imagem_id = self::baixar_imagem( (string) $d['imagem_url'], $produto->get_id(), $produto->get_name(), $gtin );
			if ( $imagem_id ) {
				$produto->set_image_id( $imagem_id );
				$produto->save();
				$alterados[] = __( 'foto', 'si5-global-woocommerce' );
			}
		}

		// marca: taxonomia de marcas do WooCommerce (9.6+), so quando o produto nao tem marca
		if ( $o['marca'] && ! empty( $d['marca'] ) && taxonomy_exists( 'product_brand' ) ) {
			$atuais = wp_get_object_terms( $produto->get_id(), 'product_brand', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $atuais ) && ! $atuais ) {
				$r = wp_set_object_terms( $produto->get_id(), (string) $d['marca'], 'product_brand', false );
				if ( ! is_wp_error( $r ) ) {
					$alterados[] = __( 'marca', 'si5-global-woocommerce' );
				}
			}
		}

		return $alterados;
	}

	/** Baixa a foto para a biblioteca de midia (a URL do catalogo pode nao ter extensao). */
	private static function baixar_imagem( string $url, int $produto_id, string $titulo, string $gtin ): int {
		if ( 0 !== strpos( $url, 'https://' ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}
		$mime = function_exists( 'wp_get_image_mime' ) ? wp_get_image_mime( $tmp ) : '';
		$ext  = array( 'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif' )[ $mime ] ?? '';
		if ( '' === $ext ) {
			wp_delete_file( $tmp );
			return 0;
		}
		$arquivo = array( 'name' => sanitize_file_name( $gtin . '.' . $ext ), 'tmp_name' => $tmp );
		$id      = media_handle_sideload( $arquivo, $produto_id, $titulo );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $titulo );
		return (int) $id;
	}

	private static function marcar( WC_Product $produto, string $status, ?string $gtin ): void {
		$produto->update_meta_data( self::META_STATUS, $status );
		$produto->update_meta_data( self::META_QUANDO, time() );
		if ( $gtin ) {
			$produto->update_meta_data( self::META_GTIN, $gtin );
		}
		$produto->save_meta_data();
	}
}
