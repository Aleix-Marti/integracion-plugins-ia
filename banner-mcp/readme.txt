=== Banner MCP ===
Contributors:      aleixmarti
Tags:              banner, anuncio, mcp, ia, modelo-de-contexto
Requires at least: 6.9
Tested up to:      7.0
Stable tag:        1.0.0
Requires PHP:      8.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Muestra una barra de anuncio personalizable en la parte superior del sitio. Se puede
controlar remotamente desde Claude u otros clientes MCP gracias al MCP Adapter.

== Descripción ==

**Banner MCP** es un plugin de WordPress que muestra una barra de anuncio fija en la
parte superior de todas las páginas públicas del sitio. Lo diferencial de este plugin
frente a otros similares es su integración nativa con la **Abilities API de WordPress 6.9**
y el **MCP Adapter**, lo que permite gestionar el banner de forma remota desde Claude
u otros clientes MCP sin necesidad de acceder al panel de administración.

=== Funcionalidades principales ===

* **Barra de anuncio fija** en la parte superior del frontend.
* **Página de ajustes** en *Ajustes → Banner MCP* con campo de texto y checkbox.
* **Integración MCP completa**: tres habilidades registradas en la Abilities API.
* **Compatible con la admin bar** de WordPress (se desplaza correctamente).
* **Fallback para temas sin wp_body_open()**: inserta el banner mediante JavaScript.
* **Accesible**: usa el atributo `role="banner"` y `aria-label`.
* **Responsive**: la barra se adapta a pantallas pequeñas.
* Cumple íntegramente con los **WordPress Coding Standards (WPCS)**.
* **Sin dependencias externas** de PHP: solo requiere WordPress core.

=== Habilidades MCP ===

Una vez instalado el MCP Adapter, Claude puede utilizar las siguientes habilidades:

**`banner-mcp/get-settings`**
Devuelve el estado actual del banner. Sin parámetros de entrada.
Respuesta: `{ "enabled": true, "text": "Texto actual del banner" }`

**`banner-mcp/update-settings`**
Actualiza el texto, el estado de activación, o ambos en una sola llamada.
Parámetros (todos opcionales):
- `text` (string): Nuevo texto del banner. Máx. 300 caracteres.
- `enabled` (boolean): `true` para activar, `false` para desactivar.

Respuesta: `{ "success": true, "enabled": true, "text": "Nuevo texto", "updated": ["text", "enabled"] }`

**`banner-mcp/toggle-banner`**
Invierte el estado del banner. Sin parámetros de entrada.
Respuesta: `{ "enabled": false, "previous_state": true, "text": "Texto actual" }`

=== Requisitos ===

* **WordPress 6.9 o superior** (Abilities API nativa).
* **PHP 8.0 o superior**.
* **MCP Adapter** instalado y activo (para la integración con Claude/MCP).
* Opcional: `@automattic/mcp-wordpress-remote` (proxy local para Claude Code/Desktop).

=== Arquitectura técnica ===

El plugin NO crea ningún endpoint REST propio ni ningún servidor MCP independiente.
En su lugar, registra habilidades usando la Abilities API nativa de WordPress 6.9
(`wp_register_ability()`), que el MCP Adapter convierte automáticamente en herramientas
MCP expuestas en `/wp-json/mcp/mcp-adapter-default-server`.

El flujo de comunicación con Claude es el siguiente:

  Claude Code / Claude Desktop
      │
      │  stdio (MCP JSON-RPC)
      ▼
  @automattic/mcp-wordpress-remote  ← proxy local (npx)
      │
      │  HTTP + Application Password
      ▼
  WordPress: /wp-json/mcp/mcp-adapter-default-server
      │
      │  Abilities API
      ▼
  banner-mcp/get-settings
  banner-mcp/update-settings
  banner-mcp/toggle-banner

== Instalación ==

= Instalación estándar =

1. Descarga el ZIP del plugin.
2. En el panel de WordPress, ve a **Plugins → Añadir nuevo → Subir plugin**.
3. Selecciona el ZIP y haz clic en **Instalar ahora**.
4. Activa el plugin.
5. Ve a **Ajustes → Banner MCP** para configurar el mensaje y activar el banner.

= Instalación con WP-CLI =

  wp plugin install banner-mcp.zip --activate

= Configuración de la integración MCP (paso a paso) =

**Paso 1: Instalar el MCP Adapter**

  wp plugin install https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip --activate

**Paso 2: Crear una Application Password en WordPress**

1. Ve a **Usuarios → Tu perfil**.
2. Desplázate hasta la sección **Contraseñas de aplicación**.
3. Introduce un nombre descriptivo (p. ej., "Claude MCP") y haz clic en **Añadir nueva contraseña de aplicación**.
4. Copia la contraseña generada (solo se muestra una vez).

