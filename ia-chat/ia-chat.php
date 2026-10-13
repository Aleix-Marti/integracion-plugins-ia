<?php
/**
 * Plugin Name: IA Chat
 * Description: Añade un chat flotante de Claude a las páginas mediante el shortcode [ia_chat].
 * Version:     1.0.0
 * Author:      Demo IA
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'IAC_VERSION', '1.0.0' );
define( 'IAC_OPTION_KEY', 'iac_settings' );
define( 'IAC_DEFAULT_MODEL', 'claude-haiku-4-5' );

// Añade el menú de configuración del chat.
add_action( 'admin_menu', 'iac_add_admin_menu' );
function iac_add_admin_menu() {
	add_options_page(
		'IA Chat',
		'IA Chat',
		'manage_options',
		'iac-settings',
		'iac_render_settings_page'
	);
}

// Carga el selector de color y los estilos solo en la página de ajustes.
add_action( 'admin_enqueue_scripts', 'iac_enqueue_admin_assets' );
function iac_enqueue_admin_assets( $hook_suffix ) {
	if ( 'settings_page_iac-settings' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		'jQuery(function($){ $(".iac-color-picker").wpColorPicker(); });'
	);
}

// Muestra y procesa los ajustes del widget.
function iac_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permisos para acceder a esta página.' );
	}

	if ( isset( $_POST['iac_save'] ) ) {
		check_admin_referer( 'iac_save_settings' );

		$current  = iac_get_settings();
		$key_input = wp_unslash( $_POST['api_key'] ?? '' );
		$color_input = wp_unslash( $_POST['color'] ?? '' );
		$instructions_input = wp_unslash( $_POST['instructions'] ?? '' );
		$token_input = $_POST['max_tokens'] ?? 800;
		$position_input = $_POST['position'] ?? '';
		$new_key  = is_string( $key_input ) ? sanitize_text_field( $key_input ) : '';
		$settings = [
			'api_key'           => ! empty( $_POST['clear_api_key'] ) ? '' : ( '' !== $new_key ? $new_key : $current['api_key'] ),
			'color'             => is_string( $color_input ) ? ( sanitize_hex_color( $color_input ) ?: '#4f46e5' ) : '#4f46e5',
			'position'          => in_array( $position_input, [ 'right', 'left' ], true ) ? $position_input : 'right',
			'max_tokens'        => min( 4096, max( 100, is_scalar( $token_input ) ? absint( $token_input ) : 800 ) ),
			'instructions'      => is_string( $instructions_input ) ? sanitize_textarea_field( $instructions_input ) : '',
		];

		update_option( IAC_OPTION_KEY, $settings );
		echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
	}

	$settings = iac_get_settings();
	?>
	<div class="wrap">
		<h1>IA Chat – Configuración</h1>
		<p>El chat utiliza Claude de Anthropic. La API Key se guarda en WordPress y nunca se envía al navegador.</p>
		<form method="post">
			<?php wp_nonce_field( 'iac_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="iac-api-key">API Key de Anthropic</label></th>
					<td>
						<input type="password" id="iac-api-key" name="api_key" class="regular-text"
							autocomplete="new-password" value="" />
						<p class="description">
							<?php echo '' !== $settings['api_key'] ? 'Hay una clave guardada. Déjalo vacío para conservarla.' : 'Introduce tu clave de Anthropic.'; ?>
							<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">Obtener API Key</a>.
						</p>
						<?php if ( '' !== $settings['api_key'] ) : ?>
							<label><input type="checkbox" name="clear_api_key" value="1" /> Eliminar la clave guardada</label>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iac-color">Color de la burbuja</label></th>
					<td><input type="text" id="iac-color" name="color" class="iac-color-picker"
						value="<?php echo esc_attr( $settings['color'] ); ?>" data-default-color="#4f46e5" /></td>
				</tr>
				<tr>
					<th scope="row">Posición</th>
					<td>
						<label><input type="radio" name="position" value="right" <?php checked( $settings['position'], 'right' ); ?> /> Derecha</label><br />
						<label><input type="radio" name="position" value="left" <?php checked( $settings['position'], 'left' ); ?> /> Izquierda</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iac-max-tokens">Límite de tokens</label></th>
					<td>
						<input type="number" id="iac-max-tokens" name="max_tokens" min="100" max="4096" step="50"
							value="<?php echo esc_attr( $settings['max_tokens'] ); ?>" class="small-text" />
						<p class="description">Máximo de tokens que Claude podrá generar por respuesta (100–4096).</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="iac-instructions">Instrucciones para Claude</label></th>
					<td>
						<textarea id="iac-instructions" name="instructions" rows="6" class="large-text"><?php echo esc_textarea( $settings['instructions'] ); ?></textarea>
						<p class="description">Define el tema, el tono o los límites de las respuestas. Por ejemplo: «Responde solo preguntas sobre jardinería».</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Guardar configuración', 'primary', 'iac_save' ); ?>
		</form>
		<p>Para mostrar el widget en una página, añade el shortcode <code>[ia_chat]</code>.</p>
	</div>
	<?php
}

// Devuelve los ajustes existentes combinados con valores predeterminados seguros.
function iac_get_settings() {
	$defaults = [
		'api_key'      => '',
		'color'        => '#4f46e5',
		'position'     => 'right',
		'max_tokens'   => 800,
		'instructions' => '',
	];
	$settings = wp_parse_args( get_option( IAC_OPTION_KEY, [] ), $defaults );

	$settings['api_key']      = is_string( $settings['api_key'] ) ? $settings['api_key'] : '';
	$settings['color']        = is_string( $settings['color'] ) ? ( sanitize_hex_color( $settings['color'] ) ?: $defaults['color'] ) : $defaults['color'];
	$settings['position']     = is_string( $settings['position'] ) && in_array( $settings['position'], [ 'right', 'left' ], true ) ? $settings['position'] : 'right';
	$settings['max_tokens']   = min( 4096, max( 100, is_scalar( $settings['max_tokens'] ) ? absint( $settings['max_tokens'] ) : $defaults['max_tokens'] ) );
	$settings['instructions'] = is_string( $settings['instructions'] ) ? $settings['instructions'] : '';

	return $settings;
}

// Añade los recursos del widget solo a páginas donde se usa el shortcode.
add_action( 'wp_enqueue_scripts', 'iac_register_assets' );
function iac_register_assets() {
	wp_register_style( 'iac-chat', plugins_url( 'assets/ia-chat.css', __FILE__ ), [], IAC_VERSION );
	wp_register_script( 'iac-chat', plugins_url( 'assets/ia-chat.js', __FILE__ ), [], IAC_VERSION, true );
}

// Crea el botón flotante y la ventana de conversación del shortcode.
add_shortcode( 'ia_chat', 'iac_render_chat' );
function iac_render_chat() {
	static $instance = 0;
	$instance++;
	$settings = iac_get_settings();
	$input_id = 'iac-chat-input-' . $instance;

	wp_enqueue_style( 'iac-chat' );
	wp_enqueue_script( 'iac-chat' );

	ob_start();
	?>
	<div class="iac-chat"
		data-position="<?php echo esc_attr( $settings['position'] ); ?>"
		data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		data-nonce="<?php echo esc_attr( wp_create_nonce( 'iac_chat_message' ) ); ?>"
		style="--iac-chat-color: <?php echo esc_attr( $settings['color'] ); ?>">
		<section class="iac-chat-panel" aria-label="Chat con Claude" hidden>
			<header class="iac-chat-header">
				<strong>Chat con Claude</strong>
				<button type="button" class="iac-chat-close" aria-label="Cerrar chat">&times;</button>
			</header>
			<div class="iac-chat-messages" role="log" aria-live="polite" aria-relevant="additions text">
				<div class="iac-chat-message iac-chat-message--assistant">¡Hola! ¿En qué puedo ayudarte?</div>
			</div>
			<form class="iac-chat-form">
				<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>">Escribe tu mensaje</label>
				<textarea id="<?php echo esc_attr( $input_id ); ?>" rows="2" maxlength="4000" required placeholder="Escribe tu mensaje…"></textarea>
				<button type="submit">Enviar</button>
			</form>
		</section>
		<button type="button" class="iac-chat-toggle" aria-expanded="false" aria-label="Abrir chat con Claude">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2Zm-2 11H6v-2h12Zm0-4H6V7h12Z"/></svg>
		</button>
	</div>
	<?php
	return ob_get_clean();
}

// Registra el mismo controlador para visitantes y usuarios autenticados.
add_action( 'wp_ajax_iac_chat_message', 'iac_handle_chat_message' );
add_action( 'wp_ajax_nopriv_iac_chat_message', 'iac_handle_chat_message' );
function iac_handle_chat_message() {
	check_ajax_referer( 'iac_chat_message', 'nonce' );

	$settings = iac_get_settings();
	if ( '' === $settings['api_key'] ) {
		wp_send_json_error( [ 'message' => 'El chat no está configurado. El administrador debe añadir la API Key de Anthropic.' ], 503 );
	}

	// Limita el consumo por dirección de red para reducir el abuso del endpoint público.
	$remote_ip = wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' );
	$ip        = is_string( $remote_ip ) ? sanitize_text_field( $remote_ip ) : '';
	$rate_key  = 'iac_rate_' . md5( $ip );
	$requests  = (int) get_transient( $rate_key );
	if ( $requests >= 10 ) {
		wp_send_json_error( [ 'message' => 'Has enviado demasiados mensajes. Espera un minuto e inténtalo de nuevo.' ], 429 );
	}
	set_transient( $rate_key, $requests + 1, MINUTE_IN_SECONDS );

	$raw_message = wp_unslash( $_POST['message'] ?? '' );
	$message     = is_string( $raw_message ) ? sanitize_textarea_field( $raw_message ) : '';
	if ( '' === trim( $message ) || strlen( $message ) > 4000 ) {
		wp_send_json_error( [ 'message' => 'El mensaje está vacío o supera el límite de 4000 caracteres.' ], 400 );
	}

	$messages = [];
	$raw_history = wp_unslash( $_POST['history'] ?? '[]' );
	$history     = is_string( $raw_history ) ? json_decode( $raw_history, true ) : null;
	if ( is_array( $history ) ) {
		foreach ( array_slice( $history, -10 ) as $item ) {
			if ( ! is_array( $item ) || ! in_array( $item['role'] ?? '', [ 'user', 'assistant' ], true ) || ! is_string( $item['content'] ?? null ) ) {
				continue;
			}
			$content = sanitize_textarea_field( $item['content'] );
			if ( '' !== trim( $content ) && strlen( $content ) <= 4000 ) {
				$messages[] = [ 'role' => $item['role'], 'content' => $content ];
			}
		}
	}
	$messages[] = [ 'role' => 'user', 'content' => $message ];

	$system = '' !== $settings['instructions']
		? $settings['instructions']
		: 'Eres un asistente útil y claro. Responde en español.';

	$response = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		[
			'timeout' => 60,
			'headers' => [
				'x-api-key'         => $settings['api_key'],
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			],
			'body' => wp_json_encode(
				[
					'model'      => IAC_DEFAULT_MODEL,
					'max_tokens' => $settings['max_tokens'],
					'system'     => $system,
					'messages'   => $messages,
				]
			),
		]
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [ 'message' => 'Error de conexión con Anthropic: ' . $response->get_error_message() ], 502 );
	}

	$status = wp_remote_retrieve_response_code( $response );
	$data   = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $status || ! is_array( $data ) ) {
		$error = is_array( $data ) && is_string( $data['error']['message'] ?? null )
			? $data['error']['message']
			: 'Anthropic devolvió una respuesta no válida (HTTP ' . absint( $status ) . ').';
		wp_send_json_error( [ 'message' => 'Error de Claude: ' . $error ], 502 );
	}

	$text = [];
	$content_blocks = is_array( $data['content'] ?? null ) ? $data['content'] : [];
	foreach ( $content_blocks as $block ) {
		if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) && is_string( $block['text'] ?? null ) ) {
			$text[] = $block['text'];
		}
	}
	$answer = trim( implode( "\n", $text ) );
	if ( '' === $answer ) {
		wp_send_json_error( [ 'message' => 'Claude no devolvió una respuesta de texto.' ], 502 );
	}

	wp_send_json_success( [ 'message' => $answer ] );
}
