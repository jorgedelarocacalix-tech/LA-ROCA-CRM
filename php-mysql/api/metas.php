<?php
// Metas de ventas por vendedor y cartera. Antes era una constante METAS
// hardcodeada en el JS del CRM; ahora vive en la tabla `metas` y es editable
// desde Equipo → 🎯 Meta (ver editarMeta() en index.html) sin tocar código.
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['user_id', 'cartera_id']);
    $stmt = $pdo->prepare("SELECT user_id, cartera_id, unidades, monto FROM metas $where");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    requireAdmin();
    $records = recordsFromBody(jsonBody());
    foreach ($records as $rec) {
        if (empty($rec['user_id']) || empty($rec['cartera_id'])) continue;
        $stmt = $pdo->prepare(
            "INSERT INTO metas (user_id, cartera_id, unidades, monto) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE unidades = VALUES(unidades), monto = VALUES(monto)"
        );
        $stmt->execute([
            $rec['user_id'],
            $rec['cartera_id'],
            $rec['unidades'] ?? 0,
            (isset($rec['monto']) && $rec['monto'] !== null && $rec['monto'] !== '') ? $rec['monto'] : null,
        ]);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    requireAdmin();
    [$where, $params] = buildWhere(['user_id', 'cartera_id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM metas $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
