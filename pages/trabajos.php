<?php
/** Trabajos / sesiones de foto y video — con tablero kanban */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = post('accion');
    $xhr = post('xhr') === '1';

    if ($accion === 'mover') {
        $estado = post('estado');
        if (in_array($estado, ['agendado','grabado','edicion','entregado','cancelado'], true)) {
            $pdo->prepare("UPDATE trabajos SET estado=? WHERE id=?")->execute([$estado, post_int('id')]);
        }
        if ($xhr) { http_response_code(204); exit; }
        redirect('?p=trabajos&vista=tablero');
    }

    if ($accion === 'guardar') {
        $id = post_int('id');
        if (post('titulo') === '') { flash('error', 'El título es obligatorio.'); redirect('?p=trabajos'); }
        $datos = [
            post_int('cliente_id') ?: null, post('titulo'),
            in_array(post('tipo'), ['foto','video','ambos'], true) ? post('tipo') : 'foto',
            post('descripcion'), post('estado') ?: 'agendado',
            post('fecha_sesion') ?: null, post('fecha_entrega') ?: null,
            post('lugar'), post_num('valor'), post('entrega_url'), post('notas'),
        ];
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE trabajos SET cliente_id=?,titulo=?,tipo=?,descripcion=?,estado=?,fecha_sesion=?,fecha_entrega=?,lugar=?,valor=?,entrega_url=?,notas=? WHERE id=?");
            $stmt->execute([...$datos, $id]);
            flash('ok', 'Trabajo actualizado.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO trabajos (cliente_id,titulo,tipo,descripcion,estado,fecha_sesion,fecha_entrega,lugar,valor,entrega_url,notas) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute($datos);
            flash('ok', 'Trabajo creado.');
        }
    }

    if ($accion === 'cobrar') {
        $st = $pdo->prepare("SELECT t.*, c.nombre cliente FROM trabajos t LEFT JOIN clientes c ON c.id=t.cliente_id WHERE t.id=?");
        $st->execute([post_int('id')]);
        $t = $st->fetch();
        if ($t && !$t['cobrado'] && $t['valor'] > 0) {
            $fecha = $t['fecha_entrega'] ?: date('Y-m-d');
            $ins = $pdo->prepare("INSERT INTO movimientos (tipo,categoria,concepto,monto,fecha,cliente_id,origen,ref_id) VALUES ('ingreso','Trabajo',?,?,?,?,'trabajo',?)");
            $ins->execute([$t['titulo'], $t['valor'], $fecha, $t['cliente_id'], $t['id']]);
            $movId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE trabajos SET cobrado=1, movimiento_id=? WHERE id=?")->execute([$movId, $t['id']]);
            flash('ok', 'Trabajo cobrado — ingreso registrado en el libro.');
        }
    }

    if ($accion === 'descobrar') {
        $st = $pdo->prepare("SELECT movimiento_id FROM trabajos WHERE id=?");
        $st->execute([post_int('id')]);
        $mid = (int) ($st->fetchColumn() ?: 0);
        if ($mid) $pdo->prepare("DELETE FROM movimientos WHERE id=?")->execute([$mid]);
        $pdo->prepare("UPDATE trabajos SET cobrado=0, movimiento_id=NULL WHERE id=?")->execute([post_int('id')]);
        flash('ok', 'Cobro revertido.');
    }

    if ($accion === 'eliminar') {
        $st = $pdo->prepare("SELECT movimiento_id FROM trabajos WHERE id=?");
        $st->execute([post_int('id')]);
        $mid = (int) ($st->fetchColumn() ?: 0);
        if ($mid) $pdo->prepare("DELETE FROM movimientos WHERE id=?")->execute([$mid]);
        $pdo->prepare("DELETE FROM trabajos WHERE id=?")->execute([post_int('id')]);
        flash('ok', 'Trabajo eliminado.');
    }
    $v = in_array(($_GET['vista'] ?? ''), ['lista','tablero'], true) ? '&vista=' . $_GET['vista'] : '';
    redirect('?p=trabajos' . $v);
}

$vista = $_GET['vista'] ?? 'tablero';
if (!in_array($vista, ['lista', 'tablero'], true)) $vista = 'tablero';
$clientes = $pdo->query("SELECT id, nombre FROM clientes ORDER BY nombre")->fetchAll();
$filtro   = preg_replace('/[^a-z]/', '', (string) ($_GET['estado'] ?? ''));

