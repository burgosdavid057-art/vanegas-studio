<?php
/**
 * Vanegas Studio — Helpers
 */

declare(strict_types=1);

const MESES = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
const MESES_ABR = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

function mes_nombre(int $m): string { return MESES[$m] ?? (string) $m; }
function mes_abr(int $m): string { return MESES_ABR[$m] ?? (string) $m; }

/** Escapar salida HTML */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Redirigir y cortar ejecución */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/** Guardar mensaje flash */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/** Obtener y limpiar mensajes flash */
function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* --------------------------- CSRF --------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!hash_equals(csrf_token(), (string) $sent)) {
        http_response_code(419);
        exit('Token de seguridad inválido. Recarga la página.');
    }
}

/* --------------------------- Auth --------------------------- */
function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('?p=login');
    }
}

function current_user(): array
{
    return [
        'id'      => $_SESSION['uid'] ?? null,
        'nombre'  => $_SESSION['unombre'] ?? '',
        'usuario' => $_SESSION['uusuario'] ?? '',
    ];
}

/* --------------------------- Formato --------------------------- */
/** Formato moneda COP */
function money($n): string
{
    return '$' . number_format((float) $n, 0, ',', '.');
}

/** Número compacto: 128000 -> 128K */
function compact_num($n): string
{
    $n = (float) $n;
    if ($n >= 1_000_000) {
        return rtrim(rtrim(number_format($n / 1_000_000, 1, '.', ''), '0'), '.') . 'M';
    }
    if ($n >= 1_000) {
        return rtrim(rtrim(number_format($n / 1_000, 1, '.', ''), '0'), '.') . 'K';
    }
    return (string) (int) $n;
}

/** Moneda compacta con símbolo: 800000 -> $800K */
function money_compact($n): string
{
    return '$' . compact_num($n);
}

/** Fecha legible: 2026-07-04 -> 04 jul 2026 */
function fecha($f): string
{
    if (!$f) {
        return '—';
    }
    $ts = strtotime((string) $f);
    if (!$ts) {
        return e((string) $f);
    }
    return date('d', $ts) . ' ' . mes_abr((int) date('n', $ts)) . ' ' . date('Y', $ts);
}

/** Entrada segura de POST */
function post(string $key, $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function post_num(string $key): float
{
    $v = str_replace([',', ' ', '$', '.'], ['', '', '', ''], (string) ($_POST[$key] ?? '0'));
    return (float) $v;
}

function post_int(string $key): int
{
    return (int) preg_replace('/-?\D/', '', (string) ($_POST[$key] ?? '0'));
}

function get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) && $_GET[$key] !== '' ? (int) $_GET[$key] : $default;
}

/* --------------------------- Etiquetas de estado --------------------------- */
function estado_badge(string $estado): string
{
    $map = [
        'activo'      => ['Activo', 'emerald'],
        'inactivo'    => ['Inactivo', 'zinc'],
        'mensual'     => ['Mensual', 'violet'],
        'puntual'     => ['Puntual', 'sky'],
        'agendado'    => ['Agendado', 'sky'],
        'grabado'     => ['Grabado', 'violet'],
        'edicion'     => ['En edición', 'amber'],
        'entregado'   => ['Entregado', 'emerald'],
        'cancelado'   => ['Cancelado', 'rose'],
        'pendiente'   => ['Pendiente', 'amber'],
        'pagado'      => ['Pagado', 'emerald'],
        'borrador'    => ['Borrador', 'zinc'],
        'enviada'     => ['Enviada', 'sky'],
        'aceptada'    => ['Aceptada', 'emerald'],
        'rechazada'   => ['Rechazada', 'rose'],
        'foto'        => ['Foto', 'sky'],
        'video'       => ['Video', 'violet'],
        'ambos'       => ['Foto + Video', 'fuchsia'],
    ];
    [$label, $color] = $map[$estado] ?? [ucfirst($estado), 'zinc'];
    $classes = [
        'emerald' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30',
        'amber'   => 'bg-amber-500/10 text-amber-300 ring-amber-500/30',
        'sky'     => 'bg-sky-500/10 text-sky-300 ring-sky-500/30',
        'violet'  => 'bg-violet-500/10 text-violet-300 ring-violet-500/30',
        'fuchsia' => 'bg-fuchsia-500/10 text-fuchsia-300 ring-fuchsia-500/30',
        'rose'    => 'bg-rose-500/10 text-rose-300 ring-rose-500/30',
        'zinc'    => 'bg-zinc-500/10 text-zinc-300 ring-zinc-500/30',
    ][$color];
    return '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ' . $classes . '">'
        . '<span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>' . e($label) . '</span>';
}

/** Construye un link de WhatsApp (wa.me) con mensaje pre-armado. '' si no hay teléfono. */
function wa_link(?string $telefono, string $mensaje): string
{
    $num = preg_replace('/\D/', '', (string) $telefono);
    if ($num === '') {
        return '';
    }
    $pais = preg_replace('/\D/', '', cfg('wa_pais', '57'));
    // Si tiene 10 dígitos (celular colombiano) le anteponemos el indicativo
    if (strlen($num) === 10 && $pais !== '' && strpos($num, $pais) !== 0) {
        $num = $pais . $num;
    }
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($mensaje);
}

/** Categorías sugeridas de egreso */
function categorias_egreso(): array
{
    return ['Equipo', 'Software', 'Transporte', 'Almacenamiento', 'Alquiler', 'Publicidad', 'Impuestos', 'Servicios', 'Otros'];
}
