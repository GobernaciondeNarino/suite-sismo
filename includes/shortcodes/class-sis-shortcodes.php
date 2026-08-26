<?php
/**
 * Registro y render de los shortcodes del plugin.
 *
 * Contrato común de todos los componentes: el PHP emite solo un esqueleto con
 * atributos data-* (HTML cacheable, sin datos embebidos), el JS lo hidrata
 * pidiendo la REST interna, y mientras tanto se muestra un skeleton. Si algo
 * falla, aparece un error elegante con botón de reintento. Al pie, siempre, la
 * atribución de la fuente.
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Shortcodes {

	/** @var int Contador para ids únicos. */
	private static $contador = 0;

	/** @var bool El importmap de Three.js solo puede imprimirse una vez por documento. */
	private static $importmap_impreso = false;

	/**
	 * Versiones de las librerías que el plugin sirve desde su propia carpeta.
	 *
	 * Se guardan aquí para que el número que ve el navegador en el parámetro
	 * «ver» —y por tanto la caché— cambie al actualizar el archivo.
	 */
	const D3PLUS_VERSION = '4.3.0';
	const THREE_VERSION  = '0.160.0';
	const LEAFLET_VERSION = '1.9.4';

	/**
	 * Archivos de Three.js que el importmap resuelve, relativos a assets/vendor/.
	 *
	 * Se precargan con `modulepreload` para que el navegador los pida en cuanto
	 * ve la página, sin esperar a que el módulo del globo los importe.
	 */
	const THREE_MODULOS = array(
		'three'         => 'three.module.min.js',
		'three/addons/' => 'three-addons/',
	);

	/**
	 * Textura fotográfica opcional del planeta (1,4 MB).
	 *
	 * Es lo único que el globo puede pedir fuera del sitio, y solo si alguien
	 * lo activa a propósito con textura="si". Por defecto no se carga.
	 */
	const TEXTURA_PLANETA = 'https://cdn.jsdelivr.net/npm/three-globe@2.31.1/example/img/earth-blue-marble.jpg';

	/** Atribución por defecto al pie de cada componente. */
	const FUENTE = 'U.S. Geological Survey — Earthquake Hazards Program (dominio público) · Gráficos: D3plus';

	public function __construct() {
		add_action( 'init', array( $this, 'registrar_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ), 10 );
		add_filter( 'script_loader_tag', array( $this, 'marcar_modulo' ), 9, 3 );
	}

	/**
	 * Da de alta todos los shortcodes.
	 */
	public function registrar_shortcodes() {
		// Motor de gráficos.
		add_shortcode( 'sismos_grafico', array( $this, 'sc_grafico' ) );
		add_shortcode( 'sismos_filtro', array( $this, 'sc_filtro' ) );
		add_shortcode( 'sismos_panel', array( $this, 'sc_panel' ) );

		// Piezas de texto de una vista (para maquetar por separado).
		add_shortcode( 'sismos_analisis', array( $this, 'sc_analisis' ) );
		add_shortcode( 'sismos_descripcion', array( $this, 'sc_descripcion' ) );
		add_shortcode( 'sismos_explicacion', array( $this, 'sc_explicacion' ) );
		add_shortcode( 'sismos_analisis_cualitativo', array( $this, 'sc_analisis_cualitativo' ) );
		add_shortcode( 'sismos_analisis_cuantitativo', array( $this, 'sc_analisis_cuantitativo' ) );

		// Componentes de dominio.
		add_shortcode( 'sismos_estado', array( $this, 'sc_estado' ) );
		add_shortcode( 'sismos_ultimos', array( $this, 'sc_ultimos' ) );
		add_shortcode( 'sismos_mapa', array( $this, 'sc_mapa' ) );
		add_shortcode( 'sismos_historico', array( $this, 'sc_historico' ) );
		add_shortcode( 'sismos_globo', array( $this, 'sc_globo' ) );
		add_shortcode( 'sismos_timeline', array( $this, 'sc_timeline' ) );
		add_shortcode( 'sismos_estadistica', array( $this, 'sc_estadistica' ) );
		add_shortcode( 'sismos_datos', array( $this, 'sc_datos' ) );
		add_shortcode( 'sismos_estado_api', array( $this, 'sc_estado_api' ) );

		// Amenaza y preparación (contenido oficial, sin pronósticos).
		add_shortcode( 'sismos_amenaza', array( $this, 'sc_amenaza' ) );
		add_shortcode( 'sismos_glosario', array( $this, 'sc_glosario' ) );
		add_shortcode( 'sismos_preparacion', array( $this, 'sc_preparacion' ) );
		add_shortcode( 'sismos_replicas', array( $this, 'sc_replicas' ) );
		add_shortcode( 'sismos_desinformacion', array( $this, 'sc_desinformacion' ) );
		add_shortcode( 'sismos_fuentes_oficiales', array( $this, 'sc_fuentes_oficiales' ) );
	}

	/**
	 * Registra (sin encolar) librerías CDN y scripts del plugin.
	 */
	public function registrar_assets() {
		/*
		 * Las librerías se sirven desde el propio plugin, no desde una CDN.
		 *
		 * Con CDN, cualquier bloqueador de anuncios, extensión de privacidad o
		 * proxy corporativo que se interponga deja la página sin gráficos: la
		 * huella SRI —que es lo correcto frente a un CDN comprometido— convierte
		 * esa interferencia en un bloqueo total. En un portal público eso no es
		 * aceptable. Servirlas desde aquí además evita que el navegador de quien
		 * consulta el sitio haga peticiones a terceros.
		 */
		wp_register_script( 'd3plus', SIS_URL . 'assets/vendor/d3plus.min.js', array(), self::D3PLUS_VERSION, true );

		wp_register_style( 'leaflet', SIS_URL . 'assets/vendor/leaflet.css', array(), self::LEAFLET_VERSION );
		wp_register_script( 'leaflet', SIS_URL . 'assets/vendor/leaflet.js', array(), self::LEAFLET_VERSION, true );

		// Núcleo JS del plugin.
		wp_register_script( 'sis-core', SIS_URL . 'assets/js/sis-core.js', array(), SIS_VERSION, true );
		wp_localize_script( 'sis-core', 'SIS', array(
			'rest'      => esc_url_raw( rest_url( self::rest_ns() ) ),
			'pluginUrl' => SIS_URL,
			'feed'      => SIS_Sync_Feed::BASE,
			'ambito'    => SIS_Regiones::por_defecto(),
			'ambitos'   => SIS_Regiones::lista(),
			'capasWms'  => SIS_Amenaza::capas_wms(),
		) );

		// Motor de gráficos (3 capas: shortcode → hidratador → renderer).
		wp_register_style( 'sis-grafico-css', SIS_URL . 'assets/css/grafico.css', array(), SIS_VERSION );
		wp_register_script( 'sis-renderer', SIS_URL . 'assets/js/renderer.js', array( 'd3plus' ), SIS_VERSION, true );
		wp_register_script( 'sis-grupo', SIS_URL . 'assets/js/grupo.js', array(), SIS_VERSION, true );
		wp_register_script( 'sis-grafico', SIS_URL . 'assets/js/grafico.js', array( 'sis-renderer', 'sis-core', 'sis-grupo' ), SIS_VERSION, true );
		wp_register_script( 'sis-composable', SIS_URL . 'assets/js/composable.js', array( 'sis-core', 'sis-grupo' ), SIS_VERSION, true );

		// Componentes de dominio.
		wp_register_script( 'sis-estado', SIS_URL . 'assets/js/estado.js', array( 'sis-core' ), SIS_VERSION, true );
		wp_register_script( 'sis-ultimos', SIS_URL . 'assets/js/ultimos.js', array( 'sis-core' ), SIS_VERSION, true );
		wp_register_script( 'sis-mapa', SIS_URL . 'assets/js/mapa.js', array( 'leaflet', 'sis-core' ), SIS_VERSION, true );
		wp_register_script( 'sis-estadistica', SIS_URL . 'assets/js/estadistica.js', array( 'sis-core' ), SIS_VERSION, true );
		wp_register_script( 'sis-datos', SIS_URL . 'assets/js/datos.js', array( 'sis-core' ), SIS_VERSION, true );
		wp_register_script( 'sis-estado-api', SIS_URL . 'assets/js/estado-api.js', array( 'sis-core' ), SIS_VERSION, true );
		wp_register_script( 'sis-timeline', SIS_URL . 'assets/js/timeline.js', array( 'sis-core' ), SIS_VERSION, true );
		// El globo es un módulo ES: el importmap de Three.js se imprime aparte.
		wp_register_script( 'sis-globo', SIS_URL . 'assets/js/globo.js', array(), SIS_VERSION, true );
	}

	/**
	 * Convierte el script del globo en `<script type="module">`.
	 *
	 * @param string $tag    Etiqueta HTML generada por WordPress.
	 * @param string $handle Handle del script.
	 * @param string $src    URL del script.
	 * @return string
	 */
	public function marcar_modulo( $tag, $handle, $src ) {
		if ( 'sis-globo' !== $handle ) {
			return $tag;
		}
		return '<script type="module" src="' . esc_url( $src ) . '" id="sis-globo-js"></script>' . "\n";
	}

	/**
	 * Importmap de Three.js con precarga verificada por huella.
	 * Se imprime una sola vez por documento aunque haya varios globos.
	 *
	 * @return string
	 */
	private function importmap_three() {
		if ( self::$importmap_impreso ) {
			return '';
		}
		self::$importmap_impreso = true;

		$base = SIS_URL . 'assets/vendor/';
		$ver  = '?ver=' . rawurlencode( self::THREE_VERSION );

		// Se precarga solo el módulo principal: «three/addons/» es un prefijo
		// de resolución, no un archivo, y OrbitControls llega detrás de él.
		$html = '<link rel="modulepreload" href="'
			. esc_url( $base . self::THREE_MODULOS['three'] . $ver ) . '">' . "\n";

		$mapa = array(
			'imports' => array(
				'three'         => $base . self::THREE_MODULOS['three'] . $ver,
				'three/addons/' => $base . self::THREE_MODULOS['three/addons/'],
			),
		);

		$html .= '<script type="importmap">' . wp_json_encode( $mapa ) . '</script>' . "\n";
		return $html;
	}

	/**
	 * Namespace REST del plugin (evita repetir la constante).
	 *
	 * @return string
	 */
	private static function rest_ns() {
		return SIS_Rest::NS;
	}

	/* ----------------------------------------------------------------- */
	/* Utilidades comunes                                                */
	/* ----------------------------------------------------------------- */

	/**
	 * Id único por instancia de shortcode.
	 *
	 * @return string
	 */
	private function id() {
		self::$contador++;
		return 'sis-' . self::$contador . '-' . substr( md5( (string) wp_rand() ), 0, 6 );
	}

	/**
	 * Fusiona atributos con los de apariencia comunes.
	 *
	 * @param array  $def       Valores por defecto propios del shortcode.
	 * @param array  $atts      Atributos recibidos.
	 * @param string $shortcode Nombre del shortcode (para el filtro de WP).
	 * @return array
	 */
	private function fusionar( $def, $atts, $shortcode ) {
		$apariencia = array(
			'fondo'     => '',
			'texto'     => '',
			'acento'    => '',
			'acento2'   => '',
			'tecnico'   => '',
			'borde'     => '',
			'sombra'    => '',
			'ancho'     => '',
			'espaciado' => '',
			'radio'     => '',
		);
		return shortcode_atts( array_merge( $def, $apariencia ), $atts, $shortcode );
	}

	/**
	 * Atributos comunes de consulta de datos.
	 *
	 * @return array
	 */
	private function defaults_consulta() {
		return array(
			'ambito'  => SIS_Regiones::por_defecto(),
			'anios'   => '',
			'dias'    => '',
			'min_mag' => '',
		);
	}

	/**
	 * Esqueleto de carga.
	 *
	 * @param string $texto Mensaje.
	 * @return string
	 */
	private function skeleton( $texto ) {
		return '<p class="sis-skeleton" aria-live="polite">' . esc_html( $texto ) . '</p>';
	}

	/**
	 * Pie de atribución de fuentes.
	 *
	 * @param string $fuente Texto de la fuente.
	 * @return string
	 */
	private function pie_fuentes( $fuente = self::FUENTE ) {
		return '<p class="sis-fuentes">' . esc_html__( 'Fuente:', 'sismos-narino' ) . ' ' . esc_html( $fuente ) . '</p>';
	}

	/**
	 * Atributos data-* de consulta, ya saneados.
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string
	 */
	private function data_consulta( $atts ) {
		$ambito  = SIS_Security::sanitizar_ambito( isset( $atts['ambito'] ) ? $atts['ambito'] : '' );
		$anios   = ( isset( $atts['anios'] ) && '' !== $atts['anios'] ) ? max( 0, min( 60, (int) $atts['anios'] ) ) : '';
		$dias    = ( isset( $atts['dias'] ) && '' !== $atts['dias'] ) ? SIS_Security::sanitizar_dias( $atts['dias'], 30 ) : '';
		$min_mag = ( isset( $atts['min_mag'] ) && '' !== $atts['min_mag'] ) ? SIS_Security::sanitizar_magnitud( $atts['min_mag'] ) : '';

		return ' data-ambito="' . esc_attr( $ambito ) . '"'
			. ' data-anios="' . esc_attr( $anios ) . '"'
			. ' data-dias="' . esc_attr( $dias ) . '"'
			. ' data-min-mag="' . esc_attr( $min_mag ) . '"';
	}

	/* ----------------------------------------------------------------- */
	/* Motor de gráficos                                                 */
	/* ----------------------------------------------------------------- */

	/**
	 * [sismos_grafico] — tarjeta de gráfico D3plus con barra de herramientas.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_grafico( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array(
				'view'         => 'sismos_mensuales',
				'type'         => '',
				'actions'      => '',
				'theme'        => 'claro',
				'legend'       => 'si',
				'legend_style' => 'text',
				'legend_pos'   => 'abajo',
				'toolbar'      => 'si',
				'alto'         => '420px',
				'grupo'        => '',
				'analisis'     => 'no',
				'titulo'       => '',
			)
		), $atts, 'sismos_grafico' );

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'sis-grafico-css' );
		wp_enqueue_script( 'sis-grafico' );

		$id       = $this->id();
		$view     = sanitize_key( $atts['view'] );
		$type     = sanitize_key( $atts['type'] );
		$grupo    = sanitize_key( $atts['grupo'] );
		$tema     = in_array( $atts['theme'], array( 'dark', 'oscuro' ), true ) ? 'dark' : 'claro';
		$alto     = preg_match( '/^\d{1,4}(px|vh|rem|em|%)$/', $atts['alto'] ) ? $atts['alto'] : '420px';
		$actions  = preg_replace( '/[^a-z,_]/', '', strtolower( (string) $atts['actions'] ) );
		$legend   = ( 'no' === $atts['legend'] || '0' === (string) $atts['legend'] ) ? '0' : '1';
		$lstyle   = 'icons' === $atts['legend_style'] ? 'icons' : 'text';
		$toolbar  = ( 'no' === $atts['toolbar'] || '0' === (string) $atts['toolbar'] ) ? '0' : '1';
		$posmap   = array( 'abajo' => 'bottom', 'arriba' => 'top', 'derecha' => 'right', 'izquierda' => 'left', 'bottom' => 'bottom', 'top' => 'top', 'right' => 'right', 'left' => 'left' );
		$lpos     = isset( $posmap[ $atts['legend_pos'] ] ) ? $posmap[ $atts['legend_pos'] ] : 'bottom';
		$analisis = in_array( $atts['analisis'], array( 'ambos', 'descriptivo', 'cuantitativo', 'no' ), true ) ? $atts['analisis'] : 'no';

		ob_start();
		?>
		<figure id="<?php echo esc_attr( $id ); ?>"
			class="sis-g sis-g--<?php echo esc_attr( $tema ); ?>"
			data-sis-grafico
			data-view="<?php echo esc_attr( $view ); ?>"
			data-type="<?php echo esc_attr( $type ); ?>"
			data-actions="<?php echo esc_attr( $actions ); ?>"
			data-legend="<?php echo esc_attr( $legend ); ?>"
			data-legend-style="<?php echo esc_attr( $lstyle ); ?>"
			data-legend-pos="<?php echo esc_attr( $lpos ); ?>"
			data-toolbar="<?php echo esc_attr( $toolbar ); ?>"
			data-grupo="<?php echo esc_attr( $grupo ); ?>"
			data-analisis="<?php echo esc_attr( $analisis ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<figcaption class="sis-g__title"<?php echo $atts['titulo'] ? ' data-fijo="1"' : ''; ?>><?php echo esc_html( $atts['titulo'] ? $atts['titulo'] : __( 'Gráfico', 'sismos-narino' ) ); ?></figcaption>
			<div class="sis-g__chart" id="<?php echo esc_attr( $id ); ?>-chart"
				style="--sis-g-alto:<?php echo esc_attr( $alto ); ?>"></div>
			<?php echo $this->skeleton( __( 'Cargando gráfico…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo $this->pie_fuentes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</figure>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_filtro] — control que cambia la vista/tipo/ámbito de un grupo.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_filtro( $atts ) {
		$atts = $this->fusionar( array(
			'grupo'   => 'sismos',
			'control' => 'vista',
			'etiqueta'=> '',
		), $atts, 'sismos_filtro' );

		wp_enqueue_style( 'sis-grafico-css' );
		wp_enqueue_script( 'sis-composable' );

		$control = in_array( $atts['control'], array( 'vista', 'tipo', 'ambito', 'anios' ), true ) ? $atts['control'] : 'vista';
		$id      = $this->id();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-filtro"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-filtro
			data-grupo="<?php echo esc_attr( sanitize_key( $atts['grupo'] ) ); ?>"
			data-control="<?php echo esc_attr( $control ); ?>"
			data-etiqueta="<?php echo esc_attr( $atts['etiqueta'] ); ?>">
			<?php echo $this->skeleton( __( 'Cargando control…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_panel] — panel de detalles sincronizado con un grupo.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_panel( $atts ) {
		$atts = $this->fusionar( array( 'grupo' => 'sismos' ), $atts, 'sismos_panel' );

		wp_enqueue_style( 'sis-grafico-css' );
		wp_enqueue_script( 'sis-composable' );

		$id = $this->id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-panel"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-panel data-grupo="<?php echo esc_attr( sanitize_key( $atts['grupo'] ) ); ?>">
			<?php echo $this->skeleton( __( 'Esperando al gráfico del grupo…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ----------------------------------------------------------------- */
	/* Piezas de texto de una vista                                      */
	/* ----------------------------------------------------------------- */

	/**
	 * Atributos por defecto de los bloques de texto.
	 *
	 * @return array
	 */
	private function defaults_bloque() {
		return array_merge(
			$this->defaults_consulta(),
			array(
				'view'   => 'sismos_mensuales',
				'titulo' => '',
				'grupo'  => '',
			)
		);
	}

	/**
	 * Bloque de texto de una vista.
	 *
	 * @param array  $atts Atributos.
	 * @param string $modo Pieza a mostrar.
	 * @return string
	 */
	private function bloque_analisis( $atts, $modo ) {
		wp_enqueue_style( 'sis-grafico-css' );
		wp_enqueue_script( 'sis-grafico' );

		$id = $this->id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-analisis-bloque"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-analisis
			data-view="<?php echo esc_attr( sanitize_key( $atts['view'] ) ); ?>"
			data-modo="<?php echo esc_attr( $modo ); ?>"
			data-titulo="<?php echo esc_attr( $atts['titulo'] ); ?>"
			data-grupo="<?php echo esc_attr( sanitize_key( $atts['grupo'] ) ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $this->skeleton( __( 'Cargando análisis…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_analisis] — descriptivo + cuantitativo de una vista.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_analisis( $atts ) {
		$atts = $this->fusionar( array_merge( $this->defaults_bloque(), array( 'modo' => 'ambos' ) ), $atts, 'sismos_analisis' );
		$modo = in_array( $atts['modo'], array( 'ambos', 'descriptivo', 'cuantitativo', 'descripcion', 'como_funciona' ), true ) ? $atts['modo'] : 'ambos';
		return $this->bloque_analisis( $atts, $modo );
	}

	/**
	 * [sismos_descripcion] — qué muestra la vista.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_descripcion( $atts ) {
		$atts = $this->fusionar( $this->defaults_bloque(), $atts, 'sismos_descripcion' );
		return $this->bloque_analisis( $atts, 'descripcion' );
	}

	/**
	 * [sismos_explicacion] — cómo funciona el cálculo de la vista.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_explicacion( $atts ) {
		$atts = $this->fusionar( $this->defaults_bloque(), $atts, 'sismos_explicacion' );
		return $this->bloque_analisis( $atts, 'como_funciona' );
	}

	/**
	 * [sismos_analisis_cualitativo] — interpretación de la vista.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_analisis_cualitativo( $atts ) {
		$atts = $this->fusionar( $this->defaults_bloque(), $atts, 'sismos_analisis_cualitativo' );
		return $this->bloque_analisis( $atts, 'descriptivo' );
	}

	/**
	 * [sismos_analisis_cuantitativo] — cifras clave calculadas del dato.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_analisis_cuantitativo( $atts ) {
		$atts = $this->fusionar( $this->defaults_bloque(), $atts, 'sismos_analisis_cuantitativo' );
		return $this->bloque_analisis( $atts, 'cuantitativo' );
	}

	/* ----------------------------------------------------------------- */
	/* Componentes de dominio                                            */
	/* ----------------------------------------------------------------- */

	/**
	 * [sismos_estado] — semáforo de actividad reciente y último sismo.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_estado( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array( 'compacto' => 'no', 'vivo' => 'si' )
		), $atts, 'sismos_estado' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-estado' );

		$id = $this->id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-estado"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-estado
			data-compacto="<?php echo esc_attr( 'si' === $atts['compacto'] ? '1' : '0' ); ?>"
			data-vivo="<?php echo esc_attr( 'no' === $atts['vivo'] ? '0' : '1' ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $this->skeleton( __( 'Consultando la actividad sísmica…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo $this->pie_fuentes( 'USGS — feeds GeoJSON (actualización ~1 min)' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_ultimos] — lista de los últimos sismos registrados.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_ultimos( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array( 'limite' => '10', 'vivo' => 'si' )
		), $atts, 'sismos_ultimos' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-ultimos' );

		$id = $this->id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-ultimos"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-ultimos
			data-limite="<?php echo esc_attr( max( 1, min( 100, (int) $atts['limite'] ) ) ); ?>"
			data-vivo="<?php echo esc_attr( 'no' === $atts['vivo'] ? '0' : '1' ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $this->skeleton( __( 'Cargando los últimos sismos…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo $this->pie_fuentes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_mapa] — mapa de epicentros (Leaflet).
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_mapa( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array(
				'alto'       => '460px',
				'municipios' => 'si',
				'zoom'       => '',
				'amenaza'    => 'si',
				'periodo'    => '475',
			)
		), $atts, 'sismos_mapa' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_style( 'leaflet' );
		wp_enqueue_script( 'sis-mapa' );

		$id   = $this->id();
		$alto = preg_match( '/^\d{1,4}(px|vh|rem|em)$/', $atts['alto'] ) ? $atts['alto'] : '460px';

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-mapa"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-mapa
			data-alto="<?php echo esc_attr( $alto ); ?>"
			data-municipios="<?php echo esc_attr( 'no' === $atts['municipios'] ? '0' : '1' ); ?>"
			data-amenaza="<?php echo esc_attr( 'no' === $atts['amenaza'] ? '0' : '1' ); ?>"
			data-periodo="<?php echo esc_attr( (int) SIS_Amenaza::capa_wms( $atts['periodo'] )['periodo'] ); ?>"
			data-zoom="<?php echo esc_attr( preg_replace( '/[^0-9]/', '', (string) $atts['zoom'] ) ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<div class="sis-mapa__lienzo" style="height:<?php echo esc_attr( $alto ); ?>"></div>
			<?php echo $this->skeleton( __( 'Cargando el mapa de epicentros…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo $this->pie_fuentes( 'Sismos: USGS · Amenaza: Servicio Geológico Colombiano (Modelo Nacional de Amenaza Sísmica) · Base: OpenStreetMap · Municipios: DANE' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_estadistica] — ficha estadística del catálogo.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_estadistica( $atts ) {
		$atts = $this->fusionar( $this->defaults_consulta(), $atts, 'sismos_estadistica' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-estadistica' );

		$id = $this->id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-estadistica"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-estadistica
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $this->skeleton( __( 'Calculando estadísticas del catálogo…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo $this->pie_fuentes(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_datos] — botones de datos abiertos (JSON/CSV/API).
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_datos( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array( 'recurso' => 'eventos', 'texto' => '' )
		), $atts, 'sismos_datos' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-datos' );

		$recurso = in_array( $atts['recurso'], array( 'eventos', 'estadistica', 'recurrencia' ), true ) ? $atts['recurso'] : 'eventos';
		$id      = $this->id();

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-datos"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-datos
			data-recurso="<?php echo esc_attr( $recurso ); ?>"
			data-texto="<?php echo esc_attr( $atts['texto'] ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $this->skeleton( __( 'Preparando la descarga…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_estado_api] — panel público de salud de las fuentes.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_estado_api( $atts ) {
		$atts = $this->fusionar( array(), $atts, 'sismos_estado_api' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-estado-api' );

		$id = $this->id();
		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-estado-api"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-estado-api>
			<?php echo $this->skeleton( __( 'Consultando el estado de las fuentes…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * [sismos_globo] — globo 3D con los últimos sismos del ámbito.
	 *
	 * Cada sismo se dibuja con dos líneas sobre su epicentro: hacia afuera la
	 * magnitud, con el color del mapa de calor, y hacia adentro la profundidad
	 * focal. Alrededor se siembra el campo de calor, que comparte esa escala.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	/**
	 * [sismos_historico] — el registro completo en dos lecturas.
	 *
	 * Publica juntas las dos gráficas que responden a «¿cómo ha sido esto a lo
	 * largo del tiempo?»: barras de sismos por año, que dan la perspectiva
	 * larga, y una línea mensual con su media móvil de doce meses, que separa
	 * la tendencia del ruido de las secuencias de réplicas. Ambas recorren
	 * todo el catálogo disponible, no una ventana reciente.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_historico( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array(
				'alto'     => '380px',
				'theme'    => 'claro',
				'toolbar'  => 'si',
				'analisis' => 'no',
				'titulo'   => '',
			)
		), $atts, 'sismos_historico' );

		$comunes = array(
			'ambito'   => $atts['ambito'],
			'min_mag'  => $atts['min_mag'],
			// Sin ventana: el histórico es todo el catálogo, no los últimos años.
			'anios'    => '0',
			'alto'     => $atts['alto'],
			'theme'    => $atts['theme'],
			'toolbar'  => $atts['toolbar'],
			'analisis' => $atts['analisis'],
		);

		$titulo = sanitize_text_field( (string) $atts['titulo'] );

		ob_start();
		?>
		<section class="sis sis-historico">
			<?php if ( $titulo ) : ?>
				<h2 class="sis-historico__titulo"><?php echo esc_html( $titulo ); ?></h2>
			<?php endif; ?>

			<div class="sis-historico__par">
				<?php
				echo $this->sc_grafico( array_merge( $comunes, array( // phpcs:ignore WordPress.Security.EscapeOutput
					'view'   => 'sismos_anuales',
					'type'   => 'bar',
					'titulo' => __( 'Sismos por año', 'sismos-narino' ),
				) ) );

				echo $this->sc_grafico( array_merge( $comunes, array( // phpcs:ignore WordPress.Security.EscapeOutput
					'view'   => 'historico_mensual',
					'type'   => 'line',
					'titulo' => __( 'Serie mensual y tendencia a 12 meses', 'sismos-narino' ),
				) ) );
				?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function sc_globo( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array(
				'limite'     => '50',
				'calidad'    => 'auto',
				// El globo abre encuadrando la zona sísmica: si además girase
				// solo, en pocos segundos la perdería de vista. Quien quiera el
				// planeta en rotación lo pide con autorotar="si".
				'autorotar'  => 'no',
				'alto'       => '70vh',
				// La textura fotográfica del planeta pesa 1,4 MB y es
				// decorativa: el globo dibuja su propio planeta con retícula y
				// los sismos se leen igual —o mejor— sobre él. Quien la quiera
				// la pide con textura="si" y asume la descarga.
				'textura'    => 'no',
				'municipios' => 'si',
				'timeline'   => 'no',
			)
		), $atts, 'sismos_globo' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-globo' );

		$limite  = max( 5, min( 200, (int) $atts['limite'] ) );
		$calidad = in_array( $atts['calidad'], array( 'auto', 'alta', 'ligera' ), true ) ? $atts['calidad'] : 'auto';
		$alto    = preg_match( '/^\d{1,4}(px|vh|dvh|rem|em)$/', $atts['alto'] ) ? $atts['alto'] : '70vh';
		$conMuni = 'no' !== $atts['municipios'];

		wp_localize_script( 'sis-globo', 'SISGLOBO', array(
			'rest'         => esc_url_raw( rest_url( self::rest_ns() ) ),
			'ambito'       => SIS_Security::sanitizar_ambito( $atts['ambito'] ),
			'limite'       => $limite,
			'calidad'      => $calidad,
			'autorotar'    => 'no' !== $atts['autorotar'],
			// Textura del planeta servida por CDN; si no carga, el globo dibuja
			// un planeta propio con retícula y sigue funcionando.
			'textura'      => 'si' === $atts['textura'] ? self::TEXTURA_PLANETA : '',
			// Versión simplificada para el globo: a esta escala el detalle de la
			// cartografía completa no llega ni a un píxel, y son 300 KB menos
			// que descargar. El mapa Leaflet sigue usando la original.
			'geojson'      => $conMuni ? esc_url_raw( SIS_URL . 'data/narino_municipios_globo.geojson' ) : '',
			'geojsonDepto' => $conMuni ? esc_url_raw( SIS_URL . 'data/narino_departamento_globo.geojson' ) : '',
		) );

		$id = $this->id();

		ob_start();
		echo $this->importmap_three(); // phpcs:ignore WordPress.Security.EscapeOutput
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-globo"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-globo
			data-limite="<?php echo esc_attr( $limite ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>

			<div class="sis-globo__lienzo" style="height:<?php echo esc_attr( $alto ); ?>" role="img"
				aria-label="<?php esc_attr_e( 'Globo terráqueo con los últimos sismos: cada epicentro levanta una línea cuya altura y color indican la magnitud, y hunde otra proporcional a la profundidad.', 'sismos-narino' ); ?>"></div>

			<div class="sis-globo__controles" role="toolbar" aria-label="<?php esc_attr_e( 'Vista y capas del globo', 'sismos-narino' ); ?>">
				<button type="button" class="sis-globo__btn" data-camara="global"><?php esc_html_e( 'Global', 'sismos-narino' ); ?></button>
				<button type="button" class="sis-globo__btn is-activo" data-camara="sismos"><?php esc_html_e( 'Zona sísmica', 'sismos-narino' ); ?></button>
				<button type="button" class="sis-globo__btn" data-camara="narino"><?php esc_html_e( 'Nariño', 'sismos-narino' ); ?></button>
				<span class="sis-globo__sep-btn" aria-hidden="true"></span>
				<button type="button" class="sis-globo__btn is-activo" data-capa="calor" aria-pressed="true"><?php esc_html_e( 'Mapa de calor', 'sismos-narino' ); ?></button>
				<button type="button" class="sis-globo__btn is-activo" data-capa="profundidad" aria-pressed="true"><?php esc_html_e( 'Profundidad', 'sismos-narino' ); ?></button>
				<?php if ( $conMuni ) : ?>
					<button type="button" class="sis-globo__btn is-activo" data-capa="municipios" aria-pressed="true"><?php esc_html_e( 'Municipios', 'sismos-narino' ); ?></button>
				<?php endif; ?>
				<button type="button" class="sis-globo__btn" data-rotar aria-pressed="false"><?php esc_html_e( 'Girar', 'sismos-narino' ); ?></button>
			</div>

			<div class="sis-globo__cintillo" aria-live="polite"></div>

			<div class="sis-globo__leyenda">
				<span class="sis-globo__leyenda-tit"><?php esc_html_e( 'Magnitud', 'sismos-narino' ); ?></span>
				<span class="sis-globo__rampa" aria-hidden="true"></span>
				<span class="sis-globo__leyenda-esc"><span>3</span><span>4</span><span>5</span><span>6</span><span>7+</span></span>
				<span class="sis-globo__leyenda-pie"><?php esc_html_e( 'Hacia afuera: magnitud · Hacia adentro: profundidad', 'sismos-narino' ); ?></span>
			</div>

			<?php echo $this->skeleton( __( 'Cargando el globo…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo $this->pie_fuentes( 'Sismos: USGS · Cartografía municipal: DANE · Motor 3D: Three.js' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		$salida = ob_get_clean();

		if ( 'si' === $atts['timeline'] ) {
			$salida .= $this->sc_timeline( array(
				'ambito' => $atts['ambito'],
				'limite' => (string) $limite,
			) );
		}

		return $salida;
	}

	/**
	 * [sismos_timeline] — recorre los últimos sismos y enfoca el globo.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_timeline( $atts ) {
		$atts = $this->fusionar( array_merge(
			$this->defaults_consulta(),
			array(
				'limite' => '50',
				'logo'   => 'si',
			)
		), $atts, 'sismos_timeline' );

		wp_enqueue_style( 'sis-estilos' );
		wp_enqueue_script( 'sis-timeline' );

		$id = $this->id();
		// La marca institucional viaja como atributo, no como script aparte: si
		// el archivo no estuviera, la imagen se oculta sola y la barra sigue.
		$logo = 'no' === $atts['logo'] ? '' : SIS_URL . 'assets/img/TIC.png';

		ob_start();
		?>
		<div id="<?php echo esc_attr( $id ); ?>" class="sis sis-tl"
			style="<?php echo esc_attr( SIS_Estilos::estilo_inline( $atts ) ); ?>"
			data-sis-timeline
			data-logo="<?php echo esc_url( $logo ); ?>"
			data-limite="<?php echo esc_attr( max( 5, min( 200, (int) $atts['limite'] ) ) ); ?>"
			<?php echo $this->data_consulta( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<?php echo $this->skeleton( __( 'Cargando la línea de tiempo…', 'sismos-narino' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ----------------------------------------------------------------- */
	/* Amenaza y preparación (contenido oficial, sin pronósticos)        */
	/* ----------------------------------------------------------------- */

	/**
	 * Envoltorio común de los componentes de contenido estático.
	 *
	 * Se renderizan en PHP —no por JS— porque son textos fijos: así el HTML
	 * queda cacheable, indexable y accesible sin JavaScript.
	 *
	 * @param array  $atts    Atributos del shortcode.
	 * @param string $clase   Clase CSS del componente.
	 * @param string $interior HTML ya escapado.
	 * @param string $fuente  Atribución del pie.
	 * @return string
	 */
	private function bloque_estatico( $atts, $clase, $interior, $fuente = '' ) {
		wp_enqueue_style( 'sis-estilos' );
		$id = $this->id();

		return '<section id="' . esc_attr( $id ) . '" class="sis ' . esc_attr( $clase ) . '"'
			. ' style="' . esc_attr( SIS_Estilos::estilo_inline( $atts ) ) . '">'
			. $interior
			. ( $fuente ? $this->pie_fuentes( $fuente ) : '' )
			. '</section>';
	}

	/**
	 * Descargo institucional, obligatorio en los componentes de amenaza.
	 *
	 * @return string
	 */
	private function descargo() {
		return '<p class="sis-aviso">' . esc_html( SIS_Amenaza::descargo() ) . '</p>';
	}

	/**
	 * Lista de pasos con título e introducción.
	 *
	 * @param array $seccion {titulo, intro, pasos}.
	 * @return string
	 */
	private function lista_pasos( $seccion ) {
		$html = '<h3 class="sis-h3">' . esc_html( $seccion['titulo'] ) . '</h3>';
		if ( ! empty( $seccion['intro'] ) ) {
			$html .= '<p class="sis-analisis">' . esc_html( $seccion['intro'] ) . '</p>';
		}
		$html .= '<ul class="sis-lista">';
		foreach ( (array) $seccion['pasos'] as $paso ) {
			$html .= '<li>' . esc_html( $paso ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * [sismos_amenaza] — qué amenaza sísmica tiene Nariño y dónde consultarla.
	 * Publica contexto geológico, referencia normativa y enlaces oficiales.
	 * No incluye ninguna estimación propia de sismos futuros.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_amenaza( $atts ) {
		$atts = $this->fusionar( array(
			'titulo'    => 'Amenaza sísmica en Nariño',
			'normativa' => 'si',
			'fuentes'   => 'si',
		), $atts, 'sismos_amenaza' );

		$html = '<h2 class="sis-h2">' . esc_html( $atts['titulo'] ) . '</h2>';
		$html .= $this->descargo();

		foreach ( SIS_Amenaza::contexto_geologico() as $bloque ) {
			$html .= '<h3 class="sis-h3">' . esc_html( $bloque['titulo'] ) . '</h3>';
			$html .= '<p class="sis-analisis">' . esc_html( $bloque['texto'] );
			if ( ! empty( $bloque['enlace'] ) ) {
				$html .= ' <a class="sis-enlace" href="' . esc_url( $bloque['enlace'] ) . '" target="_blank" rel="noopener noreferrer">'
					. esc_html__( 'Ver la fuente', 'sismos-narino' ) . '</a>';
			}
			$html .= '</p>';
		}

		if ( 'no' !== $atts['normativa'] ) {
			$n = SIS_Amenaza::normativa();
			$html .= '<h3 class="sis-h3">' . esc_html__( 'Qué dice la norma', 'sismos-narino' ) . '</h3>';
			$html .= '<dl class="sis-dl">';
			$html .= '<dt>' . esc_html__( 'Norma vigente', 'sismos-narino' ) . '</dt><dd>' . esc_html( $n['norma'] ) . '</dd>';
			$html .= '<dt>' . esc_html__( 'Zona de amenaza de Pasto', 'sismos-narino' ) . '</dt><dd>' . esc_html( $n['zona_pasto'] ) . ' (Aa = ' . esc_html( $n['aa_pasto'] ) . ' · Av = ' . esc_html( $n['av_pasto'] ) . ')</dd>';
			$html .= '<dt>' . esc_html__( 'Litoral Pacífico', 'sismos-narino' ) . '</dt><dd>' . esc_html__( 'Coeficientes más altos, hasta Aa = ', 'sismos-narino' ) . esc_html( $n['aa_pacifico'] ) . ' · Av = ' . esc_html( $n['av_pacifico'] ) . '</dd>';
			$html .= '<dt>' . esc_html__( 'Vigencia', 'sismos-narino' ) . '</dt><dd>' . esc_html( $n['vigencia'] ) . '</dd>';
			$html .= '<dt>' . esc_html__( 'Microzonificación', 'sismos-narino' ) . '</dt><dd>' . esc_html( $n['microzonificacion'] ) . '</dd>';
			$html .= '</dl>';
			$html .= '<p class="sis-nota">' . esc_html( $n['nota'] ) . '</p>';
		}

		if ( 'no' !== $atts['fuentes'] ) {
			$html .= $this->html_fuentes( array( 'sgc_amenaza', 'sgc_catalogo', 'ovsp' ) );
		}

		return $this->bloque_estatico( $atts, 'sis-amenaza', $html, 'Servicio Geológico Colombiano · NSR-10 · USGS' );
	}

	/**
	 * [sismos_glosario] — alerta temprana, pronóstico, probabilidad y predicción.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_glosario( $atts ) {
		$atts = $this->fusionar( array( 'titulo' => 'Entienda los términos' ), $atts, 'sismos_glosario' );

		$html = '<h2 class="sis-h2">' . esc_html( $atts['titulo'] ) . '</h2>';
		$html .= '<p class="sis-analisis">' . esc_html__( 'Cuatro conceptos que suelen confundirse. Solo uno de ellos es imposible, y es justamente el que más circula en redes.', 'sismos-narino' ) . '</p>';

		foreach ( SIS_Amenaza::glosario() as $t ) {
			$html .= '<div class="sis-glosario__item">';
			$html .= '<h3 class="sis-h3">' . esc_html( $t['termino'] );
			$html .= ' <span class="sis-chip" style="background:' . ( $t['es_posible'] ? '#3EBA6A' : '#C0392B' ) . '">'
				. esc_html( $t['es_posible'] ? __( 'Es posible', 'sismos-narino' ) : __( 'No es posible', 'sismos-narino' ) )
				. '</span></h3>';
			$html .= '<p class="sis-analisis">' . esc_html( $t['definicion'] ) . '</p>';
			$html .= '<p class="sis-nota">' . esc_html( $t['ejemplo'] ) . '</p>';
			$html .= '</div>';
		}

		return $this->bloque_estatico( $atts, 'sis-glosario', $html, 'Marco conceptual del USGS · Servicio Geológico Colombiano' );
	}

	/**
	 * [sismos_preparacion] — guía ciudadana antes, durante y después.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_preparacion( $atts ) {
		$atts = $this->fusionar( array(
			'seccion' => 'todas',
			'titulo'  => 'Cómo prepararse',
		), $atts, 'sismos_preparacion' );

		$ruta      = SIS_DIR . 'includes/data/textos-preparacion.php';
		$contenido = is_readable( $ruta ) ? include $ruta : array();
		$seccion   = sanitize_key( $atts['seccion'] );

		$html = '<h2 class="sis-h2">' . esc_html( $atts['titulo'] ) . '</h2>';

		if ( 'todas' !== $seccion && isset( $contenido[ $seccion ] ) ) {
			$html .= $this->lista_pasos( $contenido[ $seccion ] );
		} else {
			foreach ( $contenido as $bloque ) {
				$html .= $this->lista_pasos( $bloque );
			}
		}

		return $this->bloque_estatico( $atts, 'sis-preparacion', $html, 'UNGRD · Servicio Geológico Colombiano · Cruz Roja Colombiana' );
	}

	/**
	 * [sismos_replicas] — qué esperar después de un sismo fuerte.
	 * Información educativa fija: no genera cifras ni probabilidades propias.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_replicas( $atts ) {
		$atts = $this->fusionar( array( 'titulo' => 'Después de un sismo fuerte: las réplicas' ), $atts, 'sismos_replicas' );

		$r = SIS_Amenaza::replicas();

		$html  = '<h2 class="sis-h2">' . esc_html( $atts['titulo'] ) . '</h2>';
		$html .= '<p class="sis-analisis">' . esc_html( $r['que_son'] ) . '</p>';
		$html .= '<p class="sis-analisis">' . esc_html( $r['cuanto_duran'] ) . '</p>';

		$html .= $this->lista_pasos( array( 'titulo' => __( 'Qué hacer', 'sismos-narino' ), 'intro' => '', 'pasos' => $r['que_hacer'] ) );
		$html .= $this->lista_pasos( array( 'titulo' => __( 'Qué no hacer', 'sismos-narino' ), 'intro' => '', 'pasos' => $r['no_haga'] ) );

		$html .= '<p class="sis-aviso">' . esc_html( $r['donde_mirar'] ) . '</p>';
		$html .= $this->html_fuentes( array( 'sgc_recientes', 'sgc_sentidos' ) );

		return $this->bloque_estatico( $atts, 'sis-replicas', $html, 'Servicio Geológico Colombiano' );
	}

	/**
	 * [sismos_desinformacion] — cómo reconocer una «predicción» falsa.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_desinformacion( $atts ) {
		$atts = $this->fusionar( array( 'titulo' => 'Cómo reconocer una predicción falsa' ), $atts, 'sismos_desinformacion' );

		$html  = '<h2 class="sis-h2">' . esc_html( $atts['titulo'] ) . '</h2>';
		$html .= '<p class="sis-analisis">' . esc_html__( 'Los sismos no se pueden predecir. Cualquier mensaje que anuncie uno —con o sin sellos oficiales— es falso. Estas son las señales para identificarlo antes de compartirlo.', 'sismos-narino' ) . '</p>';

		$html .= '<ul class="sis-lista sis-lista--senales">';
		foreach ( SIS_Amenaza::senales_falsas() as $sn ) {
			$html .= '<li><strong>' . esc_html( $sn['senal'] ) . '</strong><br><span class="sis-nota">' . esc_html( $sn['explicacion'] ) . '</span></li>';
		}
		$html .= '</ul>';

		$html .= $this->html_fuentes( array( 'sgc_recientes', 'usgs' ) );

		return $this->bloque_estatico( $atts, 'sis-desinformacion', $html, 'Desmentidos del SGC y del IDEAM · USGS' );
	}

	/**
	 * [sismos_fuentes_oficiales] — directorio de consulta oficial.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public function sc_fuentes_oficiales( $atts ) {
		$atts = $this->fusionar( array( 'titulo' => 'Dónde consultar información oficial' ), $atts, 'sismos_fuentes_oficiales' );

		$html  = '<h2 class="sis-h2">' . esc_html( $atts['titulo'] ) . '</h2>';
		$html .= $this->html_fuentes( array() );
		$html .= $this->descargo();

		return $this->bloque_estatico( $atts, 'sis-fuentes-oficiales', $html );
	}

	/**
	 * Lista de fuentes oficiales, opcionalmente filtrada por clave.
	 *
	 * @param string[] $claves Claves a incluir (vacío = todas).
	 * @return string
	 */
	private function html_fuentes( $claves = array() ) {
		$html = '<ul class="sis-fuentes-lista">';
		foreach ( SIS_Amenaza::fuentes_oficiales() as $f ) {
			if ( ! empty( $claves ) && ! in_array( $f['clave'], $claves, true ) ) {
				continue;
			}
			$html .= '<li>';
			$html .= '<a class="sis-enlace" href="' . esc_url( $f['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $f['nombre'] ) . '</a>';
			$html .= ' <span class="sis-nota">· ' . esc_html( $f['entidad'] ) . '</span>';
			$html .= '<br><span class="sis-nota">' . esc_html( $f['descripcion'] ) . '</span>';
			$html .= '</li>';
		}
		return $html . '</ul>';
	}
}
