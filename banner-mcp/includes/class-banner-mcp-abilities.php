<?php
/**
 * Registro de habilidades en la Abilities API de WordPress para integración MCP.
 *
 * Este fichero registra tres habilidades que el MCP Adapter expone automáticamente
 * al servidor MCP, permitiendo que Claude u otros clientes MCP lean y modifiquen
 * la configuración del banner de forma remota y programática.
 *
 * Requisito: WordPress 6.9+ (Abilities API nativa).
 * Requisito: MCP Adapter plugin instalado y activo para la exposición MCP.
 *
 * Las habilidades se registran con el flag meta.mcp.public = true, lo que hace
 * que el MCP Adapter las incluya en el servidor por defecto sin necesidad de
 * crear un servidor MCP personalizado.
 *
 * @package BannerMcp
 * @since   1.0.0
 *
 * @see https://github.com/WordPress/mcp-adapter
 * @see https://developer.wordpress.org/apis/abilities/
 */

// Evitar acceso directo al fichero.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase que gestiona el registro de habilidades en la Abilities API.
 *
 * Registra las siguientes habilidades:
 *
 * - banner-mcp/get-settings:    Devuelve el texto y el estado actual del banner.
 * - banner-mcp/update-settings: Actualiza el texto, el estado, o ambos.
 * - banner-mcp/toggle-banner:   Invierte el estado de activación del banner.
 *
 * @since 1.0.0
 */
class Banner_Mcp_Abilities {

