<?php
/**
 * Orquestador singleton del plugin.
 *
 * Responsabilidad única: cargar las dependencias y registrar los hooks de
 * cada subsistema (estilos, REST, shortcodes, sincronización y admin).
 *
 * @package SismosNarino
 */

namespace GobernacionNarino\Sismos;

defined( 'ABSPATH' ) || exit;

final class SIS_Plugin {

	/** @var SIS_Plugin|null Instancia única. */
	private static $instancia = null;

	/** @var bool Evita requerir las dependencias dos veces. */
	private static $cargado = false;

	/** @var SIS_Estilos */
	public $estilos;
	/** @var SIS_Rest */
	public $rest;
	/** @var SIS_Shortcodes */
	public $shortcodes;
	/** @var SIS_Sync */
	public $sync;
	/** @var SIS_Admin|null */
	public $admin = null;

	/**
	 * Devuelve (creando si hace falta) la instancia única.
	 *
	 * @return SIS_Plugin
	 */
	public static function instancia() {
		if ( null === self::$instancia ) {
			self::$instancia = new self();
		}
		return self::$instancia;
	}

	/**
	 * Requiere todos los archivos de clase del plugin.
	 * Idempotente: seguro llamarlo varias veces.
	 */
	public static function cargar_dependencias() {
		if ( self::$cargado ) {
			return;
		}

		$base = SIS_DIR . 'includes/';

		// Núcleo.
		require_once $base . 'class-sis-activator.php';
		require_once $base . 'class-sis-cache.php';
		require_once $base . 'class-sis-security.php';
		require_once $base . 'class-sis-estilos.php';

		// Datos.
		require_once $base . 'data/class-sis-municipios.php';
		require_once $base . 'data/class-sis-regiones.php';

		// Análisis (orden: catálogo → estadística → pronóstico → texto).
		require_once $base . 'analysis/class-sis-catalogo.php';
		require_once $base . 'analysis/class-sis-estadistica.php';
		require_once $base . 'analysis/class-sis-forecast.php';
		require_once $base . 'analysis/class-sis-texto.php';

		// Vistas del motor de gráficos (dependen de análisis).
		require_once $base . 'data/class-sis-views.php';

		// REST (expone catálogo, estadística, pronóstico y /render).
		require_once $base . 'class-sis-rest.php';

		// Sincronización (Capa 1 — cron).
		require_once $base . 'sync/class-sis-sync-usgs.php';
		require_once $base . 'sync/class-sis-sync-feed.php';
		require_once $base . 'sync/class-sis-sync.php';

		// Presentación.
		require_once $base . 'shortcodes/class-sis-shortcodes.php';

		// Administración (se requiere siempre para que existan las acciones
		// AJAX/cron registradas, aunque la UI solo se pinte en el panel).
		require_once $base . 'admin/class-sis-admin.php';

		self::$cargado = true;
	}

	/**
	 * Constructor privado: registra los hooks de los subsistemas.
	 */
	private function __construct() {
		self::cargar_dependencias();

		// Idioma (es_CO).
		add_action( 'init', array( $this, 'cargar_textdomain' ) );

		// Migración al actualizar (en admin): siembra fuentes nuevas sin reactivar.
		add_action( 'admin_init', array( SIS_Activator::class, 'migrar_si_necesario' ) );

		// Subsistemas: cada uno registra sus propios hooks en su constructor.
		$this->estilos    = new SIS_Estilos();
		$this->rest       = new SIS_Rest();
		$this->shortcodes = new SIS_Shortcodes();
		$this->sync       = new SIS_Sync();

		if ( is_admin() ) {
			$this->admin = new SIS_Admin();
		}
	}

	/**
	 * Carga las traducciones del plugin.
	 */
	public function cargar_textdomain() {
		load_plugin_textdomain(
			'sismos-narino',
			false,
			dirname( SIS_BASENAME ) . '/languages'
		);
	}

	/** Clonación e hidratación deshabilitadas (singleton). */
	private function __clone() {}
	public function __wakeup() {
		throw new \Exception( 'No se permite deserializar SIS_Plugin.' );
	}
}
