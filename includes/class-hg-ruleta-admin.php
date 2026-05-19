<?php
/**
 * Pantallas de administración del plugin:
 * - Premios (configurar lista + pesos)
 * - Historial de giros
 * - Configuración general (límite diario, WhatsApp, textos)
 *
 * @package HingeniaRuleta
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HG_Ruleta_Admin {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_hg_ruleta_save_prizes', array( $this, 'save_prizes' ) );
		add_action( 'admin_post_hg_ruleta_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_hg_ruleta_export_csv', array( $this, 'export_csv' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Ruleta de Premios', 'hingenia-ruleta' ),
			__( '🎁 Ruleta', 'hingenia-ruleta' ),
			'manage_options',
			'hg-ruleta',
			array( $this, 'render_prizes' ),
			'dashicons-superhero',
			30
		);
		add_submenu_page( 'hg-ruleta', __( 'Premios', 'hingenia-ruleta' ),       __( 'Premios', 'hingenia-ruleta' ),       'manage_options', 'hg-ruleta',           array( $this, 'render_prizes' ) );
		add_submenu_page( 'hg-ruleta', __( 'Historial', 'hingenia-ruleta' ),     __( 'Historial', 'hingenia-ruleta' ),     'manage_options', 'hg-ruleta-history',   array( $this, 'render_history' ) );
		add_submenu_page( 'hg-ruleta', __( 'Configuración', 'hingenia-ruleta' ), __( 'Configuración', 'hingenia-ruleta' ), 'manage_options', 'hg-ruleta-settings',  array( $this, 'render_settings' ) );
	}

	/* ===================================================================
	   PREMIOS
	   =================================================================== */

	public function render_prizes() {
		$prizes  = HG_Ruleta_Data::get_prizes();
		$updated = isset( $_GET['updated'] );
		?>
		<div class="wrap hg-rul">
			<h1>🎁 <?php esc_html_e( 'Premios de la ruleta', 'hingenia-ruleta' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Define los premios que pueden salir. El "peso" controla cuánto sale cada uno: más peso = más probabilidad. (Ej: si A pesa 1 y B pesa 9, B saldrá 9× más que A.)', 'hingenia-ruleta' ); ?>
			</p>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Premios guardados.', 'hingenia-ruleta' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hg_ruleta_save_prizes">
				<?php wp_nonce_field( 'hg_ruleta_prizes' ); ?>

				<table class="widefat hg-rul-table">
					<thead>
						<tr>
							<th style="width:42px;">#</th>
							<th><?php esc_html_e( 'Etiqueta', 'hingenia-ruleta' ); ?></th>
							<th><?php esc_html_e( 'Color', 'hingenia-ruleta' ); ?></th>
							<th><?php esc_html_e( 'Peso', 'hingenia-ruleta' ); ?></th>
							<th><?php esc_html_e( 'Activo', 'hingenia-ruleta' ); ?></th>
							<th><?php esc_html_e( 'Eliminar', 'hingenia-ruleta' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$i    = 0;
						$rows = $prizes ? $prizes : HG_Ruleta_Data::default_prizes();
						foreach ( $rows as $p ) :
							$i++;
							?>
							<tr>
								<td><?php echo $i; ?></td>
								<td><input type="text"   name="prizes[<?php echo $i; ?>][label]"  value="<?php echo esc_attr( $p['label'] ); ?>"  class="widefat"></td>
								<td><input type="color"  name="prizes[<?php echo $i; ?>][color]"  value="<?php echo esc_attr( ! empty( $p['color'] ) ? $p['color'] : '#2563eb' ); ?>"></td>
								<td><input type="number" name="prizes[<?php echo $i; ?>][weight]" value="<?php echo (int) $p['weight']; ?>" min="0" max="999" style="width:80px"></td>
								<td style="text-align:center"><input type="checkbox" name="prizes[<?php echo $i; ?>][active]" value="1" <?php checked( ! empty( $p['active'] ) ); ?>></td>
								<td style="text-align:center"><input type="checkbox" name="prizes[<?php echo $i; ?>][delete]" value="1"></td>
							</tr>
						<?php endforeach; ?>
						<?php for ( $k = 0; $k < 3; $k++ ) : $i++; ?>
							<tr style="background:#fafbfc">
								<td><?php echo $i; ?></td>
								<td><input type="text"   name="prizes[<?php echo $i; ?>][label]"  value="" class="widefat" placeholder="<?php esc_attr_e( 'Nuevo premio…', 'hingenia-ruleta' ); ?>"></td>
								<td><input type="color"  name="prizes[<?php echo $i; ?>][color]"  value="#2563eb"></td>
								<td><input type="number" name="prizes[<?php echo $i; ?>][weight]" value="1" min="0" max="999" style="width:80px"></td>
								<td style="text-align:center"><input type="checkbox" name="prizes[<?php echo $i; ?>][active]" value="1" checked></td>
								<td style="text-align:center">—</td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'Para añadir más premios, guarda y vuelve: siempre quedan 3 filas vacías al final.', 'hingenia-ruleta' ); ?></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar premios', 'hingenia-ruleta' ); ?></button></p>
			</form>

			<h2 style="margin-top:30px"><?php esc_html_e( 'Probabilidad de cada premio (vista previa)', 'hingenia-ruleta' ); ?></h2>
			<?php $this->render_weights_preview(); ?>
		</div>

		<style>
			.hg-rul-table input[type=text], .hg-rul-table input[type=number] { padding:5px 8px; }
			.hg-rul-bar { display:flex; gap:8px; background:#fff; border:1px solid #dcdcde; border-radius:10px; padding:14px; flex-wrap:wrap; align-items:flex-end; min-height:160px; }
			.hg-rul-bar .col { flex:1; min-width:110px; display:flex; flex-direction:column; align-items:center; gap:6px; }
			.hg-rul-bar .bar { width:48px; border-radius:8px 8px 0 0; }
			.hg-rul-bar small { font-size:11px; color:#646970; text-align:center; line-height:1.3; }
			.hg-rul-bar b { font-size:14px; color:#1d2327; }
		</style>
		<?php
	}

	private function render_weights_preview() {
		$active = HG_Ruleta_Data::active_prizes();
		if ( empty( $active ) ) {
			echo '<p>' . esc_html__( 'No hay premios activos.', 'hingenia-ruleta' ) . '</p>';
			return;
		}
		$total = 0;
		foreach ( $active as $p ) {
			$total += max( 0, (int) $p['weight'] );
		}
		if ( $total <= 0 ) {
			$total = count( $active );
			$uniform = true;
		} else {
			$uniform = false;
		}
		echo '<div class="hg-rul-bar">';
		foreach ( $active as $p ) {
			$w   = $uniform ? 1 : max( 0, (int) $p['weight'] );
			$pct = round( $w / $total * 100 );
			$h   = max( 14, min( 130, (int) ( $pct * 1.4 ) ) );
			printf(
				'<div class="col"><div class="bar" style="height:%dpx;background:%s"></div><b>%d%%</b><small>%s</small></div>',
				(int) $h,
				esc_attr( ! empty( $p['color'] ) ? $p['color'] : '#2563eb' ),
				(int) $pct,
				esc_html( $p['label'] )
			);
		}
		echo '</div>';
	}

	public function save_prizes() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'hingenia-ruleta' ) );
		}
		check_admin_referer( 'hg_ruleta_prizes' );

		$raw = isset( $_POST['prizes'] ) && is_array( $_POST['prizes'] ) ? wp_unslash( $_POST['prizes'] ) : array();
		$out = array();
		foreach ( $raw as $p ) {
			if ( ! empty( $p['delete'] ) ) {
				continue;
			}
			$label = isset( $p['label'] ) ? sanitize_text_field( $p['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$color = isset( $p['color'] ) ? sanitize_hex_color( $p['color'] ) : '#2563eb';
			$out[] = array(
				'label'  => $label,
				'color'  => $color ? $color : '#2563eb',
				'weight' => isset( $p['weight'] ) ? max( 0, (int) $p['weight'] ) : 1,
				'active' => ! empty( $p['active'] ) ? '1' : '',
			);
		}
		HG_Ruleta_Data::save_prizes( $out );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=hg-ruleta' ) ) );
		exit;
	}

	/* ===================================================================
	   HISTORIAL
	   =================================================================== */

	public function render_history() {
		$rows      = HG_Ruleta_Data::get_history( 500 );
		$total     = HG_Ruleta_Data::count_all();
		$claimed   = HG_Ruleta_Data::count_claimed();
		$pct       = $total ? round( $claimed / $total * 100 ) : 0;
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=hg_ruleta_export_csv' ),
			'hg_ruleta_export'
		);
		?>
		<div class="wrap">
			<h1>📜 <?php esc_html_e( 'Historial de giros', 'hingenia-ruleta' ); ?></h1>

			<div class="hg-rul-stats" style="display:flex;gap:12px;margin:16px 0;">
				<div class="hg-rul-stat"><span><?php esc_html_e( 'Giros totales', 'hingenia-ruleta' ); ?></span><b><?php echo (int) $total; ?></b></div>
				<div class="hg-rul-stat is-ok"><span><?php esc_html_e( 'Reclamados', 'hingenia-ruleta' ); ?></span><b><?php echo (int) $claimed; ?> <small><?php echo (int) $pct; ?>%</small></b></div>
			</div>

			<p style="margin-bottom:10px;">
				<a href="<?php echo esc_url( $export_url ); ?>" class="button">⬇ <?php esc_html_e( 'Exportar CSV', 'hingenia-ruleta' ); ?></a>
			</p>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Usuario', 'hingenia-ruleta' ); ?></th>
					<th><?php esc_html_e( 'Correo', 'hingenia-ruleta' ); ?></th>
					<th><?php esc_html_e( 'Premio ganado', 'hingenia-ruleta' ); ?></th>
					<th><?php esc_html_e( 'Fecha y hora del giro', 'hingenia-ruleta' ); ?></th>
					<th><?php esc_html_e( '¿Reclamó por WhatsApp?', 'hingenia-ruleta' ); ?></th>
				</tr></thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Aún no hay giros.', 'hingenia-ruleta' ); ?></td></tr>
					<?php else : foreach ( $rows as $r ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $r->name ); ?></strong> <small>(#<?php echo (int) $r->user_id; ?>)</small></td>
							<td><?php echo $r->email ? '<a href="mailto:' . esc_attr( $r->email ) . '">' . esc_html( $r->email ) . '</a>' : '—'; ?></td>
							<td><?php echo esc_html( $r->prize_label ); ?></td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->created_at ) ) ); ?></td>
							<td><?php echo ! empty( $r->claimed_at )
								? '<span style="color:#0a7b3e;font-weight:700">✓ ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $r->claimed_at ) ) ) . '</span>'
								: '<span style="color:#94a3b8">—</span>'; ?></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
		<style>
			.hg-rul-stat { background:#fff; border:1px solid #dcdcde; border-radius:10px; padding:12px 18px; min-width:140px; }
			.hg-rul-stat span { display:block; font-size:12px; color:#646970; font-weight:600; }
			.hg-rul-stat b { font-size:26px; font-weight:800; color:#1d2327; letter-spacing:-.02em; }
			.hg-rul-stat.is-ok { background:#f0f9f2; border-color:#bbe6c6; }
			.hg-rul-stat.is-ok b { color:#0a7b3e; }
			.hg-rul-stat.is-ok b small { font-size:13px; font-weight:700; color:#16a34a; }
		</style>
		<?php
	}

	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'hingenia-ruleta' ) );
		}
		check_admin_referer( 'hg_ruleta_export' );

		$rows = HG_Ruleta_Data::get_history( 100000 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ruleta-' . gmdate( 'Ymd' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'Usuario', 'Correo', 'Premio', 'Fecha del giro', 'Reclamado' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array(
				$r->name,
				$r->email,
				$r->prize_label,
				$r->created_at,
				! empty( $r->claimed_at ) ? $r->claimed_at : 'No',
			) );
		}
		fclose( $out );
		exit;
	}

	/* ===================================================================
	   CONFIGURACIÓN
	   =================================================================== */

	public function render_settings() {
		$s       = HG_Ruleta_Data::get_settings();
		$updated = isset( $_GET['updated'] );
		?>
		<div class="wrap">
			<h1>⚙ <?php esc_html_e( 'Configuración de la ruleta', 'hingenia-ruleta' ); ?></h1>
			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuración guardada.', 'hingenia-ruleta' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hg_ruleta_save_settings">
				<?php wp_nonce_field( 'hg_ruleta_settings' ); ?>

				<h2><?php esc_html_e( 'Giros', 'hingenia-ruleta' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Giros por día (por usuario)', 'hingenia-ruleta' ); ?></label></th>
						<td>
							<input type="number" name="settings[spin_limit]" value="<?php echo (int) $s['spin_limit']; ?>" min="1" max="20" style="width:90px">
							<p class="description"><?php esc_html_e( '1 = solo un giro al día por usuario logueado.', 'hingenia-ruleta' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Reclamo por WhatsApp', 'hingenia-ruleta' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Número de WhatsApp', 'hingenia-ruleta' ); ?></label></th>
						<td>
							<input type="text" name="settings[whatsapp_number]" value="<?php echo esc_attr( $s['whatsapp_number'] ); ?>" class="regular-text" placeholder="51974258856">
							<p class="description"><?php esc_html_e( 'Solo dígitos, con código de país, sin "+".', 'hingenia-ruleta' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Mensaje pre-armado', 'hingenia-ruleta' ); ?></label></th>
						<td>
							<textarea name="settings[whatsapp_message]" class="large-text" rows="3"><?php echo esc_textarea( $s['whatsapp_message'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Variables: {nombre} (del usuario) · {premio} (el que ganó).', 'hingenia-ruleta' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Textos visibles', 'hingenia-ruleta' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Título del banner', 'hingenia-ruleta' ); ?></label></th>
						<td><input type="text" name="settings[banner_title]" value="<?php echo esc_attr( $s['banner_title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Subtítulo del banner', 'hingenia-ruleta' ); ?></label></th>
						<td><input type="text" name="settings[banner_subtitle]" value="<?php echo esc_attr( $s['banner_subtitle'] ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Título del modal', 'hingenia-ruleta' ); ?></label></th>
						<td><input type="text" name="settings[modal_title]" value="<?php echo esc_attr( $s['modal_title'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Subtítulo del modal', 'hingenia-ruleta' ); ?></label></th>
						<td><input type="text" name="settings[modal_subtitle]" value="<?php echo esc_attr( $s['modal_subtitle'] ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Texto del centro de la rueda', 'hingenia-ruleta' ); ?></label></th>
						<td>
							<input type="text" name="settings[wheel_center_text]" value="<?php echo esc_attr( $s['wheel_center_text'] ); ?>" maxlength="3" style="width:90px">
							<p class="description"><?php esc_html_e( '1-3 caracteres (suele ser un emoji o las iniciales).', 'hingenia-ruleta' ); ?></p>
						</td>
					</tr>
				</table>

				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar configuración', 'hingenia-ruleta' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'hingenia-ruleta' ) );
		}
		check_admin_referer( 'hg_ruleta_settings' );

		$raw   = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$prev  = HG_Ruleta_Data::get_settings();
		$clean = array(
			'spin_limit'         => max( 1, (int) ( $raw['spin_limit'] ?? 1 ) ),
			'whatsapp_number'    => preg_replace( '/\D/', '', $raw['whatsapp_number'] ?? '' ),
			'whatsapp_message'   => sanitize_textarea_field( $raw['whatsapp_message'] ?? '' ),
			'wheel_center_text'  => sanitize_text_field( $raw['wheel_center_text'] ?? 'H' ),
			'banner_title'       => sanitize_text_field( $raw['banner_title']    ?? $prev['banner_title'] ),
			'banner_subtitle'    => sanitize_text_field( $raw['banner_subtitle'] ?? $prev['banner_subtitle'] ),
			'modal_title'        => sanitize_text_field( $raw['modal_title']     ?? $prev['modal_title'] ),
			'modal_subtitle'     => sanitize_text_field( $raw['modal_subtitle']  ?? $prev['modal_subtitle'] ),
			'login_required_msg' => $prev['login_required_msg'],
		);
		HG_Ruleta_Data::save_settings( $clean );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=hg-ruleta-settings' ) ) );
		exit;
	}
}
