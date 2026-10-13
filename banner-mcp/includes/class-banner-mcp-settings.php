<?php
/**
 * Página de configuración del plugin Banner MCP.
 *
 * Registra el menú de administración y gestiona el formulario de opciones
 * con el texto del banner y el checkbox de activación.
 *
 * @package BannerMcp
 * @since   1.0.0
 */

// Evitar acceso directo al fichero.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase que gestiona la página de ajustes del plugin.
 *
 * Se encarga de:
 * - Registrar el submenú en "Ajustes".
 * - Registrar las opciones con la Settings API de WordPress.
 * - Renderizar el formulario de configuración.
 * - Sanitizar y validar los datos antes de guardarlos.
 *
 * @since 1.0.0
 */
class Banner_Mcp_Settings {

	/**
	 * Slug de la página de ajustes en el panel de administración.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'banner-mcp-settings';

	/**
	 * Nombre del grupo de opciones para la Settings API.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTIONS_GROUP = 'banner_mcp_options_group';

	/**
	 * Nombre de la sección en el formulario de ajustes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SECTION_MAIN = 'banner_mcp_section_main';

	/**
	 * Registra los hooks de WordPress necesarios.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
		add_filter( 'plugin_action_links_' . BANNER_MCP_PLUGIN_BASENAME, array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * Registra la página de ajustes bajo el menú "Ajustes" de WordPress.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'Banner MCP', 'banner-mcp' ),
			__( 'Banner MCP', 'banner-mcp' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Registra los ajustes, secciones y campos con la Settings API.
	 *
	 * Se registran dos opciones:
	 * - banner_mcp_enabled: activa o desactiva el banner (0/1).
	 * - banner_mcp_text: texto que se muestra en el banner.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::OPTIONS_GROUP,
			BANNER_MCP_OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_enabled' ),
				'default'           => false,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			BANNER_MCP_OPTION_TEXT,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_settings_section(
			self::SECTION_MAIN,
			__( 'Configuración del banner', 'banner-mcp' ),
			array( __CLASS__, 'render_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			BANNER_MCP_OPTION_ENABLED,
			__( 'Mostrar banner', 'banner-mcp' ),
			array( __CLASS__, 'render_field_enabled' ),
			self::PAGE_SLUG,
			self::SECTION_MAIN
		);

		add_settings_field(
			BANNER_MCP_OPTION_TEXT,
			__( 'Texto del banner', 'banner-mcp' ),
			array( __CLASS__, 'render_field_text' ),
			self::PAGE_SLUG,
			self::SECTION_MAIN
		);
	}

	/**
	 * Sanitiza el valor del checkbox de activación.
	 *
	 * Convierte cualquier valor recibido del formulario a '1' o '0'.
	 *
	 * @since  1.0.0
	 * @param  mixed $value Valor recibido del formulario.
	 * @return string '1' si está marcado, '0' en caso contrario.
	 */
	public static function sanitize_enabled( $value ) {
		return ( '1' === $value || true === $value ) ? '1' : '0';
	}

