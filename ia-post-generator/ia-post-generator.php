<?php
/**
 * Plugin Name: IA Post Generator
 * Plugin URI:  https://demoeitia.com
 * Description: Genera posts de WordPress usando la API de Anthropic (modelos Claude).
 * Version:     1.1.0
 * Author:      Demo IA
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'IAPG_VERSION',   '1.2.0' );
define( 'IAPG_OPTION_KEY', 'iapg_settings' );
define( 'IAPG_DEFAULT_MODEL', 'claude-haiku-4-5' );
define( 'IAPG_CONNECTOR_ID', 'anthropic' );

// ---------------------------------------------------------------------------
// Admin menus
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
    add_menu_page(
        'IA Post Generator',
        'IA Generator',
        'edit_posts',
        'iapg-generator',
        'iapg_page_generator',
        'dashicons-superhero',
        30
    );

    add_submenu_page(
        'iapg-generator',
        'Configuración – IA Generator',
        'Configuración',
        'manage_options',
        'iapg-settings',
        'iapg_page_settings'
    );
} );

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

function iapg_page_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }

    if ( isset( $_POST['iapg_save'] ) && check_admin_referer( 'iapg_settings_save' ) ) {
        $source = sanitize_text_field( $_POST['api_key_source'] ?? '' );
        if ( 'connector' !== $source || ! iapg_is_connector_available() ) {
            $source = 'manual';
        }

        $settings = [
            'api_key'           => sanitize_text_field( $_POST['api_key'] ?? '' ),
            'api_key_source'    => $source,
            'model'             => iapg_sanitize_model( $_POST['model'] ?? '' ),
            'max_tokens'        => absint( $_POST['max_tokens'] ?? 800 ),
            'post_status'       => in_array( $_POST['post_status'] ?? '', [ 'draft', 'publish' ], true )
                                   ? $_POST['post_status'] : 'draft',
            'base_instructions' => sanitize_textarea_field( $_POST['base_instructions'] ?? '' ),
        ];
        update_option( IAPG_OPTION_KEY, $settings );
        echo '<div class="notice notice-success"><p>Configuración guardada.</p></div>';
    }

    $s      = iapg_get_settings();
    $models = iapg_get_models();

    $connector_available  = iapg_is_connector_available();
    $connector_key_source = $connector_available ? iapg_get_connector_key_source() : 'none';
    $connector_has_key    = 'none' !== $connector_key_source;
    ?>
    <div class="wrap">
        <h1>IA Post Generator – Configuración</h1>
        <form method="post">
            <?php wp_nonce_field( 'iapg_settings_save' ); ?>
            <table class="form-table" role="presentation">

                <tr>
                    <th>Origen de la API Key</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">Origen de la API Key</legend>

                            <label for="api_key_source_manual">
                                <input type="radio" id="api_key_source_manual"
                                       name="api_key_source" value="manual"
                                       <?php checked( $s['api_key_source'], 'manual' ); ?> />
                                Introducir la API Key en este plugin
                            </label>
                            <br />

                            <label for="api_key_source_connector">
                                <input type="radio" id="api_key_source_connector"
                                       name="api_key_source" value="connector"
                                       <?php checked( $s['api_key_source'], 'connector' ); ?>
                                       <?php disabled( ! $connector_available ); ?> />
                                Usar el conector de Anthropic de WordPress
                                (<em>Ajustes → Conectores</em>)
                            </label>

                            <p class="description">
                                <?php if ( ! $connector_available ) : ?>
                                    El conector de Anthropic no está disponible en esta instalación.
                                    Requiere WordPress 7.0 o superior con el conector registrado.
                                <?php elseif ( $connector_has_key ) : ?>
                                    Clave detectada en el conector
                                    (<?php echo esc_html( iapg_connector_source_label( $connector_key_source ) ); ?>).
                                    <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">Gestionar conectores</a>.
                                <?php else : ?>
                                    El conector de Anthropic está registrado pero aún no tiene ninguna clave
                                    configurada. <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">Configúrala aquí</a>.
                                <?php endif; ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th><label for="api_key">API Key de Anthropic</label></th>
                    <td>
                        <input type="password" id="api_key" name="api_key"
                               value="<?php echo esc_attr( $s['api_key'] ); ?>"
                               class="regular-text" autocomplete="off" />
                        <p class="description">
                            Obtén tu clave en
                            <a href="https://console.anthropic.com/keys" target="_blank">console.anthropic.com</a>.
                            Solo se utiliza si el origen seleccionado es <strong>este plugin</strong>.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="model">Modelo</label></th>
                    <td>
                        <select id="model" name="model">
                            <?php foreach ( $models as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"
                                    <?php selected( $s['model'], $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Para minimizar el coste y la latencia se recomienda <strong>Claude Haiku 4.5</strong>.
                            Para textos más elaborados, <strong>Claude Sonnet 5.5</strong> ofrece el mejor
                            equilibrio entre calidad y precio.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="max_tokens">Máximo de tokens de respuesta</label></th>
                    <td>
                        <input type="number" id="max_tokens" name="max_tokens"
                               value="<?php echo esc_attr( $s['max_tokens'] ); ?>"
                               min="200" max="4000" step="50" class="small-text" />
                        <p class="description">
                            Recomendado: 600–1000 para artículos cortos, 1500–2000 para artículos largos.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="post_status">Estado del post generado</label></th>
                    <td>
                        <select id="post_status" name="post_status">
                            <option value="draft"   <?php selected( $s['post_status'], 'draft'   ); ?>>Borrador</option>
                            <option value="publish" <?php selected( $s['post_status'], 'publish' ); ?>>Publicado</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th style="padding-top: 24px;">
                        <label for="base_instructions">Instrucciones base</label>
                    </th>
                    <td style="padding-top: 24px;">
                        <textarea
                            id="base_instructions"
                            name="base_instructions"
                            rows="8"
                            class="large-text"
                            placeholder="Ej: Escribe siempre en catalán. Usa un tono divulgativo y cercano. Incluye siempre una introducción, al menos tres secciones con subtítulo h2, y una conclusión. Evita el lenguaje técnico excesivo."
                        ><?php echo esc_textarea( $s['base_instructions'] ); ?></textarea>
                        <p class="description">
                            Estas instrucciones se añaden automáticamente a <strong>todos</strong> los prompts
                            como contexto de sistema. Define aquí el idioma, el tono, la estructura,
                            el público objetivo u otras normas editoriales que deba seguir siempre la IA.<br>
                            Si se deja vacío, la IA usará su criterio por defecto.
                        </p>
                    </td>
                </tr>

            </table>
            <?php submit_button( 'Guardar configuración', 'primary', 'iapg_save' ); ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Generator page
// ---------------------------------------------------------------------------

function iapg_page_generator() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Sin permisos.' );
    }

    $result  = null;
    $error   = null;
    $prompt  = '';

    if ( isset( $_POST['iapg_generate'] ) && check_admin_referer( 'iapg_generate_post' ) ) {
        $prompt = sanitize_textarea_field( $_POST['prompt'] ?? '' );

        if ( empty( $prompt ) ) {
            $error = 'Escribe una instrucción antes de generar.';
        } else {
            $result = iapg_generate_and_create_post( $prompt );
            if ( is_wp_error( $result ) ) {
                $error  = $result->get_error_message();
                $result = null;
            }
        }
    }

    $s = iapg_get_settings();
    ?>
    <div class="wrap">
        <h1>IA Post Generator</h1>
        <p class="description">
            Escribe una instrucción en lenguaje natural y el agente creará el post automáticamente.
        </p>

        <?php if ( $error ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
        <?php endif; ?>

        <?php if ( $result ) : ?>
            <div class="notice notice-success">
                <p>
                    Post creado correctamente:
                    <strong><?php echo esc_html( $result['title'] ); ?></strong> —
                    <a href="<?php echo esc_url( get_edit_post_link( $result['post_id'] ) ); ?>" target="_blank">
                        Editar post
                    </a>
                    &nbsp;|&nbsp;
                    <a href="<?php echo esc_url( get_permalink( $result['post_id'] ) ); ?>" target="_blank">
                        Ver post
                    </a>
                </p>
                <details style="margin: 8px 0 4px;">
                    <summary style="cursor:pointer; color:#2271b1;">Ver contenido generado</summary>
                    <div style="background:#fff;border:1px solid #ddd;padding:12px;margin-top:8px;max-height:300px;overflow:auto;">
                        <?php echo wp_kses_post( $result['content'] ); ?>
                    </div>
                </details>
                <p style="color:#666;font-size:12px;">
                    Tokens usados — entrada: <?php echo intval( $result['input_tokens'] ); ?> &nbsp;|&nbsp;
                    salida: <?php echo intval( $result['output_tokens'] ); ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $s['base_instructions'] ) ) : ?>
            <div style="background:#f0f6fc;border-left:3px solid #2271b1;padding:8px 12px;margin:12px 0;border-radius:0 4px 4px 0;">
                <strong style="font-size:12px;color:#2271b1;">Instrucciones base activas:</strong>
                <p style="margin:4px 0 0;font-size:12px;color:#444;white-space:pre-wrap;"><?php echo esc_html( $s['base_instructions'] ); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" style="margin-top:16px;">
            <?php wp_nonce_field( 'iapg_generate_post' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="prompt">Instrucción</label></th>
                    <td>
                        <textarea id="prompt" name="prompt" rows="4" class="large-text"
                                  placeholder="Ej: Crea un artículo sobre las últimas novedades de CSS en 2024"
                        ><?php echo esc_textarea( $prompt ); ?></textarea>
                        <p class="description">
                            Puedes incluir tono, longitud, idioma o cualquier detalle adicional.
                            Las instrucciones base configuradas se aplicarán siempre.
                        </p>
                    </td>
                </tr>
            </table>
            <?php if ( '' === iapg_resolve_api_key( $s ) ) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php if ( 'connector' === $s['api_key_source'] ) : ?>
                            El conector de Anthropic no tiene ninguna API Key configurada.
                            <a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>">
                                Ir a Conectores
                            </a>
                        <?php else : ?>
                            No hay API Key configurada.
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=iapg-settings' ) ); ?>">
                                Ir a Configuración
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            <?php submit_button( '✦ Generar post', 'primary large', 'iapg_generate', true,
                [ 'id' => 'iapg-submit' ] ); ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Core: call Anthropic API and create post
// ---------------------------------------------------------------------------

function iapg_generate_and_create_post( string $user_prompt ) {
    $settings = iapg_get_settings();

    $api_key = iapg_resolve_api_key( $settings );

    if ( '' === $api_key ) {
        $msg = 'connector' === $settings['api_key_source']
            ? 'El conector de Anthropic no tiene ninguna API Key configurada. Añádela en Ajustes → Conectores o cambia el origen de la clave.'
            : 'Configura la API Key de Anthropic antes de generar.';

        return new \WP_Error( 'no_api_key', $msg );
    }

    // Build system prompt: AI returns structured blocks, PHP converts to Gutenberg markup.
    // This approach uses fewer output tokens and guarantees valid block format.
    $system_fixed = <<<'PROMPT'
Eres un redactor web. Responde SOLO con JSON válido, sin texto adicional ni bloques de código.
El JSON debe tener estas claves:
- "title": string
- "excerpt": string (máx 30 palabras, sin HTML)
- "blocks": array de objetos, cada uno con "type" y contenido según el tipo:
  - { "type": "paragraph", "content": "texto plano o con <strong> y <em>" }
  - { "type": "heading", "level": 2, "content": "texto del título" }  (level puede ser 2 o 3)
  - { "type": "quote", "content": "texto de la cita", "cite": "fuente opcional" }
  - { "type": "list", "items": ["elemento 1", "elemento 2"] }
Usa solo estos cuatro tipos. No incluyas HTML de bloque en los contenidos (sin <p>, <h2>, <ul>, <blockquote>).
PROMPT;

    $system = ! empty( $settings['base_instructions'] )
        ? $system_fixed . "\n\nInstrucciones editoriales a seguir siempre:\n" . $settings['base_instructions']
        : $system_fixed;

    $user = "Instrucción: {$user_prompt}\n\nGenera el JSON ahora.";

    $body = wp_json_encode( [
        'model'      => $settings['model'],
        'max_tokens' => $settings['max_tokens'],
        'system'     => $system,
        'messages'   => [
            [ 'role' => 'user', 'content' => $user ],
        ],
    ] );

    $response = wp_remote_post(
        'https://api.anthropic.com/v1/messages',
        [
            'timeout' => 60,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => $body,
        ]
    );

    if ( is_wp_error( $response ) ) {
        return new \WP_Error( 'http_error', 'Error de red: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $raw  = wp_remote_retrieve_body( $response );
    $data = json_decode( $raw, true );

    if ( ! is_array( $data ) ) {
        return new \WP_Error( 'invalid_api_response', "Anthropic devolvió una respuesta no válida (HTTP {$code})." );
    }

    if ( $code !== 200 ) {
        $msg = $data['error']['message'] ?? "Error HTTP {$code}";
        $msg = is_string( $msg ) ? $msg : "Error HTTP {$code}";
        return new \WP_Error( 'api_error', "Error de la API: {$msg}" );
    }

    $text_blocks    = [];
    $content_blocks = is_array( $data['content'] ?? null ) ? $data['content'] : [];
    foreach ( $content_blocks as $content_block ) {
        if ( is_array( $content_block ) && 'text' === ( $content_block['type'] ?? '' ) && is_string( $content_block['text'] ?? null ) ) {
            $text_blocks[] = $content_block['text'];
        }
    }
    $text = implode( "\n", $text_blocks );

    if ( '' === trim( $text ) ) {
        return new \WP_Error( 'empty_response', 'Anthropic no devolvió ningún bloque de texto. Revisa el modelo configurado e inténtalo de nuevo.' );
    }

    if ( 'max_tokens' === ( $data['stop_reason'] ?? '' ) ) {
        return new \WP_Error( 'response_truncated', 'La respuesta se cortó al alcanzar el máximo de tokens. Aumenta “Máximo de tokens de respuesta” en Configuración e inténtalo de nuevo.' );
    }

    // Strip markdown code fences if present (```json ... ```)
    $text = preg_replace( '/^```(?:json)?\s*/i', '', trim( $text ) );
    $text = preg_replace( '/```\s*$/', '', $text );

    $post_data = json_decode( trim( $text ), true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new \WP_Error( 'invalid_json', 'La IA no devolvió JSON válido (' . json_last_error_msg() . '). Inténtalo de nuevo; si persiste, revisa las instrucciones base.' );
    }

    if ( ! is_array( $post_data ) || ! is_string( $post_data['title'] ?? null ) || '' === trim( $post_data['title'] ) || ! is_array( $post_data['blocks'] ?? null ) || empty( $post_data['blocks'] ) ) {
        return new \WP_Error( 'invalid_format', 'La IA devolvió JSON válido, pero faltan el título o los bloques del artículo. Inténtalo de nuevo; si persiste, revisa las instrucciones base.' );
    }

    if ( isset( $post_data['excerpt'] ) && ! is_string( $post_data['excerpt'] ) ) {
        return new \WP_Error( 'invalid_format', 'La IA devolvió un JSON con un resumen de formato inesperado. Inténtalo de nuevo; si persiste, revisa las instrucciones base.' );
    }

    // Convert the blocks array to valid Gutenberg block markup
    $post_content = iapg_blocks_to_gutenberg( $post_data['blocks'] );

    $post_id = wp_insert_post( [
        'post_title'   => sanitize_text_field( $post_data['title'] ),
        'post_content' => $post_content,
        'post_excerpt' => sanitize_text_field( $post_data['excerpt'] ?? '' ),
        'post_status'  => $settings['post_status'],
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post',
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return new \WP_Error( 'insert_error', 'Error al crear el post: ' . $post_id->get_error_message() );
    }

    return [
        'post_id'       => $post_id,
        'title'         => $post_data['title'],
        'content'       => $post_content,
        'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
        'output_tokens' => $data['usage']['output_tokens'] ?? 0,
    ];
}

