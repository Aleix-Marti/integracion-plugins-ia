<?php
/**
 * Renderizado del banner en el frontend del sitio web.
 *
 * Se encarga de mostrar la barra de anuncio en la parte superior de todas
 * las páginas públicas cuando el banner está activado en los ajustes.
 *
 * @package BannerMcp
 * @since   1.0.0
 */

// Evitar acceso directo al fichero.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase que gestiona la visualización del banner en el frontend.
 *
 * Inyecta el HTML del banner y los estilos CSS necesarios en las páginas
 * públicas del sitio cuando el banner está activado y tiene texto configurado.
 *
 * @since 1.0.0
 */
class Banner_Mcp_Frontend {

	/**
	 * Registra los hooks de WordPress para el frontend.
	 *
	 * Solo registra los hooks cuando NO estamos en el panel de administración,
	 * ya que el banner es exclusivo del frontend público.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_styles' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'render_banner' ) );

		/*
		 * Fallback para temas que no implementan wp_body_open().
		 * Si el tema no llama a wp_body_open() en su plantilla, utilizamos
		 * wp_head para inyectar el banner mediante JavaScript al cargar la página.
		 */
		add_action( 'wp_head', array( __CLASS__, 'maybe_inject_banner_via_js' ) );
	}

	/**
	 * Determina si el banner debe mostrarse.
	 *
	 * El banner solo se muestra cuando:
	 * - La opción de activación está a '1'.
	 * - El texto del banner no está vacío.
	 *
	 * @since 1.0.0
	 * @return bool True si el banner debe mostrarse, false en caso contrario.
	 */
	public static function should_display() {
		$enabled = get_option( BANNER_MCP_OPTION_ENABLED, '0' );
		$text    = get_option( BANNER_MCP_OPTION_TEXT, '' );

		return ( '1' === $enabled && ! empty( trim( $text ) ) );
	}

	/**
	 * Encola el fichero CSS del banner cuando este debe mostrarse.
	 *
	 * Añade también estilos en línea para compensar la altura del banner,
	 * evitando que el contenido de la página quede tapado.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function enqueue_frontend_styles() {
		if ( ! self::should_display() ) {
			return;
		}

		wp_enqueue_style(
			'banner-mcp-frontend',
			BANNER_MCP_PLUGIN_URL . 'assets/css/banner-mcp-frontend.css',
			array(),
			BANNER_MCP_VERSION
		);

		/*
		 * Compensa el desplazamiento del contenido si el tema tiene una cabecera
		 * fija (position: sticky o fixed). Se añade padding-top al <body>.
		 * El valor se calcula en CSS mediante una variable personalizada.
		 */
		wp_add_inline_style(
			'banner-mcp-frontend',
			'body.banner-mcp-active { padding-top: var(--banner-mcp-height, 44px); }'
		);
	}

	/**
	 * Renderiza el HTML del banner justo después de la apertura de <body>.
	 *
	 * Este método se engancha a wp_body_open(), disponible en temas que
	 * llaman a wp_body_open() en su plantilla (recomendado desde WP 5.2).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_banner() {
		if ( ! self::should_display() ) {
			return;
		}

		$text = get_option( BANNER_MCP_OPTION_TEXT, '' );

		// Añadir clase al body para el ajuste de padding.
		add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );

		?>
		<div id="banner-mcp" class="banner-mcp" role="banner" aria-label="<?php esc_attr_e( 'Mensaje de anuncio', 'banner-mcp' ); ?>">
			<div class="banner-mcp__inner">
				<p class="banner-mcp__text"><?php echo esc_html( $text ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Inyecta el banner mediante un script JS en línea para temas sin wp_body_open().
	 *
	 * Si el tema no implementa wp_body_open(), este fallback inserta el banner
	 * dinámicamente al principio de <body> cuando el DOM está listo.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_inject_banner_via_js() {
		if ( ! self::should_display() ) {
			return;
		}

		/*
		 * Detectamos si wp_body_open ya fue llamado en este request.
		 * Si el hook ya se ejecutó, no necesitamos el fallback.
		 * Usamos did_action() para comprobarlo.
		 */
		if ( did_action( 'wp_body_open' ) ) {
			return;
		}

		$text = get_option( BANNER_MCP_OPTION_TEXT, '' );
		?>
		<script>
		( function () {
			document.addEventListener( 'DOMContentLoaded', function () {
				if ( document.getElementById( 'banner-mcp' ) ) {
					return;
				}
				var banner = document.createElement( 'div' );
				banner.id = 'banner-mcp';
				banner.className = 'banner-mcp';
				banner.setAttribute( 'role', 'banner' );
				banner.setAttribute( 'aria-label', <?php echo wp_json_encode( __( 'Mensaje de anuncio', 'banner-mcp' ) ); ?> );
				banner.innerHTML = '<div class="banner-mcp__inner"><p class="banner-mcp__text">' + <?php echo wp_json_encode( esc_html( $text ) ); ?> + '</p></div>';
				document.body.insertBefore( banner, document.body.firstChild );
				document.body.classList.add( 'banner-mcp-active' );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Añade la clase CSS 'banner-mcp-active' al elemento <body>.
	 *
	 * Esto permite ajustar el espaciado del contenido para que no quede
	 * tapado bajo el banner fijo.
	 *
	 * @since 1.0.0
	 * @param array $classes Array de clases CSS del body.
	 * @return array Array modificado con la clase adicional.
	 */
	public static function add_body_class( array $classes ) {
		$classes[] = 'banner-mcp-active';
		return $classes;
	}
}
