<?php
/** Vista imprimible de una cotización (para guardar como PDF) */
$pdo = db();
$id = get_int('id');
$st = $pdo->prepare("SELECT co.*, c.nombre cliente, c.email, c.telefono FROM cotizaciones co LEFT JOIN clientes c ON c.id=co.cliente_id WHERE co.id=?");
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { http_response_code(404); exit('Cotización no encontrada.'); }

$negocio = cfg('negocio', 'Vanegas Studio');
$vence = $c['fecha'] ? date('Y-m-d', strtotime($c['fecha'] . ' +' . (int) $c['validez_dias'] . ' days')) : null;
$items = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $c['detalle'])));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cotización <?= str_pad((string) $c['id'], 4, '0', STR_PAD_LEFT) ?> · <?= e($negocio) ?></title>
<style>
    :root { --brand:#7c3aed; --accent:#db2777; --ink:#1a1a2e; --muted:#6b7280; }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'Segoe UI',system-ui,sans-serif; color:var(--ink); background:#f3f4f6; padding:2rem 1rem; }
    .sheet { max-width:800px; margin:0 auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,.1); }
    .top { background:linear-gradient(120deg,var(--brand),var(--accent)); color:#fff; padding:2.2rem 2.5rem; display:flex; justify-content:space-between; align-items:flex-start; }
    .brand { font-size:1.6rem; font-weight:800; letter-spacing:-.5px; }
    .brand small { display:block; font-size:.72rem; font-weight:500; text-transform:uppercase; letter-spacing:3px; opacity:.85; margin-top:2px; }
    .doc { text-align:right; }
    .doc h1 { font-size:1.1rem; letter-spacing:2px; text-transform:uppercase; }
    .doc p { font-size:.85rem; opacity:.9; margin-top:2px; }
    .body { padding:2.5rem; }
    .meta { display:flex; justify-content:space-between; gap:2rem; flex-wrap:wrap; margin-bottom:2rem; }
    .meta .lbl { font-size:.7rem; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:4px; }
    .meta .val { font-weight:600; }
    h2.tit { font-size:1.35rem; margin-bottom:1.2rem; color:var(--ink); }
    ul.items { list-style:none; margin-bottom:1.5rem; }
    ul.items li { padding:.7rem 0; border-bottom:1px solid #eee; display:flex; gap:.6rem; align-items:flex-start; font-size:.95rem; }
    ul.items li::before { content:'✓'; color:var(--brand); font-weight:700; }
    .total { background:#faf5ff; border:1px solid #eee; border-radius:12px; padding:1.3rem 1.6rem; display:flex; justify-content:space-between; align-items:center; }
    .total .t-lbl { font-size:.8rem; text-transform:uppercase; letter-spacing:1px; color:var(--muted); }
    .total .t-val { font-size:1.8rem; font-weight:800; color:var(--brand); }
    .foot { padding:1.5rem 2.5rem; border-top:1px solid #eee; font-size:.8rem; color:var(--muted); text-align:center; }
    .bar { position:fixed; top:1rem; right:1rem; display:flex; gap:.5rem; }
    .btn { background:var(--brand); color:#fff; border:none; padding:.6rem 1.1rem; border-radius:10px; font-size:.9rem; font-weight:600; cursor:pointer; text-decoration:none; }
    .btn.sec { background:#fff; color:var(--ink); border:1px solid #ddd; }
    @media print { body { background:#fff; padding:0; } .sheet { box-shadow:none; border-radius:0; max-width:none; } .bar { display:none; } }
</style>
</head>
<body>
    <div class="bar">
        <button class="btn" onclick="window.print()">🖨️ Imprimir / PDF</button>
        <a class="btn sec" href="?p=cotizaciones">Volver</a>
    </div>
    <div class="sheet">
        <div class="top">
            <div class="brand"><?= e($negocio) ?><small>Foto & Video</small></div>
            <div class="doc">
                <h1>Cotización</h1>
                <p>N.º <?= str_pad((string) $c['id'], 4, '0', STR_PAD_LEFT) ?></p>
                <p><?= fecha($c['fecha']) ?></p>
            </div>
        </div>
        <div class="body">
            <div class="meta">
                <div>
                    <p class="lbl">Cliente</p>
                    <p class="val"><?= e($c['cliente'] ?: 'Cliente') ?></p>
                    <?php if ($c['email']): ?><p style="font-size:.85rem;color:var(--muted)"><?= e($c['email']) ?></p><?php endif; ?>
                    <?php if ($c['telefono']): ?><p style="font-size:.85rem;color:var(--muted)"><?= e($c['telefono']) ?></p><?php endif; ?>
                </div>
                <div style="text-align:right">
                    <p class="lbl">Validez</p>
                    <p class="val"><?= (int) $c['validez_dias'] ?> días</p>
                    <?php if ($vence): ?><p style="font-size:.85rem;color:var(--muted)">Hasta <?= fecha($vence) ?></p><?php endif; ?>
                </div>
            </div>

            <h2 class="tit"><?= e($c['titulo']) ?></h2>
            <?php if ($items): ?>
                <ul class="items">
                    <?php foreach ($items as $it): ?><li><?= e($it) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="total">
                <span class="t-lbl">Total propuesto</span>
                <span class="t-val"><?= money($c['valor']) ?></span>
            </div>
        </div>
        <div class="foot">
            Gracias por confiar en <?= e($negocio) ?>. Esta cotización es válida por <?= (int) $c['validez_dias'] ?> días desde su emisión.
        </div>
    </div>
</body>
</html>
<?php exit; ?>
