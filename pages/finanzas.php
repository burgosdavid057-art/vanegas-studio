<?php
/** Finanzas — libro de ingresos y egresos por mes */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = post('accion');
    $ret = '?p=finanzas&anio=' . post_int('r_anio') . '&mes=' . post_int('r_mes');

    if ($accion === 'guardar') {
        $id = post_int('id');
        if (post('concepto') === '') { flash('error', 'El concepto es obligatorio.'); redirect($ret); }
        $tipo = post('tipo') === 'egreso' ? 'egreso' : 'ingreso';
        $datos = [
            $tipo, post('categoria'), post('concepto'), post_num('monto'),
            post('fecha') ?: date('Y-m-d'), post_int('cliente_id') ?: null, post('metodo'), post('notas'),
        ];
        if ($id > 0) {
            // Solo se editan movimientos manuales
            $org = $pdo->prepare("SELECT origen FROM movimientos WHERE id=?");
            $org->execute([$id]);
            if ($org->fetchColumn() !== 'manual') { flash('error', 'Ese movimiento se gestiona desde Mensualidades o Trabajos.'); redirect($ret); }
            $stmt = $pdo->prepare("UPDATE movimientos SET tipo=?,categoria=?,concepto=?,monto=?,fecha=?,cliente_id=?,metodo=?,notas=? WHERE id=?");
            $stmt->execute([...$datos, $id]);
            flash('ok', 'Movimiento actualizado.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO movimientos (tipo,categoria,concepto,monto,fecha,cliente_id,metodo,notas,origen) VALUES (?,?,?,?,?,?,?,?, 'manual')");
            $stmt->execute($datos);
            flash('ok', 'Movimiento registrado.');
        }
    }

    if ($accion === 'eliminar') {
        $org = $pdo->prepare("SELECT origen FROM movimientos WHERE id=?");
        $org->execute([post_int('id')]);
        if ($org->fetchColumn() === 'manual') {
            $pdo->prepare("DELETE FROM movimientos WHERE id=?")->execute([post_int('id')]);
            flash('ok', 'Movimiento eliminado.');
        } else {
            flash('error', 'Ese ingreso viene de Mensualidades/Trabajos. Reviértelo desde allí.');
        }
    }
    redirect($ret);
}

$anio = get_int('anio', (int) date('Y'));
$mes  = max(1, min(12, get_int('mes', (int) date('n'))));
$tipoF = in_array(($_GET['tipo'] ?? ''), ['ingreso','egreso'], true) ? $_GET['tipo'] : '';

$ini = sprintf('%04d-%02d-01', $anio, $mes);
$fin = date('Y-m-t', strtotime($ini));

$args = [$ini, $fin];
$sql = "SELECT m.*, c.nombre cliente FROM movimientos m LEFT JOIN clientes c ON c.id=m.cliente_id WHERE m.fecha BETWEEN ? AND ?";
if ($tipoF) { $sql .= " AND m.tipo = ?"; $args[] = $tipoF; }
$sql .= " ORDER BY m.fecha DESC, m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$movs = $stmt->fetchAll();

// Totales del mes
$tot = $pdo->prepare("SELECT tipo, COALESCE(SUM(monto),0) t FROM movimientos WHERE fecha BETWEEN ? AND ? GROUP BY tipo");
$tot->execute([$ini, $fin]);
$ingresos = 0.0; $egresos = 0.0;
foreach ($tot as $r) { if ($r['tipo'] === 'ingreso') $ingresos = (float) $r['t']; else $egresos = (float) $r['t']; }
$balance = $ingresos - $egresos;

// Navegación de mes
$prevM = $mes - 1; $prevA = $anio; if ($prevM < 1) { $prevM = 12; $prevA--; }
$nextM = $mes + 1; $nextA = $anio; if ($nextM > 12) { $nextM = 1; $nextA++; }

$clientes = $pdo->query("SELECT id, nombre FROM clientes ORDER BY nombre")->fetchAll();

$pageTitle    = 'Finanzas';
$pageSubtitle = mes_nombre($mes) . ' ' . $anio;
require __DIR__ . '/../partials/header.php';
?>

