<?php
/**
 * Vanegas Marketing — Front controller / router
 */

require __DIR__ . '/inc/config.php';

$page = preg_replace('/[^a-z_]/', '', (string) ($_GET['p'] ?? 'dashboard'));

// Sesión
if ($page === 'login') {
    require __DIR__ . '/pages/login.php';
    exit;
}
if ($page === 'logout') {
    session_destroy();
    redirect('?p=login');
}

require_login();

$routes = ['dashboard', 'clientes', 'mensualidades', 'trabajos', 'cotizaciones', 'finanzas', 'reportes', 'ajustes', 'cotizacion_print'];
if (!in_array($page, $routes, true)) {
    $page = 'dashboard';
}

require __DIR__ . '/pages/' . $page . '.php';
