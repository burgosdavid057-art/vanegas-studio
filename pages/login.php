<?php
/** Login de administrador */
if (is_logged_in()) {
    redirect('?p=dashboard');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $usuario = post('usuario');
    $clave   = (string) ($_POST['clave'] ?? '');

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $u = $stmt->fetch();

    if ($u && password_verify($clave, $u['clave'])) {
        session_regenerate_id(true);
        $_SESSION['uid']      = (int) $u['id'];
        $_SESSION['unombre']  = $u['nombre'];
        $_SESSION['uusuario'] = $u['usuario'];
        redirect('?p=dashboard');
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ingresar · <?= APP_NAME ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: {
    colors: { base:'#0a0a12', card:'#171725', brand:{400:'#a78bfa',500:'#8b5cf6',600:'#7c3aed'}, accent:{500:'#ec4899',600:'#db2777'} },
    fontFamily: { sans:['Inter','sans-serif'], display:['"Space Grotesk"','Inter','sans-serif'] },
}}};
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<style>
    body{background:#0a0a12}
    .blob{position:fixed;border-radius:9999px;filter:blur(120px);opacity:.4;pointer-events:none}
    .grad-text{background:linear-gradient(120deg,#a78bfa,#f472b6);-webkit-background-clip:text;background-clip:text;color:transparent}
    .grad-btn{background:linear-gradient(120deg,#7c3aed,#db2777)}
    .grad-btn:hover{filter:brightness(1.12)}
</style>
</head>
<body class="min-h-screen font-sans text-zinc-200 antialiased flex items-center justify-center p-4 overflow-hidden">
    <div class="blob" style="width:460px;height:460px;top:-140px;left:-100px;background:radial-gradient(circle,#7c3aed,transparent 70%)"></div>
    <div class="blob" style="width:420px;height:420px;bottom:-160px;right:-90px;background:radial-gradient(circle,#db2777,transparent 70%)"></div>

    <div class="relative w-full max-w-sm" id="card">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl grad-btn text-2xl font-display font-bold text-white shadow-lg shadow-brand-600/30">V</div>
            <h1 class="font-display text-2xl font-bold text-white">Vanegas <span class="grad-text">Marketing</span></h1>
            <p class="mt-1 text-sm text-zinc-500">Panel interno de gestión</p>
        </div>

        <form method="post" class="space-y-4 rounded-2xl border border-white/8 bg-card/70 p-6 backdrop-blur-xl shadow-2xl">
            <?= csrf_field() ?>
            <?php if ($error): ?>
                <div class="rounded-lg bg-rose-500/10 px-3 py-2.5 text-sm text-rose-300 ring-1 ring-rose-500/30"><?= e($error) ?></div>
            <?php endif; ?>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-400">Usuario</label>
                <input name="usuario" autofocus required autocomplete="username"
                       class="w-full rounded-xl border border-white/10 bg-base/60 px-4 py-2.5 text-white outline-none transition placeholder:text-zinc-600 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30"
                       placeholder="admin">
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-zinc-400">Contraseña</label>
                <input type="password" name="clave" required autocomplete="current-password"
                       class="w-full rounded-xl border border-white/10 bg-base/60 px-4 py-2.5 text-white outline-none transition placeholder:text-zinc-600 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30"
                       placeholder="••••••••">
            </div>
            <button class="grad-btn w-full rounded-xl py-2.5 font-semibold text-white shadow-lg shadow-brand-600/25 transition active:scale-[.99]">
                Ingresar
            </button>
        </form>
        <p class="mt-6 text-center text-xs text-zinc-600">Acceso restringido &middot; solo personal autorizado</p>
    </div>

    <script>
        window.addEventListener('load',()=>{ if(window.gsap) gsap.from('#card',{opacity:0,y:24,duration:.7,ease:'power3.out'}); });
    </script>
</body>
</html>