$base = "SELECT t.*, c.nombre AS cliente, c.telefono FROM trabajos t LEFT JOIN clientes c ON c.id = t.cliente_id";
if ($vista === 'lista' && in_array($filtro, ['agendado','grabado','edicion','entregado','cancelado'], true)) {
    $sqlList = $base . " WHERE t.estado = " . $pdo->quote($filtro);
} else {
    $sqlList = $base;
}
$sqlList .= " ORDER BY t.fecha_sesion IS NULL, t.fecha_sesion DESC, t.creado DESC";
$trabajos = $pdo->query($sqlList)->fetchAll();

$conteos = ['todos'=>0,'agendado'=>0,'grabado'=>0,'edicion'=>0,'entregado'=>0,'cancelado'=>0];
foreach ($pdo->query("SELECT estado, COUNT(*) n FROM trabajos GROUP BY estado") as $r) {
    $conteos[$r['estado']] = (int) $r['n'];
    $conteos['todos'] += (int) $r['n'];
}
$porCobrar = (float) $pdo->query("SELECT COALESCE(SUM(valor),0) FROM trabajos WHERE cobrado=0 AND estado!='cancelado'")->fetchColumn();

$iconos = ['foto'=>'📷','video'=>'🎬','ambos'=>'🎞️'];

/** Render de una tarjeta (compartida entre lista y tablero) */
function card_trabajo(array $t, array $iconos): string
{
    $vencido = $t['fecha_entrega'] && strtotime($t['fecha_entrega']) < strtotime(date('Y-m-d')) && in_array($t['estado'], ['agendado','grabado','edicion'], true);
    $waMsg = 'Hola ' . ($t['cliente'] ?: '') . ', te comparto el cobro por "' . $t['titulo'] . '": ' . money($t['valor']) . '. ¡Gracias!';
    $wa = (!$t['cobrado'] && $t['valor'] > 0) ? wa_link($t['telefono'] ?? '', $waMsg) : '';
    ob_start(); ?>
    <div class="kcard rounded-xl border border-white/5 bg-white/[.02] p-3.5" draggable="true" data-id="<?= (int) $t['id'] ?>">
        <div class="mb-2 flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-base"><?= $iconos[$t['tipo']] ?? '📷' ?></span>
                <p class="font-medium leading-tight text-white"><?= e($t['titulo']) ?></p>
            </div>
            <?php if ($t['cobrado']): ?><span class="shrink-0 text-emerald-400" title="Cobrado">$</span><?php endif; ?>
        </div>
        <p class="text-xs text-zinc-500"><?= e($t['cliente'] ?: 'Sin cliente') ?></p>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-400">
            <?php if ($t['fecha_entrega']): ?><span class="<?= $vencido ? 'text-rose-300' : '' ?>">📦 <?= fecha($t['fecha_entrega']) ?></span><?php endif; ?>
            <?php if ($t['valor'] > 0): ?><span class="font-semibold text-white"><?= money($t['valor']) ?></span><?php endif; ?>
        </div>
        <div class="mt-2.5 flex items-center gap-1 border-t border-white/5 pt-2.5">
            <?php if ($t['entrega_url']): ?>
                <a href="<?= e($t['entrega_url']) ?>" target="_blank" rel="noopener" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-brand-300" title="Ver entrega">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($wa): ?>
                <a href="<?= e($wa) ?>" target="_blank" rel="noopener" class="rounded-lg p-1.5 text-zinc-400 hover:bg-emerald-500/10 hover:text-emerald-300" title="Recordar cobro por WhatsApp">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.978-1.607zM17.5 14.382c-.075-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.767.967-.94 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                </a>
            <?php endif; ?>
            <?php if ($t['valor'] > 0 && !$t['cobrado']): ?>
                <button @click="cobrar(<?= (int) $t['id'] ?>)" class="rounded-lg px-2 py-1 text-[11px] font-semibold text-emerald-300 hover:bg-emerald-500/10" title="Registrar cobro">✓ Cobrar</button>
            <?php endif; ?>
            <button @click='editar(<?= json_encode($t, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="ml-auto rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-brand-300" title="Editar">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <button @click="borrar(<?= (int) $t['id'] ?>, '<?= e(addslashes($t['titulo'])) ?>')" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-rose-400" title="Eliminar">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
    </div>
    <?php return ob_get_clean();
}