	/**
	 * Registra los hooks de WordPress para la Abilities API.
	 *
	 * Se usa 'plugins_loaded' con prioridad alta para garantizar que la
	 * comprobación de la función wp_register_ability() se realiza después
	 * de que todos los plugins hayan cargado. El registro real se aplaza
	 * al hook 'wp_abilities_api_init', que es el punto de entrada correcto
	 * según la documentación oficial de la Abilities API.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		/*
		 * El hook wp_abilities_api_init se introdujo en WordPress 6.9.
		 * Solo nos enganchamos si la función existe, garantizando compatibilidad
		 * hacia atrás sin errores fatales en versiones anteriores de WordPress.
		 */
		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		}
	}

	/**
	 * Registra las tres habilidades del plugin en la Abilities API de WordPress.
	 *
	 * Este método es el callback del hook 'wp_abilities_api_init'. Se ejecuta
	 * una vez que la Abilities API está completamente inicializada y lista para
	 * recibir registros.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_abilities() {
		self::register_get_settings();
		self::register_update_settings();
		self::register_toggle_banner();
	}

	/**
	 * Registra la habilidad 'banner-mcp/get-settings'.
	 *
	 * Permite a Claude consultar el estado actual del banner: si está activo
	 * y cuál es el texto que se muestra. No requiere parámetros de entrada.
	 *
	 * Ejemplo de respuesta:
	 * {
	 *   "enabled": true,
	 *   "text": "¡Oferta especial! 20% de descuento este fin de semana."
	 * }
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_get_settings() {
		wp_register_ability(
			'banner-mcp/get-settings',
			array(
				'label'       => 'Get Banner Settings',
				'description' => 'Returns the current configuration of the announcement banner: whether it is enabled and the text it displays.',
				'category'    => 'site',

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'enabled' => array(
							'type'        => 'boolean',
							'description' => 'Whether the banner is currently visible on the frontend.',
						),
						'text'    => array(
							'type'        => 'string',
							'description' => 'The message text displayed in the banner.',
						),
					),
					'required'   => array( 'enabled', 'text' ),
				),

				'execute_callback' => function () {
					return array(
						'enabled' => ( '1' === get_option( BANNER_MCP_OPTION_ENABLED, '0' ) ),
						'text'    => (string) get_option( BANNER_MCP_OPTION_TEXT, '' ),
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta' => array(
					'mcp' => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Registra la habilidad 'banner-mcp/update-settings'.
	 *
	 * Permite a Claude actualizar el texto del banner, su estado de activación,
	 * o ambos en una sola llamada. Todos los parámetros son opcionales; solo
	 * se actualizan los campos que se proporcionen.
	 *
	 * Parámetros de entrada:
	 * - text (string, opcional): Nuevo texto para el banner. Máx. 300 caracteres.
	 * - enabled (boolean, opcional): true para activar, false para desactivar.
	 *
	 * Ejemplo de uso desde Claude:
	 * "Actualiza el banner a '¡Envío gratis hoy!' y actívalo."
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_update_settings() {
		wp_register_ability(
			'banner-mcp/update-settings',
			array(
				'label'       => 'Update Banner Settings',
				'description' => 'Updates the announcement banner configuration. You can change the text, the enabled state, or both in a single call. Only the fields you provide will be modified.',
				'category'    => 'site',

				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'text'    => array(
							'type'        => 'string',
							'description' => 'The new message text for the banner. Maximum 300 characters. Leave empty or omit to keep the current value.',
							'maxLength'   => 300,
						),
						'enabled' => array(
							'type'        => 'boolean',
							'description' => 'Set to true to show the banner on the frontend, or false to hide it. Omit to keep the current state.',
						),
					),
				),

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array(
							'type'        => 'boolean',
							'description' => 'Whether the update was applied successfully.',
						),
						'enabled' => array(
							'type'        => 'boolean',
							'description' => 'The new enabled state after the update.',
						),
						'text'    => array(
							'type'        => 'string',
							'description' => 'The banner text after the update.',
						),
						'updated' => array(
							'type'        => 'array',
							'description' => 'List of fields that were actually modified.',
							'items'       => array( 'type' => 'string' ),
						),
					),
				),

				'execute_callback' => function ( $input ) {
					$updated = array();

					// Actualizar el texto solo si se proporcionó en la llamada.
					if ( isset( $input['text'] ) ) {
						$sanitized_text = sanitize_text_field( (string) $input['text'] );
						$sanitized_text = mb_substr( $sanitized_text, 0, 300 );
						update_option( BANNER_MCP_OPTION_TEXT, $sanitized_text );
						$updated[] = 'text';
					}

					// Actualizar el estado solo si se proporcionó en la llamada.
					if ( isset( $input['enabled'] ) ) {
						$new_enabled = ( true === $input['enabled'] || '1' === $input['enabled'] ) ? '1' : '0';
						update_option( BANNER_MCP_OPTION_ENABLED, $new_enabled );
						$updated[] = 'enabled';
					}

					return array(
						'success' => true,
						'enabled' => ( '1' === get_option( BANNER_MCP_OPTION_ENABLED, '0' ) ),
						'text'    => (string) get_option( BANNER_MCP_OPTION_TEXT, '' ),
						'updated' => $updated,
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta' => array(
					'mcp' => array(
						'public' => true,
					),
				),
			)
		);
	}

	/**
	 * Registra la habilidad 'banner-mcp/toggle-banner'.
	 *
	 * Invierte el estado de activación del banner en una sola llamada sin
	 * parámetros. Si el banner está activo, lo desactiva; si está inactivo,
	 * lo activa. Es especialmente útil para comandos rápidos como
	 * "activa el banner" o "desactiva el banner".
	 *
	 * Ejemplo de respuesta tras activar:
	 * {
	 *   "enabled": true,
	 *   "previous_state": false,
	 *   "text": "¡Oferta especial!"
	 * }
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_toggle_banner() {
		wp_register_ability(
			'banner-mcp/toggle-banner',
			array(
				'label'       => 'Toggle Banner',
				'description' => 'Flips the banner visibility: activates it if it is currently hidden, or deactivates it if it is currently visible. No input required.',
				'category'    => 'site',

				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'enabled'        => array(
							'type'        => 'boolean',
							'description' => 'The new enabled state after toggling.',
						),
						'previous_state' => array(
							'type'        => 'boolean',
							'description' => 'The enabled state before toggling.',
						),
						'text'           => array(
							'type'        => 'string',
							'description' => 'The current banner text (unchanged by this operation).',
						),
					),
				),

				'execute_callback' => function () {
					$current_state = ( '1' === get_option( BANNER_MCP_OPTION_ENABLED, '0' ) );
					$new_state     = ! $current_state;

					update_option( BANNER_MCP_OPTION_ENABLED, $new_state ? '1' : '0' );

					return array(
						'enabled'        => $new_state,
						'previous_state' => $current_state,
						'text'           => (string) get_option( BANNER_MCP_OPTION_TEXT, '' ),
					);
				},

				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},

				'meta' => array(
					'mcp' => array(
						'public' => true,
					),
				),
			)
		);
	}
}
