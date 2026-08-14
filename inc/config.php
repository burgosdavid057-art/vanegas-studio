<?php
/**
 * Vanegas Studio — Configuración central
 * Sesión, conexión SQLite, esquema, seed y helpers.
 * Gestor financiero para estudio de foto & video.
 */

declare(strict_types=1);

session_start();

date_default_timezone_set('America/Bogota');

define('APP_NAME', 'Vanegas Studio');
define('BASE_PATH', dirname(__DIR__));
define('DATA_DIR', BASE_PATH . '/data');
define('DB_FILE', DATA_DIR . '/vanegas.db');

/* ---------------------------------------------------------------------------
 * Conexión a la base de datos (SQLite vía PDO)
 * ------------------------------------------------------------------------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }

    $firstRun = !file_exists(DB_FILE);

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    init_schema($pdo, $firstRun);

    return $pdo;
}

/* ---------------------------------------------------------------------------
 * Esquema + datos semilla
 * ------------------------------------------------------------------------- */
function init_schema(PDO $pdo, bool $seed): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre   TEXT NOT NULL,
            usuario  TEXT NOT NULL UNIQUE,
            clave    TEXT NOT NULL,
            creado   TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS clientes (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre         TEXT NOT NULL,
            tipo           TEXT NOT NULL DEFAULT 'puntual',   -- mensual | puntual
            tarifa_mensual REAL NOT NULL DEFAULT 0,
            dia_cobro      INTEGER NOT NULL DEFAULT 1,
            email          TEXT,
            telefono       TEXT,
            estado         TEXT NOT NULL DEFAULT 'activo',    -- activo | inactivo
            notas          TEXT,
            creado         TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );

        -- Libro financiero: única fuente de la verdad para ingresos y egresos
        CREATE TABLE IF NOT EXISTS movimientos (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo       TEXT NOT NULL DEFAULT 'ingreso',   -- ingreso | egreso
            categoria  TEXT,
            concepto   TEXT NOT NULL,
            monto      REAL NOT NULL DEFAULT 0,
            fecha      TEXT NOT NULL,
            cliente_id INTEGER,
            metodo     TEXT,
            origen     TEXT NOT NULL DEFAULT 'manual',     -- manual | mensualidad | trabajo
            ref_id     INTEGER,
            notas      TEXT,
            creado     TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
        );

        -- Mensualidades (cobros recurrentes por mes)
        CREATE TABLE IF NOT EXISTS mensualidades (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id    INTEGER NOT NULL,
            anio          INTEGER NOT NULL,
            mes           INTEGER NOT NULL,
            monto         REAL NOT NULL DEFAULT 0,
            estado        TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | pagado
            fecha_pago    TEXT,
            metodo        TEXT,
            movimiento_id INTEGER,
            notas         TEXT,
            creado        TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            UNIQUE (cliente_id, anio, mes),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
        );

        -- Trabajos / sesiones de foto y video
        CREATE TABLE IF NOT EXISTS trabajos (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id    INTEGER,
            titulo        TEXT NOT NULL,
            tipo          TEXT NOT NULL DEFAULT 'foto',      -- foto | video | ambos
            descripcion   TEXT,
            estado        TEXT NOT NULL DEFAULT 'agendado',  -- agendado | grabado | edicion | entregado | cancelado
            fecha_sesion  TEXT,
            fecha_entrega TEXT,
            lugar         TEXT,
            valor         REAL NOT NULL DEFAULT 0,
            cobrado       INTEGER NOT NULL DEFAULT 0,
            movimiento_id INTEGER,
            entrega_url   TEXT,
            orden         INTEGER NOT NULL DEFAULT 0,
            notas         TEXT,
            creado        TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
        );

        -- Cotizaciones / propuestas
        CREATE TABLE IF NOT EXISTS cotizaciones (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id   INTEGER,
            titulo       TEXT NOT NULL,
            detalle      TEXT,
            valor        REAL NOT NULL DEFAULT 0,
            estado       TEXT NOT NULL DEFAULT 'borrador',  -- borrador | enviada | aceptada | rechazada
            fecha        TEXT,
            validez_dias INTEGER NOT NULL DEFAULT 15,
            trabajo_id   INTEGER,
            creado       TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
        );

        -- Configuración clave/valor
        CREATE TABLE IF NOT EXISTS config (
            clave TEXT PRIMARY KEY,
            valor TEXT
        );
    ");

    migrate($pdo);

    if (!$seed) {
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, clave) VALUES (?,?,?)");
    $stmt->execute(['Vanegas', 'admin', password_hash('vanegas2025', PASSWORD_DEFAULT)]);

    seed_demo($pdo);
}

