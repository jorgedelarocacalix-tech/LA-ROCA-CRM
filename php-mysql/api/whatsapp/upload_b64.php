<?php
// Sube una imagen o PDF (en base64) al servidor, en uploads/wa/ — antes se subía a
// Supabase Storage. POST, requiere sesión.
// Body: {base64, nombre, tipo}
// Responde {ok:true, url} — la URL es pública porque Twilio necesita poder
// descargar el archivo para reenviarlo (ver api/whatsapp/enviar_imagen_url.php).
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$body = jsonBody();
$b64 = $body['base64'] ?? '';
$nombreOriginal = (string)($body['nombre'] ?? 'archivo');
$tipo = (string)($body['tipo'] ?? '');

if ($b64 === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Falta el archivo']);
    exit;
}

$extPorTipo = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
$ext = $extPorTipo[$tipo] ?? null;
if (!$ext) {
    $extNombre = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if (in_array($extNombre, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $ext = $extNombre;
}
if (!$ext) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tipo de archivo no permitido (sólo PDF o imágenes)']);
    exit;
}

$data = base64_decode($b64, true);
if ($data === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Base64 inválido']);
    exit;
}
if (strlen($data) > 15 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El archivo no puede pesar más de 15 MB']);
    exit;
}

if (!is_dir(WA_UPLOAD_DIR) && !mkdir(WA_UPLOAD_DIR, 0755, true) && !is_dir(WA_UPLOAD_DIR)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo crear la carpeta de subida']);
    exit;
}

$filename = time() . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
$path = WA_UPLOAD_DIR . $filename;
if (file_put_contents($path, $data) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo']);
    exit;
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme = $https ? 'https' : 'http';
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);   // .../php-mysql/api/whatsapp
$baseDir = dirname(dirname($scriptDir));          // .../php-mysql
$url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseDir . '/uploads/wa/' . $filename;

echo json_encode(['ok' => true, 'url' => $url]);
