<?php
// Remove so as configuracoes do plugin. NCM/CEST gravados nos produtos ficam (sao dados da loja).
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'si5g_opcoes' );
delete_option( 'si5g_varredura' );
delete_option( 'si5g_limite_ate' );