/** Migraciones no destructivas: añade columnas/valores que falten en BD existentes */
function migrate(PDO $pdo): void
{
    $cols = array_column($pdo->query("PRAGMA table_info(trabajos)")->fetchAll(), 'name');
    if (!in_array('entrega_url', $cols, true)) {
        $pdo->exec("ALTER TABLE trabajos ADD COLUMN entrega_url TEXT");
    }
    if (!in_array('orden', $cols, true)) {
        $pdo->exec("ALTER TABLE trabajos ADD COLUMN orden INTEGER NOT NULL DEFAULT 0");
    }

    $defaults = ['meta_mensual' => '3000000', 'wa_pais' => '57', 'negocio' => 'Vanegas Studio'];
    $ins = $pdo->prepare("INSERT OR IGNORE INTO config (clave, valor) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
}

/** Lee un valor de configuración */
function cfg(string $clave, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT clave, valor FROM config") as $r) {
            $cache[$r['clave']] = $r['valor'];
        }
    }
    return $cache[$clave] ?? $default;
}

function seed_demo(PDO $pdo): void
{
    // --- Clientes ---
    $clientes = [
        // nombre, tipo, tarifa, dia_cobro, email, telefono, estado
        ['Restaurante La Brasa', 'mensual', 800000, 5, 'gerencia@labrasa.co', '3104567890', 'activo'],
        ['Gimnasio PowerFit', 'mensual', 650000, 1, 'marketing@powerfit.co', '3159876543', 'activo'],
        ['Boutique Alma', 'mensual', 500000, 10, 'hola@boutiquealma.co', '3201122334', 'activo'],
        ['Boda Andrea & Juan', 'puntual', 0, 1, 'andrea@gmail.com', '3117788990', 'activo'],
        ['Dr. Gómez — Marca personal', 'puntual', 0, 1, 'consultorio@drgomez.co', '3126655443', 'activo'],
    ];
    $ins = $pdo->prepare("INSERT INTO clientes (nombre,tipo,tarifa_mensual,dia_cobro,email,telefono,estado) VALUES (?,?,?,?,?,?,?)");
    foreach ($clientes as $c) {
        $ins->execute($c);
    }

    $anio = (int) date('Y');
    $mesActual = (int) date('n');

    $insMov = $pdo->prepare("INSERT INTO movimientos (tipo,categoria,concepto,monto,fecha,cliente_id,metodo,origen,ref_id) VALUES (?,?,?,?,?,?,?,?,?)");
    $insMen = $pdo->prepare("INSERT INTO mensualidades (cliente_id,anio,mes,monto,estado,fecha_pago,metodo,movimiento_id) VALUES (?,?,?,?,?,?,?,?)");

    // --- Mensualidades: últimos 4 meses para los 3 clientes mensuales ---
    $mensuales = [1 => 800000, 2 => 650000, 3 => 500000];
    $mesesAtras = [];
    for ($i = 3; $i >= 0; $i--) {
        $m = $mesActual - $i;
        $a = $anio;
        while ($m <= 0) { $m += 12; $a--; }
        $mesesAtras[] = [$a, $m];
    }
    foreach ($mensuales as $cid => $tarifa) {
        foreach ($mesesAtras as $idx => [$a, $m]) {
            // El mes actual queda pendiente; los anteriores pagados
            $pagado = $idx < count($mesesAtras) - 1;
            $movId = null;
            if ($pagado) {
                $fecha = sprintf('%04d-%02d-%02d', $a, $m, min(15, (int) date('j')));
                $insMov->execute(['ingreso', 'Mensualidad', 'Mensualidad ' . mes_abr($m) . ' — ' . $clientes[$cid - 1][0], $tarifa, $fecha, $cid, 'Transferencia', 'mensualidad', 0]);
                $movId = (int) $pdo->lastInsertId();
            }
            $insMen->execute([$cid, $a, $m, $tarifa, $pagado ? 'pagado' : 'pendiente', $movId ? $fecha : null, $movId ? 'Transferencia' : null, $movId]);
            if ($movId) {
                $pdo->prepare("UPDATE movimientos SET ref_id=? WHERE id=?")->execute([(int) $pdo->lastInsertId(), $movId]);
            }
        }
    }

    // --- Trabajos ---
    $hoy = date('Y-m-d');
    $trabajos = [
        // cliente_id, titulo, tipo, descripcion, estado, fecha_sesion, fecha_entrega, lugar, valor, cobrado
        [4, 'Boda Andrea & Juan', 'video', 'Cobertura completa + video resumen 5 min', 'entregado', date('Y-m-d', strtotime('-25 days')), date('Y-m-d', strtotime('-5 days')), 'Hacienda El Retiro', 3500000, 1],
        [5, 'Sesión marca personal Dr. Gómez', 'foto', '30 fotos editadas para redes y web', 'edicion', date('Y-m-d', strtotime('-4 days')), date('Y-m-d', strtotime('+3 days')), 'Consultorio', 900000, 0],
        [1, 'Reel mensual La Brasa — platos nuevos', 'video', 'Reel 30s para Instagram', 'grabado', date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+2 days')), 'Sede principal', 0, 0],
        [3, 'Catálogo temporada Boutique Alma', 'foto', 'Lookbook 40 prendas', 'agendado', date('Y-m-d', strtotime('+6 days')), date('Y-m-d', strtotime('+13 days')), 'Estudio', 1200000, 0],
    ];
    $insTra = $pdo->prepare("INSERT INTO trabajos (cliente_id,titulo,tipo,descripcion,estado,fecha_sesion,fecha_entrega,lugar,valor,cobrado,movimiento_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($trabajos as $t) {
        $movId = null;
        if ($t[9] && $t[8] > 0) { // cobrado
            $insMov->execute(['ingreso', 'Trabajo', $t[1], $t[8], $t[6], $t[0], 'Transferencia', 'trabajo', 0]);
            $movId = (int) $pdo->lastInsertId();
        }
        $insTra->execute([...$t, $movId]);
        if ($movId) {
            $pdo->prepare("UPDATE movimientos SET ref_id=? WHERE id=?")->execute([(int) $pdo->lastInsertId(), $movId]);
        }
    }

    // --- Egresos de ejemplo ---
    $egresos = [
        ['Software', 'Suscripción Adobe Creative Cloud', 120000, date('Y-m-05'), 'Tarjeta'],
        ['Equipo', 'Alquiler lente 24-70mm f2.8', 150000, date('Y-m-d', strtotime('-20 days')), 'Efectivo'],
        ['Transporte', 'Gasolina desplazamientos', 220000, date('Y-m-d', strtotime('-10 days')), 'Efectivo'],
        ['Equipo', 'Disco duro SSD externo 2TB', 780000, date('Y-m-d', strtotime('-15 days')), 'Tarjeta'],
        ['Almacenamiento', 'Google One 2TB (nube)', 45000, date('Y-m-08'), 'Tarjeta'],
    ];
    foreach ($egresos as $g) {
        $insMov->execute(['egreso', $g[0], $g[1], $g[2], $g[3], null, $g[4], 'manual', null]);
    }

    // Link de entrega de ejemplo en la boda (trabajo entregado)
    $pdo->exec("UPDATE trabajos SET entrega_url='https://drive.google.com/drive/ejemplo-boda' WHERE titulo LIKE 'Boda%'");

    // --- Cotizaciones ---
    $cotis = [
        [4, 'Paquete boda premium', "Cobertura 8h · 2 cámaras · video resumen 5 min · 300 fotos editadas · álbum digital", 3500000, 'aceptada', date('Y-m-d', strtotime('-40 days')), 20],
        [null, 'Video institucional empresa', "Guion + grabación 1 día + edición 3 min + música con licencia", 2800000, 'enviada', date('Y-m-d', strtotime('-3 days')), 15],
        [3, 'Producción contenido redes (mes)', "12 fotos + 4 reels editados al mes", 1500000, 'borrador', date('Y-m-d'), 15],
    ];
    $insCot = $pdo->prepare("INSERT INTO cotizaciones (cliente_id,titulo,detalle,valor,estado,fecha,validez_dias) VALUES (?,?,?,?,?,?,?)");
    foreach ($cotis as $c) {
        $insCot->execute($c);
    }
}

require __DIR__ . '/helpers.php';