	/**
	 * Renderiza la descripción de la sección de ajustes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_section_description() {
		echo '<p>' . esc_html__( 'Configura el mensaje y la visibilidad del banner de anuncio en la parte superior de la web. Estos ajustes también pueden modificarse remotamente desde Claude u otros clientes MCP.', 'banner-mcp' ) . '</p>';
	}

	/**
	 * Renderiza el campo checkbox de activación.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_field_enabled() {
		$enabled = get_option( BANNER_MCP_OPTION_ENABLED, '0' );
		?>
		<label for="<?php echo esc_attr( BANNER_MCP_OPTION_ENABLED ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( BANNER_MCP_OPTION_ENABLED ); ?>"
				name="<?php echo esc_attr( BANNER_MCP_OPTION_ENABLED ); ?>"
				value="1"
				<?php checked( '1', $enabled ); ?>
			/>
			<?php esc_html_e( 'Activar el banner en el frontend', 'banner-mcp' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Cuando está marcado, se muestra la barra de anuncio en la parte superior de todas las páginas públicas del sitio.', 'banner-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderiza el campo de texto del mensaje del banner.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_field_text() {
		$text = get_option( BANNER_MCP_OPTION_TEXT, '' );
		?>
		<input
			type="text"
			id="<?php echo esc_attr( BANNER_MCP_OPTION_TEXT ); ?>"
			name="<?php echo esc_attr( BANNER_MCP_OPTION_TEXT ); ?>"
			value="<?php echo esc_attr( $text ); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e( 'Escribe aquí el mensaje del banner…', 'banner-mcp' ); ?>"
			maxlength="300"
		/>
		<p class="description">
			<?php esc_html_e( 'Mensaje que se mostrará en la barra de anuncio. Máximo 300 caracteres.', 'banner-mcp' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderiza el HTML completo de la página de ajustes.
	 *
	 * Verifica capacidades antes de renderizar para prevenir accesos no autorizados.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'banner-mcp' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="banner-mcp-admin-intro">
				<p>
					<?php
					esc_html_e(
						'Este plugin muestra un banner personalizable en la parte superior del sitio web. La configuración puede gestionarse desde aquí o de forma remota mediante Claude u otros clientes MCP que tengan acceso a esta instalación de WordPress a través del MCP Adapter.',
						'banner-mcp'
					);
					?>
				</p>

				<?php if ( class_exists( 'WP\MCP\Core\McpAdapter' ) ) : ?>
					<div class="notice notice-success inline">
						<p>
							<strong><?php esc_html_e( 'MCP Adapter detectado.', 'banner-mcp' ); ?></strong>
							<?php esc_html_e( 'Las habilidades de este plugin están disponibles en el servidor MCP.', 'banner-mcp' ); ?>
							<code>/wp-json/mcp/mcp-adapter-default-server</code>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-warning inline">
						<p>
							<strong><?php esc_html_e( 'MCP Adapter no detectado.', 'banner-mcp' ); ?></strong>
							<?php
							printf(
								/* translators: %s: URL de descarga del MCP Adapter */
								esc_html__( 'Para controlar este plugin desde Claude, instala el %s.', 'banner-mcp' ),
								'<a href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noopener noreferrer">MCP Adapter</a>'
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTIONS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Guardar cambios', 'banner-mcp' ) );
				?>
			</form>

			<hr />

			<div class="banner-mcp-mcp-info">
				<h2><?php esc_html_e( 'Habilidades MCP disponibles', 'banner-mcp' ); ?></h2>
				<p><?php esc_html_e( 'Una vez instalado y configurado el MCP Adapter, Claude puede utilizar las siguientes habilidades para gestionar este banner de forma remota:', 'banner-mcp' ); ?></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nombre de la habilidad', 'banner-mcp' ); ?></th>
							<th><?php esc_html_e( 'Descripción', 'banner-mcp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>banner-mcp/get-settings</code></td>
							<td><?php esc_html_e( 'Devuelve el estado actual del banner: texto y si está activado.', 'banner-mcp' ); ?></td>
						</tr>
						<tr>
							<td><code>banner-mcp/update-settings</code></td>
							<td><?php esc_html_e( 'Actualiza el texto del banner, el estado de activación, o ambos a la vez.', 'banner-mcp' ); ?></td>
						</tr>
						<tr>
							<td><code>banner-mcp/toggle-banner</code></td>
							<td><?php esc_html_e( 'Invierte el estado del banner: si está activado lo desactiva, y viceversa.', 'banner-mcp' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Encola los estilos CSS específicos de la página de ajustes del plugin.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix Sufijo del hook de la pantalla actual.
	 * @return void
	 */
	public static function enqueue_admin_styles( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'banner-mcp-admin',
			BANNER_MCP_PLUGIN_URL . 'assets/css/banner-mcp-admin.css',
			array(),
			BANNER_MCP_VERSION
		);
	}

	/**
	 * Añade un enlace directo a "Ajustes" en la lista de plugins.
	 *
	 * @since 1.0.0
	 * @param array $links Array de enlaces de acción del plugin.
	 * @return array Array modificado con el enlace a los ajustes.
	 */
	public static function add_settings_link( array $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Ajustes', 'banner-mcp' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
