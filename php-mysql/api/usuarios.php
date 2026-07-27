<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['id', 'pin', 'role']);
    $order = buildOrder(['name'], 'name');
    $lim = limitOffset();
    $stmt = $pdo->prepare("SELECT id, name, pin, role, cartera FROM usuarios $where $order $lim");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        // El frontend espera poder hacer JSON.parse() o usarlo ya como array —
        // se manda tal cual (string JSON) y el JS ya sabe parsearlo.
    }
    echo json_encode($rows);
    exit;
}

if ($method === 'POST') {
    $records = recordsFromBody(jsonBody());
    $myId = currentUserId();
    $isAdmin = in_array($_SESSION['cu_role'] ?? '', ['admin', 'subadmin'], true);
    foreach ($records as $rec) {
        $isSelf = isset($rec['id']) && $rec['id'] === $myId;
        if (!$isSelf && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }
        if (empty($rec['id']) || empty($rec['name']) || empty($rec['pin'])) continue;
        upsert($pdo, 'usuarios', [
            'id' => $rec['id'],
            'name' => $rec['name'],
            'pin' => (string)$rec['pin'],
            'role' => $rec['role'] ?? 'vendedor',
            'cartera' => normalizeJsonCol($rec['cartera'] ?? null),
        ]);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    requireAdmin();
    [$where, $params] = buildWhere(['id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM usuarios $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
