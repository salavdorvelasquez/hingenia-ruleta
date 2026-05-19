<?php
/**
 * Plugin Name: Hingenia Ruleta de Premios
 * Plugin URI: https://hingenia.com
 * Description: Ruleta gamificada de premios para alumnos. El plugin maneja toda la lógica (premios, pesos, AJAX del giro, historial y configuración). El tema solo renderiza la rueda — los datos los lee de este plugin.
 * Version: 1.0.1
 * Author: Hingenia
 * Text Domain: hingenia-ruleta
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: Proprietary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HG_RULETA_VERSION', '1.0.1' );
define( 'HG_RULETA_DIR', plugin_dir_path( __FILE__ ) );
define( 'HG_RULETA_URL', plugin_dir_url( __FILE__ ) );
define( 'HG_RULETA_BASENAME', plugin_basename( __FILE__ ) );

require_once HG_RULETA_DIR . 'includes/class-hg-ruleta-data.php';
require_once HG_RULETA_DIR . 'includes/class-hg-ruleta-ajax.php';
require_once HG_RULETA_DIR . 'includes/class-hg-ruleta-admin.php';

add_action( 'plugins_loaded', function () {
	HG_Ruleta_Data::boot();
	HG_Ruleta_Ajax::get_instance();
	if ( is_admin() ) {
		HG_Ruleta_Admin::get_instance();
	}
} );

register_activation_hook( __FILE__, function () {
	require_once HG_RULETA_DIR . 'includes/class-hg-ruleta-data.php';
	HG_Ruleta_Data::create_table();
	HG_Ruleta_Data::install_defaults();
	update_option( HG_Ruleta_Data::OPT_DB_VER, HG_Ruleta_Data::DB_VERSION );
} );