<div x-data="finPage()">
    <!-- Navegación de mes -->
    <div class="mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="?p=finanzas&anio=<?= $prevA ?>&mes=<?= $prevM ?><?= $tipoF ? '&tipo='.$tipoF : '' ?>" class="rounded-lg border border-white/10 p-2 text-zinc-400 hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="min-w-[9rem] text-center font-display text-lg font-bold text-white"><?= mes_nombre($mes) ?> <?= $anio ?></span>
            <a href="?p=finanzas&anio=<?= $nextA ?>&mes=<?= $nextM ?><?= $tipoF ? '&tipo='.$tipoF : '' ?>" class="rounded-lg border border-white/10 p-2 text-zinc-400 hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <button @click="nuevo()" class="grad-btn inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Movimiento
        </button>
    </div>

    <!-- Totales -->
    <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div data-anim class="rounded-2xl glass p-5">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Ingresos</p>
            <p class="mt-1 font-display text-2xl font-bold text-emerald-300">+<?= money($ingresos) ?></p>
        </div>
        <div data-anim class="rounded-2xl glass p-5">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Egresos</p>
            <p class="mt-1 font-display text-2xl font-bold text-rose-300">−<?= money($egresos) ?></p>
        </div>
        <div data-anim class="relative overflow-hidden rounded-2xl glass p-5">
            <div class="absolute inset-0 bg-gradient-to-br <?= $balance >= 0 ? 'from-emerald-600/15' : 'from-rose-600/15' ?> to-transparent"></div>
            <p class="relative text-xs uppercase tracking-wide text-zinc-500">Balance del mes</p>
            <p class="relative mt-1 font-display text-2xl font-bold <?= $balance >= 0 ? 'text-white' : 'text-rose-300' ?>"><?= ($balance>=0?'':'−') . money(abs($balance)) ?></p>
        </div>
    </div>

    <!-- Filtro tipo -->
    <div class="mb-4 flex gap-1">
        <?php foreach (['' => 'Todos', 'ingreso' => 'Ingresos', 'egreso' => 'Egresos'] as $k => $l): ?>
            <a href="?p=finanzas&anio=<?= $anio ?>&mes=<?= $mes ?><?= $k ? '&tipo='.$k : '' ?>"
               class="rounded-lg px-3 py-1.5 text-sm font-medium transition <?= $tipoF === $k ? 'grad-btn text-white' : 'bg-white/5 text-zinc-400 hover:text-white' ?>"><?= $l ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Tabla -->
    <div class="overflow-hidden rounded-2xl glass">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-white/5 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-5 py-3.5 font-medium">Fecha</th>
                        <th class="px-5 py-3.5 font-medium">Concepto</th>
                        <th class="px-5 py-3.5 font-medium">Categoría</th>
                        <th class="px-5 py-3.5 font-medium">Cliente</th>
                        <th class="px-5 py-3.5 font-medium text-right">Monto</th>
                        <th class="px-5 py-3.5 font-medium text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (!$movs): ?>
                        <tr><td colspan="6" class="px-5 py-12 text-center text-zinc-500">Sin movimientos en <?= mes_nombre($mes) ?>. Registra el primero.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($movs as $m): $auto = $m['origen'] !== 'manual'; ?>
                        <tr class="row-hover" data-anim>
                            <td class="px-5 py-3.5 text-zinc-400"><?= fecha($m['fecha']) ?></td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg <?= $m['tipo']==='ingreso' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300' ?>">
                                        <?= $m['tipo']==='ingreso' ? '↓' : '↑' ?>
                                    </span>
                                    <div>
                                        <p class="font-medium text-white"><?= e($m['concepto']) ?></p>
                                        <?php if ($auto): ?><p class="text-[11px] text-zinc-600">auto · <?= e($m['origen']) ?></p><?php elseif ($m['metodo']): ?><p class="text-[11px] text-zinc-600"><?= e($m['metodo']) ?></p><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5"><?php if ($m['categoria']): ?><span class="rounded-md bg-white/5 px-2 py-0.5 text-xs text-zinc-300"><?= e($m['categoria']) ?></span><?php else: ?><span class="text-zinc-600">—</span><?php endif; ?></td>
                            <td class="px-5 py-3.5 text-zinc-400"><?= e($m['cliente'] ?: '—') ?></td>
                            <td class="px-5 py-3.5 text-right font-semibold <?= $m['tipo']==='ingreso' ? 'text-emerald-300' : 'text-rose-300' ?>">
                                <?= ($m['tipo']==='ingreso'?'+':'−') . money($m['monto']) ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <?php if ($auto): ?>
                                        <span class="text-xs text-zinc-600" title="Se gestiona desde su origen">🔒</span>
                                    <?php else: ?>
                                        <button @click='editar(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-brand-300">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="borrar(<?= (int) $m['id'] ?>, '<?= e(addslashes($m['concepto'])) ?>')" class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-rose-400">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm" @keydown.escape.window="open=false">
        <div @click.away="open=false" x-show="open" x-transition class="my-10 w-full max-w-lg rounded-2xl border border-white/10 bg-card shadow-2xl">
            <form method="post" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" :value="form.id">
                <input type="hidden" name="r_anio" value="<?= $anio ?>">
                <input type="hidden" name="r_mes" value="<?= $mes ?>">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-white" x-text="form.id ? 'Editar movimiento' : 'Nuevo movimiento'"></h3>
                    <button type="button" @click="open=false" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <!-- Selector tipo -->
                <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl bg-black/30 p-1">
                    <button type="button" @click="form.tipo='ingreso'" :class="form.tipo==='ingreso' ? 'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/30' : 'text-zinc-400'" class="rounded-lg py-2 text-sm font-semibold transition">↓ Ingreso</button>
                    <button type="button" @click="form.tipo='egreso'" :class="form.tipo==='egreso' ? 'bg-rose-500/20 text-rose-300 ring-1 ring-rose-500/30' : 'text-zinc-400'" class="rounded-lg py-2 text-sm font-semibold transition">↑ Egreso</button>
                </div>
                <input type="hidden" name="tipo" :value="form.tipo">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="lbl">Concepto *</label>
                        <input name="concepto" x-model="form.concepto" required class="fld">
                    </div>
                    <div>
                        <label class="lbl">Monto ($) *</label>
                        <input type="number" step="any" name="monto" x-model="form.monto" required class="fld">
                    </div>
                    <div>
                        <label class="lbl">Fecha</label>
                        <input type="date" name="fecha" x-model="form.fecha" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Categoría</label>
                        <input name="categoria" x-model="form.categoria" list="cats" class="fld" placeholder="Equipo, Software...">
                        <datalist id="cats">
                            <?php foreach (categorias_egreso() as $cat): ?><option value="<?= e($cat) ?>"><?php endforeach; ?>
                            <option value="Trabajo"><option value="Mensualidad"><option value="Otro">
                        </datalist>
                    </div>
                    <div>
                        <label class="lbl">Método</label>
                        <input name="metodo" x-model="form.metodo" placeholder="Efectivo, Nequi..." class="fld">
                    </div>
                    <div class="sm:col-span-2" x-show="form.tipo==='ingreso'">
                        <label class="lbl">Cliente (opcional)</label>
                        <select name="cliente_id" x-model="form.cliente_id" class="fld">
                            <option value="">— Ninguno —</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?= (int) $cl['id'] ?>"><?= e($cl['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl">Notas</label>
                        <textarea name="notas" x-model="form.notas" rows="2" class="fld"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="open=false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-zinc-300 hover:bg-white/5">Cancelar</button>
                    <button class="grad-btn rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <form method="post" id="delForm" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="r_anio" value="<?= $anio ?>">
        <input type="hidden" name="r_mes" value="<?= $mes ?>">
        <input type="hidden" name="id" id="delId">
    </form>
</div>

<style>
    .fld { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(10,10,18,.6); padding:.6rem .9rem; color:#fff; outline:none; transition:.15s; }
    .fld:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.22); }
    .lbl { display:block; margin-bottom:.375rem; font-size:.875rem; color:#a1a1aa; }
    select.fld option, datalist option { background:#171725; }
</style>

<script>
function finPage() {
    return {
        open: false, form: {},
        vacio: { id:'', tipo:'ingreso', concepto:'', monto:0, fecha:'<?= date('Y-m-d') ?>', categoria:'', metodo:'', cliente_id:'', notas:'' },
        nuevo()  { this.form = {...this.vacio}; this.open = true; },
        editar(m){ this.form = {...this.vacio, ...m}; this.open = true; },
        borrar(id, c){ if (confirm('¿Eliminar "' + c + '"?')) { document.getElementById('delId').value = id; document.getElementById('delForm').submit(); } },
    };
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
