<?php
/** Reportes — resumen financiero anual */
$pdo = db();

$anio = get_int('anio', (int) date('Y'));
$ini = "$anio-01-01"; $fin = "$anio-12-31";

// --- Exportar a CSV (para el contador) ---
if (($_GET['export'] ?? '') === 'csv') {
    $st = $pdo->prepare("SELECT m.fecha, m.tipo, m.categoria, m.concepto, c.nombre cliente, m.metodo, m.monto FROM movimientos m LEFT JOIN clientes c ON c.id=m.cliente_id WHERE m.fecha BETWEEN ? AND ? ORDER BY m.fecha");
    $st->execute([$ini, $fin]);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte-vanegas-' . $anio . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
    fputcsv($out, ['Fecha', 'Tipo', 'Categoría', 'Concepto', 'Cliente', 'Método', 'Monto'], ';');
    $ti = 0; $te = 0;
    foreach ($st as $r) {
        fputcsv($out, [$r['fecha'], $r['tipo'], $r['categoria'], $r['concepto'], $r['cliente'], $r['metodo'], (int) $r['monto']], ';');
        if ($r['tipo'] === 'ingreso') $ti += (float) $r['monto']; else $te += (float) $r['monto'];
    }
    fputcsv($out, [], ';');
    fputcsv($out, ['', '', '', '', '', 'Total ingresos', (int) $ti], ';');
    fputcsv($out, ['', '', '', '', '', 'Total egresos', (int) $te], ';');
    fputcsv($out, ['', '', '', '', '', 'Balance', (int) ($ti - $te)], ';');
    fclose($out);
    exit;
}

// Ingresos/egresos por mes
$mensual = array_fill(1, 12, ['ingreso' => 0.0, 'egreso' => 0.0]);
$st = $pdo->prepare("SELECT CAST(strftime('%m', fecha) AS INTEGER) m, tipo, COALESCE(SUM(monto),0) t FROM movimientos WHERE fecha BETWEEN ? AND ? GROUP BY m, tipo");
$st->execute([$ini, $fin]);
foreach ($st as $r) {
    $m = (int) $r['m'];
    if ($m >= 1 && $m <= 12) $mensual[$m][$r['tipo']] = (float) $r['t'];
}

$totIng = array_sum(array_column($mensual, 'ingreso'));
$totEgr = array_sum(array_column($mensual, 'egreso'));
$balance = $totIng - $totEgr;
$maxBar = max(1, ...array_map(fn($x) => max($x['ingreso'], $x['egreso']), $mensual));

// Ingresos por origen
$porOrigen = ['mensualidad' => 0.0, 'trabajo' => 0.0, 'manual' => 0.0];
$st = $pdo->prepare("SELECT origen, COALESCE(SUM(monto),0) t FROM movimientos WHERE tipo='ingreso' AND fecha BETWEEN ? AND ? GROUP BY origen");
$st->execute([$ini, $fin]);
foreach ($st as $r) { $porOrigen[$r['origen']] = (float) $r['t']; }

// Egresos por categoría
$st = $pdo->prepare("SELECT COALESCE(NULLIF(categoria,''),'Sin categoría') cat, COALESCE(SUM(monto),0) t FROM movimientos WHERE tipo='egreso' AND fecha BETWEEN ? AND ? GROUP BY cat ORDER BY t DESC");
$st->execute([$ini, $fin]);
$egrCats = $st->fetchAll();
$maxEgr = max(1, ...(array_map(fn($r) => (float) $r['t'], $egrCats) ?: [1]));

$mrr = (float) $pdo->query("SELECT COALESCE(SUM(tarifa_mensual),0) FROM clientes WHERE tipo='mensual' AND estado='activo'")->fetchColumn();
$mesesConIng = count(array_filter($mensual, fn($x) => $x['ingreso'] > 0));
$promMes = $mesesConIng ? $totIng / $mesesConIng : 0;

$pageTitle    = 'Reportes';
$pageSubtitle = 'Resumen del año ' . $anio;
require __DIR__ . '/../partials/header.php';
?>

