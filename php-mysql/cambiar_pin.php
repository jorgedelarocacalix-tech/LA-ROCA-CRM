<?php
// Utilidad opcional: permite que el usuario logueado cambie su propio PIN.
// El mecanismo principal para gestionar PINs sigue siendo la pantalla
// Equipo (admin/subadmin) dentro de la app, igual que en la versión original.
require_once __DIR__ . '/auth.php';
requireAuth();
header('Content-Type: application/json');

$data = jsonBody();
$pin = (string)($data['pin'] ?? '');

if (!preg_match('/^\d{4}$/', $pin)) {
    http_response_code(400);
    echo json_encode(['error' => 'El PIN debe tener 4 dígitos.']);
    exit;
}

$pdo = db();

$dup = $pdo->prepare('SELECT id FROM usuarios WHERE pin = ? AND id <> ?');
$dup->execute([$pin, currentUserId()]);
if ($dup->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Ese PIN ya está en uso por otro usuario.']);
    exit;
}

$stmt = $pdo->prepare('UPDATE usuarios SET pin = ? WHERE id = ?');
$stmt->execute([$pin, currentUserId()]);
echo json_encode(['ok' => true]);
