# LA ROCA CRM — versión PHP + MySQL (para VPS con cPanel)

Esta carpeta es una versión paralela del CRM, adaptada para correr en un VPS
con cPanel usando PHP + MySQL en lugar de Supabase. **No reemplaza** el
`index.html` de la raíz del repo (ese sigue funcionando igual, en GitHub
Pages/Netlify con Supabase, sin cambios).

El módulo de WhatsApp (que en la primera versión de esta migración se había
quitado) fue reconstruido en PHP puro — sin Node.js, sin ngrok — hablando
directo con la API REST de Twilio. Ver la sección **"Módulo de WhatsApp"**
más abajo antes de darlo por funcionando: hoy en día sólo sirve el Sandbox
de pruebas de Twilio, no el WhatsApp Business real (desactivado por Meta).

## Qué se portó

El CRM original hablaba directo con la API REST de Supabase desde el
navegador (`SB_URL`/`SB_KEY` visibles en el código fuente) y con la API de
Claude/Anthropic con una API key embebida en el JS (ofuscada invirtiendo el
string, pero de todas formas visible y usable por cualquiera que abriera
"ver código fuente"). Esta versión:

- Mantiene el mismo diseño visual, el mismo Kanban (Recompra / Clientes
  Nuevos / Facebook-Publicidad), la gestión de clientes, el registro de
  gestiones, Equipo, Monitor, Recursos, Mensajes internos, Mi Meta y el chat
  de WhatsApp (lista de conversaciones, envío de texto/imagen/PDF, envío
  masivo con plantilla y `[Nombre]`).
- El backend de WhatsApp ya no es un servidor Node.js/Express aparte (el
  original nunca se subió a GitHub — sólo existía en la máquina del dueño).
  Se reconstruyó en PHP puro llamando directo a la API REST de Twilio con
  cURL (Twilio no exige SDK de Node, tiene API HTTP normal con Basic Auth).
- Cambia el backend a PHP + PDO/MySQL. El frontend sigue usando las mismas
  funciones internas (`sbF`/`sbUp`/`sbDel`) que antes hablaban con Supabase,
  pero ahora apuntan a `api/<tabla>.php` en el propio servidor — por eso casi
  todo el resto del código de `index.html` (Kanban, dashboards, Monitor,
  guiones de venta, cálculo de puntaje crediticio, etc.) no tuvo que tocarse.
- Autenticación por sesión de PHP: `login.php` valida el PIN contra la tabla
  `usuarios` en MySQL (antes: 14 usuarios y PINs hardcodeados en texto plano
  dentro del JS, visibles con "ver código fuente"). Ahora el PIN nunca viaja
  ni se guarda en el cliente.
- Metas de vendedores: antes era una constante `METAS` hardcodeada en el
  JS (había que editar código y volver a desplegar para cambiar una meta).
  Ahora vive en la tabla `metas` y es editable desde Equipo → botón "🎯 Meta"
  de cada usuario, sin tocar código.

## Qué se quitó o se simplificó (y por qué)

- **Asistente IA / "Motivar equipo IA" / mensajes motivacionales al iniciar
  sesión**: usaban una API key de Anthropic (Claude) **hardcodeada en el
  JS del cliente** (`'AAAEpfJB-...'.split('').reverse().join('')` — ofuscada
  pero perfectamente legible y usable por cualquiera). Es un gasto recurrente
  de pago que el dueño quiere eliminar, y además es un riesgo de seguridad
  real: cualquier persona que abra "ver código fuente" del CRM en producción
  puede copiar esa clave y usarla por su cuenta. **Se eliminó por completo**
  en esta versión — no se reemplazó por nada, ya que no es parte del flujo
  central de ventas (Kanban/clientes/gestiones/metas).
- **Análisis con IA del módulo de WhatsApp** ("Análisis del día" y la
  recomendación automática por conversación): en la versión original corrían
  contra la API de Claude, igual que el asistente del CRM. Es el mismo tipo de
  gasto recurrente que el dueño decidió eliminar de esta migración (ver punto
  anterior), así que **no se reconstruyeron**. El resto del módulo de WhatsApp
  sí se reconstruyó — ver la sección "Módulo de WhatsApp" más abajo.