<!-- Navegación de año -->
<div class="mb-5 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <a href="?p=reportes&anio=<?= $anio - 1 ?>" class="rounded-lg border border-white/10 p-2 text-zinc-400 hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <span class="min-w-[4rem] text-center font-display text-lg font-bold text-white"><?= $anio ?></span>
        <a href="?p=reportes&anio=<?= $anio + 1 ?>" class="rounded-lg border border-white/10 p-2 text-zinc-400 hover:bg-white/5 hover:text-white">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
    </div>
    <div class="no-print flex items-center gap-2">
        <a href="?p=reportes&anio=<?= $anio ?>&export=csv" class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3.5 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5" title="Descargar Excel/CSV">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            CSV
        </a>
        <button onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3.5 py-2 text-sm font-medium text-zinc-300 hover:bg-white/5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Imprimir
        </button>
    </div>
</div>

<style>
@media print {
    aside, header.sticky, footer, .no-print { display:none !important; }
    body, .glass { background:#fff !important; color:#111 !important; }
    .glass { border:1px solid #ddd !important; box-shadow:none !important; }
    main { padding:0 !important; }
    .lg\:pl-64 { padding-left:0 !important; }
    * { color:#111 !important; }
    .text-emerald-300 { color:#059669 !important; }
    .text-rose-300 { color:#dc2626 !important; }
}
</style>

<!-- KPIs anuales -->
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div data-anim class="rounded-2xl glass p-5">
        <p class="text-xs uppercase tracking-wide text-zinc-500">Ingresos del año</p>
        <p class="mt-1 font-display text-2xl font-bold text-emerald-300"><?= money($totIng) ?></p>
    </div>
    <div data-anim class="rounded-2xl glass p-5">
        <p class="text-xs uppercase tracking-wide text-zinc-500">Egresos del año</p>
        <p class="mt-1 font-display text-2xl font-bold text-rose-300"><?= money($totEgr) ?></p>
    </div>
    <div data-anim class="relative overflow-hidden rounded-2xl glass p-5">
        <div class="absolute inset-0 bg-gradient-to-br <?= $balance>=0 ? 'from-emerald-600/15' : 'from-rose-600/15' ?> to-transparent"></div>
        <p class="relative text-xs uppercase tracking-wide text-zinc-500">Balance neto</p>
        <p class="relative mt-1 font-display text-2xl font-bold <?= $balance>=0 ? 'text-white' : 'text-rose-300' ?>"><?= ($balance>=0?'':'−').money(abs($balance)) ?></p>
    </div>
    <div data-anim class="rounded-2xl glass p-5">
        <p class="text-xs uppercase tracking-wide text-zinc-500">Promedio / mes</p>
        <p class="mt-1 font-display text-2xl font-bold text-white"><?= money($promMes) ?></p>
    </div>
</div>

<!-- Gráfico mensual -->
<div data-anim class="mt-4 rounded-2xl glass p-5">
    <div class="mb-5 flex items-center justify-between">
        <h2 class="font-display font-semibold text-white">Ingresos vs egresos por mes</h2>
        <div class="flex items-center gap-4 text-xs">
            <span class="flex items-center gap-1.5 text-zinc-400"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-400"></span>Ingresos</span>
            <span class="flex items-center gap-1.5 text-zinc-400"><span class="h-2.5 w-2.5 rounded-sm bg-rose-400"></span>Egresos</span>
        </div>
    </div>
    <div class="flex items-end gap-2 overflow-x-auto pb-2" style="min-height:200px">
        <?php for ($m = 1; $m <= 12; $m++):
            $ing = $mensual[$m]['ingreso']; $egr = $mensual[$m]['egreso'];
            $hIng = round($ing / $maxBar * 160); $hEgr = round($egr / $maxBar * 160); ?>
            <div class="flex flex-1 flex-col items-center gap-1" style="min-width:44px">
                <div class="flex h-[160px] w-full items-end justify-center gap-1">
                    <div class="w-1/2 rounded-t bg-gradient-to-t from-emerald-500/40 to-emerald-400 transition-all" style="height:<?= max($ing>0?4:0,$hIng) ?>px" title="Ingresos <?= money($ing) ?>"></div>
                    <div class="w-1/2 rounded-t bg-gradient-to-t from-rose-500/40 to-rose-400 transition-all" style="height:<?= max($egr>0?4:0,$hEgr) ?>px" title="Egresos <?= money($egr) ?>"></div>
                </div>
                <span class="text-[11px] text-zinc-500"><?= mes_abr($m) ?></span>
            </div>
        <?php endfor; ?>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
    <!-- Origen de ingresos -->
    <div data-anim class="rounded-2xl glass p-5">
        <h2 class="mb-4 font-display font-semibold text-white">¿De dónde viene el dinero?</h2>
        <?php
        $labels = ['mensualidad' => ['Mensualidades', 'brand'], 'trabajo' => ['Trabajos puntuales', 'accent'], 'manual' => ['Otros ingresos', 'sky']];
        foreach ($labels as $key => [$lbl, $col]):
            $val = $porOrigen[$key]; $pct = $totIng > 0 ? round($val / $totIng * 100) : 0;
            $bar = ['brand'=>'from-brand-600 to-brand-400','accent'=>'from-accent-600 to-accent-400','sky'=>'from-sky-600 to-sky-400'][$col]; ?>
            <div class="mb-4">
                <div class="mb-1.5 flex items-baseline justify-between text-sm">
                    <span class="text-zinc-300"><?= $lbl ?></span>
                    <span class="font-medium text-white"><?= money($val) ?> <span class="text-xs text-zinc-500"><?= $pct ?>%</span></span>
                </div>
                <div class="h-2.5 w-full overflow-hidden rounded-full bg-white/5">
                    <div class="h-full rounded-full bg-gradient-to-r <?= $bar ?>" style="width:<?= max(2,$pct) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="mt-5 rounded-xl bg-white/[.03] p-4">
            <p class="text-xs text-zinc-500">Ingreso recurrente proyectado</p>
            <p class="mt-1 font-display text-xl font-bold text-white"><?= money($mrr) ?><span class="text-sm font-normal text-zinc-500">/mes · <?= money($mrr*12) ?>/año</span></p>
        </div>
    </div>

    <!-- Egresos por categoría -->
    <div data-anim class="rounded-2xl glass p-5">
        <h2 class="mb-4 font-display font-semibold text-white">Gastos por categoría</h2>
        <?php if (!$egrCats): ?>
            <p class="py-8 text-center text-sm text-zinc-500">Sin egresos registrados este año.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($egrCats as $r): $pct = round((float) $r['t'] / $maxEgr * 100); ?>
                    <div>
                        <div class="mb-1 flex items-baseline justify-between text-sm">
                            <span class="text-zinc-300"><?= e($r['cat']) ?></span>
                            <span class="font-medium text-white"><?= money($r['t']) ?></span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-white/5">
                            <div class="h-full rounded-full bg-gradient-to-r from-rose-600 to-rose-400" style="width:<?= max(2,$pct) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabla mensual -->
<div data-anim class="mt-4 overflow-hidden rounded-2xl glass">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-white/5 text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Mes</th>
                    <th class="px-5 py-3 font-medium text-right">Ingresos</th>
                    <th class="px-5 py-3 font-medium text-right">Egresos</th>
                    <th class="px-5 py-3 font-medium text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php for ($m = 1; $m <= 12; $m++): $ing = $mensual[$m]['ingreso']; $egr = $mensual[$m]['egreso']; $bal = $ing - $egr; ?>
                    <tr class="row-hover <?= ($ing==0 && $egr==0) ? 'opacity-40' : '' ?>">
                        <td class="px-5 py-2.5 text-zinc-300"><?= mes_nombre($m) ?></td>
                        <td class="px-5 py-2.5 text-right text-emerald-300"><?= $ing>0 ? money($ing) : '—' ?></td>
                        <td class="px-5 py-2.5 text-right text-rose-300"><?= $egr>0 ? money($egr) : '—' ?></td>
                        <td class="px-5 py-2.5 text-right font-medium <?= $bal>=0 ? 'text-white' : 'text-rose-300' ?>"><?= ($ing==0&&$egr==0)?'—':(($bal>=0?'':'−').money(abs($bal))) ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot class="border-t border-white/10 font-semibold">
                <tr>
                    <td class="px-5 py-3 text-white">Total <?= $anio ?></td>
                    <td class="px-5 py-3 text-right text-emerald-300"><?= money($totIng) ?></td>
                    <td class="px-5 py-3 text-right text-rose-300"><?= money($totEgr) ?></td>
                    <td class="px-5 py-3 text-right <?= $balance>=0 ? 'text-white' : 'text-rose-300' ?>"><?= ($balance>=0?'':'−').money(abs($balance)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
