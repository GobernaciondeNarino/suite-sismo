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
	 * Catálogo de shortcodes con botón de copiar.
	 */
	public function pantalla_elementos() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$ruta      = SIS_DIR . 'includes/data/textos-elementos.php';
		$elementos = is_readable( $ruta ) ? include $ruta : array();

		$this->cabecera( __( 'Elementos publicables', 'sismos-narino' ) );
		?>
		<p class="description"><?php esc_html_e( 'Copie cualquiera de estos shortcodes y péguelo en una página, entrada o widget.', 'sismos-narino' ); ?></p>

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

		<h2><?php esc_html_e( 'Vistas disponibles para [sismos_grafico]', 'sismos-narino' ); ?></h2>
		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'view', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Nombre', 'sismos-narino' ); ?></th>
				<th><?php esc_html_e( 'Tipos compatibles', 'sismos-narino' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( SIS_Views::lista() as $v ) : ?>
				<tr>
					<td><code><?php echo esc_html( $v['id'] ); ?></code></td>
					<td><?php echo esc_html( $v['name'] ); ?><p class="description"><?php echo esc_html( $v['description'] ); ?></p></td>
					<td><?php echo esc_html( implode( ', ', $v['compatibles'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
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
