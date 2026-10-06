<?php
/**
 * layout/footer.php
 * Plantilla de pie de página: cierra workspace y app-content e incluye los scripts JavaScript.
 */

require_once __DIR__ . '/../config/config.php';

$baseUrl = rtrim(BASE_URL, '/');
?>
        </div><!-- /.workspace -->
    </main><!-- /.app-content -->

    <!-- Script Global del Sistema (Control de Modales y Utilidades AJAX) -->
    <script src="<?= $baseUrl ?>/assets/js/main.js"></script>

    <!-- Script Opcional Específico de Módulo -->
    <?php if (isset($js_modulo)): ?>
        <script src="<?= $baseUrl ?>/assets/js/<?= htmlspecialchars($js_modulo) ?>.js"></script>
    <?php endif; ?>
</body>
</html>
