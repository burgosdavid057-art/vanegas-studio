<?php
/** Panel principal — resumen financiero del estudio */
$pageTitle    = 'Panel';
$pageSubtitle = 'Resumen · ' . mes_nombre((int) date('n')) . ' ' . date('Y');

$pdo = db();
$anio = (int) date('Y');
$mes  = (int) date('n');
$ini  = sprintf('%04d-%02d-01', $anio, $mes);
$fin  = date('Y-m-t', strtotime($ini));

// Totales del mes actual
$tot = $pdo->prepare("SELECT tipo, COALESCE(SUM(monto),0) t FROM movimientos WHERE fecha BETWEEN ? AND ? GROUP BY tipo");
$tot->execute([$ini, $fin]);
$ingMes = 0.0; $egrMes = 0.0;
foreach ($tot as $r) { if ($r['tipo']==='ingreso') $ingMes = (float) $r['t']; else $egrMes = (float) $r['t']; }
$balMes = $ingMes - $egrMes;

$mrr = (float) $pdo->query("SELECT COALESCE(SUM(tarifa_mensual),0) FROM clientes WHERE tipo='mensual' AND estado='activo'")->fetchColumn();

// Mensualidades pendientes del mes actual
$penSt = $pdo->prepare("
    SELECT m.*, c.nombre, c.dia_cobro, c.telefono FROM mensualidades m JOIN clientes c ON c.id=m.cliente_id
    WHERE m.anio=? AND m.mes=? AND m.estado='pendiente' ORDER BY c.dia_cobro
");
$penSt->execute([$anio, $mes]);
$pendientes = $penSt->fetchAll();
$totPendMes = array_sum(array_map(fn($r) => (float) $r['monto'], $pendientes));

// Próximas entregas (trabajos activos)
$entregas = $pdo->query("
    SELECT t.*, c.nombre cliente FROM trabajos t LEFT JOIN clientes c ON c.id=t.cliente_id
    WHERE t.estado IN ('agendado','grabado','edicion')
    ORDER BY t.fecha_entrega IS NULL, t.fecha_entrega ASC LIMIT 6
")->fetchAll();
$trabActivos = (int) $pdo->query("SELECT COUNT(*) FROM trabajos WHERE estado IN ('agendado','grabado','edicion')")->fetchColumn();

// Últimos 6 meses para mini gráfico
$serie = [];
for ($i = 5; $i >= 0; $i--) {
    $m = $mes - $i; $a = $anio;
    while ($m <= 0) { $m += 12; $a--; }
    $s = sprintf('%04d-%02d-01', $a, $m);
    $e = date('Y-m-t', strtotime($s));
    $q = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END),0) FROM movimientos WHERE fecha BETWEEN ? AND ?");
    $q->execute([$s, $e]);
    $serie[] = ['mes' => $m, 'bal' => (float) $q->fetchColumn()];
}
$maxSerie = max(1, ...array_map(fn($x) => abs($x['bal']), $serie));

$meta = (float) cfg('meta_mensual', '0');
$metaPct = $meta > 0 ? min(100, round($ingMes / $meta * 100)) : 0;

$kpis = [
    ['Ingresos del mes', $ingMes, '+', 'emerald'],
    ['Egresos del mes', $egrMes, '−', 'rose'],
    ['Balance del mes', $balMes, $balMes>=0?'':'−', $balMes>=0?'brand':'rose'],
    ['Recurrente / mes', $mrr, '', 'accent'],
];

require __DIR__ . '/../partials/header.php';
?>

<!-- KPIs -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($kpis as [$label, $val, $prefix, $color]):
        $grad = ['emerald'=>'from-emerald-600/20','rose'=>'from-rose-600/20','brand'=>'from-brand-600/20','accent'=>'from-accent-600/20'][$color];
        $txt  = ['emerald'=>'text-emerald-300','rose'=>'text-rose-300','brand'=>'text-white','accent'=>'text-white'][$color];
        $dot  = ['emerald'=>'bg-emerald-400','rose'=>'bg-rose-400','brand'=>'bg-brand-400','accent'=>'bg-accent-400'][$color]; ?>
        <div data-anim class="relative overflow-hidden rounded-2xl glass p-5">
            <div class="absolute inset-0 bg-gradient-to-br <?= $grad ?> to-transparent opacity-60"></div>
            <div class="relative">
                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-zinc-400">
                    <span class="h-1.5 w-1.5 rounded-full <?= $dot ?>"></span><?= e($label) ?>
                </div>
                <p class="mt-3 font-display text-3xl font-bold <?= $txt ?>"><?= $prefix ?><span data-count="<?= (int) abs($val) ?>" data-prefix="$"><?= money(abs($val)) ?></span></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($meta > 0): ?>
