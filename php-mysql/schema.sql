-- LA ROCA CRM — esquema MySQL (reemplazo de Supabase/Postgres)
-- Importar en phpMyAdmin (cPanel) sobre una base de datos vacía.
-- Refleja el modelo real de la app original (index.html con Supabase REST):
-- usuarios, carteras (líneas de negocio), clientes (kanban de recompra),
-- gestiones (llamadas/actividad de vendedores, incluye ventas auto-registradas),
-- leads (kanban de clientes nuevos / Facebook-publicidad), metas (por vendedor
-- y cartera — antes una constante METAS hardcodeada en el JS), recursos
-- (archivos/links del equipo) y mensajes_internos (chat interno del equipo).

-- ── CARTERAS (líneas de negocio: La Roca, Roca Motors, Su Mueble, Su Moto...) ──
CREATE TABLE IF NOT EXISTS carteras (
  id   VARCHAR(40) PRIMARY KEY,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO carteras (id, name) VALUES
  ('roca1',         'Roca 1'),
  ('roca1motors',   'Roca Motors 1'),
  ('roca2motors',   'Roca Motors 2'),
  ('sumotodanli',   'Su Moto Danli'),
  ('sumuebledanli', 'Su Mueble Danli')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ── USUARIOS (antes: 14 usuarios + PIN hardcodeados en texto plano dentro del JS) ──
-- role: admin | subadmin | vendedor
-- cartera: JSON array de IDs de `carteras` a los que tiene acceso (admin: [] = todas)
CREATE TABLE IF NOT EXISTS usuarios (
  id         VARCHAR(40) PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  pin        VARCHAR(10)  NOT NULL,
  role       ENUM('admin','subadmin','vendedor') NOT NULL DEFAULT 'vendedor',
  cartera    JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_pin (pin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO usuarios (id, name, pin, role, cartera) VALUES
  ('admin',              'Jorge Daniel', '1234', 'admin',    JSON_ARRAY()),
  ('u1779318896833',     'Ana Castro',   '3434', 'subadmin', JSON_ARRAY('roca1','roca1motors')),
  ('v_ale',              'Alejandra',    '1111', 'vendedor', JSON_ARRAY('roca1','roca1motors')),
  ('v_ale2',             'Alejandra',    '3131', 'vendedor', JSON_ARRAY('roca2motors')),
  ('v_all',              'Allison',      '5252', 'vendedor', JSON_ARRAY('sumotodanli','sumuebledanli')),
  ('v_bra',              'Brayan',       '5555', 'vendedor', JSON_ARRAY('sumotodanli','sumuebledanli')),
  ('v_den',              'Denys',        '4141', 'vendedor', JSON_ARRAY('roca2motors')),
  ('v_fer',              'fer',          '2323', 'vendedor', JSON_ARRAY('roca1','roca1motors')),
  ('v_gab',              'Gabriela',     '4242', 'vendedor', JSON_ARRAY('roca2motors')),
  ('v_joh',              'johana',       '1212', 'subadmin', JSON_ARRAY('roca1','roca1motors')),
  ('v_jor',              'Jorge',        '5454', 'vendedor', JSON_ARRAY('sumotodanli','sumuebledanli')),
  ('v_kar',              'Karla',        '5353', 'vendedor', JSON_ARRAY('sumotodanli','sumuebledanli')),
  ('v_mil',              'Milagro',      '2222', 'subadmin', JSON_ARRAY('roca2motors')),
  ('v_say',              'Sayda',        '5151', 'subadmin', JSON_ARRAY('sumotodanli','sumuebledanli'))
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ── CLIENTES (tarjetas del Kanban de Recompra) ──
-- kb_col: prospecto|contactado|interesado|negociando|credito|vendido|perdido
-- kb_comments: JSON array de {texto, autor, fecha} — historial de seguimiento
-- payments: JSON array de {fecha, monto, forma} — historial de pagos (import Excel opcional)
CREATE TABLE IF NOT EXISTS clientes (
  id           VARCHAR(40) PRIMARY KEY,
  cartera_id   VARCHAR(40) NOT NULL,
  nombre       VARCHAR(150) NOT NULL,
  telefono     VARCHAR(30)  NULL,
  estado       VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
  kb_col       VARCHAR(30)  NULL,
  kb_comments  JSON NULL,
  total_pagos  INT NOT NULL DEFAULT 0,
  monto_total  DECIMAL(14,2) NOT NULL DEFAULT 0,
  ultimo_pago  VARCHAR(20)  NULL,
  primer_pago  VARCHAR(20)  NULL,
  antiguedad   DECIMAL(8,2) NOT NULL DEFAULT 0,
  patron       VARCHAR(20)  NOT NULL DEFAULT 'Mensual',
  payments     JSON NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clientes_cartera (cartera_id),
  KEY idx_clientes_nombre (nombre),
  CONSTRAINT fk_clientes_cartera FOREIGN KEY (cartera_id) REFERENCES carteras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── GESTIONES (llamadas/actividad de vendedores) ──
-- client_id NULL = registro de "actividad" (ver estado='actividad', usado para el
-- log de acciones, p.ej. movimientos de Kanban) en vez de una gestión real de venta.
-- estado='pago' = venta contada para Metas/Monitor (manual o auto-registrada al mover
-- una tarjeta a "Vendido"/"Con cuenta activa" en el Kanban).
CREATE TABLE IF NOT EXISTS gestiones (
  id          VARCHAR(40) PRIMARY KEY,
  client_id   VARCHAR(40) NULL,
  cartera_id  VARCHAR(40) NULL,
  user_id     VARCHAR(40) NULL,
  user_name   VARCHAR(120) NULL,
  date_str    VARCHAR(20) NULL,
  date_iso    DATE NULL,
  resultado   VARCHAR(40) NULL,
  estado      VARCHAR(30) NULL,
  producto    VARCHAR(150) NULL,
  notas       TEXT NULL,
  num2        VARCHAR(30) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gestiones_cartera (cartera_id),
  KEY idx_gestiones_client (client_id),
  KEY idx_gestiones_user (user_id),
  KEY idx_gestiones_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── LEADS (Kanban "Clientes Nuevos" y "Facebook/Publicidad" — comparten esta tabla;
--    el tablero de Facebook filtra en el cliente por `fuente` conteniendo
--    facebook/fb/publicidad/pub) ──
-- lead_col: nl|nc|ni2|nn|ncr|nv|np
CREATE TABLE IF NOT EXISTS leads (
  id             VARCHAR(40) PRIMARY KEY,
  nombre         VARCHAR(150) NOT NULL,
  telefono       VARCHAR(30) NULL,
  fecha_contacto VARCHAR(20) NULL,
  fuente         VARCHAR(60) NULL,
  producto       VARCHAR(150) NULL,
  notas          TEXT NULL,
  lead_col       VARCHAR(20) NOT NULL DEFAULT 'nl',
  kb_comments    JSON NULL,
  created_by     VARCHAR(120) NULL,
  created_at     VARCHAR(20) NULL,
  assigned_to    VARCHAR(120) NULL,
  lead_cartera   VARCHAR(40) NULL,
  KEY idx_leads_col (lead_col)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── METAS (antes: constante METAS hardcodeada en el JS; ahora editable desde
--    Equipo → 🎯 Meta, sin tocar código) ──
CREATE TABLE IF NOT EXISTS metas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     VARCHAR(40) NOT NULL,
  cartera_id  VARCHAR(40) NOT NULL,
  unidades    DECIMAL(8,2) NOT NULL DEFAULT 0,
  monto       DECIMAL(14,2) NULL,
  UNIQUE KEY uq_metas_user_cartera (user_id, cartera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO metas (user_id, cartera_id, unidades, monto) VALUES
  ('v_fer',           'roca1',         25, 375000),
  ('v_fer',           'roca1motors',    3, NULL),
  ('v_ale',           'roca1',         30, 375000),
  ('v_ale',           'roca1motors',    3, NULL),
  ('u1779318896833',  'roca1',         30, 375000),
  ('u1779318896833',  'roca1motors',    3, NULL),
  ('v_mil',           'roca1',          3, 100000),
  ('v_den',           'roca2motors',   30, NULL),
  ('v_gab',           'roca2motors',   20, NULL),
  ('v_jor',           'sumuebledanli', 20, 250000),
  ('v_jor',           'sumotodanli',    6, NULL),
  ('v_kar',           'sumuebledanli', 20, 300000),
  ('v_kar',           'sumotodanli',    4, NULL),
  ('v_say',           'sumuebledanli', 20, 300000),
  ('v_say',           'sumotodanli',    4, NULL),
  ('v_bra',           'sumuebledanli', 10, 150000),
  ('v_bra',           'sumotodanli',    2, NULL),
  ('v_all',           'sumuebledanli', 15, 250000),
  ('v_all',           'sumotodanli',    3, NULL)
ON DUPLICATE KEY UPDATE unidades = VALUES(unidades), monto = VALUES(monto);

-- ── RECURSOS (archivos/links del equipo: promociones, catálogos, scripts, etc.) ──
CREATE TABLE IF NOT EXISTS recursos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(150) NOT NULL,
  descripcion VARCHAR(255) NULL,
  url         VARCHAR(500) NOT NULL,
  categoria   VARCHAR(60) NOT NULL DEFAULT 'Otros',
  created_by  VARCHAR(120) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MENSAJES INTERNOS (chat interno del equipo) ──
-- to_user NULL = mensaje para todo el equipo. leido_por: JSON array de nombres.
CREATE TABLE IF NOT EXISTS mensajes_internos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  from_name  VARCHAR(120) NULL,
  from_role  VARCHAR(30)  NULL,
  to_user    VARCHAR(120) NULL,
  mensaje    TEXT NOT NULL,
  tipo       VARCHAR(20) NOT NULL DEFAULT 'admin',
  leido_por  JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── WHATSAPP (reconstruido — ver php-mysql/README.md sección "Módulo de WhatsApp") ──
-- Backend original (Node.js/Express + Twilio) nunca se subió a GitHub, vivía sólo en la
-- máquina local del dueño. Este esquema replica lo documentado en CONTEXT.md para el
-- backend de Twilio Sandbox (el WhatsApp Business real de Meta sigue desactivado).

-- Una fila por contacto/número. `estado` es una etiqueta simple derivada del último
-- movimiento (no hay análisis con IA en esta versión): 'pregunta' = llegó un mensaje
-- entrante sin responder todavía, 'enviado' = se le mandó un envío masivo, NULL = ya
-- se le respondió individualmente / sin novedad.
CREATE TABLE IF NOT EXISTS wa_conversaciones (
  telefono       VARCHAR(30) PRIMARY KEY,
  nombre         VARCHAR(150) NULL,
  empresa        VARCHAR(100) NULL,
  canal          VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
  estado         VARCHAR(30) NULL,
  ultimo_mensaje VARCHAR(255) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historial de mensajes. direccion: entrante (del cliente) | saliente (nuestro).
-- entrega: enviado -> sent -> delivered -> read / failed (actualizado por
-- api/whatsapp/status_callback.php cuando Twilio notifica cambios de estado).
-- twilio_sid: SID del mensaje en Twilio, usado para casar el status callback.
CREATE TABLE IF NOT EXISTS wa_mensajes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  telefono   VARCHAR(30) NOT NULL,
  direccion  ENUM('entrante','saliente') NOT NULL,
  contenido  TEXT NULL,
  media_url  VARCHAR(500) NULL,
  timestamp  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  twilio_sid VARCHAR(64) NULL,
  entrega    VARCHAR(20) NULL DEFAULT 'enviado',
  KEY idx_wa_mensajes_telefono (telefono),
  KEY idx_wa_mensajes_sid (twilio_sid),
  CONSTRAINT fk_wa_mensajes_telefono FOREIGN KEY (telefono) REFERENCES wa_conversaciones(telefono) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
