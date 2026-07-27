<?php
// Historial de mensajes de un teléfono. GET ?telefono=..., requiere sesión.
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$telefono = trim($_GET['telefono'] ?? '');
if ($telefono === '') { echo json_encode([]); exit; }

$stmt = db()->prepare('SELECT * FROM wa_mensajes WHERE telefono = ? ORDER BY timestamp ASC LIMIT 500');
$stmt->execute([$telefono]);
echo json_encode($stmt->fetchAll());
