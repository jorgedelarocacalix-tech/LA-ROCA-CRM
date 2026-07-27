<?php
session_start();
require_once __DIR__ . '/config.php';

function requireAuth(): void {
    if (empty($_SESSION['cu_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autenticado']);
        exit;
    }
}

// Solo admin/subadmin pueden hacer altas y bajas de usuarios, editar metas, etc.
function requireAdmin(): void {
    requireAuth();
    if (!in_array($_SESSION['cu_role'] ?? '', ['admin', 'subadmin'], true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }
}

function currentUserId(): ?string {
    return $_SESSION['cu_id'] ?? null;
}

/**
 * La app manda filtros al estilo PostgREST (?campo=eq.valor, ?campo=gte.valor, etc.)
 * porque el frontend viene de una versión que hablaba con Supabase REST. Estos
 * helpers los traducen a SQL con parámetros preparados, sin cambiar el JS del cliente.
 */
function filterOp(string $raw): array {
    foreach (['gte', 'lte', 'gt', 'lt', 'neq', 'eq'] as $op) {
        $prefix = $op . '.';
        if (strpos($raw, $prefix) === 0) {
            return [$op, substr($raw, strlen($prefix))];
        }
    }
    return ['eq', $raw];
}

/**
 * $_GET colapsa claves repetidas (p.ej. ?created_at=gte.X&created_at=lte.Y sólo
 * deja la última) — pero el frontend sí manda ese patrón (rango de fechas con
 * gte + lte sobre la misma columna) en un par de pantallas (Monitor). Por eso
 * esta parte no usa $_GET sino que parsea QUERY_STRING a mano, preservando
 * repeticiones.
 */
function queryPairs(): array {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($qs === '') return [];
    $pairs = [];
    foreach (explode('&', $qs) as $part) {
        if ($part === '') continue;
        $eq = strpos($part, '=');
        if ($eq === false) { $k = urldecode($part); $v = ''; }
        else { $k = urldecode(substr($part, 0, $eq)); $v = urldecode(str_replace('+', ' ', substr($part, $eq + 1))); }
        $pairs[] = [$k, $v];
    }
    return $pairs;
}

/**
 * Construye la parte WHERE de una consulta a partir de la query string,
 * restringido a $allowedCols (whitelist de columnas reales de la tabla — evita
 * cualquier inyección por nombre de columna). Devuelve [sqlWhere, params].
 */
function buildWhere(array $allowedCols): array {
    $clauses = [];
    $params = [];
    $opSql = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
    foreach (queryPairs() as [$col, $rawVal]) {
        if (!in_array($col, $allowedCols, true)) continue;
        [$op, $val] = filterOp($rawVal);
        $clauses[] = "`$col` " . $opSql[$op] . ' ?';
        $params[] = $val;
    }
    return [$clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '', $params];
}

function buildOrder(array $allowedCols, string $default = ''): string {
    $raw = $_GET['order'] ?? $default;
    if ($raw === '') return '';
    $parts = explode('.', $raw, 2);
    $col = $parts[0];
    $dir = (isset($parts[1]) && strtolower($parts[1]) === 'desc') ? 'DESC' : 'ASC';
    if (!in_array($col, $allowedCols, true)) return '';
    return "ORDER BY `$col` $dir";
}

function limitOffset(int $defaultLimit = 5000): string {
    $limit = isset($_GET['limit']) ? max(1, min(20000, (int)$_GET['limit'])) : $defaultLimit;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    return "LIMIT $limit OFFSET $offset";
}

function jsonBody(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

/**
 * El frontend a veces manda columnas JSON (cartera, kb_comments, payments,
 * leido_por) ya como array nativo, y a veces (usuarios.cartera en un punto
 * del código viejo) como un string que ya es JSON.stringify() de un array.
 * Esto normaliza ambos casos a un string JSON válido para guardar en la
 * columna JSON de MySQL.
 */
function normalizeJsonCol($val): ?string {
    if ($val === null) return null;
    if (is_array($val)) return json_encode($val);
    if (is_string($val)) {
        json_decode($val);
        if (json_last_error() === JSON_ERROR_NONE) return $val;
        return json_encode($val);
    }
    return json_encode($val);
}

/**
 * Convierte una lista de columnas -> valores en un INSERT ... ON DUPLICATE KEY
 * UPDATE genérico. $idCol es la columna clave (normalmente `id`); si el
 * registro no trae esa columna, hace un INSERT simple (para tablas con id
 * autoincremental como recursos/mensajes_internos).
 */
function upsert(PDO $pdo, string $table, array $row, string $idCol = 'id'): void {
    $cols = array_keys($row);
    $placeholders = array_fill(0, count($cols), '?');
    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
    if (isset($row[$idCol]) && $row[$idCol] !== null && $row[$idCol] !== '') {
        $updates = implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", array_filter($cols, fn($c) => $c !== $idCol)));
        $sql = "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $placeholders) . ")"
             . ($updates ? " ON DUPLICATE KEY UPDATE $updates" : '');
    } else {
        unset($row[$idCol]);
        $cols = array_keys($row);
        $placeholders = array_fill(0, count($cols), '?');
        $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $sql = "INSERT INTO `$table` ($colList) VALUES (" . implode(',', $placeholders) . ")";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($row));
}

function recordsFromBody(array $body): array {
    // sbUp() del frontend siempre manda un array (uno o varios registros).
    if ($body === []) return [];
    $isList = array_keys($body) === range(0, count($body) - 1);
    return $isList ? $body : [$body];
}
