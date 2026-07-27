# LA ROCA CRM — versión PHP + MySQL (para VPS con cPanel)

Esta carpeta es una versión paralela del CRM, adaptada para correr en un VPS
con cPanel usando PHP + MySQL en lugar de Supabase. **No reemplaza** el
`index.html` de la raíz del repo (ese sigue funcionando igual, en GitHub
Pages/Netlify con Supabase, sin cambios) ni el backend de WhatsApp
(Node.js + Twilio) que sigue corriendo aparte.

## Qué se portó

El CRM original hablaba directo con la API REST de Supabase desde el
navegador (`SB_URL`/`SB_KEY` visibles en el código fuente) y con la API de
Claude/Anthropic con una API key embebida en el JS (ofuscada invirtiendo el
string, pero de todas formas visible y usable por cualquiera que abriera
"ver código fuente"). Esta versión:

- Mantiene el mismo diseño visual, el mismo Kanban (Recompra / Clientes
  Nuevos / Facebook-Publicidad), la gestión de clientes, el registro de
  gestiones, Equipo, Monitor, Recursos, Mensajes internos y Mi Meta.
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
- **Módulo de WhatsApp** (chat, envío masivo, análisis IA de conversaciones):
  dependía de un backend Node.js/Express aparte corriendo en la máquina de
  Jorge y expuesto por ngrok, más la API de Twilio (también de pago). No es
  algo que se pueda portar a PHP en un hosting compartido de cPanel sin
  reconstruir todo ese backend de mensajería (webhooks, Twilio, Meta Cloud
  API). **Se eliminó de esta versión.** Si más adelante se quiere ese
  backend en el propio VPS, lo más realista es correr el `server.js` de
  Node aparte (el VPS con cPanel normalmente sí puede correr una app Node.js
  además de PHP) — es un proyecto separado de esta migración.
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
  schema.sql              — tablas MySQL + datos semilla (usuarios, carteras, metas)
  config.php              — credenciales de la base de datos (editar antes de subir)
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
  index.html                   — la app (mismo diseño; sin Supabase, sin IA, sin WhatsApp)
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
6. Abre la URL de tu dominio/subdominio en el navegador — debe pedir el PIN
   igual que la versión actual.

No requiere carpeta de `uploads/` ni permisos especiales de escritura: esta
app no sube archivos al servidor (los "recursos" son links externos, y ya no
hay análisis de PDFs con IA).

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
- El módulo de WhatsApp y el asistente de IA no están disponibles en esta
  versión — si el equipo los usa activamente, avísame para planear cómo
  incorporarlos (por ejemplo, corriendo el backend de Node.js del WhatsApp
  aparte, en el mismo VPS).
