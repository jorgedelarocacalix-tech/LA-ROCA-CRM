<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['id', 'cartera_id', 'nombre', 'estado']);
    $order = buildOrder(['nombre', 'created_at'], 'nombre');
    $lim = limitOffset();
    $stmt = $pdo->prepare("SELECT * FROM clientes $where $order $lim");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $records = recordsFromBody(jsonBody());
    foreach ($records as $rec) {
        if (empty($rec['id']) || empty($rec['cartera_id']) || empty($rec['nombre'])) continue;
        upsert($pdo, 'clientes', [
            'id' => $rec['id'],
            'cartera_id' => $rec['cartera_id'],
            'nombre' => $rec['nombre'],
            'telefono' => $rec['telefono'] ?? null,
            'estado' => $rec['estado'] ?? 'pendiente',
            'kb_col' => $rec['kb_col'] ?? null,
            'kb_comments' => normalizeJsonCol($rec['kb_comments'] ?? []),
            'total_pagos' => $rec['total_pagos'] ?? 0,
            'monto_total' => $rec['monto_total'] ?? 0,
            'ultimo_pago' => $rec['ultimo_pago'] ?? null,
            'primer_pago' => $rec['primer_pago'] ?? null,
            'antiguedad' => $rec['antiguedad'] ?? 0,
            'patron' => $rec['patron'] ?? 'Mensual',
            'payments' => normalizeJsonCol($rec['payments'] ?? []),
        ]);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    [$where, $params] = buildWhere(['id', 'cartera_id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM clientes $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
