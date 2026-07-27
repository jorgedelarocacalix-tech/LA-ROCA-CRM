<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

$data = jsonBody();
$pin = (string)($data['pin'] ?? '');

if ($pin === '' || !preg_match('/^\d{4}$/', $pin)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'PIN inválido']);
    exit;
}

$stmt = db()->prepare('SELECT id, name, pin, role, cartera FROM usuarios WHERE pin = ?');
$stmt->execute([$pin]);
$u = $stmt->fetch();

if (!$u) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'PIN incorrecto']);
    exit;
}

$cartera = [];
if ($u['cartera']) {
    $decoded = json_decode($u['cartera'], true);
    $cartera = is_array($decoded) ? $decoded : [];
}

$_SESSION['cu_id'] = $u['id'];
$_SESSION['cu_role'] = $u['role'];

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => $u['id'],
        'name' => $u['name'],
        'role' => $u['role'],
        'cartera' => $cartera,
    ],
]);
