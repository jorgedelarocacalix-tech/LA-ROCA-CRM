<?php
// Twilio llama aquí cuando cambia el estado de un mensaje que mandamos (sent ->
// delivered -> read / failed). Configúralo en la consola de Twilio como "STATUS
// CALLBACK URL" -> https://tudominio/php-mysql/api/whatsapp/status_callback.php
//
// Tampoco lleva sesión de usuario — lo llama Twilio directamente.
require_once __DIR__ . '/_common.php';
header('Content-Type: application/json');

if (!waValidateTwilioSignature()) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$sid = $_POST['MessageSid'] ?? '';
$status = strtolower($_POST['MessageStatus'] ?? ''); // queued|sent|delivered|read|failed|undelivered

if ($sid === '' || $status === '') {
    echo json_encode(['ok' => true]);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('UPDATE wa_mensajes SET entrega = ? WHERE twilio_sid = ?');
$stmt->execute([$status, $sid]);

echo json_encode(['ok' => true]);
