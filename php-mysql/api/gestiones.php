<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['id', 'client_id', 'cartera_id', 'user_id', 'user_name', 'estado', 'created_at']);
    $order = buildOrder(['created_at', 'date_iso'], 'created_at.desc');
    $lim = limitOffset();
    $stmt = $pdo->prepare("SELECT * FROM gestiones $where $order $lim");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $records = recordsFromBody(jsonBody());
    foreach ($records as $rec) {
        if (empty($rec['id'])) continue;
        upsert($pdo, 'gestiones', [
            'id' => $rec['id'],
            'client_id' => $rec['client_id'] ?? null,
            'cartera_id' => $rec['cartera_id'] ?? null,
            'user_id' => $rec['user_id'] ?? null,
            'user_name' => $rec['user_name'] ?? null,
            'date_str' => $rec['date_str'] ?? null,
            'date_iso' => $rec['date_iso'] ?: null,
            'resultado' => $rec['resultado'] ?? null,
            'estado' => $rec['estado'] ?? null,
            'producto' => $rec['producto'] ?? null,
            'notas' => $rec['notas'] ?? null,
            'num2' => $rec['num2'] ?? null,
        ]);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    [$where, $params] = buildWhere(['id', 'cartera_id', 'client_id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM gestiones $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
