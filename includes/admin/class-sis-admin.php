<?php
/**
 * Panel de administración.
 *
 * Cinco pantallas: Resumen (estado y acciones), Fuentes (configuración de las
 * APIs), Amenaza (referencia normativa y descargo institucional), Apariencia
 * (variables CSS) y Elementos (catálogo de shortcodes para copiar y pegar).
 *
 * Toda escritura pasa por comprobación de capacidad (manage_options) y nonce.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'sismos-narino';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		// Guardado de formularios.
		add_action( 'admin_post_sis_guardar_fuentes', array( $this, 'guardar_fuentes' ) );
		add_action( 'admin_post_sis_guardar_amenaza', array( $this, 'guardar_amenaza' ) );
		add_action( 'admin_post_sis_guardar_estilo', array( $this, 'guardar_estilo' ) );

		// Acciones asíncronas.
		add_action( 'wp_ajax_sis_sincronizar', array( $this, 'ajax_sincronizar' ) );
		add_action( 'wp_ajax_sis_probar', array( $this, 'ajax_probar' ) );
	}

	/* ----------------------------------------------------------------- */
	/* Menú y assets                                                     */
	/* ----------------------------------------------------------------- */

	/**
	 * Registra el menú y sus pantallas.
	 */
	public function menu() {
		add_menu_page(
			__( 'Sismos Nariño', 'sismos-narino' ),
			__( 'Sismos Nariño', 'sismos-narino' ),
			self::CAP,
			self::SLUG,
			array( $this, 'pantalla_resumen' ),
			'dashicons-chart-area',
			58
		);

		$sub = array(
			''            => array( __( 'Resumen', 'sismos-narino' ), 'pantalla_resumen' ),
			'-fuentes'    => array( __( 'Fuentes', 'sismos-narino' ), 'pantalla_fuentes' ),
			'-amenaza'    => array( __( 'Amenaza y normativa', 'sismos-narino' ), 'pantalla_amenaza' ),
			'-apariencia' => array( __( 'Apariencia', 'sismos-narino' ), 'pantalla_apariencia' ),
			'-elementos'  => array( __( 'Elementos', 'sismos-narino' ), 'pantalla_elementos' ),
		);

		foreach ( $sub as $sufijo => $datos ) {
			add_submenu_page(
				self::SLUG,
				$datos[0],
				$datos[0],
				self::CAP,
				self::SLUG . $sufijo,
				array( $this, $datos[1] )
			);
		}
	}

	/**
	 * Encola el JS del panel solo en nuestras pantallas.
	 *
	 * @param string $hook Hook de la pantalla actual.
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'sis-admin-css', SIS_URL . 'assets/css/admin.css', array(), SIS_VERSION );
		wp_enqueue_script( 'sis-admin', SIS_URL . 'assets/js/admin.js', array(), SIS_VERSION, true );
		wp_localize_script( 'sis-admin', 'SISAdmin', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'sis_admin' ),
		) );
	}

	/**
	 * Cabecera común de las pantallas.
	 *
	 * @param string $titulo Título.
	 */
	private function cabecera( $titulo ) {
		echo '<div class="wrap"><h1>' . esc_html( $titulo ) . '</h1>';
		if ( isset( $_GET['sis_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cambios guardados.', 'sismos-narino' ) . '</p></div>';
		}
	}

	/* ----------------------------------------------------------------- */
	/* Pantalla: Resumen                                                 */
	/* ----------------------------------------------------------------- */

	/**
	 * Estado general, acciones y pronóstico vigente.
	 */
	public function pantalla_resumen() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$this->cabecera( __( 'Sismos Nariño — Resumen', 'sismos-narino' ) );

		$fuentes = SIS_Sync::estado();
		$ambito  = SIS_Regiones::por_defecto();
		$cat     = SIS_Catalogo::obtener( $ambito );
		$stats   = SIS_Estadistica::resumen( $cat['eventos'] );
		?>
		<p class="description" style="max-width:820px">
			<?php esc_html_e( 'Motor de datos: USGS FDSN Event Web Service y feeds GeoJSON de resumen (sin clave de API, actualización del feed cada minuto).', 'sismos-narino' ); ?>
		</p>

		<div class="notice notice-info inline" style="max-width:820px">
			<p><strong><?php esc_html_e( 'Este plugin no pronostica sismos.', 'sismos-narino' ); ?></strong>
			<?php esc_html_e( 'Publica estadística de lo ya ocurrido, contexto de amenaza y preparación ciudadana, y remite a la autoridad técnica. La predicción de un sismo —fecha, lugar y magnitud— no es posible, y el pronóstico probabilístico de réplicas es competencia del Servicio Geológico Colombiano, que hoy no lo emite.', 'sismos-narino' ); ?></p>
		</div>

		<h2><?php esc_html_e( 'Catálogo vigente', 'sismos-narino' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<tbody>
				<tr><th><?php esc_html_e( 'Ámbito de referencia', 'sismos-narino' ); ?></th><td><?php echo esc_html( SIS_Regiones::obtener( $ambito )['nombre'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Sismos cargados', 'sismos-narino' ); ?></th><td><?php echo esc_html( number_format_i18n( $cat['total'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Última actualización', 'sismos-narino' ); ?></th><td><?php echo esc_html( $cat['actualizado'] ? $cat['actualizado'] . ' UTC' : '—' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Origen del dato', 'sismos-narino' ); ?></th><td><?php echo esc_html( $cat['origen'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Magnitud de completitud', 'sismos-narino' ); ?></th><td><?php echo esc_html( number_format_i18n( $stats['gutenberg']['mc'], 1 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Valor b', 'sismos-narino' ); ?></th><td><?php echo esc_html( number_format_i18n( $stats['gutenberg']['b'], 2 ) . ' ± ' . number_format_i18n( $stats['gutenberg']['b_error'], 2 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Ventana del catálogo', 'sismos-narino' ); ?></th><td><?php echo esc_html( number_format_i18n( $stats['anios'], 1 ) . ' ' . __( 'años', 'sismos-narino' ) ); ?></td></tr>
			</tbody>
		</table>

		<p>
			<button type="button" class="button button-primary" data-sis-accion="sincronizar" data-fuente="usgs_fdsn">
				<?php esc_html_e( 'Sincronizar catálogo ahora', 'sismos-narino' ); ?>
			</button>
			<button type="button" class="button" data-sis-accion="sincronizar" data-fuente="usgs_feed">
				<?php esc_html_e( 'Refrescar feed reciente', 'sismos-narino' ); ?>
			</button>
			<span class="sis-admin-estado" aria-live="polite"></span>
		</p>

		<h2><?php esc_html_e( 'Recurrencia observada', 'sismos-narino' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Lo que registró el catálogo en la ventana consultada. Son promedios del pasado, no una proyección ni un calendario.', 'sismos-narino' ); ?></p>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th><?php esc_html_e( 'Magnitud', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Sismos observados', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Tasa anual observada', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Intervalo medio', 'sismos-narino' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $stats['umbrales'] as $u ) : ?>
				<tr>
					<td><?php echo esc_html( 'M ≥ ' . number_format_i18n( $u['magnitud'], 1 ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $u['observados'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $u['tasa_anual_obs'], 2 ) . ' /' . __( 'año', 'sismos-narino' ) ); ?></td>
					<td><?php echo esc_html( $u['intervalo_medio'] ? number_format_i18n( $u['intervalo_medio'], 1 ) . ' ' . __( 'años', 'sismos-narino' ) : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Fuentes', 'sismos-narino' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th><?php esc_html_e( 'Fuente', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Estado', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Última sincronización', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Resultado', 'sismos-narino' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $fuentes as $f ) : ?>
				<tr>
					<td><?php echo esc_html( $f['nombre'] ); ?></td>
					<td><?php echo esc_html( $f['salud'] ); ?></td>
					<td><?php echo esc_html( $f['ultima_sync'] ? $f['ultima_sync'] : '—' ); ?></td>
					<td><?php echo esc_html( $f['ultimo_resultado'] ? $f['ultimo_resultado'] : '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	/* ----------------------------------------------------------------- */
	/* Pantalla: Fuentes                                                 */
	/* ----------------------------------------------------------------- */

	/**
	 * Configuración de las fuentes de datos.
	 */
	public function pantalla_fuentes() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$cfg  = wp_parse_args( get_option( 'sis_api_config', array() ), SIS_Activator::config_apis_por_defecto() );
		$fdsn = wp_parse_args( isset( $cfg['usgs_fdsn'] ) ? $cfg['usgs_fdsn'] : array(), SIS_Activator::config_apis_por_defecto()['usgs_fdsn'] );
		$feed = wp_parse_args( isset( $cfg['usgs_feed'] ) ? $cfg['usgs_feed'] : array(), SIS_Activator::config_apis_por_defecto()['usgs_feed'] );

		$this->cabecera( __( 'Fuentes de datos', 'sismos-narino' ) );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sis_guardar_fuentes">
			<?php wp_nonce_field( 'sis_fuentes' ); ?>

			<h2><?php esc_html_e( 'USGS — FDSN Event (catálogo histórico)', 'sismos-narino' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Activa', 'sismos-narino' ); ?></th>
					<td><label><input type="checkbox" name="fdsn_activa" value="1" <?php checked( ! empty( $fdsn['activa'] ) ); ?>> <?php esc_html_e( 'Sincronizar por cron cada 12 horas', 'sismos-narino' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="fdsn_url"><?php esc_html_e( 'Punto final', 'sismos-narino' ); ?></label></th>
					<td>
						<input type="url" class="regular-text code" id="fdsn_url" name="fdsn_url" value="<?php echo esc_attr( $fdsn['url'] ); ?>">
						<p class="description"><?php esc_html_e( 'Solo se admiten servidores de la lista blanca del plugin (USGS, IRIS, SGC).', 'sismos-narino' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ámbitos a sincronizar', 'sismos-narino' ); ?></th>
					<td>
						<?php foreach ( SIS_Regiones::lista() as $a ) : ?>
							<label style="display:block;margin-bottom:4px">
								<input type="checkbox" name="fdsn_ambitos[]" value="<?php echo esc_attr( $a['slug'] ); ?>"
									<?php checked( in_array( $a['slug'], (array) $fdsn['ambitos'], true ) ); ?>>
								<strong><?php echo esc_html( $a['nombre'] ); ?></strong>
								<span class="description">— <?php echo esc_html( $a['descripcion'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fdsn_anios"><?php esc_html_e( 'Años de historia', 'sismos-narino' ); ?></label></th>
					<td>
						<input type="number" min="1" max="60" id="fdsn_anios" name="fdsn_anios" value="<?php echo esc_attr( (int) $fdsn['anios'] ); ?>">
						<p class="description"><?php esc_html_e( 'Cuanta más historia, más estable es el ajuste de Gutenberg-Richter. 36 años es un buen punto de partida.', 'sismos-narino' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fdsn_min_mag"><?php esc_html_e( 'Magnitud mínima', 'sismos-narino' ); ?></label></th>
					<td>
						<input type="number" step="0.1" min="0" max="9" id="fdsn_min_mag" name="fdsn_min_mag" value="<?php echo esc_attr( $fdsn['min_mag'] ); ?>">
						<p class="description"><?php esc_html_e( 'Pida por debajo de la magnitud de completitud esperada: el propio plugin estima Mc con los datos descargados.', 'sismos-narino' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fdsn_ttl"><?php esc_html_e( 'Vida de la caché (minutos)', 'sismos-narino' ); ?></label></th>
					<td><input type="number" min="10" max="10080" id="fdsn_ttl" name="fdsn_ttl" value="<?php echo esc_attr( (int) $fdsn['ttl'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Verificar TLS', 'sismos-narino' ); ?></th>
					<td><label><input type="checkbox" name="fdsn_ssl" value="1" <?php checked( ! empty( $fdsn['sslverify'] ) ); ?>> <?php esc_html_e( 'Sí (recomendado)', 'sismos-narino' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Probar', 'sismos-narino' ); ?></th>
					<td>
						<button type="button" class="button" data-sis-accion="probar"><?php esc_html_e( 'Contar sismos disponibles', 'sismos-narino' ); ?></button>
						<span class="sis-admin-estado" aria-live="polite"></span>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'USGS — Feed GeoJSON (sismicidad reciente)', 'sismos-narino' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Activa', 'sismos-narino' ); ?></th>
					<td><label><input type="checkbox" name="feed_activa" value="1" <?php checked( ! empty( $feed['activa'] ) ); ?>> <?php esc_html_e( 'Refrescar por cron cada hora (el navegador consulta además cada 2 minutos)', 'sismos-narino' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="feed_dataset"><?php esc_html_e( 'Feed', 'sismos-narino' ); ?></label></th>
					<td>
						<select id="feed_dataset" name="feed_dataset">
							<?php foreach ( SIS_Sync_Feed::feeds() as $slug => $nombre ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $feed['dataset_id'], $slug ); ?>><?php echo esc_html( $nombre ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="feed_ttl"><?php esc_html_e( 'Vida de la caché (minutos)', 'sismos-narino' ); ?></label></th>
					<td><input type="number" min="1" max="1440" id="feed_ttl" name="feed_ttl" value="<?php echo esc_attr( (int) $feed['ttl'] ); ?>"></td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	/**
	 * Guarda la configuración de fuentes.
	 */
	public function guardar_fuentes() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'sismos-narino' ) );
		}
		check_admin_referer( 'sis_fuentes' );

		$cfg = wp_parse_args( get_option( 'sis_api_config', array() ), SIS_Activator::config_apis_por_defecto() );

		$url = isset( $_POST['fdsn_url'] ) ? esc_url_raw( wp_unslash( $_POST['fdsn_url'] ) ) : '';
		if ( ! SIS_Security::url_permitida( $url ) ) {
			$url = SIS_Sync_Usgs::URL; // fuera de la lista blanca: se ignora.
		}

		$ambitos = isset( $_POST['fdsn_ambitos'] ) ? (array) wp_unslash( $_POST['fdsn_ambitos'] ) : array();
		$ambitos = array_values( array_unique( array_map( array( SIS_Security::class, 'sanitizar_ambito' ), $ambitos ) ) );
		if ( empty( $ambitos ) ) {
			$ambitos = array( SIS_Regiones::por_defecto() );
		}

		$cfg['usgs_fdsn']['activa']    = ! empty( $_POST['fdsn_activa'] );
		$cfg['usgs_fdsn']['url']       = $url;
		$cfg['usgs_fdsn']['ambitos']   = $ambitos;
		$cfg['usgs_fdsn']['anios']     = isset( $_POST['fdsn_anios'] ) ? max( 1, min( 60, (int) $_POST['fdsn_anios'] ) ) : 36;
		$cfg['usgs_fdsn']['min_mag']   = SIS_Security::sanitizar_magnitud( isset( $_POST['fdsn_min_mag'] ) ? wp_unslash( $_POST['fdsn_min_mag'] ) : 2.5, 2.5 );
		$cfg['usgs_fdsn']['ttl']       = isset( $_POST['fdsn_ttl'] ) ? max( 10, min( 10080, (int) $_POST['fdsn_ttl'] ) ) : 720;
		$cfg['usgs_fdsn']['sslverify'] = ! empty( $_POST['fdsn_ssl'] );

		$feeds   = SIS_Sync_Feed::feeds();
		$dataset = isset( $_POST['feed_dataset'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_dataset'] ) ) : 'all_day';
		$dataset = isset( $feeds[ $dataset ] ) ? $dataset : 'all_day';

		$cfg['usgs_feed']['activa']     = ! empty( $_POST['feed_activa'] );
		$cfg['usgs_feed']['dataset_id'] = $dataset;
		$cfg['usgs_feed']['url']        = SIS_Sync_Feed::url( $dataset );
		$cfg['usgs_feed']['ttl']        = isset( $_POST['feed_ttl'] ) ? max( 1, min( 1440, (int) $_POST['feed_ttl'] ) ) : 10;

		update_option( 'sis_api_config', $cfg );
		SIS_Cache::delete_grupo( 'pronostico' );
		SIS_Sync::auditar( 'config', 'fuentes', 'ok', 0, 'Configuración de fuentes actualizada' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-fuentes&sis_ok=1' ) );
		exit;
	}

	/* ----------------------------------------------------------------- */
	/* Pantalla: Amenaza y normativa                                     */
	/* ----------------------------------------------------------------- */

	/**
	 * Referencia normativa publicada por los componentes de amenaza.
	 *
	 * Aquí NO hay parámetros de pronóstico: el plugin no estima sismos
	 * futuros. Lo que se edita son las cifras de la norma vigente y las notas
	 * de vigencia, que deben verificarse contra el texto oficial de la NSR-10
	 * y actualizarse cuando cambie.
	 */
	public function pantalla_amenaza() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$n = SIS_Amenaza::normativa();

		$this->cabecera( __( 'Amenaza y normativa', 'sismos-narino' ) );
		?>
		<div class="notice notice-warning inline" style="max-width:840px">
			<p><strong><?php esc_html_e( 'Verifique antes de publicar.', 'sismos-narino' ); ?></strong>
			<?php esc_html_e( 'Los coeficientes deben tomarse de la Tabla A.2.3-2 y del Apéndice A-4 de la NSR-10, o del sistema de consulta de amenaza del SGC. Si se adopta por decreto la actualización AIS 100-24, actualice estos valores y la nota de vigencia.', 'sismos-narino' ); ?></p>
		</div>

		<p class="description" style="max-width:840px">
			<?php esc_html_e( 'Estos textos alimentan el shortcode [sismos_amenaza] y la ruta REST /amenaza. La amenaza probabilística oficial no se calcula aquí: se consulta en el Modelo Nacional de Amenaza Sísmica del SGC, al que la plataforma enlaza.', 'sismos-narino' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sis_guardar_amenaza">
			<?php wp_nonce_field( 'sis_amenaza' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="a_norma"><?php esc_html_e( 'Norma vigente', 'sismos-narino' ); ?></label></th>
					<td><input type="text" class="large-text" id="a_norma" name="a_norma" value="<?php echo esc_attr( $n['norma'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="a_zona_pasto"><?php esc_html_e( 'Zona de amenaza de Pasto', 'sismos-narino' ); ?></label></th>
					<td><input type="text" class="regular-text" id="a_zona_pasto" name="a_zona_pasto" value="<?php echo esc_attr( $n['zona_pasto'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="a_aa_pasto"><?php esc_html_e( 'Aa / Av en Pasto', 'sismos-narino' ); ?></label></th>
					<td>
						<input type="text" class="small-text" id="a_aa_pasto" name="a_aa_pasto" value="<?php echo esc_attr( $n['aa_pasto'] ); ?>">
						<input type="text" class="small-text" id="a_av_pasto" name="a_av_pasto" value="<?php echo esc_attr( $n['av_pasto'] ); ?>">
						<p class="description"><?php esc_html_e( 'Aceleración pico efectiva de diseño y coeficiente de velocidad, para 10 % de probabilidad de excedencia en 50 años.', 'sismos-narino' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="a_aa_pacifico"><?php esc_html_e( 'Aa / Av máximos en el litoral', 'sismos-narino' ); ?></label></th>
					<td>
						<input type="text" class="small-text" id="a_aa_pacifico" name="a_aa_pacifico" value="<?php echo esc_attr( $n['aa_pacifico'] ); ?>">
						<input type="text" class="small-text" id="a_av_pacifico" name="a_av_pacifico" value="<?php echo esc_attr( $n['av_pacifico'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="a_vigencia"><?php esc_html_e( 'Nota de vigencia', 'sismos-narino' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="a_vigencia" name="a_vigencia"><?php echo esc_textarea( $n['vigencia'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="a_microzonificacion"><?php esc_html_e( 'Microzonificación', 'sismos-narino' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="a_microzonificacion" name="a_microzonificacion"><?php echo esc_textarea( $n['microzonificacion'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="a_nota"><?php esc_html_e( 'Nota metodológica', 'sismos-narino' ); ?></label></th>
					<td><textarea class="large-text" rows="3" id="a_nota" name="a_nota"><?php echo esc_textarea( $n['nota'] ); ?></textarea></td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Descargo institucional publicado', 'sismos-narino' ); ?></h2>
		<p class="description" style="max-width:840px"><?php echo esc_html( SIS_Amenaza::descargo() ); ?></p>

		<h2><?php esc_html_e( 'Fuentes oficiales enlazadas', 'sismos-narino' ); ?></h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr>
				<th><?php esc_html_e( 'Entidad', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Recurso', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Enlace', 'sismos-narino' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( SIS_Amenaza::fuentes_oficiales() as $f ) : ?>
				<tr>
					<td><?php echo esc_html( $f['entidad'] ); ?></td>
					<td><?php echo esc_html( $f['nombre'] ); ?><p class="description"><?php echo esc_html( $f['descripcion'] ); ?></p></td>
					<td><a href="<?php echo esc_url( $f['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $f['url'] ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php
	}

	/**
	 * Guarda la referencia normativa.
	 */
	public function guardar_amenaza() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'sismos-narino' ) );
		}
		check_admin_referer( 'sis_amenaza' );

		$post = wp_unslash( $_POST );
		$n    = SIS_Amenaza::normativa_por_defecto();

		foreach ( array_keys( $n ) as $clave ) {
			if ( isset( $post[ 'a_' . $clave ] ) ) {
				$n[ $clave ] = sanitize_text_field( $post[ 'a_' . $clave ] );
			}
		}

		update_option( 'sis_amenaza', $n );
		SIS_Sync::auditar( 'config', 'amenaza', 'ok', 0, 'Referencia normativa actualizada' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-amenaza&sis_ok=1' ) );
		exit;
	}

	/* ----------------------------------------------------------------- */
	/* Pantalla: Apariencia                                              */
	/* ----------------------------------------------------------------- */

	/**
	 * Variables de apariencia del front.
	 */
	public function pantalla_apariencia() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$e = SIS_Estilos::estilo();
		$this->cabecera( __( 'Apariencia', 'sismos-narino' ) );
		?>
		<p class="description" style="max-width:800px">
			<?php esc_html_e( 'Por defecto los componentes son transparentes y sin bordes, para fundirse con el tema del sitio. Estos valores pueden sobrescribirse por shortcode con los atributos fondo, acento, borde, sombra, ancho, espaciado y radio.', 'sismos-narino' ); ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sis_guardar_estilo">
			<?php wp_nonce_field( 'sis_estilo' ); ?>

			<table class="form-table" role="presentation">
				<?php
				$campos = array(
					'fondo'          => __( 'Fondo', 'sismos-narino' ),
					'texto'          => __( 'Color de texto', 'sismos-narino' ),
					'tipografia'     => __( 'Tipografía', 'sismos-narino' ),
					'acento'         => __( 'Acento principal', 'sismos-narino' ),
					'acento_2'       => __( 'Acento secundario', 'sismos-narino' ),
					'acento_tecnico' => __( 'Acento técnico', 'sismos-narino' ),
					'mute'           => __( 'Texto secundario', 'sismos-narino' ),
					'borde'          => __( 'Grosor del borde', 'sismos-narino' ),
					'borde_color'    => __( 'Color del borde', 'sismos-narino' ),
					'borde_radio'    => __( 'Radio de esquina', 'sismos-narino' ),
					'sombra'         => __( 'Sombra', 'sismos-narino' ),
					'ancho_max'      => __( 'Ancho máximo', 'sismos-narino' ),
					'espaciado'      => __( 'Espaciado interno', 'sismos-narino' ),
				);
				foreach ( $campos as $clave => $etiqueta ) :
					?>
					<tr>
						<th scope="row"><label for="e_<?php echo esc_attr( $clave ); ?>"><?php echo esc_html( $etiqueta ); ?></label></th>
						<td><input type="text" class="regular-text code" id="e_<?php echo esc_attr( $clave ); ?>" name="e_<?php echo esc_attr( $clave ); ?>" value="<?php echo esc_attr( $e[ $clave ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>
		</div>
		<?php
	}

	/**
	 * Guarda la apariencia.
	 */
	public function guardar_estilo() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sin permisos.', 'sismos-narino' ) );
		}
		check_admin_referer( 'sis_estilo' );

		$post = wp_unslash( $_POST );
		$e    = SIS_Activator::estilo_por_defecto();
		foreach ( array_keys( $e ) as $clave ) {
			if ( isset( $post[ 'e_' . $clave ] ) ) {
				$e[ $clave ] = SIS_Estilos::sanitizar_css( $post[ 'e_' . $clave ] );
			}
		}

		update_option( 'sis_estilo', $e );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG . '-apariencia&sis_ok=1' ) );
		exit;
	}

	/* ----------------------------------------------------------------- */
	/* Pantalla: Elementos                                               */
	/* ----------------------------------------------------------------- */

	/**
	 * Pestañas del catálogo de elementos.
	 *
	 * @return array<string,string>
	 */
	private static function pestanas() {
		return array(
			'graficas'   => __( 'Gráficas', 'sismos-narino' ),
			'historicas' => __( 'Visualizaciones históricas', 'sismos-narino' ),
			'globo'      => __( 'Globo y mapa', 'sismos-narino' ),
			'texto'      => __( 'Información', 'sismos-narino' ),
		);
	}

	/**
	 * Pestaña a la que pertenece cada vista del motor de gráficos.
	 *
	 * Es información de presentación, no del dominio: por eso vive aquí y no
	 * en el catálogo de vistas. Lo que no esté listado cae en «Gráficas».
	 *
	 * @return array<string,string>
	 */
	private static function pestana_vistas() {
		return array(
			'sismos_anuales'        => 'historicas',
			'historico_mensual'     => 'historicas',
			'acumulado'             => 'historicas',
			'energia_acumulada'     => 'historicas',
			'calendario_sismico'    => 'historicas',
			'recurrencia_historica' => 'historicas',
			'mayores_sismos'        => 'historicas',
			'intervalos'            => 'historicas',
		);
	}

	/**
	 * Catálogo de shortcodes, en pestañas y con una tarjeta por gráfica.
	 */
	public function pantalla_elementos() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$pestanas = self::pestanas();
		$actual   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'graficas'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $pestanas[ $actual ] ) ) {
			$actual = 'graficas';
		}

		$ruta      = SIS_DIR . 'includes/data/textos-elementos.php';
		$elementos = is_readable( $ruta ) ? include $ruta : array();
		$elementos = array_values( array_filter( $elementos, static function ( $el ) use ( $actual ) {
			return isset( $el['grupo'] ) && $actual === $el['grupo'];
		} ) );

		$this->cabecera( __( 'Elementos publicables', 'sismos-narino' ) );
		?>
		<p class="description sis-intro"><?php esc_html_e( 'Copie cualquier shortcode y péguelo en una página, entrada o widget. Cada gráfica trae además sus tres textos —descripción, interpretación y cifras— como shortcodes independientes, para maquetarlos donde convenga.', 'sismos-narino' ); ?></p>

		<details class="sis-ayuda">
			<summary><?php esc_html_e( 'Cómo filtrar territorio y periodo en cualquier componente', 'sismos-narino' ); ?></summary>
			<div class="sis-ayuda__cuerpo">
				<p><?php esc_html_e( 'Todos los componentes que consultan el catálogo aceptan los mismos cinco atributos. El texto que acompaña a la gráfica —descripción, interpretación y cifras— se recalcula con el filtro y dice en su primera línea qué territorio y qué periodo se está mirando.', 'sismos-narino' ); ?></p>
				<table class="widefat striped">
					<thead><tr>
						<th style="width:9em"><?php esc_html_e( 'Atributo', 'sismos-narino' ); ?></th>
						<th><?php esc_html_e( 'Qué hace', 'sismos-narino' ); ?></th>
						<th style="width:12em"><?php esc_html_e( 'Ejemplo', 'sismos-narino' ); ?></th>
					</tr></thead>
					<tbody>
						<tr><td><code>ambito</code></td><td><?php esc_html_e( 'Territorio: narino (solo el departamento), regional (Nariño y la zona de subducción vecina), radio (300 km alrededor de Pasto) o colombia.', 'sismos-narino' ); ?></td><td><code>ambito="narino"</code></td></tr>
						<tr><td><code>dias</code></td><td><?php esc_html_e( 'Ventana móvil: los últimos N días contados desde hoy.', 'sismos-narino' ); ?></td><td><code>dias="30"</code></td></tr>
						<tr><td><code>anios</code></td><td><?php esc_html_e( 'Ventana móvil larga: los últimos N años contados desde hoy.', 'sismos-narino' ); ?></td><td><code>anios="5"</code></td></tr>
						<tr><td><code>anio</code></td><td><?php esc_html_e( 'Año de calendario completo, del 1 de enero al 31 de diciembre.', 'sismos-narino' ); ?></td><td><code>anio="2026"</code></td></tr>
						<tr><td><code>mes</code></td><td><?php esc_html_e( 'Mes de calendario (1 a 12). Sin «anio» se entiende el mes del año en curso.', 'sismos-narino' ); ?></td><td><code>anio="2016" mes="4"</code></td></tr>
					</tbody>
				</table>
				<p><strong><?php esc_html_e( 'Si combina varios:', 'sismos-narino' ); ?></strong> <?php esc_html_e( 'una fecha de calendario manda sobre una ventana móvil, porque quien escribe anio="2026" pide ese año y no «los últimos N». Entre días y años gana «dias», que es la más específica. Los atributos que quedan descartados no se aplican ni aparecen en la página.', 'sismos-narino' ); ?></p>
				<p><strong><?php esc_html_e( 'Con ambito="narino":', 'sismos-narino' ); ?></strong> <?php esc_html_e( 'todo lo publicado —epicentros, cifras y textos— queda dentro del recuadro del departamento. Como allí el catálogo global registra unos pocos sismos al año, una ventana corta puede salir vacía; el componente lo explica en lugar de dejar un hueco.', 'sismos-narino' ); ?></p>
			</div>
		</details>

		<nav class="nav-tab-wrapper sis-tabs" aria-label="<?php esc_attr_e( 'Tipos de elemento', 'sismos-narino' ); ?>">
			<?php foreach ( $pestanas as $slug => $etiqueta ) : ?>
				<a class="nav-tab<?php echo $slug === $actual ? ' nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-elementos&tab=' . $slug ) ); ?>"
					<?php echo $slug === $actual ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $etiqueta ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( $elementos ) : ?>
			<h2><?php esc_html_e( 'Componentes', 'sismos-narino' ); ?></h2>
			<div class="sis-tabla-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Componentes publicables', 'sismos-narino' ); ?>">
			<table class="widefat striped">
				<thead><tr>
					<th style="width:22%"><?php esc_html_e( 'Componente', 'sismos-narino' ); ?></th>
					<th><?php esc_html_e( 'Qué publica', 'sismos-narino' ); ?></th>
					<th style="width:28%"><?php esc_html_e( 'Ejemplo', 'sismos-narino' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $elementos as $el ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $el['titulo'] ); ?></strong><br>
							<code><?php echo esc_html( $el['shortcode'] ); ?></code>
						</td>
						<td>
							<?php echo esc_html( $el['que_hace'] ); ?>
							<p class="description"><strong><?php esc_html_e( 'Atributos:', 'sismos-narino' ); ?></strong> <?php echo esc_html( $el['atributos'] ); ?></p>
						</td>
						<td>
							<code class="sis-admin-ejemplo"><?php echo esc_html( $el['ejemplo'] ); ?></code><br>
							<button type="button" class="button button-small" data-sis-copiar="<?php echo esc_attr( $el['ejemplo'] ); ?>"><?php esc_html_e( 'Copiar', 'sismos-narino' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

		<?php
		// Las tarjetas de vistas solo tienen sentido donde hay gráficas.
		if ( in_array( $actual, array( 'graficas', 'historicas' ), true ) ) {
			$mapa   = self::pestana_vistas();
			$vistas = array_values( array_filter( SIS_Views::lista(), static function ( $v ) use ( $mapa, $actual ) {
				$destino = isset( $mapa[ $v['id'] ] ) ? $mapa[ $v['id'] ] : 'graficas';
				return $destino === $actual;
			} ) );
			$this->tarjetas_vistas( $vistas, $actual );
		}
		?>
		</div>
		<?php
	}

	/**
	 * Atributos de consulta que traen por defecto las tarjetas de cada pestaña.
	 *
	 * Son el punto de partida que se copia y pega, no un límite: quien maqueta
	 * los cambia en la página. «Gráficas» mira lo reciente dentro del
	 * departamento; «Visualizaciones históricas», la perspectiva larga del
	 * mismo territorio.
	 *
	 * @param string $tab Pestaña activa.
	 * @return array<string,string> atributo → valor.
	 */
	private static function consulta_por_defecto( $tab ) {
		if ( 'historicas' === $tab ) {
			return array( 'ambito' => 'narino', 'anios' => '8' );
		}
		return array( 'ambito' => 'narino', 'dias' => '15' );
	}

	/**
	 * Los atributos anteriores, ya escritos para pegar en un shortcode.
	 *
	 * @param string $tab Pestaña activa.
	 * @return string
	 */
	private static function atributos_por_defecto( $tab ) {
		$out = '';
		foreach ( self::consulta_por_defecto( $tab ) as $k => $v ) {
			$out .= sprintf( ' %s="%s"', $k, $v );
		}
		return $out;
	}

	/**
	 * Rejilla de tarjetas: una por vista, con sus cuatro shortcodes.
	 *
	 * Cada gráfica se publica con tres textos que la acompañan —qué es, cómo
	 * se lee y qué dicen las cifras—. Tenerlos como shortcodes aparte permite
	 * maquetarlos donde convenga; la tarjeta los deja listos para copiar para
	 * que nadie tenga que recordar la sintaxis.
	 *
	 * @param array[] $vistas Vistas del motor.
	 */
	private function tarjetas_vistas( array $vistas, $tab = 'graficas' ) {
		if ( ! $vistas ) {
			return;
		}
		$tipos  = SIS_Views::tipos();
		$filtro = self::atributos_por_defecto( $tab );
		$consulta = self::consulta_por_defecto( $tab );
		?>
		<h2><?php esc_html_e( 'Gráficas disponibles', 'sismos-narino' ); ?></h2>
		<p class="description sis-intro"><?php esc_html_e( 'Una tarjeta por gráfica. El primer shortcode publica la gráfica; los tres siguientes publican sus textos por separado. Si prefiere gráfica y textos juntos, use el último.', 'sismos-narino' ); ?></p>
		<p class="description sis-intro">
			<strong><?php esc_html_e( 'Filtro de partida:', 'sismos-narino' ); ?></strong>
			<code><?php echo esc_html( trim( $filtro ) ); ?></code> —
			<?php
			echo esc_html(
				'historicas' === $tab
					? __( 'la perspectiva larga del departamento. Cámbielo en la página con anios, anio, mes o dias.', 'sismos-narino' )
					: __( 'lo ocurrido dentro del departamento en los últimos quince días. Cámbielo en la página con dias, anios, anio o mes.', 'sismos-narino' )
			);
			?>
		</p>
		<?php if ( 'narino' === $consulta['ambito'] ) : ?>
			<p class="description sis-intro sis-nota">
				<?php esc_html_e( 'Dentro del recuadro estricto del departamento el catálogo global registra unos pocos sismos al año, así que una ventana corta puede salir vacía. No es un fallo: el componente lo explica y sugiere ampliar el ámbito a «regional», que es el dominio que gobierna la amenaza de Nariño.', 'sismos-narino' ); ?>
			</p>
		<?php endif; ?>

		<div class="sis-cards">
			<?php foreach ( $vistas as $v ) : ?>
				<?php
				$id       = $v['id'];
				$tipo     = $v['default'];
				$etiqueta = isset( $tipos[ $tipo ]['label'] ) ? $tipos[ $tipo ]['label'] : $tipo;

				$lineas = array(
					__( 'Gráfica', 'sismos-narino' )     => sprintf( '[sismos_grafico view="%s" type="%s"%s]', $id, $tipo, $filtro ),
					__( 'Descripción', 'sismos-narino' ) => sprintf( '[sismos_descripcion view="%s"%s]', $id, $filtro ),
					__( 'Cualitativo', 'sismos-narino' ) => sprintf( '[sismos_analisis_cualitativo view="%s"%s]', $id, $filtro ),
					__( 'Cuantitativo', 'sismos-narino' ) => sprintf( '[sismos_analisis_cuantitativo view="%s"%s]', $id, $filtro ),
					__( 'Todo junto', 'sismos-narino' )  => sprintf( '[sismos_grafico view="%s" analisis="ambos"%s]', $id, $filtro ),
				);
				?>
				<article class="sis-card">
					<div class="sis-card__cab">
						<h3 class="sis-card__titulo"><?php echo esc_html( $v['name'] ); ?></h3>
						<span class="sis-card__etq sis-card__etq--tipo"><?php echo esc_html( $etiqueta ); ?></span>
					</div>
					<p class="sis-card__id"><code>view="<?php echo esc_html( $id ); ?>"</code></p>
					<p class="sis-card__desc"><?php echo esc_html( $v['description'] ); ?></p>

					<?php foreach ( $lineas as $etq => $sc ) : ?>
						<div class="sis-sc">
							<span class="sis-sc__etq"><?php echo esc_html( $etq ); ?></span>
							<code class="sis-sc__code"><?php echo esc_html( $sc ); ?></code>
							<button type="button" class="button button-small" data-sis-copiar="<?php echo esc_attr( $sc ); ?>"
								aria-label="<?php echo esc_attr( sprintf( __( 'Copiar el shortcode de %1$s para %2$s', 'sismos-narino' ), $etq, $v['name'] ) ); ?>"><?php esc_html_e( 'Copiar', 'sismos-narino' ); ?></button>
						</div>
					<?php endforeach; ?>

					<p class="sis-card__pie">
						<strong><?php esc_html_e( 'Otros tipos:', 'sismos-narino' ); ?></strong>
						<?php echo esc_html( implode( ', ', array_map( static function ( $t ) use ( $tipos ) {
							return isset( $tipos[ $t ]['label'] ) ? $tipos[ $t ]['label'] : $t;
						}, $v['compatibles'] ) ) ); ?>
					</p>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/* ----------------------------------------------------------------- */
	/* Acciones AJAX                                                     */
	/* ----------------------------------------------------------------- */

	/**
	 * Sincroniza una fuente bajo demanda.
	 */
	public function ajax_sincronizar() {
		check_ajax_referer( 'sis_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'mensaje' => __( 'Sin permisos.', 'sismos-narino' ) ), 403 );
		}

		$fuente = isset( $_POST['fuente'] ) ? sanitize_key( wp_unslash( $_POST['fuente'] ) ) : 'usgs_fdsn';
		$sync   = new SIS_Sync();
		$r      = $sync->ejecutar_fuente( $fuente );

		wp_send_json_success( array(
			'mensaje' => sprintf(
				/* translators: 1: resultado, 2: registros, 3: milisegundos */
				__( '%1$s · %2$s registros · %3$s ms', 'sismos-narino' ),
				$r['ok'] ? 'OK' : 'ERROR',
				number_format_i18n( $r['registros'] ),
				number_format_i18n( $r['latencia_ms'] )
			),
			'detalle' => $r['mensaje'],
		) );
	}

	/**
	 * Cuenta los sismos que devolvería la configuración vigente.
	 */
	public function ajax_probar() {
		check_ajax_referer( 'sis_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'mensaje' => __( 'Sin permisos.', 'sismos-narino' ) ), 403 );
		}

		$cfg = get_option( 'sis_api_config', array() );
		$fd  = isset( $cfg['usgs_fdsn'] ) ? $cfg['usgs_fdsn'] : array();
		$r   = SIS_Sync_Usgs::contar( $fd, SIS_Regiones::por_defecto() );

		wp_send_json_success( array( 'mensaje' => $r['mensaje'] ) );
	}
}
