<?php
// Credenciales de Twilio para el módulo de WhatsApp.
//
// Consíguelas en https://console.twilio.com (Account SID y Auth Token están en el
// dashboard principal, arriba a la izquierda). El número de WhatsApp de abajo es el
// Sandbox de pruebas de Twilio (+14155238886) — ver README.md, sección "Módulo de
// WhatsApp", para la explicación completa de por qué hoy sólo funciona el Sandbox
// (el WhatsApp Business real de Meta está desactivado desde agosto 2024) y qué pasos
// le faltan al dueño para activar un número real.
define('TWILIO_ACCOUNT_SID', 'CAMBIAR_twilio_account_sid');
define('TWILIO_AUTH_TOKEN', 'CAMBIAR_twilio_auth_token');

// Sandbox de Twilio. Cuando exista un número de WhatsApp Business real (vía Meta
// Cloud API + Twilio, o Twilio directo), reemplázalo aquí con formato "whatsapp:+504...".
define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886');

// Carpeta local donde se guardan las imágenes/PDFs subidos desde el chat antes de
// mandarlos por Twilio (antes: bucket de Supabase Storage). Debe tener permiso de
// escritura para el usuario de PHP y quedar accesible públicamente por URL, porque
// Twilio necesita poder descargar el archivo para reenviarlo por WhatsApp.
define('WA_UPLOAD_DIR', __DIR__ . '/uploads/wa/');
