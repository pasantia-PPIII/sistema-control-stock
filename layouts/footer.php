<?php
/**
 * layouts/footer.php
 * Plantilla de pie de página para el Sistema DeControl.
 * 
 * Cierra las etiquetas de workspace y app-content e incluye los scripts JavaScript.
 */

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
?>
        </div><!-- /.workspace -->
    </main><!-- /.app-content -->

    <!-- Script Global del Sistema (Control de Modales y Utilidades AJAX) -->
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>

    <!-- Script Opcional Específico de Módulo -->
    <?php if (isset($js_modulo)): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= htmlspecialchars($js_modulo) ?>.js"></script>
    <?php endif; ?>
</body>
</html>
