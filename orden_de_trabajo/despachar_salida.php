<?php
/**
 * orden_de_trabajo/despachar_salida.php
 * Endpoint AJAX POST para procesar la comisión y despacho de insumos:
 * - Asigna cuadrilla de operarios (orden_trabajo_operarios y comision)
 * - Registra cabecera de movimiento (entrega)
 * - Descuenta materiales consumibles del stock disponible
 * - Presta herramientas cambiando su estado a 'Prestada'
 * - Actualiza fechas, horarios, trabajo y estado de la ODT a 'En Proceso'
 * Todo en una sola transacción PDO.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada.']);
    exit;
}
requireRole(['admin', 'panolero']);

try {
    $idOdt = isset($_POST['id_odt']) ? (int)$_POST['id_odt'] : 0;
    if ($idOdt <= 0) {
        throw new Exception('Identificador de Orden de Trabajo inválido.');
    }

    // 1. Validar operarios de la comisión
    $operariosIds = isset($_POST['operarios']) && is_array($_POST['operarios']) 
        ? array_values(array_filter(array_map('intval', $_POST['operarios']))) 
        : [];

    if (empty($operariosIds)) {
        throw new Exception('Debe seleccionar al menos un agente para conformar la comisión de salida.');
    }

    // 2. Herramientas seleccionadas
    $herramientasIds = isset($_POST['herramientas']) && is_array($_POST['herramientas'])
        ? array_values(array_filter(array_map('intval', $_POST['herramientas'])))
        : [];

    // 3. Materiales seleccionados
    $materialesIds = isset($_POST['material_id']) && is_array($_POST['material_id'])
        ? array_map('intval', $_POST['material_id'])
        : [];
    $materialesCant = isset($_POST['material_cant']) && is_array($_POST['material_cant'])
        ? array_map('floatval', $_POST['material_cant'])
        : [];

    $materialesDespacho = [];
    foreach ($materialesIds as $idx => $mId) {
        if ($mId > 0 && isset($materialesCant[$idx]) && $materialesCant[$idx] > 0) {
            $materialesDespacho[] = [
                'id_insumo' => $mId,
                'cantidad'  => $materialesCant[$idx]
            ];
        }
    }

    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // 4. Insertar en 'orden_trabajo_operarios' ignorando duplicados (ON CONFLICT DO NOTHING)
    $stmtOp = $pdo->prepare("
        INSERT INTO orden_trabajo_operarios (id_odt, id_operario) 
        VALUES (:odt, :op) 
        ON CONFLICT (id_odt, id_operario) DO NOTHING
    ");
    $stmtCom = $pdo->prepare("
        INSERT INTO comision (id_odt, id_operario, activo) 
        SELECT :odt, :op, TRUE 
        WHERE NOT EXISTS (SELECT 1 FROM comision WHERE id_odt = :odt AND id_operario = :op)
    ");
    $stmtComUpd = $pdo->prepare("
        UPDATE comision SET activo = TRUE WHERE id_odt = :odt AND id_operario = :op
    ");

    foreach ($operariosIds as $idOp) {
        $stmtOp->execute([':odt' => $idOdt, ':op' => $idOp]);
        $stmtCom->execute([':odt' => $idOdt, ':op' => $idOp]);
        $stmtComUpd->execute([':odt' => $idOdt, ':op' => $idOp]);
    }

    // 5. Insertar cabecera de movimiento (entrega)
    // Primer agente asignado como responsable del remito
    $respOperario = $operariosIds[0];

    // Obtener DNI del usuario autenticado validando que exista en la tabla usuario
    $dniUsuario = $_SESSION['dni_usuario'] ?? null;
    if (!empty($dniUsuario)) {
        $chkStmt = $pdo->prepare("SELECT dni FROM usuario WHERE dni = :dni AND activo = TRUE");
        $chkStmt->execute([':dni' => $dniUsuario]);
        $dniValido = $chkStmt->fetchColumn();
        if (!$dniValido) {
            $dniUsuario = null;
        }
    }
    if (empty($dniUsuario)) {
        $uStmt = $pdo->query("SELECT dni FROM usuario WHERE activo = TRUE ORDER BY id_rol ASC LIMIT 1");
        $dniUsuario = $uStmt->fetchColumn();
    }

    $idMovimiento = null;
    $pdo->exec("SAVEPOINT sp_movimiento");
    try {
        // Esquema relacional PostgreSQL: id_tipo_mov = 1 (Entrega), dni_usuario, id_operario
        $stmtMov = $pdo->prepare("
            INSERT INTO movimiento (id_tipo_mov, id_odt, dni_usuario, id_operario, fecha, hora, observaciones, activo) 
            VALUES (1, :odt, :dni_usr, :resp, CURRENT_DATE, CURRENT_TIME, 'Salida a comisión de trabajo', TRUE) 
            RETURNING id_movimiento
        ");
        $stmtMov->execute([
            ':odt'     => $idOdt,
            ':dni_usr' => $dniUsuario,
            ':resp'    => $respOperario
        ]);
        $idMovimiento = (int)$stmtMov->fetchColumn();
        $pdo->exec("RELEASE SAVEPOINT sp_movimiento");
    } catch (Throwable $eM) {
        $pdo->exec("ROLLBACK TO SAVEPOINT sp_movimiento");
        // Fallback para esquema alternativo plano: tipo = 'entrega', id_usuario
        $stmtMov = $pdo->prepare("
            INSERT INTO movimiento (tipo, id_odt, id_usuario, id_operario, fecha, observaciones) 
            VALUES ('entrega', :odt, :usr, :resp, CURRENT_TIMESTAMP, 'Salida a comisión de trabajo') 
            RETURNING id_movimiento
        ");
        $idUsuario = (int)($_SESSION['id_usuario'] ?? 1);
        $stmtMov->execute([
            ':odt'  => $idOdt,
            ':usr'  => $idUsuario,
            ':resp' => $respOperario
        ]);
        $idMovimiento = (int)$stmtMov->fetchColumn();
    }

    if (!$idMovimiento) {
        throw new Exception("No se pudo registrar la cabecera del movimiento de entrega.");
    }

    // 6. Para cada material enviado: verificar stock_actual >= cantidad, descontar del stock y agregar renglón a 'movimiento_detalle'
    $stmtDetalleMat = $pdo->prepare("
        INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad) 
        VALUES (:mid, :iid, :cant)
    ");

    foreach ($materialesDespacho as $mat) {
        $descontado = false;
        $mId = (int)$mat['id_insumo'];

        // Intentar actualizar en esquema normalizado (stock_actual vinculado a insumos)
        $pdo->exec("SAVEPOINT sp_mat_{$mId}");
        try {
            $stmtStockNorm = $pdo->prepare("
                UPDATE stock_actual sa 
                SET stock_actual = sa.stock_actual - :cant, fecha = CURRENT_DATE 
                FROM insumos i 
                WHERE i.id_insumo = :id_insumo 
                  AND i.id_stock_actual = sa.id_stock_actual 
                  AND sa.stock_actual >= :cant
            ");
            $stmtStockNorm->execute([':cant' => $mat['cantidad'], ':id_insumo' => $mId]);
            if ($stmtStockNorm->rowCount() > 0) {
                $descontado = true;
                $pdo->exec("RELEASE SAVEPOINT sp_mat_{$mId}");
            } else {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_mat_{$mId}");
            }
        } catch (Throwable $eSN) {
            $pdo->exec("ROLLBACK TO SAVEPOINT sp_mat_{$mId}");
        }

        // Fallback: si no actualizó, intentar directamente en columna stock_actual de insumos
        if (!$descontado) {
            $pdo->exec("SAVEPOINT sp_mat_plano_{$mId}");
            try {
                $stmtStockPlano = $pdo->prepare("
                    UPDATE insumos 
                    SET stock_actual = stock_actual - :cant 
                    WHERE id_insumo = :id 
                      AND stock_actual >= :cant
                ");
                $stmtStockPlano->execute([':cant' => $mat['cantidad'], ':id' => $mId]);
                if ($stmtStockPlano->rowCount() > 0) {
                    $descontado = true;
                    $pdo->exec("RELEASE SAVEPOINT sp_mat_plano_{$mId}");
                } else {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_mat_plano_{$mId}");
                }
            } catch (Throwable $eSP) {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_mat_plano_{$mId}");
            }
        }

        if (!$descontado) {
            throw new Exception("Stock insuficiente para el material ID #{$mId}.");
        }

        $stmtDetalleMat->execute([
            ':mid'  => $idMovimiento,
            ':iid'  => $mId,
            ':cant' => $mat['cantidad']
        ]);
    }

    // 7. Para cada herramienta enviada: verificar que esté 'Disponible', actualizar su estado a 'Prestada' y registrar en 'movimiento_detalle'
    foreach ($herramientasIds as $idHerramienta) {
        $prestada = false;
        $hId = (int)$idHerramienta;

        // 1. Intentar actualizar sobre insumos con id_estado_herramienta (1 = Disponible -> 2 = Prestada)
        $pdo->exec("SAVEPOINT sp_her_{$hId}");
        try {
            $stmtPrestarNorm = $pdo->prepare("
                UPDATE insumos 
                SET id_estado_herramienta = 2 
                WHERE id_insumo = :id 
                  AND (id_estado_herramienta = 1 OR id_estado_herramienta IS NULL)
            ");
            $stmtPrestarNorm->execute([':id' => $hId]);
            if ($stmtPrestarNorm->rowCount() > 0) {
                $prestada = true;
                $pdo->exec("RELEASE SAVEPOINT sp_her_{$hId}");
            } else {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_her_{$hId}");
            }
        } catch (Throwable $ePN) {
            $pdo->exec("ROLLBACK TO SAVEPOINT sp_her_{$hId}");
        }

        // 2. Intentar actualizar sobre insumos con columna string estado_herramienta
        if (!$prestada) {
            $pdo->exec("SAVEPOINT sp_her_plano_{$hId}");
            try {
                $stmtPrestarPlano = $pdo->prepare("
                    UPDATE insumos 
                    SET estado_herramienta = 'Prestada' 
                    WHERE id_insumo = :id 
                      AND (estado_herramienta = 'Disponible' OR estado_herramienta IS NULL)
                ");
                $stmtPrestarPlano->execute([':id' => $hId]);
                if ($stmtPrestarPlano->rowCount() > 0) {
                    $prestada = true;
                    $pdo->exec("RELEASE SAVEPOINT sp_her_plano_{$hId}");
                } else {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_her_plano_{$hId}");
                }
            } catch (Throwable $ePP) {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_her_plano_{$hId}");
            }
        }

        // 3. Intentar actualizar sobre tabla relacional herramienta
        if (!$prestada) {
            $pdo->exec("SAVEPOINT sp_her_rel_{$hId}");
            try {
                $stmtPrestarRel = $pdo->prepare("
                    UPDATE herramienta 
                    SET id_estado_herramienta = 2 
                    WHERE id_herramienta = :id 
                      AND (id_estado_herramienta = 1 OR id_estado_herramienta IS NULL)
                ");
                $stmtPrestarRel->execute([':id' => $hId]);
                if ($stmtPrestarRel->rowCount() > 0) {
                    $prestada = true;
                    $pdo->exec("RELEASE SAVEPOINT sp_her_rel_{$hId}");
                } else {
                    $pdo->exec("ROLLBACK TO SAVEPOINT sp_her_rel_{$hId}");
                }
            } catch (Throwable $ePR) {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_her_rel_{$hId}");
            }
        }

        if (!$prestada) {
            throw new Exception("La herramienta ID #{$hId} ya no se encuentra en estado 'Disponible'.");
        }

        // Registrar detalle en movimiento_detalle
        $pdo->exec("SAVEPOINT sp_det_her_{$hId}");
        try {
            $stmtDetalleHer = $pdo->prepare("
                INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta) 
                VALUES (:mid, :iid, 1.00, 2)
            ");
            $stmtDetalleHer->execute([
                ':mid' => $idMovimiento,
                ':iid' => $hId
            ]);
            $pdo->exec("RELEASE SAVEPOINT sp_det_her_{$hId}");
        } catch (Throwable $eDH) {
            $pdo->exec("ROLLBACK TO SAVEPOINT sp_det_her_{$hId}");
            $stmtDetalleHer = $pdo->prepare("
                INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, estado_herramienta_resultante) 
                VALUES (:mid, :iid, 1.00, 'Prestada')
            ");
            $stmtDetalleHer->execute([
                ':mid' => $idMovimiento,
                ':iid' => $hId
            ]);
        }
    }

    // 8. Actualizar la orden de trabajo (estado = 'En Proceso', fecha_inicio, fecha_final, hora_inicio, hora_final, trabajo_a_realizar, observaciones)
    $stmtUpdateOdt = $pdo->prepare("
        UPDATE orden_de_trabajo 
        SET estado = 'En Proceso',
            id_mov_egreso = COALESCE(:id_mov, id_mov_egreso),
            fecha_inicio = COALESCE(:f_ini, fecha_inicio),
            fecha_final = COALESCE(:f_fin, fecha_final),
            hora_inicio = COALESCE(:h_ini, hora_inicio),
            hora_final = COALESCE(:h_fin, hora_final),
            trabajo_a_realizar = COALESCE(:trabajo, trabajo_a_realizar),
            observaciones = COALESCE(:obs, observaciones)
        WHERE id_odt = :odt
    ");
    $stmtUpdateOdt->execute([
        ':odt'     => $idOdt,
        ':id_mov'  => $idMovimiento,
        ':f_ini'   => !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null,
        ':f_fin'   => !empty($_POST['fecha_final']) ? $_POST['fecha_final'] : null,
        ':h_ini'   => !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null,
        ':h_fin'   => !empty($_POST['hora_final']) ? $_POST['hora_final'] : null,
        ':trabajo' => !empty($_POST['trabajo_a_realizar']) ? trim($_POST['trabajo_a_realizar']) : null,
        ':obs'     => !empty($_POST['observaciones']) ? trim($_POST['observaciones']) : null,
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Comisión de trabajo y despacho de insumos registrados con éxito.',
        'id_odt'  => $idOdt
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;