**Paso 3: Configurar el cliente MCP**

Añade la siguiente configuración al fichero `~/.claude/claude_desktop_config.json`
(Claude Desktop) o al fichero MCP de Claude Code `~/.claude.json`:

  {
    "mcpServers": {
      "mi-wordpress": {
        "command": "npx",
        "args": [
          "-y",
          "@automattic/mcp-wordpress-remote@latest"
        ],
        "env": {
          "WP_API_URL": "https://TU-DOMINIO.com/wp-json/mcp/mcp-adapter-default-server",
          "WP_API_USERNAME": "tu-usuario-wordpress",
          "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
        }
      }
    }
  }

Sustituye `TU-DOMINIO.com`, `tu-usuario-wordpress` y la contraseña por tus valores reales.

**Paso 4: Verificar la conexión**

Reinicia Claude Desktop o recarga la sesión de Claude Code. Las habilidades
`banner-mcp/get-settings`, `banner-mcp/update-settings` y `banner-mcp/toggle-banner`
deberían aparecer disponibles.

Puedes verificarlo en Claude con:
  "¿Qué habilidades de WordPress tienes disponibles?"
  "¿Cuál es el estado actual del banner?"

== Preguntas frecuentes ==

= ¿Necesito el MCP Adapter para usar el banner? =

No. El banner funciona de forma totalmente independiente. La página de ajustes en
*Ajustes → Banner MCP* está siempre disponible. El MCP Adapter solo es necesario
si quieres controlar el banner desde Claude u otros clientes MCP.

= ¿Qué pasa si desactivo el plugin? =

Los ajustes (texto y estado) se conservan en la base de datos. Al reactivar el plugin,
todo estará como lo dejaste.

= ¿Qué pasa si desinstalo el plugin? =

Se eliminan todas las opciones de la base de datos (`banner_mcp_text` y `banner_mcp_enabled`).
La instalación queda completamente limpia.

= ¿Es compatible con mi tema? =

Sí. El plugin incluye un fallback de JavaScript para temas que no implementan la función
`wp_body_open()`. En el caso improbable de que el banner interfiera con la cabecera de tu
tema, puedes añadir CSS personalizado:

  body.banner-mcp-active .site-header {
    top: var(--banner-mcp-height, 44px);
  }

= ¿Puedo cambiar el color o el estilo del banner? =

Sí. El plugin usa variables CSS personalizadas. Puedes sobreescribirlas desde el
personalizador de temas o un plugin de CSS:

  :root {
    --banner-mcp-bg:        #e52d27;   /* Fondo rojo */
    --banner-mcp-color:     #ffffff;   /* Texto blanco */
    --banner-mcp-height:    52px;      /* Altura mayor */
    --banner-mcp-font-size: 1rem;
  }

= ¿Funciona en WordPress Multisite? =

El plugin está diseñado para instalaciones de un solo sitio. En Multisite, cada subsite
tendría su propia configuración si el plugin se activa por sitio (no en red).

= ¿Requiere HTTPS para la integración MCP? =

Las Application Passwords de WordPress requieren HTTPS en entornos de producción.
En entornos locales (como LocalWP o DDEV) funciona sin HTTPS. El MCP Adapter también
soporta autenticación alternativa.

= ¿Cómo le digo a Claude que active o desactive el banner? =

Ejemplos de comandos en lenguaje natural:
* "Activa el banner de la web."
* "Desactiva el banner de anuncio."
* "Cambia el texto del banner a '¡Nueva colección disponible!'"
* "Muéstrame la configuración actual del banner."
* "Actualiza el banner con el texto 'Envío gratis' y actívalo."

== Capturas de pantalla ==

1. Página de ajustes en el panel de administración.
2. Banner de anuncio en el frontend del sitio.
3. Tabla de habilidades MCP disponibles en la página de ajustes.

== Registro de cambios ==

= 1.0.0 =
* Versión inicial.
* Página de ajustes con campo de texto y checkbox.
* Banner fijo en la parte superior del frontend.
* Tres habilidades registradas en la Abilities API de WordPress 6.9.
* Compatibilidad con MCP Adapter para integración con Claude.
* Fallback JavaScript para temas sin wp_body_open().
* Estilos responsive con variables CSS personalizables.
* Compatible con la barra de administración de WordPress.
* Cumple WordPress Coding Standards (WPCS).

== Actualización ==

No se requieren pasos especiales para actualizar. Los ajustes se conservan entre versiones.

== Privacidad ==

Este plugin no recopila, almacena ni transmite datos personales de los visitantes.
Las únicas opciones que guarda son el texto del banner y su estado de activación,
ambos en la tabla `wp_options` de la base de datos del sitio.
