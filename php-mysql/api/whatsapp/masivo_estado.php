<?php
// Consulta estado de entrega y respuestas después de un envío masivo. GET, requiere sesión.
// ?telefonos=504.....,504.....&desde=2026-07-27T12:00:00.000Z
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$telefonos = array_values(array_filter(array_map('trim', explode(',', $_GET['telefonos'] ?? ''))));
$desde = trim($_GET['desde'] ?? '');
if (!$telefonos) { echo json_encode([]); exit; }

$pdo = db();
$stmtOut = $pdo->prepare(
    "SELECT entrega FROM wa_mensajes WHERE telefono = ? AND direccion = 'saliente'"
    . ($desde !== '' ? ' AND timestamp >= ?' : '') . ' ORDER BY timestamp DESC LIMIT 1'
);
$stmtIn = $pdo->prepare(
    "SELECT COUNT(*) AS c FROM wa_mensajes WHERE telefono = ? AND direccion = 'entrante'"
    . ($desde !== '' ? ' AND timestamp >= ?' : '')
);

$out = [];
foreach ($telefonos as $tel) {
    $params = $desde !== '' ? [$tel, $desde] : [$tel];
    $stmtOut->execute($params);
    $rowOut = $stmtOut->fetch();
    $stmtIn->execute($params);
    $rowIn = $stmtIn->fetch();
    $out[] = [
        'telefono' => $tel,
        'entrega' => $rowOut['entrega'] ?? 'enviado',
        'respondio' => (int)($rowIn['c'] ?? 0) > 0,
    ];
}

echo json_encode($out);
