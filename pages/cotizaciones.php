<?php
/** Cotizaciones / propuestas */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = post('accion');

    if ($accion === 'guardar') {
        $id = post_int('id');
        if (post('titulo') === '') { flash('error', 'El título es obligatorio.'); redirect('?p=cotizaciones'); }
        $datos = [
            post_int('cliente_id') ?: null, post('titulo'), post('detalle'), post_num('valor'),
            post('estado') ?: 'borrador', post('fecha') ?: date('Y-m-d'), max(1, post_int('validez_dias') ?: 15),
        ];
        if ($id > 0) {
            $pdo->prepare("UPDATE cotizaciones SET cliente_id=?,titulo=?,detalle=?,valor=?,estado=?,fecha=?,validez_dias=? WHERE id=?")->execute([...$datos, $id]);
            flash('ok', 'Cotización actualizada.');
        } else {
            $pdo->prepare("INSERT INTO cotizaciones (cliente_id,titulo,detalle,valor,estado,fecha,validez_dias) VALUES (?,?,?,?,?,?,?)")->execute($datos);
            flash('ok', 'Cotización creada.');
        }
    }

    if ($accion === 'estado') {
        $nuevo = post('valor');
        if (in_array($nuevo, ['borrador','enviada','aceptada','rechazada'], true)) {
            $pdo->prepare("UPDATE cotizaciones SET estado=? WHERE id=?")->execute([$nuevo, post_int('id')]);
            flash('ok', 'Estado actualizado.');
        }
    }

    if ($accion === 'convertir') {
        $st = $pdo->prepare("SELECT * FROM cotizaciones WHERE id=?");
        $st->execute([post_int('id')]);
        $c = $st->fetch();
        if ($c && !$c['trabajo_id']) {
            $ins = $pdo->prepare("INSERT INTO trabajos (cliente_id,titulo,tipo,descripcion,estado,valor) VALUES (?,?,?,?,'agendado',?)");
            $ins->execute([$c['cliente_id'], $c['titulo'], 'foto', $c['detalle'], $c['valor']]);
            $tid = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE cotizaciones SET trabajo_id=?, estado='aceptada' WHERE id=?")->execute([$tid, $c['id']]);
            flash('ok', 'Cotización convertida en trabajo (agendado).');
        }
    }

    if ($accion === 'eliminar') {
        $pdo->prepare("DELETE FROM cotizaciones WHERE id=?")->execute([post_int('id')]);
        flash('ok', 'Cotización eliminada.');
    }
    redirect('?p=cotizaciones');
}

$clientes = $pdo->query("SELECT id, nombre, telefono FROM clientes ORDER BY nombre")->fetchAll();
$telefonos = [];
foreach ($clientes as $cl) $telefonos[$cl['id']] = $cl['telefono'];

