<?php
defined( 'ABSPATH' ) || exit;

/**
 * Opcoes do plugin (uma linha so em wp_options).
 */
class SI5G_Opcoes {
	const CHAVE = 'si5g_opcoes';

	public static function padrao(): array {
		return array(
			'token'              => '',
			'titulo'             => 'vazio',   // vazio = so quando o produto nao tem nome (ou o nome e o proprio codigo); nunca = nao mexe
			'descricao'          => 1,         // ingredientes/alergenos quando a descricao estiver vazia
			'imagem'             => 1,         // so quando o produto nao tem imagem
			'marca'              => 1,         // taxonomia de marcas do WooCommerce, quando existir e o produto nao tiver marca
			'ncm'                => 1,
			'cest'               => 1,
			'ncm_sobrescrever'   => 0,         // 0 = so grava NCM/CEST quando estiverem vazios
			'gtin_no_produto'    => 1,         // grava o GTIN achado no SKU/meta no campo GTIN do WooCommerce
			'usar_sku'           => 1,         // SKU vale como codigo de barras quando for um GTIN valido
			'metas_gtin'         => '_gtin, _ean, _barcode, hwp_product_gtin, _wpm_gtin_code, _alg_ean',
			'metas_ncm_extra'    => '',        // campos de plugins de nota fiscal que tambem devem receber o NCM
			'metas_cest_extra'   => '',
		);
	}

	public static function todas(): array {
		$salvas = get_option( self::CHAVE, array() );
		return array_merge( self::padrao(), is_array( $salvas ) ? $salvas : array() );
	}

	public static function get( string $nome ) {
		$o = self::todas();
		return $o[ $nome ] ?? null;
	}

	/** Lista de chaves de meta separadas por virgula -> array limpo. */
	public static function lista( string $nome ): array {
		$itens = array_map( 'trim', explode( ',', (string) self::get( $nome ) ) );
		return array_values( array_filter( $itens, fn ( $m ) => $m !== '' && preg_match( '/^[A-Za-z0-9_\-]+$/', $m ) ) );
	}

	public static function sanitizar( $entrada ): array {
		$p   = self::padrao();
		$e   = is_array( $entrada ) ? $entrada : array();
		$out = array();
		$out['token']  = isset( $e['token'] ) ? sanitize_text_field( $e['token'] ) : '';
		$out['titulo'] = in_array( $e['titulo'] ?? '', array( 'vazio', 'nunca' ), true ) ? $e['titulo'] : $p['titulo'];
		foreach ( array( 'descricao', 'imagem', 'marca', 'ncm', 'cest', 'ncm_sobrescrever', 'gtin_no_produto', 'usar_sku' ) as $c ) {
			$out[ $c ] = empty( $e[ $c ] ) ? 0 : 1;
		}
		foreach ( array( 'metas_gtin', 'metas_ncm_extra', 'metas_cest_extra' ) as $c ) {
			$out[ $c ] = isset( $e[ $c ] ) ? sanitize_text_field( $e[ $c ] ) : '';
		}
		return $out;
	}
}