$pageTitle    = 'Trabajos';
$pageSubtitle = 'Sesiones de foto y video';
require __DIR__ . '/../partials/header.php';

$cols = ['agendado' => 'Agendados', 'grabado' => 'Grabados', 'edicion' => 'En edición', 'entregado' => 'Entregados'];
$tabs = ['' => 'Todos', 'agendado' => 'Agendados', 'grabado' => 'Grabados', 'edicion' => 'En edición', 'entregado' => 'Entregados', 'cancelado' => 'Cancelados'];
?>

<div x-data="trabajosPage()">
    <!-- Barra superior -->
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-2">
            <!-- Toggle vista -->
            <div class="flex rounded-xl bg-black/30 p-1">
                <a href="?p=trabajos&vista=tablero" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition <?= $vista==='tablero' ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white' ?>">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                    Tablero
                </a>
                <a href="?p=trabajos&vista=lista" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition <?= $vista==='lista' ? 'bg-white/10 text-white' : 'text-zinc-400 hover:text-white' ?>">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Lista
                </a>
            </div>
            <?php if ($vista === 'lista'): ?>
                <div class="hidden flex-wrap gap-1 sm:flex">
                    <?php foreach ($tabs as $k => $label): $active = $filtro === $k; ?>
                        <a href="?p=trabajos&vista=lista<?= $k ? '&estado=' . $k : '' ?>"
                           class="rounded-lg px-2.5 py-1.5 text-xs font-medium transition <?= $active ? 'grad-btn text-white' : 'bg-white/5 text-zinc-400 hover:text-white' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($porCobrar > 0): ?>
                <span class="hidden text-sm text-zinc-400 sm:inline">Por cobrar: <span class="font-semibold text-amber-300"><?= money($porCobrar) ?></span></span>
            <?php endif; ?>
            <button @click="nuevo()" class="grad-btn inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nuevo trabajo
            </button>
        </div>
    </div>

    <?php if ($vista === 'tablero'): ?>
        <!-- TABLERO KANBAN -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($cols as $estado => $titulo):
                $items = array_values(array_filter($trabajos, fn($t) => $t['estado'] === $estado)); ?>
                <div class="flex flex-col rounded-2xl glass p-3">
                    <div class="mb-3 flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full <?= ['agendado'=>'bg-sky-400','grabado'=>'bg-violet-400','edicion'=>'bg-amber-400','entregado'=>'bg-emerald-400'][$estado] ?>"></span>
                            <h3 class="text-sm font-semibold text-white"><?= $titulo ?></h3>
                        </div>
                        <span class="rounded-full bg-white/5 px-2 py-0.5 text-xs text-zinc-400" data-count-col="<?= $estado ?>"><?= count($items) ?></span>
                    </div>
                    <div class="kcol flex-1 space-y-2.5 rounded-xl p-1 transition" data-estado="<?= $estado ?>" style="min-height:120px">
                        <?php foreach ($items as $t) echo card_trabajo($t, $iconos); ?>
                        <?php if (!$items): ?><p class="kempty px-2 py-8 text-center text-xs text-zinc-600">Arrastra aquí</p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-xs text-zinc-600">💡 Arrastra las tarjetas entre columnas para cambiar su estado. Los cancelados solo aparecen en la vista Lista.</p>

    <?php else: ?>
        <!-- VISTA LISTA -->
        <?php if (!$trabajos): ?>
            <div class="rounded-2xl glass p-10 text-center text-zinc-500">No hay trabajos en esta vista.</div>
        <?php else: ?>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($trabajos as $t) echo card_trabajo($t, $iconos); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Modal -->
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm" @keydown.escape.window="open=false">
        <div @click.away="open=false" x-show="open" x-transition class="my-10 w-full max-w-xl rounded-2xl border border-white/10 bg-card shadow-2xl">
            <form method="post" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" :value="form.id">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="font-display text-lg font-semibold text-white" x-text="form.id ? 'Editar trabajo' : 'Nuevo trabajo'"></h3>
                    <button type="button" @click="open=false" class="rounded-lg p-1.5 text-zinc-400 hover:bg-white/5 hover:text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="lbl">Título del trabajo *</label>
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
                        <label class="lbl">Tipo</label>
                        <select name="tipo" x-model="form.tipo" class="fld">
                            <option value="foto">Foto</option><option value="video">Video</option><option value="ambos">Foto + Video</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">Estado</label>
                        <select name="estado" x-model="form.estado" class="fld">
                            <option value="agendado">Agendado</option><option value="grabado">Grabado</option>
                            <option value="edicion">En edición</option><option value="entregado">Entregado</option><option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">Valor ($)</label>
                        <input type="number" step="any" name="valor" x-model="form.valor" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Fecha sesión</label>
                        <input type="date" name="fecha_sesion" x-model="form.fecha_sesion" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Fecha entrega</label>
                        <input type="date" name="fecha_entrega" x-model="form.fecha_entrega" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Lugar</label>
                        <input name="lugar" x-model="form.lugar" class="fld">
                    </div>
                    <div>
                        <label class="lbl">Link de entrega / galería</label>
                        <input type="url" name="entrega_url" x-model="form.entrega_url" placeholder="https://drive.google.com/..." class="fld">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="lbl">Descripción</label>
                        <textarea name="descripcion" x-model="form.descripcion" rows="2" class="fld"></textarea>
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
        <input type="hidden" name="id" id="actId">
        <input type="hidden" name="vista" value="<?= e($vista) ?>">
    </form>
