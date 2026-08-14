<?php
/** Gestión de clientes (mensuales y puntuales) */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = post('accion');

    if ($accion === 'guardar') {
        $id     = post_int('id');
        $nombre = post('nombre');
        if ($nombre === '') {
            flash('error', 'El nombre es obligatorio.');
            redirect('?p=clientes');
        }
        $tipo = post('tipo') === 'mensual' ? 'mensual' : 'puntual';
        $dia  = max(1, min(28, post_int('dia_cobro') ?: 1));
        $datos = [
            $nombre, $tipo, $tipo === 'mensual' ? post_num('tarifa_mensual') : 0, $dia,
            post('email'), post('telefono'), post('estado') ?: 'activo', post('notas'),
        ];
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE clientes SET nombre=?,tipo=?,tarifa_mensual=?,dia_cobro=?,email=?,telefono=?,estado=?,notas=? WHERE id=?");
            $stmt->execute([...$datos, $id]);
            flash('ok', 'Cliente actualizado.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre,tipo,tarifa_mensual,dia_cobro,email,telefono,estado,notas) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute($datos);
            flash('ok', 'Cliente creado.');
        }
    }

    if ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM clientes WHERE id=?")->execute([post_int('id')]);
        flash('ok', 'Cliente eliminado (y su historial de mensualidades).');
    }
    redirect('?p=clientes');
}

$q      = trim((string) ($_GET['q'] ?? ''));
$filtro = in_array(($_GET['tipo'] ?? ''), ['mensual', 'puntual'], true) ? $_GET['tipo'] : '';

