<?php
// Lista de conversaciones de WhatsApp (barra lateral del chat). GET, requiere sesión.
require_once __DIR__ . '/_common.php';
requireAuth();
header('Content-Type: application/json');

$pdo = db();
$rows = $pdo->query('SELECT * FROM wa_conversaciones ORDER BY updated_at DESC LIMIT 500')->fetchAll();

if ($rows) {
    // Si Twilio no mandó ProfileName (no todos los clientes lo configuran), intenta
    // completar nombre/empresa buscando el teléfono en `clientes` — sólo para mostrar
    // mejor en la lista, no se guarda en wa_conversaciones.
    $stmtCli = $pdo->prepare(
        "SELECT c.nombre, ca.name AS empresa FROM clientes c
         JOIN carteras ca ON ca.id = c.cartera_id
         WHERE c.telefono IS NOT NULL
           AND RIGHT(REPLACE(REPLACE(REPLACE(c.telefono,'-',''),' ',''),'+',''), 8) = RIGHT(REPLACE(?, '+', ''), 8)
         LIMIT 1"
    );
    foreach ($rows as &$r) {
        if (empty($r['nombre']) || empty($r['empresa'])) {
            $stmtCli->execute([$r['telefono']]);
            $m = $stmtCli->fetch();
            if ($m) {
                if (empty($r['nombre'])) $r['nombre'] = $m['nombre'];
                if (empty($r['empresa'])) $r['empresa'] = $m['empresa'];
            }
        }
    }
    unset($r);
}

echo json_encode($rows);