</div>

<style>
    .fld { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(10,10,18,.6); padding:.6rem .9rem; color:#fff; outline:none; transition:.15s; }
    .fld:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.22); }
    .lbl { display:block; margin-bottom:.375rem; font-size:.875rem; color:#a1a1aa; }
    select.fld option, datalist option { background:#171725; }
    .kcard { cursor:grab; transition:transform .12s, box-shadow .12s, opacity .12s; }
    .kcard:active { cursor:grabbing; }
    .kcard.dragging { opacity:.4; }
    .kcol.dragover { background:rgba(139,92,246,.08); outline:2px dashed rgba(139,92,246,.4); outline-offset:-2px; }
</style>

<script>
function trabajosPage() {
    return {
        open: false, form: {},
        vacio: { id:'', cliente_id:'', titulo:'', tipo:'foto', descripcion:'', estado:'agendado', fecha_sesion:'', fecha_entrega:'', lugar:'', valor:0, entrega_url:'' },
        nuevo()  { this.form = {...this.vacio}; this.open = true; },
        editar(t){ this.form = {...this.vacio, ...t}; this.open = true; },
        act(accion, id){ document.getElementById('actAccion').value = accion; document.getElementById('actId').value = id; document.getElementById('actForm').submit(); },
        cobrar(id){ this.act('cobrar', id); },
        borrar(id, t){ if (confirm('¿Eliminar el trabajo "' + t + '"?')) this.act('eliminar', id); },
    };
}

// ---- Kanban drag & drop ----
(function() {
    const csrf = '<?= csrf_token() ?>';
    let dragged = null;

    function bind() {
        document.querySelectorAll('.kcard').forEach(card => {
            card.addEventListener('dragstart', () => { dragged = card; card.classList.add('dragging'); });
            card.addEventListener('dragend', () => { card.classList.remove('dragging'); dragged = null; });
        });
        document.querySelectorAll('.kcol').forEach(col => {
            col.addEventListener('dragover', e => { e.preventDefault(); col.classList.add('dragover'); });
            col.addEventListener('dragleave', () => col.classList.remove('dragover'));
            col.addEventListener('drop', e => {
                e.preventDefault();
                col.classList.remove('dragover');
                if (!dragged) return;
                const estado = col.dataset.estado;
                const id = dragged.dataset.id;
                const empty = col.querySelector('.kempty');
                if (empty) empty.remove();
                col.appendChild(dragged);
                recount();
                fetch('?p=trabajos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrf, accion: 'mover', id, estado, xhr: '1' }),
                }).catch(() => location.reload());
            });
        });
    }
    function recount() {
        document.querySelectorAll('.kcol').forEach(col => {
            const badge = document.querySelector('[data-count-col="' + col.dataset.estado + '"]');
            if (badge) badge.textContent = col.querySelectorAll('.kcard').length;
        });
    }
    document.addEventListener('DOMContentLoaded', bind);
    if (document.readyState !== 'loading') bind();
})();
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