- **Usuarios y PINs hardcodeados en el JS**: los 14 usuarios con sus PINs en
  texto plano vivían en una función `initDB_users()` dentro de `index.html`.
  Se movieron a la tabla `usuarios` de MySQL (con los mismos PINs, para que
  el primer despliegue funcione igual) y el login ahora se valida en el
  servidor — el PIN ya no aparece en ningún archivo que el navegador
  descargue.
- **Import de historial de pagos desde Excel** (librería `xlsx.js`, botón
  "Subir cartera"): se mantiene igual — es 100% cliente (lee el archivo en
  el navegador) y solo llama a los mismos endpoints genéricos de
  `clientes`/`gestiones`, así que no dependía de Supabase de forma especial.
- Las gráficas del Dashboard/Monitor (Chart.js) y el cálculo de puntaje
  crediticio del cliente se mantienen igual — son librerías/lógica 100%
  del lado del cliente, sin costo ni dependencia externa de pago.

## Estructura

```
php-mysql/
  schema.sql              — tablas MySQL + datos semilla (usuarios, carteras, metas, WhatsApp)
  config.php              — credenciales de la base de datos (editar antes de subir)
  config_whatsapp.php     — credenciales de Twilio (editar antes de subir)
  auth.php                — sesión PHP + helpers de filtros/orden/paginación
  login.php               — valida el PIN contra `usuarios` y abre sesión
  logout.php               — cierra la sesión
  cambiar_pin.php           — cambia el PIN del usuario logueado (utilidad opcional;
                              la forma principal de gestionar PINs sigue siendo la
                              pantalla Equipo, igual que en la versión original)
  api/
    usuarios.php            — listar/crear/editar/borrar usuarios (login y equipo)
    clientes.php             — clientes del Kanban de Recompra
    gestiones.php             — llamadas/actividad de vendedores (incluye ventas)
    leads.php                  — leads del Kanban "Clientes Nuevos"/"Facebook"
    metas.php                   — metas de ventas por vendedor y cartera
    recursos.php                  — archivos/links del equipo
    mensajes_internos.php          — chat interno del equipo
    whatsapp/
      _common.php                   — helpers compartidos (Twilio, teléfonos, DB)
      webhook.php                   — webhook entrante de Twilio (sin sesión)
      status_callback.php           — callback de estado de Twilio (sin sesión)
      conversaciones.php            — lista de conversaciones (sesión)
      mensajes.php                  — historial de un teléfono (sesión)
      responder.php                 — responder un mensaje de texto (sesión)
      enviar_masivo.php             — envío masivo con plantilla (sesión)
      masivo_estado.php             — estado de entrega post-envío masivo (sesión)
      upload_b64.php                — sube imagen/PDF en base64 a uploads/wa/ (sesión)
      enviar_imagen_url.php         — envía imagen/PDF por URL vía Twilio (sesión)
      media_proxy.php               — proxy de medios de Twilio (requieren Basic Auth) (sesión)
  uploads/wa/               — imágenes/PDFs subidos desde el chat (público, sin listado,
                              .htaccess deniega ejecutar PHP — igual patrón que otras
                              apps de La Roca)
  index.html                   — la app (mismo diseño; sin Supabase, sin asistente IA)
```

## Pasos para desplegar en cPanel

1. **Base de datos**: en cPanel → *MySQL® Databases*, crea una base de datos
   y un usuario con todos los privilegios sobre ella. Anota el nombre de la
   base, el usuario y la contraseña.
2. **Importar el esquema**: en cPanel → *phpMyAdmin*, selecciona la base de
   datos y ejecuta el contenido de `schema.sql` (pestaña "SQL"). Esto crea
   las tablas y precarga los 14 usuarios/PINs y las metas actuales — el CRM
   queda funcionando igual que hoy desde el primer despliegue.
3. **Subir los archivos**: sube toda esta carpeta (`php-mysql/`) al
   directorio público de tu dominio o subdominio (ej.
   `public_html/crm/`).
