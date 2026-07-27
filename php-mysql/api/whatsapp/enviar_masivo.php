<?php
// Envío masivo con plantilla. El placeholder [Nombre] se reemplaza por el primer
// nombre de cada cliente. POST, requiere sesión.
// Body: {clientes:[{nombre,telefono,cartera}], plantilla}
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$body = jsonBody();
$clientes = $body['clientes'] ?? [];
$plantilla = trim($body['plantilla'] ?? '');

if (!is_array($clientes) || !$clientes || $plantilla === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Faltan clientes o plantilla']);
    exit;
}

$pdo = db();
$resultados = [];

foreach ($clientes as $c) {
    $telefono = trim($c['telefono'] ?? '');
    $nombre = trim($c['nombre'] ?? '');
    if ($telefono === '') continue;

    $primerNombre = $nombre !== '' ? explode(' ', $nombre)[0] : 'cliente';
    $texto = preg_replace('/\[nombre\]/i', $primerNombre, $plantilla);

    $res = twilioSendWhatsApp($telefono, $texto);

    waUpsertConversacion($pdo, $telefono, [
        'canal' => 'whatsapp',
        'estado' => 'enviado',
        'ultimo_mensaje' => mb_substr($texto, 0, 250),
    ]);
    waInsertMensaje($pdo, [
        'telefono' => $telefono,
        'direccion' => 'saliente',
        'contenido' => $texto,
        'twilio_sid' => $res['sid'] ?? null,
        'entrega' => $res['ok'] ? 'enviado' : 'failed',
    ]);

    $resultados[] = [
        'nombre' => $nombre,
        'telefono' => $telefono,
        'ok' => $res['ok'],
        'entrega' => $res['ok'] ? 'enviado' : 'failed',
        'error' => $res['ok'] ? null : ($res['error'] ?? null),
    ];

    // Pausa corta entre envíos para no saturar la API/límites del Sandbox de Twilio.
    usleep(150000);
}

echo json_encode(['ok' => true, 'resultados' => $resultados]);
