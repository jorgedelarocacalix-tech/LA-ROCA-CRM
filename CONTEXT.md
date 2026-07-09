# LA-ROCA-CRM — Contexto del Proyecto

## Stack
- **Frontend**: Single-file `index.html` desplegado en GitHub Pages
- **URL producción**: `https://jorgedelarocacalix-tech.github.io/LA-ROCA-CRM`
- **Base de datos**: Supabase (proyecto `upaenjotkocmdvfuobii`)
- **IA**: Claude API llamado directo desde el browser (`anthropic-dangerous-direct-browser-access: true`)
- **WhatsApp backend producción**: Node.js/Express puerto 3001 — `/Users/jorgecalix/laroca-crm-whatsapp/server.js`
- **WhatsApp backend DEV**: Node.js/Express puerto 3002 — `/Users/jorgecalix/laroca-crm-whatsapp-v2/server.js`
- **Repo CRM**: `https://github.com/jorgedelarocacalix-tech/LA-ROCA-CRM`

## Cómo arrancar los backends
```bash
# Producción (puerto 3001)
cd /Users/jorgecalix/laroca-crm-whatsapp && node server.js &

# DEV (puerto 3002)
cd /Users/jorgecalix/laroca-crm-whatsapp-v2 && node server.js &

# ngrok (apuntar al 3001)
ngrok http --domain=tassel-wasting-cesarean.ngrok-free.dev 3001
```

## Variables clave
```javascript
SK = 'lroca_v31'           // clave localStorage (borra v24-v30 al cargar)
WA_URL = localStorage.getItem('wa_url') || 'https://tassel-wasting-cesarean.ngrok-free.dev'
```

## Supabase
- URL: `https://upaenjotkocmdvfuobii.supabase.co`
- Anon key JWT: `eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InVwYWVuam90a29jbWR2ZnVvYmlpIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Nzg4NjQzMzUsImV4cCI6MjA5NDQ0MDMzNX0.am0lcEwz4-HK0KvrPBtv1QB7joNYDpOuQNhbc1HjwZ8`
- **IMPORTANTE**: Usar JWT largo (no `sb_publishable_`), el corto no funciona como Bearer token en Storage

### Tablas
| Tabla | Uso |
|-------|-----|
| `usuarios` | — |
| `clientes` | Clientes por cartera |
| `gestiones` | Gestiones de vendedores |
| `leads` | Leads de publicidad |
| `recursos` | Archivos/recursos del equipo |
| `mensajes_internos` | Chat interno del equipo |
| `wa_conversaciones` | Una fila por contacto WhatsApp |
| `wa_mensajes` | Historial de mensajes WhatsApp |

### Columnas wa_mensajes
`telefono, direccion, contenido, media_url, timestamp, twilio_sid, entrega`
- `twilio_sid`: SID de Twilio para tracking de estado
- `entrega`: `enviado` → `sent` → `delivered` → `read` / `failed`

### Storage
- Bucket `wa-archivos` (público, 20MB, acepta PDF e imágenes)
- RLS: anon insert + anon select habilitados
- Usado para subir PDFs e imágenes desde el CRM antes de enviar por WhatsApp

## Anthropic API Key
Guardada ofuscada en el código (invertida con `.split('').reverse().join('')`). NUNCA commitear en texto plano.

## Usuarios hardcodeados (14 en total)
| Nombre | PIN | Rol | Cartera |
|--------|-----|-----|---------|
| Jorge Daniel | 1234 | admin | — |
| Ana Castro | 3434 | subadmin | roca1, roca1motors |
| Alejandra | 1111 | vendedor | roca1, roca1motors |
| Alejandra | 3131 | vendedor | roca2motors |
| Allison | 5252 | vendedor | sumotodanli, sumuebledanli |
| Brayan | 5555 | vendedor | sumotodanli, sumuebledanli |
| Denys | 4141 | vendedor | roca2motors |
| fer | 2323 | vendedor | roca1, roca1motors |
| Gabriela | 4242 | vendedor | roca2motors |
| johana | 1212 | **subadmin** | roca1, roca1motors |
| Jorge | 5454 | vendedor | sumotodanli, sumuebledanli |
| Karla | 5353 | vendedor | sumotodanli, sumuebledanli |
| Milagro | 2222 | **subadmin** | roca2motors |
| Sayda | 5151 | **subadmin** | sumotodanli, sumuebledanli |

Login: 3 capas — hardcoded → localStorage → Supabase fallback

