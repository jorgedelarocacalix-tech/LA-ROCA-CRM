<?php
// Webhook entrante de Twilio (WhatsApp Sandbox). Configúralo en la consola de Twilio
// como "WHEN A MESSAGE COMES IN" -> https://tudominio/php-mysql/api/whatsapp/webhook.php
// (método POST). Ver README.md, sección "Módulo de WhatsApp".
//
// Este endpoint NO lleva sesión de usuario (lo llama Twilio directamente, no el
// navegador del vendedor) — se valida en su lugar la firma X-Twilio-Signature.
require_once __DIR__ . '/_common.php';
header('Content-Type: text/xml; charset=utf-8');

if (!waValidateTwilioSignature()) {
    http_response_code(403);
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

$from = waStripPrefix($_POST['From'] ?? '');
if ($from === '') {
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
}

$bodyTxt = trim($_POST['Body'] ?? '');
$profileName = trim($_POST['ProfileName'] ?? '');
$numMedia = (int)($_POST['NumMedia'] ?? 0);
$mediaUrl = $numMedia > 0 ? ($_POST['MediaUrl0'] ?? null) : null;
$mediaType = $_POST['MediaContentType0'] ?? '';
$sid = $_POST['MessageSid'] ?? ($_POST['SmsMessageSid'] ?? null);

$contenido = $bodyTxt;
if ($mediaUrl && $contenido === '') {
    $contenido = (stripos($mediaType, 'pdf') !== false) ? '[📄 PDF]' : '[📷 Imagen]';
}

$pdo = db();

// `estado` = 'pregunta' marca en la lista de conversaciones que llegó un mensaje sin
// responder todavía (no hay clasificación con IA en esta versión — ver README.md).
$convFields = ['canal' => 'whatsapp', 'estado' => 'pregunta', 'ultimo_mensaje' => mb_substr($contenido, 0, 250)];
if ($profileName !== '') $convFields['nombre'] = $profileName;
waUpsertConversacion($pdo, $from, $convFields);

waInsertMensaje($pdo, [
    'telefono' => $from,
    'direccion' => 'entrante',
    'contenido' => $contenido,
    'media_url' => $mediaUrl,
    'twilio_sid' => $sid,
    'entrega' => 'recibido',
]);

// Respuesta TwiML vacía = no auto-responder nada (bot auto-reply quedó deprioritizado
// por el dueño, según CONTEXT.md).
echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
