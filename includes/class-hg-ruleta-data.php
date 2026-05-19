<?php
/**
 * Capa de datos del plugin Ruleta:
 * - Premios y configuración (en options).
 * - Historial de giros (tabla propia).
 * - Selección aleatoria ponderada.
 *
 * @package HingeniaRuleta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HG_Ruleta_Data {

	const OPT_PRIZES   = 'hg_ruleta_prizes';
	const OPT_SETTINGS = 'hg_ruleta_settings';
	const OPT_DB_VER   = 'hg_ruleta_db_version';
	const TABLE        = 'hg_ruleta_spins';
	const DB_VERSION   = '1';

	public static function boot() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_table' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** Auto-instala/actualiza la tabla cuando cambia DB_VERSION. */
	public static function maybe_create_table() {
		if ( get_option( self::OPT_DB_VER ) !== self::DB_VERSION ) {
			self::create_table();
			self::install_defaults();
			update_option( self::OPT_DB_VER, self::DB_VERSION );
		}
	}

	public static function create_table() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			name VARCHAR(190) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			prize_index INT NOT NULL DEFAULT 0,
			prize_label VARCHAR(190) NOT NULL DEFAULT '',
			token VARCHAR(32) NOT NULL DEFAULT '',
			claimed_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY token (token),
			KEY created_at (created_at)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Premios + configuración por defecto cuando se instala el plugin. */
	public static function install_defaults() {
		if ( ! get_option( self::OPT_PRIZES ) ) {
			update_option( self::OPT_PRIZES, self::default_prizes() );
		}
		if ( ! get_option( self::OPT_SETTINGS ) ) {
			update_option( self::OPT_SETTINGS, self::default_settings() );
		}
	}

	public static function default_prizes() {
		return array(
			array( 'label' => '🎁 Curso GRATIS',  'color' => '#2563eb', 'weight' => 1,  'active' => '1' ),
			array( 'label' => '70% OFF',          'color' => '#f59e0b', 'weight' => 2,  'active' => '1' ),
			array( 'label' => 'Bono S/ 50',       'color' => '#16a34a', 'weight' => 3,  'active' => '1' ),
			array( 'label' => '50% OFF',          'color' => '#ec4899', 'weight' => 4,  'active' => '1' ),
			array( 'label' => '📘 Ebook gratis',  'color' => '#06b6d4', 'weight' => 5,  'active' => '1' ),
			array( 'label' => '30% OFF',          'color' => '#f97316', 'weight' => 6,  'active' => '1' ),
			array( 'label' => 'Bono S/ 30',       'color' => '#6366f1', 'weight' => 6,  'active' => '1' ),
			array( 'label' => '20% OFF',          'color' => '#ef4444', 'weight' => 10, 'active' => '1' ),
		);
	}

	public static function default_settings() {
		return array(
			'spin_limit'         => '1',
			'whatsapp_number'    => '51974258856',
			'whatsapp_message'   => '¡Hola Hingenia! Gané "{premio}" en la ruleta de premios. Quiero reclamarlo. (Soy {nombre})',
			'wheel_center_text'  => 'H',
			'banner_title'       => '¡Gira la ruleta de premios!',
			'banner_subtitle'    => 'Cursos gratis, descuentos y bonos te esperan. 1 giro gratis cada día.',
			'modal_title'        => '🎁 Ruleta de premios',
			'modal_subtitle'     => 'Gira y gana descuentos, bonos o un curso gratis. 1 giro por día.',
			'login_required_msg' => 'Inicia sesión para girar la ruleta.',
		);
	}

	public static function get_prizes() {
		$p = get_option( self::OPT_PRIZES, array() );
		return is_array( $p ) ? array_values( $p ) : array();
	}

	public static function save_prizes( $prizes ) {
		update_option( self::OPT_PRIZES, array_values( (array) $prizes ) );
	}

	/** Premios activos, en el orden con que se renderiza la rueda. */
	public static function active_prizes() {
		$out = array();
		foreach ( self::get_prizes() as $p ) {
			if ( ! empty( $p['active'] ) && ! empty( $p['label'] ) ) {
				$out[] = $p;
			}
		}
		return array_values( $out );
	}

	public static function get_settings() {
		$s = get_option( self::OPT_SETTINGS, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), self::default_settings() );
	}

	public static function save_settings( $settings ) {
		update_option( self::OPT_SETTINGS, $settings );
	}

	/**
	 * Selección aleatoria ponderada sobre los premios activos.
	 *
	 * @return array|null  [ index, prize ]  o  null si no hay premios.
	 */
	public static function pick_prize() {
		$prizes = self::active_prizes();
		if ( empty( $prizes ) ) {
			return null;
		}
		$total = 0;
		foreach ( $prizes as $p ) {
			$total += max( 0, (int) $p['weight'] );
		}
		// Si nadie tiene peso, distribución uniforme.
		if ( $total <= 0 ) {
			$i = wp_rand( 0, count( $prizes ) - 1 );
			return array( $i, $prizes[ $i ] );
		}
		$r   = wp_rand( 1, $total );
		$acc = 0;
		foreach ( $prizes as $i => $p ) {
			$acc += max( 0, (int) $p['weight'] );
			if ( $r <= $acc ) {
				return array( $i, $p );
			}
		}
		$i = count( $prizes ) - 1;
		return array( $i, $prizes[ $i ] );
	}

	/* ====== Historial de giros ====== */

	public static function count_user_spins_today( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE user_id = %d AND DATE(created_at) = %s',
			(int) $user_id,
			current_time( 'Y-m-d' )
		) );
	}

	public static function record_spin( $user_id, $name, $email, $prize_index, $prize_label ) {
		global $wpdb;
		$token = wp_generate_password( 20, false );
		$wpdb->insert(
			self::table_name(),
			array(
				'user_id'     => (int) $user_id,
				'name'        => $name,
				'email'       => $email,
				'prize_index' => (int) $prize_index,
				'prize_label' => $prize_label,
				'token'       => $token,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return array( 'id' => (int) $wpdb->insert_id, 'token' => $token );
	}

	public static function get_spin_by_token( $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table_name() . ' WHERE token = %s',
			$token
		) );
	}

	public static function mark_claimed( $id ) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'claimed_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function get_today_spin_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table_name() . ' WHERE user_id = %d AND DATE(created_at) = %s ORDER BY id DESC LIMIT 1',
			(int) $user_id,
			current_time( 'Y-m-d' )
		) );
	}

	public static function get_history( $limit = 500 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table_name() . ' ORDER BY created_at DESC LIMIT %d',
			(int) $limit
		) );
	}

	public static function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}

	public static function count_claimed() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() . ' WHERE claimed_at IS NOT NULL' );
	}

	/**
	 * URL pública del frontend para reclamar el premio.
	 * Se hace en frontend (init) y NO en admin-post.php a propósito:
	 * algunos firewalls/hosts (LiteSpeed, Hostinger) bloquean llamadas
	 * a wp-admin/admin-post.php con acciones que no conocen.
	 */
	public static function build_claim_url( $token ) {
		return add_query_arg(
			array( 'hg_ruleta_claim' => '1', 't' => $token ),
			home_url( '/' )
		);
	}

	/** URL de WhatsApp ya con el mensaje pre-armado. */
	public static function whatsapp_url_for( $name, $prize_label ) {
		$settings = self::get_settings();
		$msg      = strtr( $settings['whatsapp_message'], array(
			'{nombre}' => $name,
			'{premio}' => $prize_label,
		) );
		$num = preg_replace( '/\D/', '', $settings['whatsapp_number'] );
		return 'https://wa.me/' . $num . '?text=' . rawurlencode( $msg );
	}
}