// ---------------------------------------------------------------------------
// Gutenberg block builder
// ---------------------------------------------------------------------------

/**
 * Converts the AI-returned blocks array into valid Gutenberg block markup.
 * Building the markup in PHP guarantees correct comment delimiters and
 * avoids relying on the AI to produce syntactically exact block syntax.
 *
 * Supported types: paragraph, heading (level 2-3), quote, list.
 */
function iapg_blocks_to_gutenberg( array $blocks ): string {
    $output = [];

    foreach ( $blocks as $block ) {
        $type = $block['type'] ?? '';

        switch ( $type ) {

            case 'paragraph':
                $content = wp_kses( $block['content'] ?? '', [ 'strong' => [], 'em' => [], 'a' => [ 'href' => [], 'target' => [] ] ] );
                if ( $content === '' ) break;
                $output[] = "<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->";
                break;

            case 'heading':
                $level   = in_array( (int) ( $block['level'] ?? 2 ), [ 2, 3 ], true ) ? (int) $block['level'] : 2;
                $content = esc_html( $block['content'] ?? '' );
                if ( $content === '' ) break;
                $output[] = "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level} class=\"wp-block-heading\">{$content}</h{$level}>\n<!-- /wp:heading -->";
                break;

            case 'quote':
                $content = esc_html( $block['content'] ?? '' );
                if ( $content === '' ) break;
                $cite    = esc_html( $block['cite'] ?? '' );
                $cite_html = $cite !== '' ? "\n<cite>{$cite}</cite>" : '';
                $output[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->{$cite_html}</blockquote>\n<!-- /wp:quote -->";
                break;

            case 'list':
                $items = array_filter( (array) ( $block['items'] ?? [] ) );
                if ( empty( $items ) ) break;
                $items_html = '';
                foreach ( $items as $item ) {
                    $item_text   = esc_html( $item );
                    $items_html .= "<!-- wp:list-item -->\n<li>{$item_text}</li>\n<!-- /wp:list-item -->\n";
                }
                $output[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n{$items_html}</ul>\n<!-- /wp:list -->";
                break;
        }
    }

    return implode( "\n\n", $output );
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function iapg_get_settings(): array {
    $defaults = [
        'api_key'           => '',
        'api_key_source'    => 'manual',
        'model'             => IAPG_DEFAULT_MODEL,
        'max_tokens'        => 800,
        'post_status'       => 'draft',
        'base_instructions' => '',
    ];

    $settings = wp_parse_args( get_option( IAPG_OPTION_KEY, [] ), $defaults );

    $settings['model'] = iapg_sanitize_model( $settings['model'] );

    if ( ! in_array( $settings['api_key_source'], [ 'manual', 'connector' ], true ) ) {
        $settings['api_key_source'] = 'manual';
    }

    return $settings;
}

/**
 * Comprueba si el conector de Anthropic de WordPress 7.0 está disponible.
 */
function iapg_is_connector_available(): bool {
    return function_exists( 'wp_is_connector_registered' )
        && wp_is_connector_registered( IAPG_CONNECTOR_ID );
}

/**
 * Devuelve los metadatos de autenticación del conector de Anthropic.
 *
 * @return array{setting_name?:string,env_var_name?:string,constant_name?:string,method?:string}
 */
function iapg_get_connector_auth(): array {
    if ( ! iapg_is_connector_available() ) {
        return [];
    }

    $connector = wp_get_connector( IAPG_CONNECTOR_ID );
    $auth      = $connector['authentication'] ?? [];

    return is_array( $auth ) ? $auth : [];
}

/**
 * Obtiene la API Key del conector siguiendo la prioridad de WordPress:
 * variable de entorno → constante PHP → base de datos.
 */
function iapg_get_connector_api_key(): string {
    $auth = iapg_get_connector_auth();

    if ( 'api_key' !== ( $auth['method'] ?? '' ) ) {
        return '';
    }

    $env_var_name = $auth['env_var_name'] ?? '';
    if ( '' !== $env_var_name ) {
        $env_value = getenv( $env_var_name );
        if ( is_string( $env_value ) && '' !== $env_value ) {
            return $env_value;
        }
    }

    $constant_name = $auth['constant_name'] ?? '';
    if ( '' !== $constant_name && defined( $constant_name ) ) {
        $const_value = constant( $constant_name );
        if ( is_string( $const_value ) && '' !== $const_value ) {
            return $const_value;
        }
    }

    $setting_name = $auth['setting_name'] ?? '';
    if ( '' !== $setting_name ) {
        $db_value = get_option( $setting_name, '' );
        if ( is_string( $db_value ) && '' !== $db_value ) {
            return $db_value;
        }
    }

    return '';
}

/**
 * Indica de dónde proviene la clave del conector: 'env', 'constant', 'database' o 'none'.
 */
function iapg_get_connector_key_source(): string {
    $auth = iapg_get_connector_auth();

    if ( 'api_key' !== ( $auth['method'] ?? '' ) ) {
        return 'none';
    }

    $env_var_name = $auth['env_var_name'] ?? '';
    if ( '' !== $env_var_name ) {
        $env_value = getenv( $env_var_name );
        if ( is_string( $env_value ) && '' !== $env_value ) {
            return 'env';
        }
    }

    $constant_name = $auth['constant_name'] ?? '';
    if ( '' !== $constant_name && defined( $constant_name ) ) {
        $const_value = constant( $constant_name );
        if ( is_string( $const_value ) && '' !== $const_value ) {
            return 'constant';
        }
    }

    $setting_name = $auth['setting_name'] ?? '';
    if ( '' !== $setting_name && '' !== (string) get_option( $setting_name, '' ) ) {
        return 'database';
    }

    return 'none';
}

/**
 * Etiqueta legible para el origen de la clave del conector.
 */
function iapg_connector_source_label( string $source ): string {
    $labels = [
        'env'      => 'variable de entorno',
        'constant' => 'constante PHP',
        'database' => 'base de datos',
    ];

    return $labels[ $source ] ?? 'sin configurar';
}

/**
 * Resuelve la API Key efectiva según el origen configurado.
 */
function iapg_resolve_api_key( array $settings ): string {
    if ( 'connector' === $settings['api_key_source'] ) {
        return iapg_get_connector_api_key();
    }

    return (string) $settings['api_key'];
}

/**
 * Modelos de Anthropic disponibles (API IDs vigentes).
 *
 * @return array<string,string> API ID => etiqueta visible.
 */
function iapg_get_models(): array {
    return [
        'claude-haiku-4-5'  => 'Claude Haiku 4.5  (el más rápido y económico)',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (mejor equilibrio velocidad/calidad)',
        'claude-opus-5-5'   => 'Claude Opus 5.5   (máxima calidad · más tokens)',
        'claude-fable-5-1'  => 'Claude Fable 5.1  (razonamiento exigente · más lento y caro)',
    ];
}

/**
 * Valida el modelo y migra identificadores antiguos a los vigentes.
 */
function iapg_sanitize_model( $model ): string {
    $model = is_string( $model ) ? sanitize_text_field( $model ) : '';

    if ( isset( iapg_get_models()[ $model ] ) ) {
        return $model;
    }

    $legacy_map = [
        'claude-sonnet-4-5' => 'claude-sonnet-5-5',
        'claude-sonnet-4-6' => 'claude-sonnet-5-5',
        'claude-sonnet-5'   => 'claude-sonnet-5-5',
        'claude-opus-4-5'   => 'claude-opus-5-5',
        'claude-opus-4-6'   => 'claude-opus-5-5',
        'claude-opus-4-7'   => 'claude-opus-5-5',
        'claude-opus-4-8'   => 'claude-opus-5-5',
        'claude-opus-5'     => 'claude-opus-5-5',
        'claude-fable-5'    => 'claude-fable-5-1',
    ];

    return $legacy_map[ $model ] ?? IAPG_DEFAULT_MODEL;
}
