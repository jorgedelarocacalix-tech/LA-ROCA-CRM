<?php
require_once __DIR__ . '/../auth.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    [$where, $params] = buildWhere(['id']);
    // El frontend manda un filtro compuesto estilo PostgREST para vendedores:
    // or=(to_user.is.null,to_user.eq.NOMBRE) — "para todo el equipo o para mí".
    if (isset($_GET['or']) && preg_match('/to_user\.is\.null,to_user\.eq\.(.+)\)?$/', $_GET['or'], $m)) {
        $toUser = rtrim($m[1], ')');
        $extra = '(`to_user` IS NULL OR `to_user` = ?)';
        $where = $where === '' ? "WHERE $extra" : "$where AND $extra";
        $params[] = $toUser;
    }
    $order = buildOrder(['created_at'], 'created_at.desc');
    $lim = limitOffset(50);
    $stmt = $pdo->prepare("SELECT * FROM mensajes_internos $where $order $lim");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'POST') {
    $records = recordsFromBody(jsonBody());
    foreach ($records as $rec) {
        if (empty($rec['mensaje'])) continue;
        $row = [
            'from_name' => $rec['from_name'] ?? null,
            'from_role' => $rec['from_role'] ?? null,
            'to_user' => $rec['to_user'] ?? null,
            'mensaje' => $rec['mensaje'],
            'tipo' => $rec['tipo'] ?? 'admin',
            'leido_por' => normalizeJsonCol($rec['leido_por'] ?? []),
        ];
        if (!empty($rec['id'])) $row['id'] = $rec['id'];
        upsert($pdo, 'mensajes_internos', $row);
    }
    echo json_encode(true);
    exit;
}

if ($method === 'PATCH') {
    [$where, $params] = buildWhere(['id']);
    if ($where === '') { echo json_encode(true); exit; }
    $body = jsonBody();
    if (!array_key_exists('leido_por', $body)) { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("UPDATE mensajes_internos SET leido_por = ? $where");
    $stmt->execute(array_merge([normalizeJsonCol($body['leido_por'])], $params));
    echo json_encode(true);
    exit;
}

if ($method === 'DELETE') {
    [$where, $params] = buildWhere(['id']);
    if ($where === '') { echo json_encode(true); exit; }
    $stmt = $pdo->prepare("DELETE FROM mensajes_internos $where");
    $stmt->execute($params);
    echo json_encode(true);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
