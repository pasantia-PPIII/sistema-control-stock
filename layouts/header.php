<?php
/**
 * layouts/header.php
 * Plantilla de cabecera y sidebar institucional para el Sistema DeControl.
 * 
 * Directivas aplicadas:
 * - Sin iconos ni emojis en sidebar ni cabecera.
 * - Enlaces directos a assets/css/ mediante BASE_URL dinámica.
 * - Rutas a los dashboard.php de cada módulo (usuarios, insumo, movimiento, orden_de_trabajo, operario, rubro, reportes).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de URL base dinámica según el entorno del servidor web
if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
        $relative = substr($projectRoot, strlen($docRoot));
        define('BASE_URL', rtrim($relative, '/'));
    } else {
        define('BASE_URL', '/sistema-control-stock');
    }
}

$seccion_actual = $seccion ?? '';
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Administrador Taller';
$legajo_usuario = $_SESSION['usuario_id'] ?? '12345678';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina ?? 'Sistema de Control de Stock - DAM') ?></title>
    
    <!-- CSS Base Común (layout, sidebar, topbar, modales y botones de acción) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">

    <!-- CSS Específico del Módulo actual -->
    <?php if (isset($css_modulo)): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= htmlspecialchars($css_modulo) ?>.css">
    <?php endif; ?>
</head>
<body class="admin-layout">

    <!-- Sidebar Institucional Único (Sin Iconos / Emojis) -->
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <img src="<?= BASE_URL ?>/assets/img/logo.jpeg" alt="Logo Taller ONABE" class="sidebar-logo">
            <div class="brand-text">Dirección de Arquitectura<br>y Mantenimiento</div>
            <div class="brand-subtext">Poder Judicial de Corrientes</div>
        </div>
        <nav class="sidebar-menu">
            <a href="<?= BASE_URL ?>/usuarios/dashboard.php" class="menu-item <?= ($seccion_actual === 'usuarios' || $seccion_actual === 'usuario') ? 'active' : '' ?>">
                <span>Usuarios</span>
            </a>
            <a href="<?= BASE_URL ?>/insumo/dashboard.php" class="menu-item <?= ($seccion_actual === 'insumo' || $seccion_actual === 'insumos') ? 'active' : '' ?>">
                <span>Insumos</span>
            </a>
            <a href="<?= BASE_URL ?>/movimiento/dashboard.php" class="menu-item <?= ($seccion_actual === 'movimiento' || $seccion_actual === 'movimientos') ? 'active' : '' ?>">
                <span>Movimientos</span>
            </a>
            <a href="<?= BASE_URL ?>/orden_de_trabajo/dashboard.php" class="menu-item <?= ($seccion_actual === 'orden_de_trabajo' || $seccion_actual === 'ordenes_trabajo') ? 'active' : '' ?>">
                <span>Órdenes de Trabajo</span>
            </a>
            <a href="<?= BASE_URL ?>/operario/dashboard.php" class="menu-item <?= ($seccion_actual === 'operario' || $seccion_actual === 'operarios') ? 'active' : '' ?>">
                <span>Operarios</span>
            </a>
            <a href="<?= BASE_URL ?>/rubro/dashboard.php" class="menu-item <?= ($seccion_actual === 'rubro' || $seccion_actual === 'rubros') ? 'active' : '' ?>">
                <span>Rubros</span>
            </a>
            <a href="<?= BASE_URL ?>/reportes/dashboard.php" class="menu-item <?= ($seccion_actual === 'reportes' || $seccion_actual === 'reporte') ? 'active' : '' ?>">
                <span>Reportes</span>
            </a>
        </nav>
    </aside>

    <!-- Contenedor Principal -->
    <main class="app-content">
        <header class="top-bar">
            <h1 class="page-title"><?= htmlspecialchars($titulo_vista ?? 'Panel Principal') ?></h1>
            <div class="user-profile-header">
                <div class="avatar-circle"></div>
                <div class="user-meta">
                    <strong><?= htmlspecialchars($nombre_usuario) ?></strong>
                    <span>Usuario: <?= htmlspecialchars($legajo_usuario) ?></span>
                </div>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn-logout" title="Cerrar Sesión">Cerrar Sesión</a>
            </div>
        </header>

        <div class="workspace">