## Archivos principales
- `/Users/jorgecalix/LA-ROCA-CRM/index.html` — CRM completo (~3700 líneas)
- `/Users/jorgecalix/LA-ROCA-CRM/sw.js` — Service Worker v2.4 (sin caché)
- `/Users/jorgecalix/laroca-crm-whatsapp/server.js` — Backend producción
- `/Users/jorgecalix/laroca-crm-whatsapp/.env` — Variables de entorno (NO commitear)
- `/Users/jorgecalix/laroca-crm-whatsapp-v2/server.js` — Backend DEV
- `/Users/jorgecalix/laroca-web/index.html` — Sitio La Roca Comercial (GitHub Pages)

## WhatsApp Backend — Rutas (`server.js`)

### Twilio Sandbox
| Ruta | Método | Descripción |
|------|--------|-------------|
| `/webhook/whatsapp` | POST | Webhook entrante de Twilio |
| `/api/responder` | POST | Responder mensaje por Twilio |
| `/api/enviar-masivo` | POST | Envío masivo con plantilla y `[Nombre]` |
| `/api/wa-status-callback` | POST | Twilio llama aquí cuando cambia estado (delivered/read) |
| `/api/masivo-estado` | GET | Consulta estado de entrega y respuestas post-envío masivo |
| `/api/upload-archivo-b64` | POST | Sube imagen/PDF en base64 a Supabase Storage |
| `/api/enviar-imagen-url` | POST | Envía imagen o PDF por URL via Twilio |
| `/api/media-proxy` | GET | Proxy para imágenes de Twilio (requiere auth básica) |

### Meta Cloud API (pendiente de activar)
| Ruta | Método | Descripción |
|------|--------|-------------|
| `/webhook/meta-whatsapp` | GET | Verificación Meta |
| `/webhook/meta-whatsapp` | POST | Mensajes entrantes Meta |
| `/api/enviar-meta` | POST | Enviar por Meta Cloud API |

### Facebook Messenger
| Ruta | Método | Descripción |
|------|--------|-------------|
| `/webhook/facebook` | GET | Verificación FB |
| `/webhook/facebook` | POST | Mensajes entrantes Messenger |
| `/api/responder-messenger` | POST | Responder por Messenger |

### Análisis IA
| Ruta | Método | Descripción |
|------|--------|-------------|
| `/api/recomendar/:telefono` | GET | Análisis IA del historial de chat |
| `/api/analisis-diario` | GET | Resumen del día con IA |
| `/api/conversaciones` | GET | Listar conversaciones |
| `/api/mensajes/:telefono` | GET | Historial de mensajes |

## Twilio WhatsApp Sandbox
- Número Twilio: `+14155238886`
- Keyword de opt-in: `join men-husband`
- **ADVERTENCIA**: No mandar mensajes a números que no hayan hecho opt-in — WhatsApp puede restringir la cuenta

## Estado WhatsApp API (junio 2026)
- **WABA "Laroca 1" (ID: 399489613247738)**: DESACTIVADA PERMANENTEMENTE desde ago 2024
- Motivo: sitio web `corporacionlaroca2021.com` no cumplía políticas de Meta
- Apelación enviada + sitio actualizado con política de privacidad y términos
- Meta no respondió por correo; soporte directo bloqueado ("no eres miembro de negocio que cumpla requisitos")
- **Siguiente paso**: Crear Business Manager nuevo + WABA nueva + número de teléfono dedicado
- Sitio web actual para registro: `https://jorgedelarocacalix-tech.github.io/laroca-comercial-web/`

## Funcionalidades WhatsApp en el CRM

### Imágenes y PDFs
- Botón 🖼 en el chat: sube imagen a Supabase Storage → envía URL por Twilio
- Botón 📄 en el chat: convierte PDF a base64 → sube vía `/api/upload-archivo-b64` → envía URL
- Imágenes de Twilio se cargan via proxy (fetch+blob) porque `<img>` no puede enviar el header `ngrok-skip-browser-warning`
- Cache de blobs en `_waImgCache{}` para no re-descargar en cada polling de 5 segundos

### Scroll del chat
- Variable `wasAtBottom` antes de re-renderizar mensajes
- Si usuario estaba leyendo arriba → se restaura `prevScroll` (no salta al fondo)
- Solo auto-scroll al fondo si ya estaba en el fondo

### Envío masivo
- 4 plantillas predefinidas seleccionables
- Textarea editable con `[Nombre]` que se reemplaza por el nombre real de cada cliente
- Vista previa en vivo mientras escribe/edita
- Tabla de resultados post-envío: Nombre | Teléfono | Entrega | Respuesta
- Iconos: ✓ enviado / ✓✓ entregado / ✓✓👁 leído / 💬 respondió
- Actualización automática de estados a los 10s y 30s post-envío
- Botón "🔄 Actualizar estados" para consultar manualmente

