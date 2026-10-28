<?php
defined( 'ABSPATH' ) || exit;

/**
 * Consulta https://global.si5.com.br/api/produtos/{gtin} com o token do usuario (Bearer).
 * O plano gratuito tem limite diario; quando a API responde 429 o plugin para de consultar ate o horario indicado.
 */
class SI5G_API {
	const BASE   = 'https://global.si5.com.br/api/produtos/';
	const PAUSA  = 'si5g_limite_ate';

	/**
	 * @return array|null|WP_Error dados do produto; null = codigo nao existe no catalogo
	 */
	public static function consultar( string $gtin, ?string $token = null ) {
		$token = trim( (string) ( $token ?? SI5G_Opcoes::get( 'token' ) ) );
		if ( '' === $token ) {
			return new WP_Error( 'si5g_sem_token', __( 'Informe o token da API em WooCommerce › SI5 Global.', 'si5-global-woocommerce' ) );
		}
		$ate = (int) get_option( self::PAUSA, 0 );
		if ( $ate > time() ) {
			return new WP_Error( 'si5g_limite', sprintf(
				/* translators: %s: data e hora */
				__( 'Limite de consultas do plano atingido. Volta a consultar em %s.', 'si5-global-woocommerce' ),
				wp_date( 'd/m/Y H:i', $ate )
			) );
		}

		// o catalogo guarda UPC-A (12 digitos) como EAN-13 com zero a esquerda
		$consulta = 12 === strlen( $gtin ) ? '0' . $gtin : $gtin;
		// so o codigo e o token saem daqui (o readme promete isso): sem o endereco da loja no user-agent
		$resposta = wp_remote_get( self::BASE . rawurlencode( $consulta ), array(
			'timeout'    => 20,
			'user-agent' => 'SI5Global-WooCommerce/' . SI5G_VERSAO,
			'headers'    => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $resposta ) ) {
			return $resposta;
		}

		$codigo = (int) wp_remote_retrieve_response_code( $resposta );
		if ( 404 === $codigo ) {
			return null;
		}
		if ( 401 === $codigo || 403 === $codigo ) {
			return new WP_Error( 'si5g_token', __( 'Token da API recusado. Confira o token em WooCommerce › SI5 Global.', 'si5-global-woocommerce' ) );
		}
		if ( 429 === $codigo ) {
			$espera = (int) wp_remote_retrieve_header( $resposta, 'retry-after' );
			update_option( self::PAUSA, time() + max( 300, $espera ?: HOUR_IN_SECONDS ), false );
			return new WP_Error( 'si5g_limite', __( 'Limite de consultas do plano atingido. O plugin continua sozinho quando o limite renovar.', 'si5-global-woocommerce' ) );
		}
		if ( 200 !== $codigo ) {
			return new WP_Error( 'si5g_http', sprintf( __( 'A API respondeu HTTP %d.', 'si5-global-woocommerce' ), $codigo ) );
		}

		$dados = json_decode( wp_remote_retrieve_body( $resposta ), true );
		if ( ! is_array( $dados ) || empty( $dados['codbar'] ) ) {
			return new WP_Error( 'si5g_formato', __( 'Resposta inesperada da API.', 'si5-global-woocommerce' ) );
		}
		return $dados;
	}

	/** Momento em que o limite do plano libera de novo (0 = livre). */
	public static function pausado_ate(): int {
		$ate = (int) get_option( self::PAUSA, 0 );
		return $ate > time() ? $ate : 0;
	}
}
