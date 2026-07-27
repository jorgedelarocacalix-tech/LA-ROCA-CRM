<?php
// Proxy de medios de Twilio. Los medios que llegan por WhatsApp viven en URLs de
// api.twilio.com que requieren Basic Auth (Account SID + Auth Token) — un <img src>
// del navegador no puede mandar esa cabecera, así que este endpoint descarga el
// archivo del lado del servidor (con la auth) y se lo sirve al navegador ya
// autenticado. GET ?url=..., requiere sesión.
require_once __DIR__ . '/_common.php';
requireAuth();

$url = $_GET['url'] ?? '';

// Sólo se permite proxysar URLs del dominio de Twilio — evita que este endpoint se
// use como proxy abierto hacia cualquier sitio.
if ($url === '' || !preg_match('#^https://api\.twilio\.com/#', $url)) {
    http_response_code(400);
    echo 'URL inválida';
    exit;
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$data = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($data === false || $status >= 400) {
    http_response_code(502);
    echo 'No se pudo cargar el archivo de Twilio' . ($err ? ": $err" : '');
    exit;
}

header('Content-Type: ' . $contentType);
header('Cache-Control: private, max-age=3600');
echo $data;