4. **Configurar credenciales**: edita `config.php` en el servidor y reemplaza
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` con los datos del paso 1.
5. **PINs**: los PINs iniciales son los mismos 14 que ya usa el equipo hoy
   (ver tabla en `schema.sql` / `CONTEXT.md`). Se pueden cambiar desde
   Equipo → editar usuario, sin tocar código.
6. **WhatsApp (opcional pero recomendado)**: edita `config_whatsapp.php` con
   tus credenciales de Twilio y sigue los pasos de la sección "Módulo de
   WhatsApp" más abajo. Si lo dejas con los valores `CAMBIAR_...`, el resto
   del CRM funciona igual — sólo la pestaña WhatsApp mostrará errores al
   intentar enviar.
7. **Permisos de escritura**: la carpeta `uploads/wa/` necesita permiso de
   escritura para el usuario de PHP (normalmente ya viene así en cPanel; si
   no, `chmod 755 uploads/wa` desde el Administrador de archivos o SSH). Ahí
   se guardan las imágenes/PDFs que se mandan por WhatsApp — deben quedar
   accesibles públicamente por URL porque Twilio necesita poder descargarlas.
8. Abre la URL de tu dominio/subdominio en el navegador — debe pedir el PIN
   igual que la versión actual.

## Migrar los datos existentes (opcional)

Los clientes, gestiones y leads que hoy están en Supabase no se migran
automáticamente — hay que exportarlos (por ejemplo desde el editor de tablas
de Supabase, a CSV) e importarlos a las tablas equivalentes en MySQL con
phpMyAdmin. Los nombres de columnas son casi idénticos (`cliente.kb_col`,
`gestiones.date_iso`, etc. — ver `schema.sql`), así que el mapeo es directo.
Si quieres, puedo ayudarte con ese paso cuando tengas el acceso al servidor.

## Cosas para revisar antes de desplegar en producción

- **Elimina la API key de Anthropic filtrada.** Aunque esta versión PHP no la
  usa, la clave sigue viva en el `index.html` que está en GitHub Pages/Netlify
  (repo raíz, líneas con `'AAAEpfJB-...'.split('').reverse()...`). Cualquiera
  puede copiarla desde "ver código fuente" del CRM en producción y generarte
  cargos en tu cuenta de Anthropic. Recomiendo revocar/rotar esa key en la
  consola de Anthropic cuanto antes, sin importar si migras el CRM completo o
  no — es independiente de esta migración.
- Los PINs de los 14 usuarios quedaron igual que en producción (mismos
  números) para que el primer despliegue no rompa el acceso de nadie; una vez
  confirmado que todos pueden entrar, considera cambiar los PINs más débiles
  (ej. los de 4 dígitos repetidos) desde la pantalla Equipo.
- El asistente de IA no está disponible en esta versión (decisión explícita
  del dueño de no pagar por funciones de IA en esta migración). El módulo de
  WhatsApp sí está de vuelta — configúralo siguiendo la sección de abajo
  antes de que el equipo dependa de él para trabajar.

## Módulo de WhatsApp

**Importante — léelo antes de anunciarle esto al equipo de ventas:**
hoy en día (jul 2026) el número de WhatsApp Business real de Meta sigue
**desactivado permanentemente** (WABA "Laroca 1", bloqueada desde agosto
2024 — ver `CONTEXT.md`). Mientras eso no se resuelva, este módulo **sólo
funciona contra el Sandbox de pruebas de Twilio**, que tiene dos límites
importantes:

- Sólo puede escribirle a números que primero le hayan mandado el mensaje
  `join men-husband` al número de Twilio `+1 415 523 8886` (opt-in manual,
  válido por 72 horas de inactividad — después hay que repetirlo).
- No es apto para mandarle mensajes a toda la cartera de clientes todavía;
  es un ambiente de pruebas, pensado para validar que el envío/recepción
  funciona antes de tener un número de producción propio.

El dueño ya fue avisado de esta limitación y pidió construir el módulo
igual, para tenerlo listo en cuanto se resuelva el tema de Meta.

### 1. Crear una cuenta de Twilio

1. Crea una cuenta en [twilio.com](https://www.twilio.com/try-twilio) (tiene
   capa gratuita/trial suficiente para probar el Sandbox).
2. En la [consola de Twilio](https://console.twilio.com), copia el
   **Account SID** y el **Auth Token** (están arriba, en el dashboard
   principal) y pégalos en `php-mysql/config_whatsapp.php`:
   ```php
   define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
   define('TWILIO_AUTH_TOKEN', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
   ```
   `TWILIO_WHATSAPP_NUMBER` puedes dejarlo como está (`whatsapp:+14155238886`,
   el número fijo del Sandbox) mientras no tengas un número de WhatsApp
   Business real.
3. En la consola, ve a **Messaging → Try it out → Send a WhatsApp message**
   para ver el código de opt-in de tu cuenta (debería seguir siendo `join
   men-husband`, pero confírmalo ahí — Twilio a veces lo cambia por cuenta).

### 2. Configurar el webhook de Twilio (una vez que el CRM ya esté desplegado)

Una vez que hayas subido `php-mysql/` a tu VPS/cPanel y tengas la URL final:

1. En la consola de Twilio, ve a **Messaging → Try it out → Send a WhatsApp
   message → Sandbox settings** (o **Messaging → Senders → WhatsApp
   Sandbox**, según la versión de la consola).
2. En **"WHEN A MESSAGE COMES IN"** pon:
   `https://tudominio/php-mysql/api/whatsapp/webhook.php` (método `POST`).
3. En **"STATUS CALLBACK URL"** (si la consola la pide aparte, o en la
   configuración de mensajería general) pon:
   `https://tudominio/php-mysql/api/whatsapp/status_callback.php`
4. Guarda. Para probar: manda `join men-husband` desde tu celular al
   +1 415 523 8886, y luego escribe cualquier cosa — debería aparecer en la
   pestaña WhatsApp del CRM en unos segundos.

Ajusta la ruta `php-mysql/` en las URLs de arriba según dónde hayas subido
la carpeta (ej. si la subiste a `public_html/crm/`, sería
`https://tudominio/crm/api/whatsapp/webhook.php`).

### 3. Pendiente: activar un número real de WhatsApp Business (Meta)

Según lo que el propio Jorge documentó en `CONTEXT.md` (sección
"Pendientes"), el camino para tener un número real (no Sandbox) es:

1. Crear un **Business Manager nuevo** en Meta (el WABA "Laroca 1" del
   Business Manager anterior es irrecuperable — la apelación no tuvo
   respuesta y el soporte directo está bloqueado).
2. Crear una **WABA (WhatsApp Business Account) nueva** dentro de ese
   Business Manager, y un **número de teléfono dedicado** para ella (no se
   puede reusar un número que ya tenga WhatsApp personal).
3. Registrar el sitio web del negocio en Meta Business Manager — ya está
   listo para esto: `https://jorgedelarocacalix-tech.github.io/laroca-comercial-web/`
   (tiene política de privacidad y términos de servicio, que es justo lo que
   le faltaba al sitio anterior y causó el bloqueo original).
4. Con la WABA nueva ya aprobada, hay dos caminos para conectarla a este
   CRM:
   - **Vía Twilio** (recomendado, para no tocar el código de este módulo):
     conectar el número de WhatsApp Business a Twilio como "Sender", y
     simplemente cambiar `TWILIO_WHATSAPP_NUMBER` en `config_whatsapp.php`
     al número nuevo (formato `whatsapp:+504...`) — el resto del código
     (`api/whatsapp/*.php`) sigue funcionando igual, sin límite de opt-in.
   - **Vía Meta Cloud API directo** (sin Twilio): requeriría escribir
     endpoints nuevos (`webhook/meta-whatsapp` para verificación GET +
     mensajes entrantes POST, `enviar-meta` para envíos) — no está
     implementado en esta versión porque el dueño priorizó primero tener
     el Sandbox funcionando; avísame si prefieren este camino en vez de
     Twilio cuando llegue el momento.

No hay nada que hacer en el código de este repo para el punto 3 — es
trámite y configuración del lado de Meta/Twilio. Cuando el número esté
listo, sólo hay que actualizar `TWILIO_WHATSAPP_NUMBER` (y confirmar que el
webhook siga apuntando a las mismas URLs de `api/whatsapp/`).
