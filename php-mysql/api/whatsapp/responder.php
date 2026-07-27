<?php
// Responder un mensaje de texto por WhatsApp desde el chat del CRM. POST, requiere sesión.
// Body: {telefono, mensaje}
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$body = jsonBody();
$telefono = trim($body['telefono'] ?? '');
$mensaje = trim($body['mensaje'] ?? '');

if ($telefono === '' || $mensaje === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta teléfono o mensaje']);
    exit;
}

$res = twilioSendWhatsApp($telefono, $mensaje);

$pdo = db();
waUpsertConversacion($pdo, $telefono, [
    'canal' => 'whatsapp',
    'estado' => null, // se le respondió, ya no está "pendiente"
    'ultimo_mensaje' => mb_substr($mensaje, 0, 250),
]);
waInsertMensaje($pdo, [
    'telefono' => $telefono,
    'direccion' => 'saliente',
    'contenido' => $mensaje,
    'twilio_sid' => $res['sid'] ?? null,
    'entrega' => $res['ok'] ? 'enviado' : 'failed',
]);

if (!$res['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $res['error']]);
    exit;
}
echo json_encode(['ok' => true, 'sid' => $res['sid']]);
