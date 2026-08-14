<?php
/** Mensualidades — matriz de cobros recurrentes por mes */
$pdo = db();

/* Helpers locales para marcar/revertir pagos ligados al libro */
function men_pagar(PDO $pdo, int $cid, int $anio, int $mes): void
{
    $cli = $pdo->prepare("SELECT * FROM clientes WHERE id=?");
    $cli->execute([$cid]);
    $cliente = $cli->fetch();
    if (!$cliente) return;

    // Buscar o crear la mensualidad
    $st = $pdo->prepare("SELECT * FROM mensualidades WHERE cliente_id=? AND anio=? AND mes=?");
    $st->execute([$cid, $anio, $mes]);
    $men = $st->fetch();
    $monto = $men ? (float) $men['monto'] : (float) $cliente['tarifa_mensual'];

    if ($men && $men['estado'] === 'pagado') return; // ya pagado

    $fecha = date('Y-m-d');
    $concepto = 'Mensualidad ' . mes_abr($mes) . ' ' . $anio . ' — ' . $cliente['nombre'];
    $ins = $pdo->prepare("INSERT INTO movimientos (tipo,categoria,concepto,monto,fecha,cliente_id,metodo,origen,ref_id) VALUES ('ingreso','Mensualidad',?,?,?,?,?, 'mensualidad',0)");
    $ins->execute([$concepto, $monto, $fecha, $cid, null]);
    $movId = (int) $pdo->lastInsertId();

    if ($men) {
        $pdo->prepare("UPDATE mensualidades SET estado='pagado', fecha_pago=?, movimiento_id=? WHERE id=?")
            ->execute([$fecha, $movId, $men['id']]);
        $refMen = (int) $men['id'];
    } else {
        $pdo->prepare("INSERT INTO mensualidades (cliente_id,anio,mes,monto,estado,fecha_pago,movimiento_id) VALUES (?,?,?,?,'pagado',?,?)")
            ->execute([$cid, $anio, $mes, $monto, $fecha, $movId]);
        $refMen = (int) $pdo->lastInsertId();
    }
    $pdo->prepare("UPDATE movimientos SET ref_id=? WHERE id=?")->execute([$refMen, $movId]);
}

