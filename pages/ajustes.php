<?php
/** Ajustes — configuración del negocio y contraseña */
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accion = post('accion');

    if ($accion === 'negocio') {
        $set = $pdo->prepare("INSERT INTO config (clave,valor) VALUES (?,?) ON CONFLICT(clave) DO UPDATE SET valor=excluded.valor");
        $set->execute(['negocio', post('negocio') ?: 'Vanegas Studio']);
        $set->execute(['wa_pais', preg_replace('/\D/', '', post('wa_pais')) ?: '57']);
        $set->execute(['meta_mensual', (string) (int) post_num('meta_mensual')]);
        flash('ok', 'Ajustes del negocio guardados.');
    }

    if ($accion === 'clave') {
        $u = current_user();
        $st = $pdo->prepare("SELECT clave FROM usuarios WHERE id=?");
        $st->execute([$u['id']]);
        $hash = (string) $st->fetchColumn();
        $actual = (string) ($_POST['clave_actual'] ?? '');
        $nueva  = (string) ($_POST['clave_nueva'] ?? '');
        $conf   = (string) ($_POST['clave_conf'] ?? '');
        if (!password_verify($actual, $hash)) {
            flash('error', 'La contraseña actual no es correcta.');
        } elseif (strlen($nueva) < 6) {
            flash('error', 'La nueva contraseña debe tener al menos 6 caracteres.');
        } elseif ($nueva !== $conf) {
            flash('error', 'La confirmación no coincide.');
        } else {
            $pdo->prepare("UPDATE usuarios SET clave=? WHERE id=?")->execute([password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
            flash('ok', 'Contraseña actualizada.');
        }
    }
    redirect('?p=ajustes');
}

$negocio = cfg('negocio', 'Vanegas Studio');
$waPais  = cfg('wa_pais', '57');
$meta    = (int) cfg('meta_mensual', '0');

$pageTitle    = 'Ajustes';
$pageSubtitle = 'Configuración del negocio';
require __DIR__ . '/../partials/header.php';
?>

<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <!-- Negocio -->
    <div data-anim class="rounded-2xl glass p-6">
        <h2 class="mb-1 font-display font-semibold text-white">Datos del negocio</h2>
        <p class="mb-5 text-xs text-zinc-500">Se usan en cotizaciones, metas y recordatorios.</p>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="negocio">
            <div>
                <label class="lbl">Nombre del negocio</label>
                <input name="negocio" value="<?= e($negocio) ?>" class="fld">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="lbl">Indicativo país (WhatsApp)</label>
                    <input name="wa_pais" value="<?= e($waPais) ?>" class="fld" placeholder="57">
                    <p class="mt-1 text-xs text-zinc-600">Colombia = 57. Se antepone a celulares de 10 dígitos.</p>
                </div>
                <div>
                    <label class="lbl">Meta de ingresos / mes ($)</label>
                    <input type="number" step="any" name="meta_mensual" value="<?= $meta ?>" class="fld">
                    <p class="mt-1 text-xs text-zinc-600">Se muestra como progreso en el Panel.</p>
                </div>
            </div>
            <div class="pt-1">
                <button class="grad-btn rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25">Guardar cambios</button>
            </div>
        </form>
    </div>

    <!-- Contraseña -->
    <div data-anim class="rounded-2xl glass p-6">
        <h2 class="mb-1 font-display font-semibold text-white">Cambiar contraseña</h2>
        <p class="mb-5 text-xs text-zinc-500">Usuario: <span class="text-zinc-300">@<?= e(current_user()['usuario']) ?></span></p>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="accion" value="clave">
            <div>
                <label class="lbl">Contraseña actual</label>
                <input type="password" name="clave_actual" required autocomplete="current-password" class="fld">
            </div>
            <div>
                <label class="lbl">Nueva contraseña</label>
                <input type="password" name="clave_nueva" required minlength="6" autocomplete="new-password" class="fld">
            </div>
            <div>
                <label class="lbl">Confirmar nueva contraseña</label>
                <input type="password" name="clave_conf" required minlength="6" autocomplete="new-password" class="fld">
            </div>
            <div class="pt-1">
                <button class="rounded-xl border border-white/10 bg-white/5 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10">Actualizar contraseña</button>
            </div>
        </form>
    </div>
</div>

<style>
    .fld { width:100%; border-radius:.75rem; border:1px solid rgba(255,255,255,.1); background:rgba(10,10,18,.6); padding:.6rem .9rem; color:#fff; outline:none; transition:.15s; }
    .fld:focus { border-color:#8b5cf6; box-shadow:0 0 0 3px rgba(139,92,246,.22); }
    .lbl { display:block; margin-bottom:.375rem; font-size:.875rem; color:#a1a1aa; }
</style>

<?php require __DIR__ . '/../partials/footer.php'; ?>