<!-- Meta mensual -->
<div data-anim class="mt-4 rounded-2xl glass p-5">
    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-display font-semibold text-white">Meta de <?= mes_nombre($mes) ?></h2>
        <p class="text-sm text-zinc-400"><span class="font-semibold text-white"><?= money($ingMes) ?></span> de <?= money($meta) ?>
            <span class="ml-1 rounded-md px-1.5 py-0.5 text-xs font-semibold <?= $metaPct>=100 ? 'bg-emerald-500/15 text-emerald-300' : 'bg-brand-500/15 text-brand-300' ?>"><?= $metaPct ?>%</span>
        </p>
    </div>
    <div class="h-3 w-full overflow-hidden rounded-full bg-white/5">
        <div class="h-full rounded-full <?= $metaPct>=100 ? 'bg-gradient-to-r from-emerald-500 to-emerald-400' : 'grad-btn' ?> transition-all duration-1000" style="width:<?= max(1,$metaPct) ?>%"></div>
    </div>
    <?php if ($metaPct >= 100): ?>
        <p class="mt-2 text-xs text-emerald-300">🎉 ¡Meta alcanzada este mes!</p>
    <?php else: ?>
        <p class="mt-2 text-xs text-zinc-500">Faltan <?= money($meta - $ingMes) ?> para la meta.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <!-- Balance últimos 6 meses -->
    <div data-anim class="lg:col-span-2 rounded-2xl glass p-5">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h2 class="font-display font-semibold text-white">Balance últimos 6 meses</h2>
                <p class="text-xs text-zinc-500">Ingresos menos egresos</p>
            </div>
            <a href="?p=reportes" class="text-xs font-medium text-brand-300 hover:text-brand-200">Ver reportes →</a>
        </div>
        <div class="flex items-end justify-between gap-3" style="min-height:170px">
            <?php foreach ($serie as $s):
                $h = round(abs($s['bal']) / $maxSerie * 140); $pos = $s['bal'] >= 0; ?>
                <div class="flex flex-1 flex-col items-center gap-2">
                    <div class="flex h-[140px] w-full items-end justify-center">
                        <div class="w-8 rounded-t bg-gradient-to-t <?= $pos ? 'from-brand-600/40 to-brand-400' : 'from-rose-600/40 to-rose-400' ?>" style="height:<?= max(4,$h) ?>px" title="<?= money($s['bal']) ?>"></div>
                    </div>
                    <span class="text-[11px] text-zinc-500"><?= mes_abr($s['mes']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cobros pendientes del mes -->
    <div data-anim class="rounded-2xl glass p-5">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display font-semibold text-white">Cobros de <?= mes_abr($mes) ?></h2>
            <a href="?p=mensualidades" class="text-xs font-medium text-brand-300 hover:text-brand-200">Ver →</a>
        </div>
        <?php if (!$pendientes): ?>
            <p class="py-6 text-center text-sm text-zinc-500">Todo cobrado este mes 🎉</p>
        <?php else: ?>
            <p class="mb-3 text-sm text-zinc-400">Pendiente: <span class="font-semibold text-amber-300"><?= money($totPendMes) ?></span></p>
            <div class="space-y-2">
                <?php foreach ($pendientes as $p):
                    $waMsg = 'Hola ' . $p['nombre'] . ', te recuerdo el pago de la mensualidad de ' . mes_nombre($mes) . ': ' . money($p['monto']) . '. ¡Gracias! 🙌';
                    $wa = wa_link($p['telefono'] ?? '', $waMsg); ?>
                    <div class="flex items-center gap-3 rounded-xl bg-white/[.03] p-2.5">
                        <div class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-amber-500/15 text-xs font-semibold text-amber-300">día <?= (int) $p['dia_cobro'] ?></div>
                        <p class="min-w-0 flex-1 truncate text-sm text-white"><?= e($p['nombre']) ?></p>
                        <span class="shrink-0 text-sm font-semibold text-amber-300"><?= money($p['monto']) ?></span>
                        <?php if ($wa): ?>
                            <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="shrink-0 rounded-lg p-1.5 text-zinc-400 hover:bg-emerald-500/10 hover:text-emerald-300" title="Recordar por WhatsApp">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zM17.5 14.382c-.075-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.767.967-.94 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Próximas entregas -->
<div data-anim class="mt-4 rounded-2xl glass p-5">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="font-display font-semibold text-white">Próximas entregas</h2>
            <p class="text-xs text-zinc-500"><?= $trabActivos ?> trabajo(s) activo(s)</p>
        </div>
        <a href="?p=trabajos" class="text-xs font-medium text-brand-300 hover:text-brand-200">Ver todos →</a>
    </div>
    <?php if (!$entregas): ?>
        <p class="py-6 text-center text-sm text-zinc-500">No hay trabajos pendientes de entrega.</p>
    <?php else: ?>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($entregas as $t):
                $iconos = ['foto'=>'📷','video'=>'🎬','ambos'=>'🎞️'];
                $vencido = $t['fecha_entrega'] && strtotime($t['fecha_entrega']) < strtotime(date('Y-m-d')); ?>
                <div class="rounded-xl border border-white/5 bg-white/[.02] p-4">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-lg"><?= $iconos[$t['tipo']] ?? '📷' ?></span>
                            <p class="font-medium text-white"><?= e($t['titulo']) ?></p>
                        </div>
                        <?= estado_badge($t['estado']) ?>
                    </div>
                    <p class="text-xs text-zinc-500"><?= e($t['cliente'] ?: 'Sin cliente') ?></p>
                    <p class="mt-2 text-xs <?= $vencido ? 'text-rose-300' : 'text-zinc-400' ?>">📦 Entrega: <?= fecha($t['fecha_entrega']) ?><?= $vencido ? ' · vencida' : '' ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
