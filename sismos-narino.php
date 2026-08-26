<?php
/**
 * Plugin Name:  Sismos Nariño — Análisis Estadístico y Pronóstico Sísmico
 * Plugin URI:   https://gobiernoabierto.narino.gov.co/datos/sismos/
 * Description:  Análisis estadístico de la sismicidad de Nariño y la zona de subducción vecina con datos abiertos del USGS (FDSN Event + feeds GeoJSON). Gráficos D3plus con barra de herramientas, mapa de epicentros, datos abiertos y módulo de amenaza y preparación construido sobre fuentes oficiales. NO pronostica sismos: la autoridad técnica es el Servicio Geológico Colombiano.
 * Version:      2.5.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author:       Gobernación de Nariño · Secretaría TIC, Innovación y Gobierno Abierto
 * Author URI:   https://gobiernoabierto.narino.gov.co
 * License:      GPL-2.0-or-later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  sismos-narino
 * Domain Path:  /languages
 *
 * Fuentes de datos (atribución obligatoria): U.S. Geological Survey — Earthquake
 * Hazards Program (FDSN Event Web Service y feeds GeoJSON, dominio público),
 * DANE/DIVIPOLA (municipios de Nariño), Servicio Geológico Colombiano y UNGRD
 * (marco de amenaza y preparación).
 *
 * Alcance: el plugin publica estadística de lo ya ocurrido y contenido oficial
 * de amenaza y preparación. NO predice ni pronostica sismos —eso no es posible,
 * y el pronóstico de réplicas es competencia del Servicio Geológico Colombiano—.
 * Ver docs/marco-comunicacion-riesgo.md.
 */

// Salida directa bloqueada: ningún acceso fuera de WordPress.
defined( 'ABSPATH' ) || exit;

use GobernacionNarino\Sismos\SIS_Plugin;
use GobernacionNarino\Sismos\SIS_Activator;

/* -------------------------------------------------------------------------
 * Constantes del plugin
 * ---------------------------------------------------------------------- */
define( 'SIS_VERSION', '2.5.0' );
define( 'SIS_FILE', __FILE__ );
define( 'SIS_DIR', plugin_dir_path( __FILE__ ) );       // .../sismos-narino/
define( 'SIS_URL', plugin_dir_url( __FILE__ ) );        // URL pública de assets
define( 'SIS_BASENAME', plugin_basename( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Carga del orquestador y de todas las dependencias.
 * Se hace en el load del archivo (no en un hook) para que el activador
 * tenga las clases disponibles al registrar tablas y cron.
 * ---------------------------------------------------------------------- */
require_once SIS_DIR . 'includes/class-sis-plugin.php';
SIS_Plugin::cargar_dependencias();

/* -------------------------------------------------------------------------
 * Ciclo de vida
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, array( SIS_Activator::class, 'activar' ) );
register_deactivation_hook( __FILE__, array( SIS_Activator::class, 'desactivar' ) );

// Arranque: instancia singleton en plugins_loaded (registra todos los hooks).
add_action( 'plugins_loaded', array( SIS_Plugin::class, 'instancia' ) );
