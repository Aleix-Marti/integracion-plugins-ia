<?php
/**
 * Plugin Name:       Banner MCP
 * Plugin URI:        https://github.com/example/banner-mcp
 * Description:       Muestra un banner de anuncio personalizable en la parte superior de la web. Integración completa con MCP Adapter mediante la Abilities API de WordPress, lo que permite controlar el banner desde Claude u otros clientes MCP de forma remota.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            Aleix Martí
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       banner-mcp
 * Domain Path:       /languages
 *
 * @package BannerMcp
 */

// Evitar acceso directo al fichero.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes del plugin.
define( 'BANNER_MCP_VERSION', '1.0.0' );
define( 'BANNER_MCP_PLUGIN_FILE', __FILE__ );
define( 'BANNER_MCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BANNER_MCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BANNER_MCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Nombres de las opciones almacenadas en wp_options.
define( 'BANNER_MCP_OPTION_TEXT', 'banner_mcp_text' );
define( 'BANNER_MCP_OPTION_ENABLED', 'banner_mcp_enabled' );

/**
 * Carga las clases del plugin cuando WordPress ha terminado de inicializarse.
 *
 * Se usa 'plugins_loaded' para garantizar que el MCP Adapter (si está activo)
 * ya esté disponible antes de registrar las habilidades.
 *
 * @since 1.0.0
 */
function banner_mcp_init() {
	require_once BANNER_MCP_PLUGIN_DIR . 'includes/class-banner-mcp-settings.php';
	require_once BANNER_MCP_PLUGIN_DIR . 'includes/class-banner-mcp-frontend.php';
	require_once BANNER_MCP_PLUGIN_DIR . 'includes/class-banner-mcp-abilities.php';

	Banner_Mcp_Settings::init();
	Banner_Mcp_Frontend::init();
	Banner_Mcp_Abilities::init();
}
add_action( 'plugins_loaded', 'banner_mcp_init' );

/**
 * Acciones al activar el plugin.
 *
 * Establece los valores por defecto de las opciones si aún no existen.
 *
 * @since 1.0.0
 */
function banner_mcp_activate() {
	if ( false === get_option( BANNER_MCP_OPTION_TEXT ) ) {
		add_option( BANNER_MCP_OPTION_TEXT, '' );
	}
	if ( false === get_option( BANNER_MCP_OPTION_ENABLED ) ) {
		add_option( BANNER_MCP_OPTION_ENABLED, '0' );
	}
}
register_activation_hook( __FILE__, 'banner_mcp_activate' );

/**
 * Acciones al desactivar el plugin.
 *
 * No elimina las opciones para conservar la configuración si el plugin
 * se vuelve a activar posteriormente.
 *
 * @since 1.0.0
 */
function banner_mcp_deactivate() {
	// Intencionalmente vacío: se conserva la configuración al desactivar.
}
register_deactivation_hook( __FILE__, 'banner_mcp_deactivate' );

/**
 * Acciones al desinstalar el plugin.
 *
 * Elimina todas las opciones de la base de datos para dejar la instalación limpia.
 *
 * @since 1.0.0
 */
function banner_mcp_uninstall() {
	delete_option( BANNER_MCP_OPTION_TEXT );
	delete_option( BANNER_MCP_OPTION_ENABLED );
}
register_uninstall_hook( __FILE__, 'banner_mcp_uninstall' );
