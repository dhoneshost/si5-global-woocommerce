<?php
defined( 'ABSPATH' ) || exit;

/**
 * Processamento em segundo plano pelo Action Scheduler (vem com o WooCommerce):
 *  - si5g_lote: lista de produtos escolhidos (acao em massa da lista de produtos)
 *  - si5g_varrer: varredura de todos os produtos sem NCM, 25 por vez, em ordem de ID (retoma de onde parou)
 * Quando o limite do plano estoura, reagenda para quando ele renovar - nao fica martelando a API.
 */
class SI5G_Fila {
	const GRUPO     = 'si5g';
	const ESTADO    = 'si5g_varredura';
	const POR_VEZ   = 25;
	const REVER_NAO_ENCONTRADO = 30 * DAY_IN_SECONDS; // catalogo cresce todo dia: tenta de novo depois de 30 dias

	public static function iniciar(): void {
		add_action( 'si5g_lote', array( __CLASS__, 'lote' ), 10, 1 );
		add_action( 'si5g_varrer', array( __CLASS__, 'varrer' ) );
	}

	public static function disponivel(): bool {
		return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_schedule_single_action' );
	}

	/** @param int[] $ids */
	public static function enfileirar( array $ids ): int {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		foreach ( array_chunk( $ids, 20 ) as $parte ) {
			as_enqueue_async_action( 'si5g_lote', array( 'ids' => $parte ), self::GRUPO );
		}
		return count( $ids );
	}

	public static function lote( $ids ): void {
		$ids = array_values( array_map( 'absint', (array) $ids ) );
		foreach ( $ids as $i => $id ) {
			list( $status ) = SI5G_Aplicador::processar( $id );
			if ( 'limite' === $status ) {
				as_schedule_single_action( max( time() + 60, SI5G_API::pausado_ate() + 60 ), 'si5g_lote', array( 'ids' => array_slice( $ids, $i ) ), self::GRUPO );
				return;
			}
			usleep( 150000 );
		}
	}

	public static function estado(): array {
		$e = get_option( self::ESTADO, array() );
		return array_merge( array( 'ativa' => false, 'ultimo_id' => 0, 'vistos' => 0, 'ok' => 0, 'nao_encontrado' => 0, 'sem_gtin' => 0, 'erro' => 0, 'inicio' => 0, 'fim' => 0, 'pausada_ate' => 0, 'mensagem' => '' ), is_array( $e ) ? $e : array() );
	}

	private static function salvar( array $e ): void {
		update_option( self::ESTADO, $e, false );
	}

	public static function iniciar_varredura(): void {
		as_unschedule_all_actions( 'si5g_varrer', array(), self::GRUPO );
		self::salvar( array( 'ativa' => true, 'ultimo_id' => 0, 'vistos' => 0, 'ok' => 0, 'nao_encontrado' => 0, 'sem_gtin' => 0, 'erro' => 0, 'inicio' => time(), 'fim' => 0, 'pausada_ate' => 0, 'mensagem' => '' ) );
		as_enqueue_async_action( 'si5g_varrer', array(), self::GRUPO );
	}

	public static function parar_varredura(): void {
		as_unschedule_all_actions( 'si5g_varrer', array(), self::GRUPO );
		$e          = self::estado();
		$e['ativa'] = false;
		$e['fim']   = time();
		$e['mensagem'] = __( 'Parada pelo usuário.', 'si5-global-woocommerce' );
		self::salvar( $e );
	}

	public static function varrer(): void {
		global $wpdb;
		$e = self::estado();
		if ( ! $e['ativa'] ) {
			return;
		}
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','pending','private') AND ID > %d ORDER BY ID ASC LIMIT %d",
			(int) $e['ultimo_id'],
			self::POR_VEZ
		) );
		if ( ! $ids ) {
			$e['ativa']    = false;
			$e['fim']      = time();
			$e['mensagem'] = __( 'Varredura concluída.', 'si5-global-woocommerce' );
			self::salvar( $e );
			return;
		}

		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( self::pular( $id ) ) {
				$e['ultimo_id'] = $id;
				continue;
			}
			list( $status, $mensagem ) = SI5G_Aplicador::processar( $id );
			if ( 'limite' === $status ) {
				$e['pausada_ate'] = SI5G_API::pausado_ate();
				$e['mensagem']    = $mensagem;
				self::salvar( $e );
				as_schedule_single_action( max( time() + 60, $e['pausada_ate'] + 60 ), 'si5g_varrer', array(), self::GRUPO );
				return;
			}
			$e['ultimo_id'] = $id;
			$e['vistos']++;
			$e[ $status ] = ( $e[ $status ] ?? 0 ) + 1;
			if ( 'erro' === $status ) {
				$e['mensagem'] = $mensagem;
			}
			usleep( 150000 );
		}
		$e['pausada_ate'] = 0;
		self::salvar( $e );
		as_enqueue_async_action( 'si5g_varrer', array(), self::GRUPO );
	}

	/** Ja tem NCM, ou ja foi consultado sem sucesso ha pouco tempo. */
	private static function pular( int $id ): bool {
		if ( '' !== (string) get_post_meta( $id, SI5G_Aplicador::META_NCM, true ) ) {
			return true;
		}
		$status = (string) get_post_meta( $id, SI5G_Aplicador::META_STATUS, true );
		$quando = (int) get_post_meta( $id, SI5G_Aplicador::META_QUANDO, true );
		// achado no catalogo mas ainda sem NCM la (ou nao encontrado): o catalogo e classificado todo dia - tenta em 30 dias
		if ( in_array( $status, array( 'nao_encontrado', 'ok' ), true ) && $quando > time() - self::REVER_NAO_ENCONTRADO ) {
			return true;
		}
		if ( 'sem_gtin' === $status && $quando >= (int) get_post_modified_time( 'U', true, $id ) ) {
			return true; // so tenta de novo se o produto foi editado depois (pode ter ganhado o codigo)
		}
		return false;
	}
}
