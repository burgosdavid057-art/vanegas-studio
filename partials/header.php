<?php
/** Layout — cabecera, estilos y navegación lateral */
$current = $_GET['p'] ?? 'dashboard';

$nav = [
    'dashboard'     => ['Panel', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    'clientes'      => ['Clientes', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4 0M17 8a3 3 0 11-6 0 3 3 0 016 0z'],
    'mensualidades' => ['Mensualidades', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    'trabajos'      => ['Trabajos', 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
    'cotizaciones'  => ['Cotizaciones', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    'finanzas'      => ['Finanzas', 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
    'reportes'      => ['Reportes', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    'ajustes'       => ['Ajustes', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
];
$user = current_user();
?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?><?= APP_NAME ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                base:    '#0a0a12',
                surface: '#12121d',
                card:    '#171725',
                brand:   { 400:'#a78bfa', 500:'#8b5cf6', 600:'#7c3aed' },
                accent:  { 400:'#f472b6', 500:'#ec4899', 600:'#db2777' },
            },
            fontFamily: {
                sans:    ['Inter','ui-sans-serif','system-ui','sans-serif'],
                display: ['"Space Grotesk"','Inter','sans-serif'],
            },
        },
    },
};
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<style>
    body { background: #0a0a12; }
    /* Fondo con destellos de color */
    .aurora::before, .aurora::after {
        content:''; position:fixed; z-index:0; border-radius:9999px;
        filter: blur(120px); opacity:.35; pointer-events:none;
    }
    .aurora::before { width:520px; height:520px; top:-160px; left:-120px;
        background: radial-gradient(circle, #7c3aed, transparent 70%); }
    .aurora::after  { width:480px; height:480px; bottom:-180px; right:-100px;
        background: radial-gradient(circle, #db2777, transparent 70%); }
    .glass { background: rgba(23,23,37,.7); backdrop-filter: blur(16px);
        border:1px solid rgba(255,255,255,.06); }
    .grad-text { background:linear-gradient(120deg,#a78bfa,#f472b6); -webkit-background-clip:text;
        background-clip:text; color:transparent; }
    .grad-btn { background:linear-gradient(120deg,#7c3aed,#db2777); }
    .grad-btn:hover { filter:brightness(1.12); }
    ::-webkit-scrollbar { width:10px; height:10px; }
    ::-webkit-scrollbar-track { background:transparent; }
    ::-webkit-scrollbar-thumb { background:#2a2a3d; border-radius:9999px; border:2px solid #0a0a12; }
    ::-webkit-scrollbar-thumb:hover { background:#3a3a52; }
    [x-cloak] { display:none !important; }
    .nav-active { background:linear-gradient(120deg,rgba(124,58,237,.18),rgba(219,39,119,.14));
        color:#fff; box-shadow:inset 0 0 0 1px rgba(167,139,250,.25); }
    .nav-active .nav-ico { color:#c4b5fd; }
    tr.row-hover:hover { background:rgba(255,255,255,.025); }
    @keyframes rise { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:none} }
    .rise { animation: rise .5s cubic-bezier(.2,.7,.3,1) both; }
</style>
</head>
<body class="aurora min-h-screen font-sans text-zinc-200 antialiased">
<div class="relative z-10 flex min-h-screen" x-data="{ mobileOpen:false }">

    <!-- Sidebar -->
    <aside class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 border-r border-white/5 bg-surface/80 backdrop-blur-xl transition-transform lg:translate-x-0"
           :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex h-16 items-center gap-2.5 px-5 border-b border-white/5">
            <div class="grid h-9 w-9 place-items-center rounded-xl grad-btn text-white font-display font-bold">V</div>
            <div class="leading-tight">
                <p class="font-display font-semibold text-white">Vanegas</p>
                <p class="text-[11px] uppercase tracking-widest text-zinc-500">Studio · Foto & Video</p>
            </div>
        </div>
        <nav class="p-3 space-y-1">
            <?php foreach ($nav as $key => [$label, $icon]): ?>
                <a href="?p=<?= $key ?>"
                   class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-zinc-400 transition hover:text-white hover:bg-white/5 <?= $current === $key ? 'nav-active' : '' ?>">
                    <svg class="nav-ico h-5 w-5 shrink-0 transition group-hover:text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/>
                    </svg>
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="absolute inset-x-0 bottom-0 p-3 border-t border-white/5">
            <div class="flex items-center gap-3 rounded-xl px-3 py-2">
                <div class="grid h-9 w-9 place-items-center rounded-full bg-brand-500/20 text-brand-300 font-semibold">
                    <?= strtoupper(substr($user['nombre'] ?: 'A', 0, 1)) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-white"><?= e($user['nombre']) ?></p>
                    <p class="truncate text-xs text-zinc-500">@<?= e($user['usuario']) ?></p>
                </div>
                <a href="?p=logout" title="Cerrar sesión" class="rounded-lg p-1.5 text-zinc-500 hover:text-rose-400 hover:bg-white/5">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Overlay móvil -->
    <div x-show="mobileOpen" x-cloak @click="mobileOpen=false" class="fixed inset-0 z-30 bg-black/60 lg:hidden"></div>

    <!-- Contenido -->
    <div class="flex min-h-screen flex-1 flex-col lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-white/5 bg-base/70 px-4 backdrop-blur-xl sm:px-6">
            <button @click="mobileOpen=true" class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 lg:hidden">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div>
                <h1 class="font-display text-lg font-semibold text-white"><?= e($pageTitle ?? 'Panel') ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                    <p class="text-xs text-zinc-500"><?= e($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="ml-auto hidden items-center gap-2 text-xs text-zinc-500 sm:flex">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <?= ucfirst(fecha(date('Y-m-d'))) ?>
            </div>
        </header>

        <!-- Flash -->
        <?php $flashes = take_flash(); if ($flashes): ?>
            <div class="px-4 pt-4 sm:px-6 space-y-2" x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,4500)" x-transition.opacity.duration.500ms>
                <?php foreach ($flashes as $f):
                    $c = $f['type'] === 'error'
                        ? 'bg-rose-500/10 text-rose-200 ring-rose-500/30'
                        : 'bg-emerald-500/10 text-emerald-200 ring-emerald-500/30'; ?>
                    <div class="flex items-center gap-2 rounded-xl px-4 py-3 text-sm ring-1 <?= $c ?>">
                        <?= e($f['msg']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <main class="flex-1 px-4 py-6 sm:px-6">
