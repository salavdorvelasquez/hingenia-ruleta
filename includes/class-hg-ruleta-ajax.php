<?php
/**
 * Endpoints públicos:
 * - AJAX hg_ruleta_spin: gira la ruleta (logueados, 1 vez/día por defecto).
 * - admin-post hg_ruleta_claim: registra el clic y redirige al grupo de WhatsApp.
 *
 * @package HingeniaRuleta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HG_Ruleta_Ajax {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_hg_ruleta_spin', array( $this, 'handle_spin' ) );
		// Sin _nopriv: solo usuarios logueados pueden girar.

		add_action( 'admin_post_hg_ruleta_claim', array( $this, 'handle_claim' ) );
		add_action( 'admin_post_nopriv_hg_ruleta_claim', array( $this, 'handle_claim' ) );
	}

	/** Gira la ruleta. */
	public function handle_spin() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array(
				'code'    => 'login',
				'message' => __( 'Inicia sesión para girar la ruleta.', 'hingenia-ruleta' ),
			) );
		}

		check_ajax_referer( 'hg_ruleta_spin', 'nonce' );

		$settings = HG_Ruleta_Data::get_settings();
		$limit    = max( 1, (int) $settings['spin_limit'] );
		$user_id  = get_current_user_id();

		if ( HG_Ruleta_Data::count_user_spins_today( $user_id ) >= $limit ) {
			// Devuelve el último premio del día para que pueda volver a reclamar.
			$row = HG_Ruleta_Data::get_today_spin_for_user( $user_id );
			if ( $row ) {
				wp_send_json_error( array(
					'code'      => 'limit',
					'message'   => __( 'Ya giraste hoy. Vuelve mañana.', 'hingenia-ruleta' ),
					'index'     => (int) $row->prize_index,
					'label'     => $row->prize_label,
					'claim_url' => HG_Ruleta_Data::build_claim_url( $row->token ),
				) );
			}
			wp_send_json_error( array(
				'code'    => 'limit',
				'message' => __( 'Ya giraste hoy. Vuelve mañana.', 'hingenia-ruleta' ),
			) );
		}

		$pick = HG_Ruleta_Data::pick_prize();
		if ( ! $pick ) {
			wp_send_json_error( array(
				'code'    => 'no_prizes',
				'message' => __( 'Aún no hay premios configurados.', 'hingenia-ruleta' ),
			) );
		}

		list( $index, $prize ) = $pick;

		$user = wp_get_current_user();
		$name = $user->display_name ? $user->display_name : $user->user_login;
		$rec  = HG_Ruleta_Data::record_spin( $user_id, $name, $user->user_email, $index, $prize['label'] );

		wp_send_json_success( array(
			'index'        => (int) $index,
			'label'        => $prize['label'],
			'color'        => isset( $prize['color'] ) ? $prize['color'] : '#2563eb',
			'token'        => $rec['token'],
			'claim_url'    => HG_Ruleta_Data::build_claim_url( $rec['token'] ),
			'whatsapp_url' => HG_Ruleta_Data::whatsapp_url_for( $name, $prize['label'] ),
		) );
	}

	/** Registra que el usuario hizo clic en "Reclamar" y redirige a WhatsApp. */
	public function handle_claim() {
		$token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		if ( $token ) {
			$row = HG_Ruleta_Data::get_spin_by_token( $token );
			if ( $row ) {
				if ( empty( $row->claimed_at ) ) {
					HG_Ruleta_Data::mark_claimed( $row->id );
				}
				wp_redirect( HG_Ruleta_Data::whatsapp_url_for( $row->name, $row->prize_label ) );
				exit;
			}
		}
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}
