<?php
// Helpers compartidos por los endpoints de api/whatsapp/*.php: sesión PHP + PDO
// (auth.php), credenciales de Twilio (config_whatsapp.php), y funciones para
// normalizar teléfonos, validar que la petición viene de Twilio, llamar a la API
// REST de Twilio y guardar conversaciones/mensajes.
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config_whatsapp.php';

/**
 * Normaliza un teléfono a formato E.164, asumiendo Honduras (+504) cuando vienen
 * 8 dígitos locales (el formato en que están guardados clientes.telefono, ej.
 * "9876-5432"). Si ya trae código de país, lo respeta.
 */
function waNormalizePhone(string $raw): string {
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if ($digits === '') return '';
    if (strlen($digits) === 8) $digits = '504' . $digits;
    return '+' . $digits;
}

/** Quita el prefijo "whatsapp:" que Twilio antepone en From/To. */
function waStripPrefix(string $raw): string {
    return preg_replace('/^whatsapp:/', '', trim($raw));
}

function waFullUrl(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
}

/**
 * Valida la cabecera X-Twilio-Signature que manda Twilio en el webhook y en el
 * status callback (ver https://www.twilio.com/docs/usage/webhooks/webhooks-security).
 * Si TWILIO_AUTH_TOKEN todavía es el placeholder "CAMBIAR_..." no hay forma de
 * validar nada (no tenemos el token real todavía), así que se deja pasar — una vez
 * que el dueño ponga el token real en config_whatsapp.php, esta validación empieza
 * a aplicar sola, sin tocar código.
 */
function waValidateTwilioSignature(): bool {
    if (strpos(TWILIO_AUTH_TOKEN, 'CAMBIAR_') === 0) return true;
    $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
    if ($signature === '') return false;
    $params = $_POST;
    ksort($params);
    $data = waFullUrl();
    foreach ($params as $k => $v) { $data .= $k . $v; }
    $expected = base64_encode(hash_hmac('sha1', $data, TWILIO_AUTH_TOKEN, true));
    return hash_equals($expected, $signature);
}

/** Llamada genérica a la API REST de Twilio (Basic Auth con Account SID + Auth Token). */
function twilioRequest(string $method, string $path, array $params = []): array {
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . $path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) return ['ok' => false, 'error' => $err ?: 'Error de conexión con Twilio'];
    $data = json_decode($body, true);
    if ($status >= 400) return ['ok' => false, 'error' => ($data['message'] ?? 'Error de Twilio (HTTP ' . $status . ')'), 'raw' => $data];
    return ['ok' => true, 'data' => $data];
}

/** Envía un mensaje de WhatsApp (texto y/o media) por Twilio. Devuelve ['ok'=>bool,'sid'=>?,'error'=>?]. */
function twilioSendWhatsApp(string $telefono, ?string $mensaje, ?string $mediaUrl = null): array {
    if (strpos(TWILIO_ACCOUNT_SID, 'CAMBIAR_') === 0 || strpos(TWILIO_AUTH_TOKEN, 'CAMBIAR_') === 0) {
        return ['ok' => false, 'error' => 'Twilio no está configurado todavía (config_whatsapp.php sigue con los valores CAMBIAR_...)'];
    }
    $params = ['From' => TWILIO_WHATSAPP_NUMBER, 'To' => 'whatsapp:' . waNormalizePhone($telefono)];
    if ($mensaje !== null && $mensaje !== '') $params['Body'] = $mensaje;
    if ($mediaUrl) $params['MediaUrl'] = $mediaUrl;
    $res = twilioRequest('POST', '/Messages.json', $params);
    if (!$res['ok']) return ['ok' => false, 'error' => $res['error']];
    return ['ok' => true, 'sid' => $res['data']['sid'] ?? null];
}

/** Crea o actualiza la fila de wa_conversaciones para un teléfono. */
function waUpsertConversacion(PDO $pdo, string $telefono, array $fields = []): void {
    $row = array_merge(['telefono' => $telefono], $fields);
    $cols = array_keys($row);
    $placeholders = implode(', ', array_map(fn($c) => ":$c", $cols));
    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $updates = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", array_filter($cols, fn($c) => $c !== 'telefono')));
    $sql = "INSERT INTO wa_conversaciones ($colList) VALUES ($placeholders)"
         . ($updates ? " ON DUPLICATE KEY UPDATE $updates" : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_combine(array_map(fn($c) => ":$c", $cols), array_values($row)));
}

/** Inserta un mensaje en wa_mensajes. Devuelve el id autoincremental. */
function waInsertMensaje(PDO $pdo, array $row): int {
    $stmt = $pdo->prepare(
        'INSERT INTO wa_mensajes (telefono, direccion, contenido, media_url, twilio_sid, entrega) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([
        $row['telefono'],
        $row['direccion'],
        $row['contenido'] ?? null,
        $row['media_url'] ?? null,
        $row['twilio_sid'] ?? null,
        $row['entrega'] ?? 'enviado',
    ]);
    return (int)$pdo->lastInsertId();
}