function men_revertir(PDO $pdo, int $cid, int $anio, int $mes): void
{
    $st = $pdo->prepare("SELECT * FROM mensualidades WHERE cliente_id=? AND anio=? AND mes=?");
    $st->execute([$cid, $anio, $mes]);
    $men = $st->fetch();
    if (!$men) return;
    if ($men['movimiento_id']) {
        $pdo->prepare("DELETE FROM movimientos WHERE id=?")->execute([(int) $men['movimiento_id']]);
    }
    $pdo->prepare("UPDATE mensualidades SET estado='pendiente', fecha_pago=NULL, movimiento_id=NULL WHERE id=?")
        ->execute([(int) $men['id']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = post('accion');
    $anio   = post_int('anio') ?: (int) date('Y');
    $mes    = max(1, min(12, post_int('mes')));
    $cid    = post_int('cliente_id');

    if ($accion === 'pagar' && $cid && $mes) {
        men_pagar($pdo, $cid, $anio, $mes);
        flash('ok', 'Mensualidad marcada como pagada.');
    }
    if ($accion === 'revertir' && $cid && $mes) {
        men_revertir($pdo, $cid, $anio, $mes);
        flash('ok', 'Pago revertido.');
    }
    if ($accion === 'generar' && $cid && $mes) {
        $cli = $pdo->prepare("SELECT tarifa_mensual FROM clientes WHERE id=?");
        $cli->execute([$cid]);
        $tar = (float) ($cli->fetchColumn() ?: 0);
        $pdo->prepare("INSERT OR IGNORE INTO mensualidades (cliente_id,anio,mes,monto,estado) VALUES (?,?,?,?,'pendiente')")
            ->execute([$cid, $anio, $mes, $tar]);
    }
    if ($accion === 'quitar' && $cid && $mes) {
        men_revertir($pdo, $cid, $anio, $mes); // por si estaba pagado, limpia el movimiento
        $pdo->prepare("DELETE FROM mensualidades WHERE cliente_id=? AND anio=? AND mes=?")->execute([$cid, $anio, $mes]);
    }
    if ($accion === 'generar_mes') {
        $ins = $pdo->prepare("INSERT OR IGNORE INTO mensualidades (cliente_id,anio,mes,monto,estado) SELECT id,?,?,tarifa_mensual,'pendiente' FROM clientes WHERE tipo='mensual' AND estado='activo'");
        $ins->execute([$anio, $mes]);
        flash('ok', 'Cobros de ' . mes_nombre($mes) . ' generados para los clientes mensuales.');
    }
    redirect('?p=mensualidades&anio=' . $anio);
}

/* -------- Datos -------- */
$anio = get_int('anio', (int) date('Y'));
$mesActual = (int) date('n');
$anioActual = (int) date('Y');

// Clientes: mensuales activos + cualquiera con mensualidades ese año
$clientes = $pdo->prepare("
    SELECT DISTINCT c.* FROM clientes c
    LEFT JOIN mensualidades m ON m.cliente_id = c.id AND m.anio = ?
    WHERE (c.tipo='mensual' AND c.estado='activo') OR m.id IS NOT NULL
    ORDER BY c.tipo='puntual', c.nombre
");
$clientes->execute([$anio]);
$clientes = $clientes->fetchAll();

// Matriz [cliente_id][mes] = fila
$matriz = [];
$st = $pdo->prepare("SELECT * FROM mensualidades WHERE anio=?");
$st->execute([$anio]);
foreach ($st as $r) {
    $matriz[$r['cliente_id']][(int) $r['mes']] = $r;
}

// Totales del año
$totCobrado   = 0.0; $totPendiente = 0.0;
foreach ($matriz as $porMes) {
    foreach ($porMes as $r) {
        if ($r['estado'] === 'pagado') $totCobrado += (float) $r['monto'];
        else $totPendiente += (float) $r['monto'];
    }
}
$mrr = (float) $pdo->query("SELECT COALESCE(SUM(tarifa_mensual),0) FROM clientes WHERE tipo='mensual' AND estado='activo'")->fetchColumn();

$pageTitle    = 'Mensualidades';
$pageSubtitle = 'Cobros recurrentes · ' . $anio;
require __DIR__ . '/../partials/header.php';
?>

<div x-data="mensPage(<?= $anio ?>)">
    <!-- Resumen + navegación de año -->
    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid grid-cols-3 gap-3 sm:max-w-xl sm:flex-1">
            <div data-anim class="rounded-2xl glass p-4">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Cobrado <?= $anio ?></p>
                <p class="mt-1 font-display text-xl font-bold text-emerald-300"><?= money($totCobrado) ?></p>
            </div>
            <div data-anim class="rounded-2xl glass p-4">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Pendiente</p>
                <p class="mt-1 font-display text-xl font-bold text-amber-300"><?= money($totPendiente) ?></p>
            </div>
            <div data-anim class="rounded-2xl glass p-4">
                <p class="text-xs uppercase tracking-wide text-zinc-500">Recurrente/mes</p>
                <p class="mt-1 font-display text-xl font-bold text-white"><?= money($mrr) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="?p=mensualidades&anio=<?= $anio - 1 ?>" class="rounded-lg border border-white/10 p-2 text-zinc-400 hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="min-w-[4rem] text-center font-display text-lg font-bold text-white"><?= $anio ?></span>
            <a href="?p=mensualidades&anio=<?= $anio + 1 ?>" class="rounded-lg border border-white/10 p-2 text-zinc-400 hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <button @click="generarMes(<?= $mesActual ?>)" class="ml-1 grad-btn rounded-xl px-3.5 py-2 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">
                + Generar <?= mes_abr($mesActual) ?>
            </button>
        </div>
    </div>

    <?php if (!$clientes): ?>
        <div class="rounded-2xl glass p-10 text-center">
            <p class="text-zinc-400">No hay clientes mensuales todavía. <a href="?p=clientes" class="text-brand-300 underline">Crea un cliente mensual</a> y luego genera sus cobros aquí.</p>
        </div>
    <?php else: ?>
        <!-- Leyenda -->
        <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-zinc-500">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-500/70"></span> Pagado</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-amber-500/70"></span> Pendiente</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-white/10"></span> Sin generar</span>
            <span class="ml-auto hidden sm:inline">Clic en una celda para cambiar su estado</span>
        </div>

        <!-- Matriz -->
        <div class="overflow-hidden rounded-2xl glass">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-white/5 text-xs uppercase tracking-wide text-zinc-500">
                            <th class="sticky left-0 z-10 bg-card/95 px-4 py-3 text-left font-medium backdrop-blur">Cliente</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <th class="px-1 py-3 text-center font-medium <?= ($m === $mesActual && $anio === $anioActual) ? 'text-brand-300' : '' ?>"><?= mes_abr($m) ?></th>
                            <?php endfor; ?>
                            <th class="px-4 py-3 text-right font-medium">Año</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($clientes as $c): $filaTot = 0; ?>
                            <tr class="row-hover">
                                <td class="sticky left-0 z-10 bg-card/95 px-4 py-2.5 backdrop-blur">
                                    <p class="font-medium text-white"><?= e($c['nombre']) ?></p>
                                    <p class="text-xs text-zinc-500"><?= money($c['tarifa_mensual']) ?>/mes</p>
                                </td>
                                <?php for ($m = 1; $m <= 12; $m++):
                                    $cell = $matriz[$c['id']][$m] ?? null;
                                    $state = $cell ? $cell['estado'] : 'none';
                                    if ($state === 'pagado') $filaTot += (float) $cell['monto'];
                                    $isCur = ($m === $mesActual && $anio === $anioActual);
                                    if ($state === 'pagado') {
                                        $cls = 'bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25';
                                        $txt = money_compact($cell['monto']);
                                    } elseif ($state === 'pendiente') {
                                        $cls = 'bg-amber-500/15 text-amber-300 hover:bg-amber-500/25';
                                        $txt = money_compact($cell['monto']);
                                    } else {
                                        $cls = 'bg-white/[.02] text-zinc-600 hover:bg-white/5';
                                        $txt = '+';
                                    } ?>
                                    <td class="p-1 text-center">
                                        <button
                                            @click="cell(<?= (int) $c['id'] ?>, <?= $m ?>, '<?= $state ?>')"
                                            @contextmenu.prevent="menu(<?= (int) $c['id'] ?>, <?= $m ?>, '<?= $state ?>')"
                                            class="w-full rounded-lg px-1 py-2 text-xs font-semibold transition <?= $cls ?> <?= $isCur ? 'ring-1 ring-brand-400/40' : '' ?>"
                                            title="<?= $state === 'none' ? 'Generar cobro' : ($state === 'pendiente' ? 'Marcar pagado (clic derecho: quitar)' : 'Revertir a pendiente') ?>">
                                            <?= $txt ?>
                                        </button>
                                    </td>
                                <?php endfor; ?>
                                <td class="px-4 py-2.5 text-right font-semibold text-white"><?= money($filaTot) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="mt-3 text-xs text-zinc-600">💡 Clic izquierdo: sin generar → pendiente → pagado. Clic derecho sobre una celda: quitarla.</p>
    <?php endif; ?>

    <!-- Form oculto de acciones -->
    <form method="post" id="menForm" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" id="menAccion">
        <input type="hidden" name="anio" value="<?= $anio ?>">
        <input type="hidden" name="mes" id="menMes">
        <input type="hidden" name="cliente_id" id="menCli">
    </form>
</div>

<script>
function mensPage(anio) {
    return {
        anio,
        submit(accion, cid, mes){
            document.getElementById('menAccion').value = accion;
            document.getElementById('menCli').value = cid;
            document.getElementById('menMes').value = mes;
            document.getElementById('menForm').submit();
        },
        cell(cid, mes, state){
            if (state === 'none')      this.submit('generar', cid, mes);
            else if (state === 'pendiente') this.submit('pagar', cid, mes);
            else if (confirm('¿Revertir este pago a pendiente? Se eliminará el ingreso del libro.')) this.submit('revertir', cid, mes);
        },
        menu(cid, mes, state){
            if (state === 'none') return;
            if (confirm('¿Quitar esta celda? (elimina el registro del mes)')) this.submit('quitar', cid, mes);
        },
        generarMes(mes){ this.submit('generar_mes', 0, mes); },
    };
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