$cotis = $pdo->query("
    SELECT co.*, c.nombre AS cliente, c.telefono
    FROM cotizaciones co LEFT JOIN clientes c ON c.id = co.cliente_id
    ORDER BY co.fecha DESC, co.id DESC
")->fetchAll();

$totAceptadas = (float) $pdo->query("SELECT COALESCE(SUM(valor),0) FROM cotizaciones WHERE estado='aceptada'")->fetchColumn();
$totEnviadas  = (float) $pdo->query("SELECT COALESCE(SUM(valor),0) FROM cotizaciones WHERE estado='enviada'")->fetchColumn();

$pageTitle    = 'Cotizaciones';
$pageSubtitle = count($cotis) . ' cotización(es)';
require __DIR__ . '/../partials/header.php';
?>

<div x-data="cotiPage()">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-4 text-sm">
            <div class="rounded-xl glass px-4 py-2.5">
                <span class="text-zinc-500">En negociación:</span> <span class="font-semibold text-sky-300"><?= money($totEnviadas) ?></span>
            </div>
            <div class="rounded-xl glass px-4 py-2.5">
                <span class="text-zinc-500">Aceptadas:</span> <span class="font-semibold text-emerald-300"><?= money($totAceptadas) ?></span>
            </div>
        </div>
        <button @click="nuevo()" class="grad-btn inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nueva cotización
        </button>
    </div>

    <?php if (!$cotis): ?>
        <div class="rounded-2xl glass p-10 text-center text-zinc-500">Aún no hay cotizaciones. Crea la primera.</div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <?php foreach ($cotis as $c):
                $vence = $c['fecha'] ? date('Y-m-d', strtotime($c['fecha'] . ' +' . (int) $c['validez_dias'] . ' days')) : null;
                $vencida = $vence && strtotime($vence) < strtotime(date('Y-m-d')) && in_array($c['estado'], ['borrador','enviada'], true);
                $waMsg = 'Hola ' . ($c['cliente'] ?: '') . ', te comparto la cotización "' . $c['titulo'] . '" por ' . money($c['valor']) . '. Válida ' . (int) $c['validez_dias'] . ' días. Quedo atento 🙌';
                $wa = wa_link($c['telefono'] ?? '', $waMsg); ?>
                <div data-anim class="rounded-2xl glass p-5">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-display font-semibold text-white"><?= e($c['titulo']) ?></h3>
                                <?= estado_badge($c['estado']) ?>
                                <?php if ($vencida): ?><span class="rounded-full bg-rose-500/10 px-2 py-0.5 text-xs text-rose-300 ring-1 ring-rose-500/30">Vencida</span><?php endif; ?>
                            </div>
                            <p class="mt-0.5 text-xs text-zinc-500"><?= e($c['cliente'] ?: 'Sin cliente') ?> · <?= fecha($c['fecha']) ?><?php if ($vence): ?> · vence <?= fecha($vence) ?><?php endif; ?></p>
                        </div>
                        <span class="shrink-0 font-display text-lg font-bold text-white"><?= money($c['valor']) ?></span>
                    </div>
                    <?php if ($c['detalle']): ?><p class="text-sm text-zinc-500"><?= nl2br(e($c['detalle'])) ?></p><?php endif; ?>

                    <div class="mt-4 flex flex-wrap items-center gap-1.5 border-t border-white/5 pt-3">
                        <!-- Estado rápido -->
                        <?php if ($c['estado'] === 'borrador'): ?>
                            <button @click="estado(<?= (int) $c['id'] ?>, 'enviada')" class="rounded-lg bg-sky-500/10 px-2.5 py-1.5 text-xs font-semibold text-sky-300 hover:bg-sky-500/20">Marcar enviada</button>
                        <?php elseif ($c['estado'] === 'enviada'): ?>
                            <button @click="estado(<?= (int) $c['id'] ?>, 'aceptada')" class="rounded-lg bg-emerald-500/10 px-2.5 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/20">✓ Aceptada</button>
                            <button @click="estado(<?= (int) $c['id'] ?>, 'rechazada')" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-500 hover:bg-white/5 hover:text-rose-300">Rechazada</button>
                        <?php endif; ?>

                        <?php if ($c['estado'] === 'aceptada' && !$c['trabajo_id']): ?>
                            <button @click="convertir(<?= (int) $c['id'] ?>)" class="rounded-lg grad-btn px-2.5 py-1.5 text-xs font-semibold text-white">→ Crear trabajo</button>
                        <?php elseif ($c['trabajo_id']): ?>
                            <span class="rounded-lg bg-white/5 px-2.5 py-1.5 text-xs text-zinc-400">✓ Ya es trabajo</span>
                        <?php endif; ?>

                        <div class="ml-auto flex items-center gap-1">
                            <?php if ($wa): ?>
                                <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="rounded-lg p-2 text-zinc-400 hover:bg-emerald-500/10 hover:text-emerald-300" title="Enviar por WhatsApp">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zM17.5 14.382c-.075-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.767.967-.94 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                </a>
                            <?php endif; ?>
                            <a href="?p=cotizacion_print&id=<?= (int) $c['id'] ?>" target="_blank" class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-brand-300" title="Imprimir / PDF">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            </a>
                            <button @click='editar(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-brand-300" title="Editar">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button @click="borrar(<?= (int) $c['id'] ?>, '<?= e(addslashes($c['titulo'])) ?>')" class="rounded-lg p-2 text-zinc-400 hover:bg-white/5 hover:text-rose-400" title="Eliminar">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal -->
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm" @keydown.escape.window="open=false">
        <div @click.away="open=false" x-show="open" x-transition class="my-10 w-full max-w-lg rounded-2xl border border-white/10 bg-card shadow-2xl">
            <form method="post" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" :value="form.id">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-white" x-text="form.id ? 'Editar cotización' : 'Nueva cotización'"></h3>
                    <button type="button" @click="open=false" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="lbl">Título *</label>
                        <input name="titulo" x-model="form.titulo" required class="fld">
                    </div>
                    <div>
                        <label class="lbl">Cliente</label>
                        <select name="cliente_id" x-model="form.cliente_id" class="fld">
                            <option value="">— Sin cliente —</option>
                            <?php foreach ($clientes as $cl): ?><option value="<?= (int) $cl['id'] ?>"><?= e($cl['nombre']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">Valor ($)</label>
                        <input type="number" step="any" name="valor" x-model="form.valor" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Estado</label>
                        <select name="estado" x-model="form.estado" class="fld">
                            <option value="borrador">Borrador</option><option value="enviada">Enviada</option>
                            <option value="aceptada">Aceptada</option><option value="rechazada">Rechazada</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">Validez (días)</label>
                        <input type="number" name="validez_dias" x-model="form.validez_dias" class="fld">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl">Fecha</label>
                        <input type="date" name="fecha" x-model="form.fecha" class="fld">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl">Detalle (qué incluye)</label>
                        <textarea name="detalle" x-model="form.detalle" rows="4" class="fld" placeholder="Un ítem por línea..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="open=false" class="rounded-xl border border-white/10 px-4 py-2.5 text-sm font-medium text-zinc-300 hover:bg-white/5">Cancelar</button>
                    <button class="grad-btn rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <form method="post" id="actForm" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="accion" id="actAccion">
        <input type="hidden" name="valor" id="actValor">
        <input type="hidden" name="id" id="actId">
    </form>
</div>

<style>
    .fld { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(10,10,18,.6); padding:.6rem .9rem; color:#fff; outline:none; transition:.15s; }
    .fld:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.22); }
    .lbl { display:block; margin-bottom:.375rem; font-size:.875rem; color:#a1a1aa; }
    select.fld option { background:#171725; }
</style>

<script>
function cotiPage() {
    return {
        open: false, form: {},
        vacio: { id:'', cliente_id:'', titulo:'', detalle:'', valor:0, estado:'borrador', fecha:'<?= date('Y-m-d') ?>', validez_dias:15 },
        nuevo()  { this.form = {...this.vacio}; this.open = true; },
        editar(c){ this.form = {...this.vacio, ...c}; this.open = true; },
        act(accion, id, valor){ document.getElementById('actAccion').value = accion; document.getElementById('actId').value = id; document.getElementById('actValor').value = valor || ''; document.getElementById('actForm').submit(); },
        estado(id, val){ this.act('estado', id, val); },
        convertir(id){ if (confirm('¿Crear un trabajo a partir de esta cotización?')) this.act('convertir', id); },
        borrar(id, t){ if (confirm('¿Eliminar la cotización "' + t + '"?')) this.act('eliminar', id); },
    };
}
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