$where = [];
$args  = [];
if ($q !== '') { $where[] = "(nombre LIKE ? OR email LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($filtro !== '') { $where[] = "tipo = ?"; $args[] = $filtro; }
$sql = "SELECT * FROM clientes" . ($where ? " WHERE " . implode(' AND ', $where) : "") . " ORDER BY tipo='puntual', nombre";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$clientes = $stmt->fetchAll();

// Total cobrado histórico por cliente (desde el libro)
$ingresos = [];
foreach ($pdo->query("SELECT cliente_id, COALESCE(SUM(monto),0) t FROM movimientos WHERE tipo='ingreso' AND cliente_id IS NOT NULL GROUP BY cliente_id") as $r) {
    $ingresos[$r['cliente_id']] = (float) $r['t'];
}

$totMensuales = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE tipo='mensual' AND estado='activo'")->fetchColumn();
$mrr          = (float) $pdo->query("SELECT COALESCE(SUM(tarifa_mensual),0) FROM clientes WHERE tipo='mensual' AND estado='activo'")->fetchColumn();

$pageTitle    = 'Clientes';
$pageSubtitle = count($clientes) . ' cliente(s) · ' . $totMensuales . ' mensuales';
require __DIR__ . '/../partials/header.php';
?>

<div x-data="clientesPage()">
    <!-- Resumen recurrente -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:max-w-md">
        <div data-anim class="rounded-2xl glass p-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Ingreso recurrente</p>
            <p class="mt-1 font-display text-2xl font-bold text-white"><?= money($mrr) ?><span class="text-sm font-normal text-zinc-500">/mes</span></p>
        </div>
        <div data-anim class="rounded-2xl glass p-4">
            <p class="text-xs uppercase tracking-wide text-zinc-500">Clientes mensuales</p>
            <p class="mt-1 font-display text-2xl font-bold text-brand-300"><?= $totMensuales ?></p>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <form method="get" class="relative w-full sm:w-64">
                <input type="hidden" name="p" value="clientes">
                <?php if ($filtro): ?><input type="hidden" name="tipo" value="<?= e($filtro) ?>"><?php endif; ?>
                <svg class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input name="q" value="<?= e($q) ?>" placeholder="Buscar cliente..."
                       class="w-full rounded-xl border border-white/10 bg-card/60 py-2.5 pl-10 pr-4 text-sm text-white outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/25">
            </form>
            <div class="flex gap-1">
                <?php foreach (['' => 'Todos', 'mensual' => 'Mensuales', 'puntual' => 'Puntuales'] as $k => $l): ?>
                    <a href="?p=clientes<?= $k ? '&tipo=' . $k : '' ?><?= $q ? '&q=' . urlencode($q) : '' ?>"
                       class="rounded-lg px-3 py-1.5 text-sm font-medium transition <?= $filtro === $k ? 'grad-btn text-white' : 'bg-white/5 text-zinc-400 hover:text-white' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <button @click="nuevo()" class="grad-btn inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nuevo cliente
        </button>
    </div>

    <!-- Tabla -->
    <div class="overflow-hidden rounded-2xl glass">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b border-white/5 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-5 py-3.5 font-medium">Cliente</th>
                        <th class="px-5 py-3.5 font-medium">Tipo</th>
                        <th class="px-5 py-3.5 font-medium text-right">Tarifa/mes</th>
                        <th class="px-5 py-3.5 font-medium text-center">Cobro</th>
                        <th class="px-5 py-3.5 font-medium">Contacto</th>
                        <th class="px-5 py-3.5 font-medium text-right">Total cobrado</th>
                        <th class="px-5 py-3.5 font-medium text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (!$clientes): ?>
                        <tr><td colspan="7" class="px-5 py-12 text-center text-zinc-500">
                            <?= $q !== '' ? 'Sin resultados.' : 'Aún no hay clientes. Crea el primero.' ?>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($clientes as $c): ?>
                        <tr class="row-hover" data-anim>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full <?= $c['tipo']==='mensual' ? 'bg-brand-500/15 text-brand-300' : 'bg-sky-500/15 text-sky-300' ?> text-sm font-semibold">
                                        <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-white"><?= e($c['nombre']) ?></p>
                                        <?php if ($c['estado'] === 'inactivo'): ?><p class="text-xs text-zinc-500">Inactivo</p><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5"><?= estado_badge($c['tipo']) ?></td>
                            <td class="px-5 py-3.5 text-right <?= $c['tipo']==='mensual' ? 'text-white font-medium' : 'text-zinc-600' ?>">
                                <?= $c['tipo']==='mensual' ? money($c['tarifa_mensual']) : '—' ?>
                            </td>
                            <td class="px-5 py-3.5 text-center text-zinc-400">
                                <?= $c['tipo']==='mensual' ? 'día ' . (int) $c['dia_cobro'] : '—' ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-zinc-300"><?= e($c['email'] ?: '—') ?></p>
                                <p class="text-xs text-zinc-500"><?= e($c['telefono'] ?: '') ?></p>
                            </td>
                            <td class="px-5 py-3.5 text-right text-emerald-300"><?= money($ingresos[$c['id']] ?? 0) ?></td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click='editar(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-brand-300" title="Editar">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="borrar(<?= (int) $c['id'] ?>, '<?= e(addslashes($c['nombre'])) ?>')" class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-rose-400" title="Eliminar">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
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
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-white" x-text="form.id ? 'Editar cliente' : 'Nuevo cliente'"></h3>
                    <button type="button" @click="open=false" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="lbl">Nombre / negocio *</label>
                        <input name="nombre" x-model="form.nombre" required class="fld">
                    </div>
                    <div>
                        <label class="lbl">Tipo de cliente</label>
                        <select name="tipo" x-model="form.tipo" class="fld">
                            <option value="puntual">Puntual (por trabajo)</option>
                            <option value="mensual">Mensual (recurrente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">Estado</label>
                        <select name="estado" x-model="form.estado" class="fld">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>
                    <template x-if="form.tipo === 'mensual'">
                        <div class="grid grid-cols-2 gap-4 sm:col-span-2">
                            <div>
                                <label class="lbl">Tarifa mensual ($)</label>
                                <input type="number" step="any" name="tarifa_mensual" x-model="form.tarifa_mensual" class="fld">
                            </div>
                            <div>
                                <label class="lbl">Día de cobro (1-28)</label>
                                <input type="number" min="1" max="28" name="dia_cobro" x-model="form.dia_cobro" class="fld">
                            </div>
                        </div>
                    </template>
                    <div>
                        <label class="lbl">Email</label>
                        <input type="email" name="email" x-model="form.email" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Teléfono / WhatsApp</label>
                        <input name="telefono" x-model="form.telefono" class="fld">
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
        <input type="hidden" name="id" id="delId">
    </form>
</div>

<style>
    .fld { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(10,10,18,.6); padding:.6rem .9rem; color:#fff; outline:none; transition:.15s; }
    .fld:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.22); }
    .lbl { display:block; margin-bottom:.375rem; font-size:.875rem; color:#a1a1aa; }
    select.fld option { background:#171725; }
</style>

<script>
function clientesPage() {
    return {
        open: false, form: {},
        vacio: { id:'', nombre:'', tipo:'puntual', tarifa_mensual:0, dia_cobro:1, email:'', telefono:'', estado:'activo', notas:'' },
        nuevo()  { this.form = {...this.vacio}; this.open = true; },
        editar(c){ this.form = {...this.vacio, ...c}; this.open = true; },
        borrar(id, nombre){
            if (confirm('¿Eliminar a "' + nombre + '"?\nSe borrará también su historial de mensualidades.')) {
                document.getElementById('delId').value = id;
                document.getElementById('delForm').submit();
            }
        },
    };
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