## Sitio La Roca Comercial
- URL: `https://jorgedelarocacalix-tech.github.io/laroca-comercial-web/`
- Archivo: `/Users/jorgecalix/laroca-web/index.html`
- Tiene modal de Política de Privacidad y Términos de Servicio (requerido por Meta)
- Registrado en Meta Business Manager como sitio del negocio

## Problemas conocidos / Soluciones documentadas
| Problema | Solución |
|----------|----------|
| Imágenes Twilio no cargan (`<img>` no puede poner headers) | fetch+blob+cache en `_waImgCache` |
| PDF upload timeout por ngrok | Convertir a base64 JSON en vez de multipart |
| PDF incoming de WhatsApp no detectado | Check `.toLowerCase().includes('pdf')` |
| Scroll salta al fondo cada 5 segundos | `wasAtBottom` check antes de re-render |
| Service worker cachea código viejo | DevTools → Application → Service Workers → Unregister |
| Supabase key `sb_publishable_` rechazada en Storage | Usar JWT largo como anon key |
| ngrok cierra accidentalmente | `ngrok http --domain=tassel-wasting-cesarean.ngrok-free.dev 3001` |
| Browser warning de ngrok bloquea API | Header `ngrok-skip-browser-warning: true` en todos los fetch |
| Push rechazado (remote ahead) | `git fetch origin && git reset --hard origin/main` |
| Karla no podía entrar (DNS bloqueaba Supabase) | Cambiar DNS en router a 8.8.8.8 / 8.8.4.4 |

## Netlify
- URL producción Netlify: `https://laroca-crm-app.netlify.app`
- `netlify.toml` en la raíz del repo (publish=`.`, sin build command, Cache-Control no-cache)
- Deploy manual: `netlify deploy --prod --dir .` desde `/Users/jorgecalix/LA-ROCA-CRM`
- Auto-deploy desde GitHub aún no confirmado — usar deploy manual por ahora
- Si hay caché en el browser: **Cmd+Shift+R** para forzar recarga

## Sistema Mi Meta (julio 2026)
- Pestaña "🎯 Mi Meta" visible para vendedores y subadmins con METAS definidas
- Metas en constante `METAS` (~línea 560 de index.html)
- Auto-tracking: gestiones con `estado='pago'` en el mes actual
- Manual: localStorage `mv_${userId}_${carteraId}_${mes}` (solo visible en el dispositivo del vendedor)
- Monitor (admin/subadmin) muestra metas de todos los vendedores agrupadas por pool
- Grouping en Monitor usa cartera de METAS (no user.cartera) para agrupación correcta

### METAS actuales
| Usuario | ID | Cartera | Unidades | Monto |
|---------|-----|---------|----------|-------|
| Fernando | v_fer | roca1 | 25 | 375,000 |
| Fernando | v_fer | roca1motors | 3 | — |
| Alejandra (1111) | v_ale | roca1 | 30 | 375,000 |
| Alejandra (1111) | v_ale | roca1motors | 3 | — |
| Ana Castro | u1779318896833 | roca1 | 30 | 375,000 |
| Ana Castro | u1779318896833 | roca1motors | 3 | — |
| Milagro | v_mil | roca1 | 3 | 100,000 |
| Denys | v_den | roca2motors | 30 | — |
| Gabriela | v_gab | roca2motors | 20 | — |
| Jorge | v_jor | sumuebledanli | 20 | 250,000 |
| Jorge | v_jor | sumotodanli | 6 | — |
| Karla | v_kar | sumuebledanli | 20 | 300,000 |
| Karla | v_kar | sumotodanli | 4 | — |
| Sayda | v_say | sumuebledanli | 20 | 300,000 |
| Sayda | v_say | sumotodanli | 4 | — |
| Brayan | v_bra | sumuebledanli | 10 | 150,000 |
| Brayan | v_bra | sumotodanli | 2 | — |
| Allison | v_all | sumuebledanli | 15 | 250,000 |
| Allison | v_all | sumotodanli | 3 | — |

## Pendientes
1. **WhatsApp API real**: Crear nuevo Business Manager + WABA + número dedicado (Laroca 1 es irrecuperable)
2. **Facebook Messenger**: Configurar tokens de páginas FB en `.env`
3. **URL permanente backend**: Reemplazar ngrok con servidor propio o Railway/Render
4. **Bot auto-reply**: Deprioritizado por el usuario
5. **Netlify auto-deploy**: Verificar que GitHub → Netlify esté conectado para deploys automáticos
