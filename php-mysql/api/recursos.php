<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['id', 'categoria']);
    $order = buildOrder(['created_at', 'nombre'], 'created_at.desc');
    $lim = limitOffset();
    $stmt = $pdo->prepare("SELECT * FROM recursos $where $order $lim");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $records = recordsFromBody(jsonBody());
    foreach ($records as $rec) {
        if (empty($rec['nombre']) || empty($rec['url'])) continue;
        $row = [
            'nombre' => $rec['nombre'],
            'descripcion' => $rec['descripcion'] ?? null,
            'url' => $rec['url'],
            'categoria' => $rec['categoria'] ?? 'Otros',
            'created_by' => $rec['created_by'] ?? null,
        ];
        if (!empty($rec['id'])) $row['id'] = $rec['id'];
        upsert($pdo, 'recursos', $row);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    [$where, $params] = buildWhere(['id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM recursos $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
