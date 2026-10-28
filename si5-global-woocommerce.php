<?php
/**
 * Plugin Name:       SI5 Global para WooCommerce
 * Plugin URI:        https://global.si5.com.br/
 * Description:       Completa os produtos pelo código de barras (GTIN/EAN) com dados do catálogo SI5 Global: título, foto, marca, NCM e CEST. Só preenche o que está vazio.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            SI5
 * Author URI:        https://global.si5.com.br/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       si5-global-woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 */

defined( 'ABSPATH' ) || exit;

define( 'SI5G_VERSAO', '1.0.1' );
define( 'SI5G_ARQUIVO', __FILE__ );
define( 'SI5G_DIR', plugin_dir_path( __FILE__ ) );
define( 'SI5G_URL', plugin_dir_url( __FILE__ ) );

require_once SI5G_DIR . 'includes/class-si5g-opcoes.php';
require_once SI5G_DIR . 'includes/class-si5g-gtin.php';
require_once SI5G_DIR . 'includes/class-si5g-api.php';
require_once SI5G_DIR . 'includes/class-si5g-aplicador.php';
require_once SI5G_DIR . 'includes/class-si5g-fila.php';
require_once SI5G_DIR . 'includes/class-si5g-admin.php';

// O plugin nao mexe em pedidos, mas o WooCommerce pede a declaracao para nao mostrar aviso com HPOS ativo.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SI5G_ARQUIVO, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'SI5 Global para WooCommerce precisa do WooCommerce ativo.', 'si5-global-woocommerce' ) . '</p></div>';
		} );
		return;
	}
	SI5G_Fila::iniciar();
	if ( is_admin() ) {
		SI5G_Admin::iniciar();
	}
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=si5-global' ) ) . '">' . esc_html__( 'Configurar', 'si5-global-woocommerce' ) . '</a>' );
	return $links;
} );
