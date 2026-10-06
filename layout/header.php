<?php
/**
 * layout/header.php
 * Plantilla de cabecera y sidebar institucional para el Sistema de Control de Stock.
 *
 * - Requiere sesión iniciada (login por DNI); si no, redirige a auth/login.php.
 * - Enlaces a assets/ y a los dashboard.php de cada módulo mediante BASE_URL.
 */

require_once __DIR__ . '/../config/config.php';

if (!isAuthenticated()) {
    redirect('auth/login.php');
}

// BASE_URL (config.php) termina en "/"; se normaliza para concatenar rutas
$baseUrl = rtrim(BASE_URL, '/');

$seccion_actual = $seccion ?? '';
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$dni_usuario = $_SESSION['dni_usuario'] ?? '';
$alerta_flash = getAlert();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($titulo_pagina ?? 'Sistema de Control de Stock - DAM') ?></title>

    <!-- CSS Base Común (layout, sidebar, topbar, modales y botones de acción) -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/global.css">

    <!-- CSS Específico del Módulo actual -->
    <?php if (isset($css_modulo)): ?>
        <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/<?= htmlspecialchars($css_modulo) ?>.css">
    <?php endif; ?>
    <script>window.APP_PERMISOS = <?= json_encode(permisosActuales(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
</head>
<body class="admin-layout">

    <!-- Sidebar Institucional -->
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <img src="<?= $baseUrl ?>/assets/img/logo.jpeg" alt="Logo Taller ONABE" class="sidebar-logo">
            <div class="brand-text">Dirección de Arquitectura<br>y Mantenimiento</div>
            <div class="brand-subtext">Poder Judicial de Corrientes</div>
        </div>
        <nav class="sidebar-menu">
            <a href="<?= $baseUrl ?>/panel/dashboard.php" class="menu-item <?= ($seccion_actual === 'panel') ? 'active' : '' ?>">
                <span>Panel de Control</span>
            </a>
            <a href="<?= $baseUrl ?>/usuario/dashboard.php" class="menu-item <?= ($seccion_actual === 'usuarios' || $seccion_actual === 'usuario') ? 'active' : '' ?>">
                <span>Usuarios</span>
            </a>
            <a href="<?= $baseUrl ?>/insumo/dashboard.php" class="menu-item <?= ($seccion_actual === 'insumo' || $seccion_actual === 'insumos') ? 'active' : '' ?>">
                <span>Insumos</span>
            </a>
            <a href="<?= $baseUrl ?>/movimiento/dashboard.php" class="menu-item <?= ($seccion_actual === 'movimiento' || $seccion_actual === 'movimientos') ? 'active' : '' ?>">
                <span>Movimientos</span>
            </a>
            <a href="<?= $baseUrl ?>/orden_de_trabajo/dashboard.php" class="menu-item <?= ($seccion_actual === 'orden_de_trabajo' || $seccion_actual === 'ordenes_trabajo') ? 'active' : '' ?>">
                <span>Órdenes de Trabajo</span>
            </a>
            <a href="<?= $baseUrl ?>/operario/dashboard.php" class="menu-item <?= ($seccion_actual === 'operario' || $seccion_actual === 'operarios') ? 'active' : '' ?>">
                <span>Operarios</span>
            </a>
            <a href="<?= $baseUrl ?>/rubro/dashboard.php" class="menu-item <?= ($seccion_actual === 'rubro' || $seccion_actual === 'rubros') ? 'active' : '' ?>">
                <span>Rubros</span>
            </a>
            <a href="<?= $baseUrl ?>/reporte/dashboard.php" class="menu-item <?= ($seccion_actual === 'reportes' || $seccion_actual === 'reporte') ? 'active' : '' ?>">
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
                    <span>DNI: <?= htmlspecialchars($dni_usuario) ?></span>
                </div>
                <form action="<?= $baseUrl ?>/auth/logout.php" method="POST" class="form-logout">
                    <?= csrfField() ?>
                    <button type="submit" class="btn-logout" title="Cerrar Sesión">Cerrar Sesión</button>
                </form>
            </div>
        </header>

        <div class="workspace">
            <?php if ($alerta_flash): ?>
                <?php $colores = ['success' => '#16a34a', 'danger' => '#dc2626', 'warning' => '#d97706', 'info' => '#2563eb']; ?>
                <div role="alert" style="margin-bottom: 1rem; padding: 0.8rem 1rem; border-radius: 8px; color: #fff; background: <?= $colores[$alerta_flash['type']] ?? '#2563eb' ?>;">
                    <?= htmlspecialchars($alerta_flash['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
