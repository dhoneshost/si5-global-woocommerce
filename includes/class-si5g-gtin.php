<?php
defined( 'ABSPATH' ) || exit;

/**
 * Descobre o codigo de barras do produto e confere o digito verificador (regra GS1).
 */
class SI5G_Gtin {

	public static function valido( string $codigo ): bool {
		if ( ! preg_match( '/^(\d{8}|\d{12,14})$/', $codigo ) ) {
			return false;
		}
		$digitos  = array_map( 'intval', str_split( $codigo ) );
		$conferir = array_pop( $digitos );
		$soma     = 0;
		$peso     = 3;
		for ( $i = count( $digitos ) - 1; $i >= 0; $i-- ) {
			$soma += $digitos[ $i ] * $peso;
			$peso  = 3 === $peso ? 1 : 3;
		}
		return ( 10 - $soma % 10 ) % 10 === $conferir;
	}

	/**
	 * @return array{0: ?string, 1: string} [gtin, origem] - origem: produto | meta:<chave> | sku | ''
	 */
	public static function do_produto( WC_Product $produto ): array {
		$candidatos = array();
		if ( method_exists( $produto, 'get_global_unique_id' ) ) {
			$candidatos['produto'] = (string) $produto->get_global_unique_id();
		}
		foreach ( SI5G_Opcoes::lista( 'metas_gtin' ) as $meta ) {
			$candidatos[ 'meta:' . $meta ] = (string) $produto->get_meta( $meta, true );
		}
		if ( SI5G_Opcoes::get( 'usar_sku' ) ) {
			$candidatos['sku'] = (string) $produto->get_sku();
		}
		foreach ( $candidatos as $origem => $valor ) {
			$codigo = preg_replace( '/\D/', '', $valor );
			if ( '' !== $codigo && self::valido( $codigo ) ) {
				return array( $codigo, $origem );
			}
		}
		// produto variavel sem codigo proprio: usa o da primeira variacao que tiver
		if ( $produto->is_type( 'variable' ) ) {
			foreach ( $produto->get_children() as $filho_id ) {
				$filho = wc_get_product( $filho_id );
				if ( $filho ) {
					list( $codigo, $origem ) = self::do_produto( $filho );
					if ( $codigo ) {
						return array( $codigo, 'variacao:' . $filho_id . ':' . $origem );
					}
				}
			}
		}
		return array( null, '' );
	}
}
