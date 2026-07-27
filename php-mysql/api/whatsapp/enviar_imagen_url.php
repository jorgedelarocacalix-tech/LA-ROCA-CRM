<?php
// Envía una imagen o PDF por WhatsApp a partir de una URL (la que devolvió
// upload_b64.php). POST, requiere sesión.
// Body: {telefono, mediaUrl, contenido?}
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$body = jsonBody();
$telefono = trim($body['telefono'] ?? '');
$mediaUrl = trim($body['mediaUrl'] ?? '');
$contenido = trim($body['contenido'] ?? '');

if ($telefono === '' || $mediaUrl === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta teléfono o URL de archivo']);
    exit;
}

$res = twilioSendWhatsApp($telefono, $contenido !== '' ? $contenido : null, $mediaUrl);

$esPdf = stripos($mediaUrl, '.pdf') !== false || stripos($contenido, 'pdf') !== false;
$resumen = $contenido !== '' ? $contenido : ($esPdf ? '[📄 PDF]' : '[📷 Imagen]');

$pdo = db();
waUpsertConversacion($pdo, $telefono, [
    'canal' => 'whatsapp',
    'estado' => null,
    'ultimo_mensaje' => mb_substr($resumen, 0, 250),
]);
waInsertMensaje($pdo, [
    'telefono' => $telefono,
    'direccion' => 'saliente',
    'contenido' => $resumen,
    'media_url' => $mediaUrl,
    'twilio_sid' => $res['sid'] ?? null,
    'entrega' => $res['ok'] ? 'enviado' : 'failed',
]);

if (!$res['ok']) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $res['error']]);
    exit;
}
echo json_encode(['ok' => true, 'sid' => $res['sid']]);
