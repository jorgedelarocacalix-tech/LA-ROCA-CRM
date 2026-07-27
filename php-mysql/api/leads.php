<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['id', 'lead_col', 'created_by', 'assigned_to', 'lead_cartera']);
    $order = buildOrder(['created_at'], 'created_at');
    $lim = limitOffset();
    $stmt = $pdo->prepare("SELECT * FROM leads $where $order $lim");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $records = recordsFromBody(jsonBody());
    foreach ($records as $rec) {
        if (empty($rec['id']) || empty($rec['nombre'])) continue;
        upsert($pdo, 'leads', [
            'id' => $rec['id'],
            'nombre' => $rec['nombre'],
            'telefono' => $rec['telefono'] ?? null,
            'fecha_contacto' => $rec['fecha_contacto'] ?? null,
            'fuente' => $rec['fuente'] ?? null,
            'producto' => $rec['producto'] ?? null,
            'notas' => $rec['notas'] ?? null,
            'lead_col' => $rec['lead_col'] ?? 'nl',
            'kb_comments' => normalizeJsonCol($rec['kb_comments'] ?? []),
            'created_by' => $rec['created_by'] ?? null,
            'created_at' => $rec['created_at'] ?? null,
            'assigned_to' => $rec['assigned_to'] ?? null,
            'lead_cartera' => $rec['lead_cartera'] ?? null,
        ]);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    [$where, $params] = buildWhere(['id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM leads $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
