<?php
/**
 * src/Views/ejecucion/matriz.php
 * Registro de la Ejecución Financiera del Presupuesto de Gastos
 * Vista individual por partida — réplica del documento oficial (SNCF)
 * PHP 5.6 compatible — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once '../../../config/conexion.php';
require_once __DIR__ . '/../../Models/catalogo_partidas.php';
require_once __DIR__ . '/../../Models/audit_log.php';

/* ══════════════════════════════════════════════════════════════
   AUTO-CREAR TABLA AUXILIAR PARA NÚMEROS DE REGISTRO EDITABLES
   ══════════════════════════════════════════════════════════════ */
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS matriz_numeros_registro (
        id            INT(11) NOT NULL AUTO_INCREMENT,
        codificacion  VARCHAR(100) NOT NULL,
        op_id         INT(11) NOT NULL,
        mes           TINYINT(2) NOT NULL,
        anio          SMALLINT(4) NOT NULL,
        numero_reg    VARCHAR(50) NOT NULL DEFAULT '',
        detalle_custom VARCHAR(255) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_matriz_op (codificacion, op_id, mes, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);
@mysqli_query($conn, "ALTER TABLE matriz_numeros_registro ADD COLUMN detalle_custom VARCHAR(255) NULL AFTER numero_reg");
@mysqli_query($conn, "ALTER TABLE matriz_numeros_registro ADD COLUMN monto_compromiso_custom DECIMAL(18,2) NULL AFTER detalle_custom");
@mysqli_query($conn, "ALTER TABLE matriz_numeros_registro ADD COLUMN monto_pago_custom DECIMAL(18,2) NULL AFTER monto_compromiso_custom");
@mysqli_query($conn, "ALTER TABLE matriz_numeros_registro ADD COLUMN detalle_cancel_custom VARCHAR(255) NULL AFTER monto_pago_custom");

/* ── Tabla auxiliar para overrides de Retención Pendiente SENIAT ── */
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS matriz_retencion_overrides (
        id           INT(11) NOT NULL AUTO_INCREMENT,
        codificacion VARCHAR(100) NOT NULL,
        mes          TINYINT(2) NOT NULL,
        anio         SMALLINT(4) NOT NULL,
        monto_custom DECIMAL(18,2) NULL,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_ret_override (codificacion, mes, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);

/* ── Tabla auxiliar para disminuciones por OP ── */
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS matriz_disminuciones (
        id            INT(11) NOT NULL AUTO_INCREMENT,
        codificacion  VARCHAR(100) NOT NULL,
        op_id         INT(11) NOT NULL,
        mes           TINYINT(2) NOT NULL,
        anio          SMALLINT(4) NOT NULL,
        disminucion   DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        UNIQUE KEY uk_matzdisman (codificacion, op_id, mes, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);

/* ══════════════════════════════════════════════════════════════
   GUARDAR NÚMERO DE REGISTRO / DETALLE / MONTO / CRÉDITO (AJAX POST)
   ══════════════════════════════════════════════════════════════ */
/* ── Tablas para traspasos de crédito presupuestario ── */
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS traspaso_registros (
        id      INT(11) NOT NULL AUTO_INCREMENT,
        op_id   INT(11) NOT NULL,
        mes     TINYINT(2) NOT NULL,
        anio    SMALLINT(4) NOT NULL,
        texto_completo TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_tr (op_id, mes, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS traspaso_partidas (
        id           INT(11) NOT NULL AUTO_INCREMENT,
        traspaso_id  INT(11) NOT NULL,
        codificacion VARCHAR(100) NOT NULL,
        tipo         ENUM('origen','destino') NOT NULL,
        monto        DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        UNIQUE KEY uk_trp (traspaso_id, codificacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!$es_admin) {
        echo json_encode(array('ok' => false, 'msg' => 'Sin permisos'));
        exit;
    }

    /* ── Guardar número de registro ── */
        /* ── Guardar override de Retención Pendiente SENIAT ── */
    if ($_POST['action'] === 'save_retencion_seniat') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? trim($_POST['cod']) : '');
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $val_raw = isset($_POST['valor']) ? str_replace(array(' ', 'Bs.', 'Bs'), '', trim($_POST['valor'])) : '';
        $monto = ($val_raw === '') ? null : (float) str_replace(',', '', $val_raw);

        if ($cod && $mes && $anio) {
            if ($monto !== null) {
                mysqli_query(
                    $conn,
                    "INSERT INTO matriz_retencion_overrides (codificacion, mes, anio, monto_custom)
                     VALUES ('$cod', $mes, $anio, $monto)
                     ON DUPLICATE KEY UPDATE monto_custom = $monto"
                );
            } else {
                mysqli_query(
                    $conn,
                    "DELETE FROM matriz_retencion_overrides
                     WHERE codificacion = '$cod' AND mes = $mes AND anio = $anio"
                );
            }
            echo json_encode(array('ok' => true, 'monto' => $monto));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
        }
        exit;
    }

    if ($_POST['action'] === 'save_nr') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? $_POST['cod'] : '');
        $op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $nr = mysqli_real_escape_string($conn, isset($_POST['nr']) ? trim($_POST['nr']) : '');
        if ($cod && $op_id && $mes && $anio) {
            mysqli_query(
                $conn,
                "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, numero_reg)
                 VALUES ('$cod', $op_id, $mes, $anio, '$nr')
                 ON DUPLICATE KEY UPDATE numero_reg = '$nr'"
            );
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
        }
        exit;
    }

    /* ── Guardar detalle personalizado ── */
    if ($_POST['action'] === 'save_detalle') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? $_POST['cod'] : '');
        $op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $det_raw = isset($_POST['det']) ? trim($_POST['det']) : '';
        $det = mysqli_real_escape_string($conn, normalizar_texto_traspaso($det_raw));
        if ($cod && $op_id && $mes && $anio) {
            mysqli_query(
                $conn,
                "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, detalle_custom)
                 VALUES ('$cod', $op_id, $mes, $anio, '$det')
                 ON DUPLICATE KEY UPDATE detalle_custom = '$det'"
            );

            /* ── Si el detalle es un traspaso, registrar en tablas auxiliares ── */
            if (stripos($det_raw, 'TRASPASO') === 0) {
                $texto_norm = normalizar_texto_traspaso($det_raw);
                $texto_esc = mysqli_real_escape_string($conn, $texto_norm);

                /* Upsert en traspaso_registros */
                mysqli_query(
                    $conn,
                    "INSERT INTO traspaso_registros (op_id, mes, anio, texto_completo)
                     VALUES ($op_id, $mes, $anio, '$texto_esc')
                     ON DUPLICATE KEY UPDATE texto_completo = '$texto_esc'"
                );
                $tr_id_res = mysqli_query(
                    $conn,
                    "SELECT id FROM traspaso_registros WHERE op_id = $op_id AND mes = $mes AND anio = $anio LIMIT 1"
                );
                $tr_row = $tr_id_res ? mysqli_fetch_assoc($tr_id_res) : null;
                $tr_id = $tr_row ? (int) $tr_row['id'] : 0;

                if ($tr_id) {
                    /* Parsear partidas DE (origen) */
                    $orig_codes = array();
                    if (preg_match('/DE LAS PARTIDAS;\s*(.*?)\s+A LAS PARTIDA/si', $texto_norm, $mFrom)) {
                        foreach (explode(',', $mFrom[1]) as $c) {
                            $c = trim($c);
                            if ($c !== '')
                                $orig_codes[] = $c;
                        }
                    }
                    /* Parsear partidas A (destino) */
                    $dest_codes = array();
                    if (preg_match('/A LAS PARTIDAS?\s+(.*?)$/si', $texto_norm, $mTo)) {
                        foreach (explode(',', $mTo[1]) as $c) {
                            $c = trim($c);
                            if ($c !== '')
                                $dest_codes[] = $c;
                        }
                    }

                    /* Insertar/actualizar partidas involucradas */
                    foreach ($orig_codes as $oc) {
                        $oc_esc = mysqli_real_escape_string($conn, $oc);
                        mysqli_query(
                            $conn,
                            "INSERT INTO traspaso_partidas (traspaso_id, codificacion, tipo, monto)
                             VALUES ($tr_id, '$oc_esc', 'origen', 0)
                             ON DUPLICATE KEY UPDATE tipo = 'origen'"
                        );
                    }
                    foreach ($dest_codes as $dc) {
                        $dc_esc = mysqli_real_escape_string($conn, $dc);
                        mysqli_query(
                            $conn,
                            "INSERT INTO traspaso_partidas (traspaso_id, codificacion, tipo, monto)
                             VALUES ($tr_id, '$dc_esc', 'destino', 0)
                             ON DUPLICATE KEY UPDATE tipo = 'destino'"
                        );
                    }
                }
            }

            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
        }
        exit;
    }

    /* ── Crear traspaso directo desde el botón 'Asignar Traspaso' ── */
    if ($_POST['action'] === 'crear_traspaso_directo') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? trim($_POST['cod']) : '');
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $texto_raw = isset($_POST['texto']) ? trim($_POST['texto']) : '';
        $texto = normalizar_texto_traspaso($texto_raw);
        $texto_esc = mysqli_real_escape_string($conn, $texto);
        $fecha_tr = (isset($_POST['fecha']) && !empty($_POST['fecha'])) ? trim($_POST['fecha']) : date('Y-m-d');

        // Determinar mes y año a partir de la fecha del traspaso
        $ts_tr = strtotime($fecha_tr);
        if ($ts_tr !== false) {
            $mes = (int) date('n', $ts_tr);
            $anio = (int) date('Y', $ts_tr);
        }

        // Parsear monto del traspaso
        $monto_raw = isset($_POST['monto']) ? trim($_POST['monto']) : '0';
        $monto_raw = str_replace(array('Bs.', 'Bs', ' '), '', $monto_raw);
        if (strpos($monto_raw, ',') !== false && strpos($monto_raw, '.') !== false) {
            $monto_raw = str_replace('.', '', $monto_raw);
            $monto_raw = str_replace(',', '.', $monto_raw);
        } elseif (strpos($monto_raw, ',') !== false) {
            $monto_raw = str_replace(',', '.', $monto_raw);
        }
        $monto_val = max(0.0, (float) $monto_raw);

        if (!$texto_raw) {
            echo json_encode(array('ok' => false, 'msg' => 'El texto del traspaso es requerido'));
            exit;
        }

        $existing_op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $new_op_id = 0;

        if ($existing_op_id > 0) {
            $new_op_id = $existing_op_id;
            mysqli_query($conn, "UPDATE ordenes_pago SET fecha = '$fecha_tr', concepto = '$texto_esc' WHERE id = $new_op_id");
        } else {
            $res_max_op = mysqli_query($conn, "SELECT COALESCE(MAX(CAST(numero AS UNSIGNED)), 0) + 1 AS next_num FROM ordenes_pago");
            $max_row = $res_max_op ? mysqli_fetch_assoc($res_max_op) : null;
            $next_num = $max_row ? (int) $max_row['next_num'] : 1;

            $res_prov = mysqli_query($conn, "SELECT id FROM proveedores LIMIT 1");
            $r_prov = $res_prov ? mysqli_fetch_assoc($res_prov) : null;
            $prov_id = $r_prov ? (int)$r_prov['id'] : 1;

            $ins_op = mysqli_query(
                $conn,
                "INSERT INTO ordenes_pago (numero, fecha, proveedor_id, doc_tipo, concepto, monto_bruto, monto_neto_pagar, status, created_at)
                 VALUES ('$next_num', '$fecha_tr', $prov_id, 'TRASPASO', '$texto_esc', 0.00, 0.00, 'pagado', NOW())"
            );
            $new_op_id = mysqli_insert_id($conn);
        }

        if ($new_op_id) {
            // Guardar en traspaso_registros
            mysqli_query(
                $conn,
                "INSERT INTO traspaso_registros (op_id, mes, anio, texto_completo)
                 VALUES ($new_op_id, $mes, $anio, '$texto_esc')
                 ON DUPLICATE KEY UPDATE mes = $mes, anio = $anio, texto_completo = '$texto_esc'"
            );
            $r_chk = mysqli_query($conn, "SELECT id FROM traspaso_registros WHERE op_id = $new_op_id LIMIT 1");
            $tr_id = ($r_chk && $rc = mysqli_fetch_assoc($r_chk)) ? (int) $rc['id'] : 0;

            // Obtener listas de partidas
            $orig_codes = array();
            $dest_codes = array();

            if (!empty($_POST['from_codes'])) {
                foreach (explode(',', $_POST['from_codes']) as $c) {
                    $c = trim($c, " \t\n\r\0\x0B;:,.");
                    if ($c !== '') $orig_codes[] = $c;
                }
            }
            if (!empty($_POST['to_codes'])) {
                foreach (explode(',', $_POST['to_codes']) as $c) {
                    $c = trim($c, " \t\n\r\0\x0B;:,.");
                    if ($c !== '') $dest_codes[] = $c;
                }
            }

            // Fallback con regex flexible
            if (empty($orig_codes)) {
                if (preg_match('/DE LAS? PARTIDAS?[;:\s]+(.*?)(?=\s+A LAS? PARTIDAS?|$)/si', $texto, $mFrom)) {
                    foreach (explode(',', $mFrom[1]) as $c) {
                        $c = trim($c, " \t\n\r\0\x0B;:,.");
                        if ($c !== '') $orig_codes[] = $c;
                    }
                }
            }
            if (empty($dest_codes)) {
                if (preg_match('/A LAS? PARTIDAS?[;:\s]+(.*?)$/si', $texto, $mTo)) {
                    foreach (explode(',', $mTo[1]) as $c) {
                        $c = trim($c, " \t\n\r\0\x0B;:,.");
                        if ($c !== '') $dest_codes[] = $c;
                    }
                }
            }

            // Limpiar traspaso_partidas anteriores para este traspaso_id
            if ($tr_id) {
                mysqli_query($conn, "DELETE FROM traspaso_partidas WHERE traspaso_id = $tr_id");
            }

            // Registrar partidas de origen (disminución)
            foreach (array_unique($orig_codes) as $oc) {
                $oc_esc = mysqli_real_escape_string($conn, $oc);
                if ($tr_id) {
                    mysqli_query(
                        $conn,
                        "INSERT INTO traspaso_partidas (traspaso_id, codificacion, tipo, monto)
                         VALUES ($tr_id, '$oc_esc', 'origen', $monto_val)"
                    );
                }
                $vars_oc = get_matching_code_variants($oc);
                foreach ($vars_oc as $voc) {
                    $voc_esc = mysqli_real_escape_string($conn, $voc);
                    mysqli_query(
                        $conn,
                        "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, detalle_custom)
                         VALUES ('$voc_esc', $new_op_id, $mes, $anio, '$texto_esc')
                         ON DUPLICATE KEY UPDATE detalle_custom = '$texto_esc'"
                    );
                    if ($monto_val > 0) {
                        mysqli_query(
                            $conn,
                            "INSERT INTO matriz_disminuciones (codificacion, op_id, mes, anio, disminucion)
                             VALUES ('$voc_esc', $new_op_id, $mes, $anio, $monto_val)
                             ON DUPLICATE KEY UPDATE disminucion = $monto_val"
                        );
                    }
                }
            }

            // Registrar partidas de destino (aumento)
            foreach (array_unique($dest_codes) as $dc) {
                $dc_esc = mysqli_real_escape_string($conn, $dc);
                if ($tr_id) {
                    mysqli_query(
                        $conn,
                        "INSERT INTO traspaso_partidas (traspaso_id, codificacion, tipo, monto)
                         VALUES ($tr_id, '$dc_esc', 'destino', $monto_val)"
                    );
                }
                $vars_dc = get_matching_code_variants($dc);
                foreach ($vars_dc as $vdc) {
                    $vdc_esc = mysqli_real_escape_string($conn, $vdc);
                    mysqli_query(
                        $conn,
                        "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, detalle_custom)
                         VALUES ('$vdc_esc', $new_op_id, $mes, $anio, '$texto_esc')
                         ON DUPLICATE KEY UPDATE detalle_custom = '$texto_esc'"
                    );
                }
            }

            // Vincular también a la partida actual si fue seleccionada
            if ($cod) {
                $vars_cur = get_matching_code_variants($cod);
                foreach ($vars_cur as $vcur) {
                    $vcur_esc = mysqli_real_escape_string($conn, $vcur);
                    mysqli_query(
                        $conn,
                        "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, detalle_custom)
                         VALUES ('$vcur_esc', $new_op_id, $mes, $anio, '$texto_esc')
                         ON DUPLICATE KEY UPDATE detalle_custom = '$texto_esc'"
                    );
                }
            }

            echo json_encode(array('ok' => true, 'op_id' => $new_op_id, 'mes' => $mes, 'anio' => $anio));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Error al crear la orden de traspaso'));
        }
        exit;
    }

    /* ── Guardar monto de compromiso personalizado ── */
    if ($_POST['action'] === 'save_monto_comp') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? $_POST['cod'] : '');
        $op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $val_raw = isset($_POST['valor']) ? str_replace(array(',', ' '), '', $_POST['valor']) : '';
        $monto = ($val_raw === '') ? null : (float) $val_raw;

        if ($cod && $op_id && $mes && $anio) {
            if ($monto !== null) {
                mysqli_query(
                    $conn,
                    "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, monto_compromiso_custom)
                     VALUES ('$cod', $op_id, $mes, $anio, $monto)
                     ON DUPLICATE KEY UPDATE monto_compromiso_custom = $monto"
                );
            } else {
                mysqli_query(
                    $conn,
                    "UPDATE matriz_numeros_registro SET monto_compromiso_custom = NULL
                     WHERE codificacion = '$cod' AND op_id = $op_id AND mes = $mes AND anio = $anio"
                );
            }
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
        }
        exit;
    }

    /* ── Guardar monto de pago personalizado ── */
    if ($_POST['action'] === 'save_monto_pago') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? $_POST['cod'] : '');
        $op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $val_raw = isset($_POST['valor']) ? str_replace(array(',', ' '), '', $_POST['valor']) : '';
        $monto = ($val_raw === '') ? null : (float) $val_raw;

        if ($cod && $op_id && $mes && $anio) {
            if ($monto !== null) {
                mysqli_query(
                    $conn,
                    "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, monto_pago_custom)
                     VALUES ('$cod', $op_id, $mes, $anio, $monto)
                     ON DUPLICATE KEY UPDATE monto_pago_custom = $monto"
                );
            } else {
                mysqli_query(
                    $conn,
                    "UPDATE matriz_numeros_registro SET monto_pago_custom = NULL
                     WHERE codificacion = '$cod' AND op_id = $op_id AND mes = $mes AND anio = $anio"
                );
            }
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
        }
        exit;
    }

    /* ── Guardar detalle de cancelación personalizado ── */
    if ($_POST['action'] === 'save_detalle_cancel') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? $_POST['cod'] : '');
        $op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $det = mysqli_real_escape_string($conn, isset($_POST['det']) ? trim($_POST['det']) : '');

        if ($cod && $op_id && $mes && $anio) {
            mysqli_query(
                $conn,
                "INSERT INTO matriz_numeros_registro (codificacion, op_id, mes, anio, detalle_cancel_custom)
                 VALUES ('$cod', $op_id, $mes, $anio, '$det')
                 ON DUPLICATE KEY UPDATE detalle_cancel_custom = '$det'"
            );
            echo json_encode(array('ok' => true));
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
        }
        exit;
    }

    /* ── Guardar disminución por OP (campo CRÉDITO PRESUPUESTARIO ACTUALIZADO) ── */
    if ($_POST['action'] === 'save_disminucion') {
        $cod = mysqli_real_escape_string($conn, isset($_POST['cod']) ? trim($_POST['cod']) : '');
        $op_id = (int) (isset($_POST['op_id']) ? $_POST['op_id'] : 0);
        $mes = (int) (isset($_POST['mes']) ? $_POST['mes'] : 0);
        $anio = (int) (isset($_POST['anio']) ? $_POST['anio'] : 0);
        $val_raw = isset($_POST['valor']) ? str_replace(array(',', ' '), '', $_POST['valor']) : '';
        $disminucion = ($val_raw === '') ? 0.0 : max(0.0, (float) $val_raw);

        if (!$cod || !$op_id || !$mes || !$anio) {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
            exit;
        }

        /* ── Variantes del código para búsquedas en traspaso_partidas ── */
        $code_vars_ajax = get_matching_code_variants($cod);
        $esc_v_ajax = array();
        foreach ($code_vars_ajax as $cva) {
            $esc_v_ajax[] = "'" . mysqli_real_escape_string($conn, $cva) . "'";
        }
        $in_clause_ajax = !empty($esc_v_ajax) ? implode(',', $esc_v_ajax) : "'$cod'";

        /* ── Verificar si este OP es un traspaso y qué tipo es para esta partida ── */
        $traspaso_tipo = null;
        $tp_id_found = 0;
        $res_tr = mysqli_query(
            $conn,
            "SELECT tp.id AS tp_id, tp.tipo
             FROM traspaso_partidas tp
             JOIN traspaso_registros tr ON tr.id = tp.traspaso_id
             WHERE tr.op_id = $op_id AND tr.mes = $mes AND tr.anio = $anio
               AND tp.codificacion IN ($in_clause_ajax) LIMIT 1"
        );
        if ($res_tr && ($tr_row = mysqli_fetch_assoc($res_tr))) {
            $traspaso_tipo = $tr_row['tipo'];
            $tp_id_found = (int) $tr_row['tp_id'];
        }

        /* Guardar en matriz_disminuciones SÓLO para origen/normal (no para destino — son aumentos) */
        if ($traspaso_tipo !== 'destino') {
            mysqli_query(
                $conn,
                "INSERT INTO matriz_disminuciones (codificacion, op_id, mes, anio, disminucion)
                 VALUES ('$cod', $op_id, $mes, $anio, $disminucion)
                 ON DUPLICATE KEY UPDATE disminucion = $disminucion"
            );
        }

        /* Guardar monto en traspaso_partidas si aplica */
        if ($tp_id_found) {
            mysqli_query($conn, "UPDATE traspaso_partidas SET monto = $disminucion WHERE id = $tp_id_found");
        }

        /* Obtener crédito aprobado */
        $res_cred = mysqli_query($conn, "SELECT credito_original FROM partidas WHERE codigo = '$cod' LIMIT 1");
        $row_cred = $res_cred ? mysqli_fetch_assoc($res_cred) : null;
        $cred_aprobado = $row_cred ? (float) $row_cred['credito_original'] : 0.0;

        /* Asegurar fila en ejecucion_presupuestaria */
        $res_ep_chk = mysqli_query(
            $conn,
            "SELECT id FROM ejecucion_presupuestaria WHERE codificacion = '$cod' AND mes = $mes AND anio = $anio LIMIT 1"
        );
        $ep_exists = ($res_ep_chk && mysqli_num_rows($res_ep_chk) > 0);
        if (!$ep_exists) {
            mysqli_query(
                $conn,
                "INSERT IGNORE INTO ejecucion_presupuestaria
                    (codificacion, denominacion, credito_aprobado, aumentos, disminuciones, mes, anio)
                 VALUES ('$cod', '$cod', $cred_aprobado, 0.00, 0.00, $mes, $anio)"
            );
        }

        if ($traspaso_tipo === 'destino') {
            /* Partida DESTINO: el monto se suma como AUMENTO de crédito */
            $res_sum_au = mysqli_query(
                $conn,
                "SELECT COALESCE(SUM(tp.monto),0) AS total
                 FROM traspaso_partidas tp
                 JOIN traspaso_registros tr ON tr.id = tp.traspaso_id
                 WHERE tp.codificacion IN ($in_clause_ajax) AND tp.tipo = 'destino'
                   AND tr.mes = $mes AND tr.anio = $anio"
            );
            $row_au = $res_sum_au ? mysqli_fetch_assoc($res_sum_au) : null;
            $total_aumentos = $row_au ? (float) $row_au['total'] : 0.0;

            mysqli_query(
                $conn,
                "UPDATE ejecucion_presupuestaria
                 SET aumentos = $total_aumentos
                 WHERE codificacion = '$cod' AND mes = $mes AND anio = $anio"
            );

            echo json_encode(array(
                'ok' => true,
                'tipo' => 'destino',
                'total_aumento' => $total_aumentos,
                'credito_act' => $cred_aprobado + $total_aumentos
            ));
            exit;
        }

        /* Partida ORIGEN (o sin traspaso): el monto reduce el crédito como disminución */
        $res_sum = mysqli_query(
            $conn,
            "SELECT COALESCE(SUM(disminucion), 0) AS total
             FROM matriz_disminuciones
             WHERE codificacion = '$cod' AND mes = $mes AND anio = $anio"
        );
        $row_sum = $res_sum ? mysqli_fetch_assoc($res_sum) : null;
        $total_dismin = $row_sum ? (float) $row_sum['total'] : 0.0;

        mysqli_query(
            $conn,
            "UPDATE ejecucion_presupuestaria
             SET disminuciones = $total_dismin
             WHERE codificacion = '$cod' AND mes = $mes AND anio = $anio"
        );

        $nuevo_credito_act = $cred_aprobado - $total_dismin;
        echo json_encode(array(
            'ok' => true,
            'tipo' => $traspaso_tipo ?? 'normal',
            'total_dismin' => $total_dismin,
            'credito_act' => $nuevo_credito_act
        ));
        exit;
    }

    /* ── Guardar crédito original (partidas.credito_original) ── */
    if ($_POST['action'] === 'save_credito') {
        $cod_post = mysqli_real_escape_string($conn, isset($_POST['cod']) ? trim($_POST['cod']) : '');
        $val_raw = isset($_POST['valor']) ? str_replace(array(',', ' '), '', $_POST['valor']) : '';
        $nuevo = (float) $val_raw;

        if ($cod_post === '' || $val_raw === '') {
            echo json_encode(array('ok' => false, 'msg' => 'Datos incompletos'));
            exit;
        }
        if ($nuevo < 0) {
            echo json_encode(array('ok' => false, 'msg' => 'El crédito no puede ser negativo'));
            exit;
        }

        /* Leer valor anterior para auditoría */
        $res_ant = mysqli_query($conn, "SELECT credito_original FROM partidas WHERE codigo = '$cod_post' LIMIT 1");
        $ant_row = $res_ant ? mysqli_fetch_assoc($res_ant) : null;
        $anterior = $ant_row ? (float) $ant_row['credito_original'] : null;

        /* Si la partida no existe en la tabla, insertarla */
        if ($ant_row === null) {
            /* No existe — no actualizamos nada, devolvemos error */
            echo json_encode(array('ok' => false, 'msg' => 'Partida no encontrada en catálogo'));
            exit;
        }

        $res_upd = mysqli_query(
            $conn,
            "UPDATE partidas SET credito_original = $nuevo WHERE codigo = '$cod_post'"
        );
        if (!$res_upd) {
            echo json_encode(array('ok' => false, 'msg' => 'Error al guardar: ' . mysqli_error($conn)));
            exit;
        }

        /* Auditoría */
        $uid = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
        $detalle = 'Crédito original de [' . $cod_post . ']: anterior=' . number_format($anterior, 2, '.', ',') . ' → nuevo=' . number_format($nuevo, 2, '.', ',');
        log_activity($uid, 'EDITAR_CREDITO_ORIGINAL', 'Ejecución Presupuestaria', $detalle);

        echo json_encode(array('ok' => true, 'nuevo' => $nuevo, 'anterior' => $anterior));
        exit;
    }

    echo json_encode(array('ok' => false, 'msg' => 'Acción desconocida'));
    exit;
}

/* ══════════════════════════════════════════════════════════════
   FILTROS
   ══════════════════════════════════════════════════════════════ */
$mes_sel = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$anio_sel = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
if ($mes_sel < 1 || $mes_sel > 12)
    $mes_sel = (int) date('n');
if ($anio_sel < 2000 || $anio_sel > 2100)
    $anio_sel = (int) date('Y');

$meses_es = array(
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
);
$nombre_mes = isset($meses_es[$mes_sel]) ? $meses_es[$mes_sel] : $mes_sel;

/* Rango de fechas */
$anio_inicio_str = $anio_sel . '-01-01';
$mes_inicio_str = sprintf('%04d-%02d-01', $anio_sel, $mes_sel);
$mes_fin_str = date('Y-m-t', strtotime($mes_inicio_str));

/* ══════════════════════════════════════════════════════════════
   CATÁLOGO + PARTIDA SELECCIONADA
   ══════════════════════════════════════════════════════════════ */
$catalogo = get_catalogo_partidas($conn);

$cod_sel = isset($_GET['cod']) ? trim($_GET['cod']) : '';
if (empty($cod_sel) && !empty($catalogo)) {
    $cod_sel = $catalogo[0]['cod'];
}

$partida_info = null;
foreach ($catalogo as $ci) {
    if ($ci['cod'] === $cod_sel) {
        $partida_info = $ci;
        break;
    }
}
if (!$partida_info && !empty($catalogo)) {
    $partida_info = $catalogo[0];
    $cod_sel = $partida_info['cod'];
}

/* ══════════════════════════════════════════════════════════════
   PARSEAR CÓDIGO PRESUPUESTARIO
   Formato completo: 01-08-00-00-51-401-01-01-00
   Formato corto:    4.01.01.01
   ══════════════════════════════════════════════════════════════ */
function parse_codigo_mat($cod)
{
    $result = array('partida' => '', 'gen' => '', 'esp' => '', 'ordinal' => '');
    $prefix = '01-08-00-00-51-';
    $clean = str_replace(array('.', ' '), '-', trim($cod));
    if (strpos($clean, $prefix) === 0) {
        $parts = explode('-', substr($clean, strlen($prefix)));
        $result['partida'] = isset($parts[0]) ? $parts[0] : '';
        $result['gen'] = isset($parts[1]) ? $parts[1] : '';
        $result['esp'] = isset($parts[2]) ? $parts[2] : '';
        $result['ordinal'] = isset($parts[3]) ? $parts[3] : '';
    } else {
        $parts = explode('.', str_replace('-', '.', $cod));
        if (count($parts) >= 2) {
            $p0 = $parts[0];
            $p1 = isset($parts[1]) ? $parts[1] : '';
            $result['partida'] = (strlen($p0) === 1 && strlen($p1) === 2) ? $p0 . $p1 : $p0;
            $result['gen'] = isset($parts[2]) ? $parts[2] : '';
            $result['esp'] = isset($parts[3]) ? $parts[3] : '';
            $result['ordinal'] = isset($parts[4]) ? $parts[4] : '';
        }
    }
    return $result;
}

function get_codigo_punto($cod)
{
    $cp = parse_codigo_mat($cod);
    $p = trim($cp['partida']);
    if (strlen($p) === 3) {
        $ramo = substr($p, 0, 1);
        $sub = substr($p, 1, 2);
    } elseif (strlen($p) === 1) {
        $ramo = $p;
        $sub = '00';
    } else {
        $ramo = '4';
        $sub = str_pad($p, 2, '0', STR_PAD_LEFT);
    }
    $gen = !empty($cp['gen']) ? str_pad($cp['gen'], 2, '0', STR_PAD_LEFT) : '00';
    $esp = !empty($cp['esp']) ? str_pad($cp['esp'], 2, '0', STR_PAD_LEFT) : '00';
    $ord = !empty($cp['ordinal']) ? str_pad($cp['ordinal'], 2, '0', STR_PAD_LEFT) : '00';
    return $ramo . '.' . $sub . '.' . $gen . '.' . $esp . '.' . $ord;
}

function normalizar_texto_traspaso($str)
{
    if (empty($str))
        return $str;
    return preg_replace_callback('/01-08-00-00-51-(\d{3})-(\d{2})-(\d{2})-(\d{2})/', function ($m) {
        $p = $m[1];
        $ramo = substr($p, 0, 1);
        $sub = substr($p, 1, 2);
        return $ramo . '.' . $sub . '.' . $m[2] . '.' . $m[3] . '.' . $m[4];
    }, $str);
}

function render_selector_card($catalogo, $cod_sel, $partida_info, $mes_sel, $meses_es, $anio_sel)
{
    $form_id = 'form-selector-partida';
    $sel_id = 'sel-partida';
    $mes_id = 'sel-mes-partida';
    $anio_id = 'sel-anio-partida';
    $btn_id = 'btn-ver-partida';
    $card_class = 'selector-card no-print selector-card-bottom';
    $total_partidas = count($catalogo);
?>
    <div class="<?php echo $card_class; ?>" id="selector-card">
        <span class="selector-label">
            <svg style="display:inline;vertical-align:-2px;" width="13" height="13" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5z" />
                <path d="M2 17l10 5 10-5" />
                <path d="M2 12l10 5 10-5" />
            </svg>
            &nbsp;Seleccionar Partida Presupuestaria
        </span>
        <form method="GET" action="matriz.php" class="selector-row" id="<?php echo $form_id; ?>">
            <!-- Selector buscable personalizado con barra de filtro superior -->
            <div class="partida-searchable-select" data-select-id="<?php echo $sel_id; ?>">
                <!-- Select nativo oculto para envío tradicional de formulario y compatibilidad -->
                <select name="cod" id="<?php echo $sel_id; ?>" class="partida-select-native" style="display:none;" onchange="this.form.submit()">
                    <?php foreach ($catalogo as $ci): ?>
                        <?php $cod_punto = get_codigo_punto($ci['cod']); ?>
                        <option value="<?php echo htmlspecialchars($ci['cod']); ?>" <?php echo ($ci['cod'] === $cod_sel ? 'selected' : ''); ?>
                            data-punto="<?php echo htmlspecialchars($cod_punto); ?>"
                            data-denom="<?php echo htmlspecialchars($ci['denom']); ?>"
                            data-grupo="<?php echo htmlspecialchars(isset($ci['grupo']) ? $ci['grupo'] : ''); ?>">
                            <?php echo htmlspecialchars($cod_punto . ' — ' . $ci['denom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Botón disparador visible -->
                <button type="button" class="psd-trigger" aria-haspopup="listbox" aria-expanded="false" title="Haz clic para buscar o seleccionar una partida presupuestaria">
                    <span class="psd-trigger-badge"><?php echo htmlspecialchars($partida_info ? get_codigo_punto($partida_info['cod']) : '4.XX.XX.XX.XX'); ?></span>
                    <span class="psd-trigger-text"><?php echo htmlspecialchars($partida_info ? $partida_info['denom'] : 'Seleccionar Partida...'); ?></span>
                    <span class="psd-trigger-arrow">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                </button>

                <!-- Panel desplegable con barra de búsqueda y filtro arriba -->
                <div class="psd-dropdown-panel" style="display:none;">
                    <!-- Barra de filtro superior -->
                    <div class="psd-filter-header">
                        <div class="psd-search-box">
                            <span class="psd-search-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </span>
                            <input type="text" class="psd-filter-input" placeholder="Buscar por código (ej: 4.01, 401) o palabras (ej: sueldos, papelería)..." autocomplete="off" spellcheck="false">
                            <button type="button" class="psd-clear-btn" style="display:none;" title="Limpiar búsqueda">&times;</button>
                        </div>
                        <div class="psd-filter-meta">
                            <span class="psd-results-count">Mostrando <?php echo $total_partidas; ?> partidas</span>
                            <span class="psd-hint-kbd">↑↓ Navegar · ↵ Seleccionar · Esc Cerrar</span>
                        </div>
                    </div>

                    <!-- Lista con scroll de opciones -->
                    <div class="psd-options-list" role="listbox" tabindex="-1">
                        <?php foreach ($catalogo as $ci): ?>
                            <?php 
                            $cp = get_codigo_punto($ci['cod']); 
                            $isSelected = ($ci['cod'] === $cod_sel);
                            ?>
                            <div class="psd-option <?php echo $isSelected ? 'selected' : ''; ?>" 
                                 role="option" 
                                 aria-selected="<?php echo $isSelected ? 'true' : 'false'; ?>"
                                 data-value="<?php echo htmlspecialchars($ci['cod']); ?>"
                                 data-punto="<?php echo htmlspecialchars($cp); ?>"
                                 data-denom="<?php echo htmlspecialchars($ci['denom']); ?>"
                                 data-grupo="<?php echo htmlspecialchars(isset($ci['grupo']) ? $ci['grupo'] : ''); ?>">
                                <span class="psd-option-badge"><?php echo htmlspecialchars($cp); ?></span>
                                <span class="psd-option-content">
                                    <span class="psd-option-denom"><?php echo htmlspecialchars($ci['denom']); ?></span>
                                    <?php if (!empty($ci['grupo'])): ?>
                                        <span class="psd-option-grupo"><?php echo htmlspecialchars($ci['grupo']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="psd-option-check">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div class="psd-no-results" style="display:none;">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:6px; opacity:0.6;">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <div class="psd-no-results-title">No se encontraron partidas</div>
                            <div class="psd-no-results-desc">Intenta buscar por otro número de partida o palabra clave.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selector de Mes -->
            <select name="mes" id="<?php echo $mes_id; ?>" class="partida-select sel-small" onchange="this.form.submit()">
                <?php foreach ($meses_es as $n => $nm): ?>
                    <option value="<?php echo $n; ?>" <?php echo ($n == $mes_sel ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($nm); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Selector de Año -->
            <select name="anio" id="<?php echo $anio_id; ?>" class="partida-select" style="max-width:100px;" onchange="this.form.submit()">
                <?php for ($y = 2024; $y <= 2030; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y == $anio_sel ? 'selected' : ''); ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="btn-go" id="<?php echo $btn_id; ?>">Ver Partida</button>
        </form>
    </div>
<?php
}

$codigo_partes = parse_codigo_mat($cod_sel);

/* ══════════════════════════════════════════════════════════════
   DATOS ejecucion_presupuestaria + JOIN a partidas (fuente de verdad)
   ══════════════════════════════════════════════════════════════ */
$cod_esc = mysqli_real_escape_string($conn, $cod_sel);
$res_ep = mysqli_query(
    $conn,
    "SELECT ep.*,
            COALESCE(p.credito_original, ep.credito_aprobado) AS credito_original_real
     FROM ejecucion_presupuestaria ep
     LEFT JOIN partidas p ON p.codigo = ep.codificacion
     WHERE ep.codificacion = '$cod_esc' AND ep.mes = $mes_sel AND ep.anio = $anio_sel LIMIT 1"
);
$ep_row = ($res_ep && mysqli_num_rows($res_ep) > 0) ? mysqli_fetch_assoc($res_ep) : null;

/* Crédito original — fuente única: partidas.credito_original */
$credito_aprobado = $ep_row ? (float) $ep_row['credito_original_real'] : 0.0;
/* Leer también el valor directo de partidas para el campo editable */
$res_p_cred = mysqli_query($conn, "SELECT credito_original FROM partidas WHERE codigo = '$cod_esc' LIMIT 1");
$row_p_cred = $res_p_cred ? mysqli_fetch_assoc($res_p_cred) : null;
$credito_original_partida = $row_p_cred ? (float) $row_p_cred['credito_original'] : $credito_aprobado;

$credito_adicional = 0.0;
if ($ep_row && $ep_row['credito_adicional_override'] !== null) {
    $credito_adicional = (float) $ep_row['credito_adicional_override'];
}
$aumentos = $ep_row ? (float) $ep_row['aumentos'] : 0.0;
$disminuciones = $ep_row ? (float) $ep_row['disminuciones'] : 0.0;

$credito_actualizado = $credito_aprobado + $credito_adicional + $aumentos - $disminuciones;
if ($ep_row && $ep_row['credito_actualizado_override'] !== null) {
    $credito_actualizado = (float) $ep_row['credito_actualizado_override'];
}

/* ══════════════════════════════════════════════════════════════
   VARIANTES DE CÓDIGO PARA LAS QUERIES
   ══════════════════════════════════════════════════════════════ */
$code_variants = get_matching_code_variants($cod_sel);
$escaped_vars = array();
foreach ($code_variants as $cv) {
    $escaped_vars[] = "'" . mysqli_real_escape_string($conn, $cv) . "'";
}
$in_clause = !empty($escaped_vars) ? implode(',', $escaped_vars) : ("'" . $cod_esc . "'");

/* Helper to parse segment total */
if (!function_exists('parse_seg_total_m')) {
    function parse_seg_total_m($val)
    {
        if (!isset($val) || $val === '' || $val === null)
            return 0.0;
        $str = trim((string) $val);
        if (strpos($str, ',') !== false && strpos($str, '.') !== false) {
            $lastDot = strrpos($str, '.');
            $lastComma = strrpos($str, ',');
            if ($lastComma > $lastDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (strpos($str, ',') !== false) {
            $parts = explode(',', $str);
            if (count($parts) === 2 && strlen($parts[1]) === 3) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        }
        return (float) $str;
    }
}

$variants_lower = array_map('strtolower', $code_variants);

/**
 * Función para obtener el monto de una OP que corresponde a las variantes de la partida actual.
 * Revisa cont_json (totales por segmento) y op_retenciones.
 */
function get_monto_op_para_partida($conn, $op_row, $variants_lower, $in_clause)
{
    $monto_partida = 0.0;
    $matched = false;

    /* 1. Revisar op_retenciones primero (solo para IVA) */
    $oid = (int) $op_row['id'];
    $is_iva_cod = (strpos(strtolower($in_clause), '403-18-01') !== false || strpos(strtolower($in_clause), '4.03.18.01') !== false);
    if ($is_iva_cod) {
        $r = mysqli_query(
            $conn,
            "SELECT COALESCE(SUM(monto_comision),0) AS tot
             FROM op_retenciones
             WHERE op_id = $oid AND (codigo_presupuestario IN ($in_clause) OR descripcion LIKE '%IVA%')"
        );
        $rw = $r ? mysqli_fetch_assoc($r) : null;
        $m = $rw ? (float) $rw['tot'] : 0.0;
        if ($m > 0) {
            $monto_partida += $m;
            $matched = true;
        }
    }

    /* 2. Revisar cont_json */
    if (!empty($op_row['cont_json'])) {
        $segs = json_decode($op_row['cont_json'], true);
        if (is_array($segs)) {
            $n_segs = count($segs);
            $op_bruto = (float) $op_row['monto_bruto'];
            $has_explicit_total = false;
            foreach ($segs as $seg) {
                if (isset($seg['total']) && trim((string) $seg['total']) !== '' && trim((string) $seg['total']) !== '0' && trim((string) $seg['total']) !== '0.00' && trim((string) $seg['total']) !== '0,00') {
                    $has_explicit_total = true;
                    break;
                }
            }

            foreach ($segs as $seg) {
                $obra = isset($seg['obra']) ? trim($seg['obra']) : '';
                $partid = isset($seg['partid']) ? trim($seg['partid']) : '';
                $gen = isset($seg['gen']) ? trim($seg['gen']) : '';
                $espec = (isset($seg['espec']) && trim($seg['espec']) !== '') ? trim($seg['espec']) : '00';

                $short = strtolower($obra . '-' . $partid . '-' . $gen . '-' . $espec);
                $long = strtolower('01-08-00-00-51-' . $short);
                $dot = (strlen($obra) === 3)
                    ? strtolower(substr($obra, 0, 1) . '.' . substr($obra, 1) . '.' . $partid . '.' . $gen . '.' . $espec)
                    : strtolower($obra . '.' . $partid . '.' . $gen . '.' . $espec);

                foreach ($variants_lower as $v) {
                    if ($short === $v || $long === $v || $dot === $v) {
                        $seg_tot = parse_seg_total_m(isset($seg['total']) ? $seg['total'] : '');
                        $is_ret_code = (strpos($v, '403-18-01') !== false || strpos($v, '403-18-99') !== false || strpos($v, '4.03.18') !== false);
                        if ($seg_tot <= 0) {
                            if ($has_explicit_total || $is_ret_code) {
                                $seg_tot = 0.0;
                            } else {
                                $seg_tot = $op_bruto / $n_segs;
                            }
                        }
                        if ($seg_tot > 0) {
                            $matched = true;
                            $monto_partida += $seg_tot;
                        }
                        break;
                    }
                }
            }
        }
    }

    return array('matched' => $matched, 'monto' => $monto_partida);
}

/* ══════════════════════════════════════════════════════════════
   NÚMEROS DE REGISTRO, DETALLES Y OVERRIDES DE MONTOS GUARDADOS
   ══════════════════════════════════════════════════════════════ */
$nr_map = array();
$det_map = array();
$comp_map = array();
$pago_map = array();
$det_cancel_map = array();
$ytd_comp_map = array();
$ytd_pago_map = array();
$dismin_map = array(); /* disminucion por op_id para el mes seleccionado */

$res_nr_all = mysqli_query(
    $conn,
    "SELECT op_id, mes, numero_reg, detalle_custom, monto_compromiso_custom, monto_pago_custom, detalle_cancel_custom 
     FROM matriz_numeros_registro
     WHERE codificacion IN ($in_clause) AND anio = $anio_sel"
);
if ($res_nr_all) {
    while ($nr = mysqli_fetch_assoc($res_nr_all)) {
        $oid = (int) $nr['op_id'];
        $m = (int) $nr['mes'];
        if ($m === $mes_sel) {
            $nr_map[$oid] = $nr['numero_reg'];
            $det_map[$oid] = $nr['detalle_custom'];
            $comp_map[$oid] = ($nr['monto_compromiso_custom'] !== null) ? (float) $nr['monto_compromiso_custom'] : null;
            $pago_map[$oid] = ($nr['monto_pago_custom'] !== null) ? (float) $nr['monto_pago_custom'] : null;
            $det_cancel_map[$oid] = $nr['detalle_cancel_custom'];
        }
        if ($nr['monto_compromiso_custom'] !== null) {
            $ytd_comp_map[$oid] = (float) $nr['monto_compromiso_custom'];
        }
        if ($nr['monto_pago_custom'] !== null) {
            $ytd_pago_map[$oid] = (float) $nr['monto_pago_custom'];
        }
    }
}

/* Cargar mapa de disminuciones por OP del mes seleccionado */
$res_dismin = mysqli_query(
    $conn,
    "SELECT op_id, disminucion FROM matriz_disminuciones
     WHERE codificacion IN ($in_clause) AND mes = $mes_sel AND anio = $anio_sel"
);
if ($res_dismin) {
    while ($drow = mysqli_fetch_assoc($res_dismin)) {
        $dismin_map[(int) $drow['op_id']] = (float) $drow['disminucion'];
    }
}
$total_dismin_mes = array_sum($dismin_map);

/* CORRECCIÓN: $credito_actualizado ya incorpora EP.aumentos y EP.disminuciones
   que save_disminucion mantiene sincronizados con los inputs de la matriz.
   NO restar total_dismin_mes de nuevo (double-counting).
   credito_actualizado_con_dismin = credito_actualizado (EP ya tiene los valores correctos). */
$credito_actualizado_con_dismin = $credito_actualizado;
/* Base crudo para JS (sin dismin/aumento ya restados): la JS los calcula desde los inputs */
$credito_base_raw = $credito_aprobado + $credito_adicional;

/* ══════════════════════════════════════════════════════════════
   TRASPASOS QUE INVOLUCRAN ESTA PARTIDA
   Carga: tipo de cada OP traspaso para esta partida (origen/destino),
          texto completo, monto y filas externas.
   ══════════════════════════════════════════════════════════════ */
$op_traspaso_tipo = array(); /* op_id => 'origen' | 'destino' */
$traspaso_texto_map = array(); /* op_id => texto_completo (para usar en rows naturales) */
$aumento_map = array(); /* op_id => monto para partidas destino */
$traspasos_externos = array(); /* rows externas: op_id => array(...) */

$res_trp = mysqli_query(
    $conn,
    "SELECT tr.op_id, tr.texto_completo, tp.tipo, tp.monto,
            op.fecha, op.numero
     FROM traspaso_partidas tp
     JOIN traspaso_registros tr ON tr.id = tp.traspaso_id
     LEFT JOIN ordenes_pago op ON op.id = tr.op_id
     WHERE tp.codificacion IN ($in_clause)
       AND tr.mes = $mes_sel AND tr.anio = $anio_sel
     ORDER BY COALESCE(op.fecha, tr.updated_at) ASC, tr.id ASC"
);
if ($res_trp) {
    while ($trow = mysqli_fetch_assoc($res_trp)) {
        $top_id = (int) $trow['op_id'];
        $op_traspaso_tipo[$top_id] = $trow['tipo'];
        $traspaso_texto_map[$top_id] = normalizar_texto_traspaso($trow['texto_completo']);
        if ($trow['tipo'] === 'destino') {
            $aumento_map[$top_id] = (float) $trow['monto'];
        }
        /* Se marcará como externo sólo si no aparece en ops_mes naturales */
        $traspasos_externos[$top_id] = $trow;
    }
}


/* ══════════════════════════════════════════════════════════════
   ÓRDENES DE PAGO DEL MES QUE AFECTAN ESTA PARTIDA
   ══════════════════════════════════════════════════════════════ */
$res_all_ops = mysqli_query(
    $conn,
    "SELECT op.id, op.numero, op.fecha, op.monto_bruto, op.monto_neto_pagar, op.concepto, op.doc_tipo, op.rif_beneficiario, op.cont_json,
            COALESCE(p.razon_social, poc.razon_social, pos.razon_social, '') AS proveedor_nombre
     FROM ordenes_pago op
     LEFT JOIN proveedores p ON p.id = op.proveedor_id
     LEFT JOIN ordenes_compra oc ON oc.id = op.oc_id
     LEFT JOIN proveedores poc ON poc.id = oc.proveedor_id
     LEFT JOIN ordenes_servicio os ON os.id = op.os_id
     LEFT JOIN proveedores pos ON pos.id = os.proveedor_id
     WHERE op.fecha BETWEEN '$mes_inicio_str' AND '$mes_fin_str'
       AND op.deleted_at IS NULL
     ORDER BY op.fecha ASC, op.id ASC"
);

$ops_mes = array();
$op_montos = array();
$op_pagos = array();
$total_comp_mes = 0.0;
$total_pagos_mes = 0.0;

if ($res_all_ops) {
    while ($op_row = mysqli_fetch_assoc($res_all_ops)) {
        $res_match = get_monto_op_para_partida($conn, $op_row, $variants_lower, $in_clause);
        if ($res_match['matched']) {
            $oid = (int) $op_row['id'];
            $ops_mes[] = $op_row;

            $m_comp = (isset($comp_map[$oid]) && $comp_map[$oid] !== null) ? $comp_map[$oid] : $res_match['monto'];
            /* PAGOS siempre = COMPROMISOS (el campo es editable pero por defecto espeja el compromiso) */
            $m_pago = $m_comp;

            $op_montos[$oid] = $m_comp;
            $op_pagos[$oid] = $m_pago;

            $total_comp_mes += $m_comp;
            $total_pagos_mes += $m_pago;
        }
    }
}

/* ══════════════════════════════════════════════════════════════
   IVA (403-18-01-00) — GASTOS CAUSADOS POR TIPO DE PROVEEDOR
   ══════════════════════════════════════════════════════════════ */
$es_partida_iva = (strpos($cod_sel, '403-18-01') !== false);

/**
 * Clasifica un nombre de proveedor para IVA:
 *   'seniat'   → SENIAT (enteramiento, monto manual)
 *   'estatal'  → Ente estatal (CORPOELEC / CANTV → 100%)
 *   'privado'  → Proveedor privado (25%)
 */
if (!function_exists('iva_tipo_proveedor')) {
    function iva_tipo_proveedor($nombre)
    {
        $n = strtoupper(trim($nombre));
        if (strpos($n, 'SENIAT') !== false)
            return 'seniat';
        if (strpos($n, 'CORPOELEC') !== false || strpos($n, 'CANTV') !== false)
            return 'estatal';
        return 'privado';
    }
}

/* Arrays de gastos causados IVA por op_id (para el mes seleccionado) */
$op_gc_iva = array(); /* gastos causados IVA por OP */
$op_tipo_iva = array(); /* tipo de proveedor IVA por OP: seniat|estatal|privado */
$total_gc_iva_mes = 0.0;
$total_pago_iva_mes = 0.0;

/* Retención pendiente del mes ANTERIOR para pre-llenar el campo SENIAT */
$retencion_pendiente_mes_ant = 0.0;
if ($es_partida_iva && $mes_sel > 1) {
    /* mes anterior dentro del mismo año */
    $mes_ant = $mes_sel - 1;
    $mes_ant_inicio = sprintf('%04d-%02d-01', $anio_sel, $mes_ant);
    $mes_ant_fin = date('Y-m-t', strtotime($mes_ant_inicio));
    $anio_ant_ini = $anio_sel . '-01-01';

    $res_ant_comp = mysqli_query(
        $conn,
        "SELECT op.id, op.monto_bruto, op.monto_neto_pagar, op.cont_json,
                COALESCE(p.razon_social, poc.razon_social, pos.razon_social, '') AS proveedor_nombre
         FROM ordenes_pago op
         LEFT JOIN proveedores p ON p.id = op.proveedor_id
         LEFT JOIN ordenes_compra oc ON oc.id = op.oc_id
         LEFT JOIN proveedores poc ON poc.id = oc.proveedor_id
         LEFT JOIN ordenes_servicio os ON os.id = op.os_id
         LEFT JOIN proveedores pos ON pos.id = os.proveedor_id
         WHERE op.fecha BETWEEN '$anio_ant_ini' AND '$mes_ant_fin'
           AND op.deleted_at IS NULL"
    );
    $ant_comp_acum = 0.0;
    $ant_gc_acum = 0.0;
    if ($res_ant_comp) {
        /* Cargar mapa de overrides YTD del año para mes anterior */
        $cod_esc_ant = mysqli_real_escape_string($conn, $cod_sel);
        $res_nr_ant = mysqli_query(
            $conn,
            "SELECT op_id, mes, monto_compromiso_custom, monto_pago_custom
             FROM matriz_numeros_registro
             WHERE codificacion = '$cod_esc_ant' AND anio = $anio_sel"
        );
        $ytd_comp_map_ant = array();
        $ytd_pago_map_ant = array();
        if ($res_nr_ant) {
            while ($nr_ant = mysqli_fetch_assoc($res_nr_ant)) {
                $oid_a = (int) $nr_ant['op_id'];
                if ($nr_ant['monto_compromiso_custom'] !== null)
                    $ytd_comp_map_ant[$oid_a] = (float) $nr_ant['monto_compromiso_custom'];
                if ($nr_ant['monto_pago_custom'] !== null)
                    $ytd_pago_map_ant[$oid_a] = (float) $nr_ant['monto_pago_custom'];
            }
        }
        while ($ant_row = mysqli_fetch_assoc($res_ant_comp)) {
            $res_m = get_monto_op_para_partida($conn, $ant_row, $variants_lower, $in_clause);
            if (!$res_m['matched'])
                continue;
            $oid_a = (int) $ant_row['id'];
            $m_c = (isset($ytd_comp_map_ant[$oid_a])) ? $ytd_comp_map_ant[$oid_a] : $res_m['monto'];
            $ant_comp_acum += $m_c;
            /* Gastos causados del mes anterior según tipo IVA */
            $tipo_ant = iva_tipo_proveedor(isset($ant_row['proveedor_nombre']) ? $ant_row['proveedor_nombre'] : '');
            if ($tipo_ant === 'estatal') {
                $gc_ant = $m_c;
            } elseif ($tipo_ant === 'seniat') {
                $pago_ant = (isset($ytd_pago_map_ant[$oid_a])) ? $ytd_pago_map_ant[$oid_a] : (float) $ant_row['monto_neto_pagar'];
                $gc_ant = $pago_ant;
            } else {
                $gc_ant = $m_c * 0.25;
            }
            $ant_gc_acum += $gc_ant;
        }
    }
    $retencion_pendiente_mes_ant = max(0.0, $ant_comp_acum - $ant_gc_acum);
    /* Si el mes anterior tenía un override manual de retención pendiente, usarlo */
    $cod_esc_ant_ovr = mysqli_real_escape_string($conn, $cod_sel);
    $res_ant_ovr = mysqli_query(
        $conn,
        "SELECT monto_custom FROM matriz_retencion_overrides
         WHERE codificacion = '$cod_esc_ant_ovr' AND mes = $mes_ant AND anio = $anio_sel LIMIT 1"
    );
    if ($res_ant_ovr && $row_aovr = mysqli_fetch_assoc($res_ant_ovr)) {
        if ($row_aovr['monto_custom'] !== null) {
            $retencion_pendiente_mes_ant = (float) $row_aovr['monto_custom'];
        }
    }
}

if ($es_partida_iva) {
    foreach ($ops_mes as $op_iva) {
        $oid = (int) $op_iva['id'];
        $tipo = iva_tipo_proveedor(isset($op_iva['proveedor_nombre']) ? $op_iva['proveedor_nombre'] : '');
        $op_tipo_iva[$oid] = $tipo;
        $m_comp = isset($op_montos[$oid]) ? $op_montos[$oid] : 0.0;
        if ($tipo === 'estatal') {
            $gc = $m_comp;
        } elseif ($tipo === 'seniat') {
            /* Si hay monto_pago_custom guardado úsalo; si no, pre-llenar con retención pendiente */
            $gc = (isset($pago_map[$oid]) && $pago_map[$oid] !== null) ? $pago_map[$oid] : $retencion_pendiente_mes_ant;
        } else {
            $gc = $m_comp * 0.25;
        }
        $op_gc_iva[$oid] = $gc;
        $total_gc_iva_mes += $gc;
        $total_pago_iva_mes += $m_comp; /* pago = monto comprometido completo */
    }
}

/* Filtrar traspasos_externos: sólo op_id que NO aparecen en ops_mes naturales */
$ops_mes_ids = array();
foreach ($ops_mes as $nat_op) {
    $ops_mes_ids[] = (int) $nat_op['id'];
}
foreach (array_keys($traspasos_externos) as $ext_op_id) {
    if (in_array($ext_op_id, $ops_mes_ids)) {
        unset($traspasos_externos[$ext_op_id]);
    }
}
usort($traspasos_externos, function ($a, $b) {
    return strcmp($a['fecha'], $b['fecha']);
});

/* Compromisos YTD — para IVA incluir proveedor_nombre en el mismo SELECT */
if ($es_partida_iva) {
    $res_ytd_ops = mysqli_query(
        $conn,
        "SELECT op.id, op.fecha, op.monto_bruto, op.monto_neto_pagar, op.cont_json,
                COALESCE(p.razon_social, poc.razon_social, pos.razon_social, '') AS proveedor_nombre
         FROM ordenes_pago op
         LEFT JOIN proveedores p ON p.id = op.proveedor_id
         LEFT JOIN ordenes_compra oc ON oc.id = op.oc_id
         LEFT JOIN proveedores poc ON poc.id = oc.proveedor_id
         LEFT JOIN ordenes_servicio os ON os.id = op.os_id
         LEFT JOIN proveedores pos ON pos.id = os.proveedor_id
         WHERE op.fecha BETWEEN '$anio_inicio_str' AND '$mes_fin_str'
           AND op.deleted_at IS NULL"
    );
} else {
    $res_ytd_ops = mysqli_query(
        $conn,
        "SELECT id, fecha, monto_bruto, monto_neto_pagar, cont_json
         FROM ordenes_pago
         WHERE fecha BETWEEN '$anio_inicio_str' AND '$mes_fin_str'
           AND deleted_at IS NULL"
    );
}
$comp_acumulado = 0.0;
$pago_acumulado = 0.0;
$gc_acumulado_iva = 0.0; /* Gastos causados acumulados IVA (partida 403-18-01-00) */
$pago_acumulado_iva = 0.0; /* Pago acumulado IVA */
if ($res_ytd_ops) {
    while ($op_row = mysqli_fetch_assoc($res_ytd_ops)) {
        $res_match = get_monto_op_para_partida($conn, $op_row, $variants_lower, $in_clause);
        if ($res_match['matched']) {
            $oid = (int) $op_row['id'];
            $m_comp = (isset($ytd_comp_map[$oid]) && $ytd_comp_map[$oid] !== null) ? $ytd_comp_map[$oid] : $res_match['monto'];
            $m_pago = (isset($ytd_pago_map[$oid]) && $ytd_pago_map[$oid] !== null) ? $ytd_pago_map[$oid] : (float) $op_row['monto_neto_pagar'];

            $comp_acumulado += $m_comp;
            $pago_acumulado += $m_pago;

            /* Acumulado IVA — necesita proveedor_nombre: si viene en op_row úsalo, si no consultar */
            if ($es_partida_iva) {
                $prov_ytd = '';
                if (isset($op_row['proveedor_nombre'])) {
                    $prov_ytd = $op_row['proveedor_nombre'];
                } else {
                    $oid_esc = (int) $oid;
                    $rp = mysqli_query(
                        $conn,
                        "SELECT COALESCE(p.razon_social, poc.razon_social, pos.razon_social, '') AS pn
                         FROM ordenes_pago op
                         LEFT JOIN proveedores p ON p.id = op.proveedor_id
                         LEFT JOIN ordenes_compra oc ON oc.id = op.oc_id
                         LEFT JOIN proveedores poc ON poc.id = oc.proveedor_id
                         LEFT JOIN ordenes_servicio os ON os.id = op.os_id
                         LEFT JOIN proveedores pos ON pos.id = os.proveedor_id
                         WHERE op.id = $oid_esc LIMIT 1"
                    );
                    $rpr = $rp ? mysqli_fetch_assoc($rp) : null;
                    $prov_ytd = $rpr ? $rpr['pn'] : '';
                }
                $tipo_ytd = iva_tipo_proveedor($prov_ytd);
                if ($tipo_ytd === 'estatal') {
                    $gc_ytd = $m_comp;
                } elseif ($tipo_ytd === 'seniat') {
                    $gc_ytd = $m_pago; /* monto_pago_custom o monto_neto_pagar */
                } else {
                    $gc_ytd = $m_comp * 0.25;
                }
                $gc_acumulado_iva += $gc_ytd;
                $pago_acumulado_iva += $m_comp; /* pago acumulado IVA = comprometido completo */
            }
        }
    }
}

$acum_saldo = $credito_actualizado - $comp_acumulado;

function get_op_default_detalle($op)
{
    $num = ltrim(isset($op['numero']) ? $op['numero'] : '', '0');
    if (empty($num))
        $num = isset($op['numero']) ? $op['numero'] : '0';
    $num_fmt = sprintf('%03d', (int) $num);
    if ((int) $num == 0)
        $num_fmt = isset($op['numero']) ? $op['numero'] : '0';

    $tipo = strtoupper(trim(isset($op['doc_tipo']) ? $op['doc_tipo'] : ''));
    if ($tipo === 'BANAVIH' || $tipo === 'IVSS') {
        return 'O/P Nº ' . $num_fmt . ' ' . $tipo;
    }

    $conc = strtoupper(trim(isset($op['concepto']) ? $op['concepto'] : ''));
    if (strpos($conc, 'BANAVIH') !== false)
        return 'O/P Nº ' . $num_fmt . ' BANAVIH';
    if (strpos($conc, 'IVSS') !== false)
        return 'O/P Nº ' . $num_fmt . ' IVSS';
    if (strpos($conc, 'CAJA DE AHORRO') !== false || strpos($conc, 'CAJA DE AHORROS') !== false) {
        return 'O/P Nº ' . $num_fmt . ' ASOC. CIVIL CAJA DE AHORROS';
    }

    // Proveedor / Razón Social asignada a la orden
    $prov = strtoupper(trim(isset($op['proveedor_nombre']) ? $op['proveedor_nombre'] : ''));
    if (!empty($prov) && $prov !== 'N/A') {
        return 'O/P Nº ' . $num_fmt . ' ' . $prov;
    }

    // RIF del beneficiario
    $rif = strtoupper(trim(isset($op['rif_beneficiario']) ? $op['rif_beneficiario'] : ''));
    if (!empty($rif) && $rif !== 'N/A') {
        return 'O/P Nº ' . $num_fmt . ' ' . $rif;
    }

    if (!empty($tipo) && !in_array($tipo, array('ORDEN DE PAGO', 'TRANSFERENCIA', 'OS', 'OC', 'ORDEN DE COMPRA', 'ORDEN DE SERVICIO'))) {
        return 'O/P Nº ' . $num_fmt . ' ' . $tipo;
    }

    return 'O/P Nº ' . $num_fmt;
}

/* ══════════════════════════════════════════════════════════════
   FUNCIÓN FORMATO
   ══════════════════════════════════════════════════════════════ */
function fmt_m($v)
{
    return number_format((float) $v, 2, '.', ',');
}

$active = 'ejecucion-individual';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Registro de la Ejecución Financiera del Presupuesto de Gastos — Contraloría del Municipio Simón Rodríguez">
    <title>Registro Ejecución Financiera — Contraloría MSR</title>
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <style>
        /* ── Typography ── */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .page-title,
        .section-title,
        .card-title,
        .hdr-title,
        .brand-title,
        .brand-sub,
        .sb-section-label,
        .sb-parent-label,
        .sb-user-name,
        .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        /* ── Reset ── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ── */
        .site-header {
            background: #070707;
            color: white;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-bottom: 3px solid #0d47a1;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .site-header img {
            height: 52px;
            width: auto;
            display: block;
        }

        .site-header .brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .brand-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .site-header .brand-sub {
            font-size: 12.5px;
            color: #bbdefb;
        }

        .site-header .header-right {
            margin-left: auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .hdr-date {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
        }

        .site-header .hdr-sub {
            font-size: 10.5px;
            color: #94a3b8;
        }

        /* ── Layout ── */
        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 24px 28px;
            overflow-x: auto;
        }

        /* ── Page title ── */
        .page-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 12.5px;
            color: #64748b;
            margin-bottom: 20px;
        }

        /* ── Action row ── */
        .action-row {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
            align-items: center;
        }

        .btn-secondary {
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 8px 15px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: inherit;
            transition: all .15s ease;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-print {
            background: #b91c1c;
            color: #fff;
            border: none;
            padding: 8px 15px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: inherit;
            transition: background .15s;
        }

        .btn-print:hover {
            background: #df1111ff;
        }

        .btn-asignar-partida-top {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 15px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: inherit;
            transition: all .15s;
        }

        .btn-asignar-partida-top:hover {
            background: #1d4ed8;
        }

        .btn-asignar-traspaso-inline {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: 600;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-family: inherit;
            transition: all .15s ease;
            vertical-align: middle;
        }

        .btn-asignar-traspaso-inline:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e3a8a;
        }

        .btn-asignar-credito-pulsing {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            background: #dbeafe;
            color: #1e40af;
            border: 1.5px solid #93c5fd;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
            margin-right: 6px;
        }

        .btn-asignar-credito-pulsing:hover {
            background: #bfdbfe;
            border-color: #3b82f6;
            color: #1d4ed8;
        }

        /* ── DOCUMENTO OFICIAL ─────────────────────────────── */
        .doc-wrapper {
            background: #ffffff;
            border: 1.5px solid #94a3b8;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .08);
            padding: 14px 18px 18px;
            margin-bottom: 22px;
            max-width: 1380px;
        }

        /* Título centrado del documento */
        .doc-title-bar {
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1.5px solid #000;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }

        /* Encabezado institucional */
        .doc-header {
            display: grid;
            grid-template-columns: 60px 1fr 56px auto;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .doc-logo-left {
            width: 54px;
            height: auto;
        }

        .doc-logo-sncf {
            width: 46px;
            height: auto;
        }

        .doc-institution {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.5;
            text-align: center;
        }

        .hoja-box {
            border: 1px solid #000;
            display: grid;
            grid-template-columns: auto auto;
        }

        .hoja-label {
            background: #d0d0d0;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 3px 7px;
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
        }

        .hoja-value {
            padding: 3px 12px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
        }

        /* Caja código presupuestario */
        .codigo-block {
            margin: 6px 0;
        }

        .codigo-table {
            border-collapse: collapse;
            font-size: 9px;
        }

        .codigo-table th,
        .codigo-table td {
            border: 1px solid #000;
            text-align: center;
            padding: 2px 10px;
            white-space: nowrap;
        }

        .codigo-table thead tr:first-child th {
            background: #d0d0d0;
        }

        .codigo-table thead tr:last-child th {
            background: #e8e8e8;
        }

        .codigo-table th {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 8px;
        }

        .codigo-table td {
            font-weight: 700;
            font-size: 11px;
        }

        /* Denominación + crédito original */
        .denom-credito-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0;
            gap: 16px;
        }

        .denom-text {
            font-size: 10.5px;
            font-style: italic;
            font-weight: 600;
            color: #1e293b;
            flex: 1;
        }

        .credito-original-box {
            border: 1px solid #000;
            display: grid;
            grid-template-columns: 1fr auto;
            min-width: 280px;
        }

        .credito-label {
            background: #d0d0d0;
            padding: 3px 8px;
            font-size: 7.5px;
            font-weight: 800;
            text-transform: uppercase;
            border-right: 1px solid #000;
            line-height: 1.4;
            display: flex;
            align-items: center;
        }

        .credito-value {
            padding: 3px 12px;
            font-size: 12px;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        /* ── TABLA PRINCIPAL DE MOVIMIENTOS ── */
        .mat-table-wrap {
            overflow-x: auto;
            margin-top: 8px;
        }

        .mat-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            min-width: 980px;
        }

        .mat-table th {
            background: #d0d0d0;
            border: 0.8px solid #666;
            padding: 3px 4px;
            text-align: center;
            font-size: 7.5px;
            font-weight: 800;
            text-transform: uppercase;
            line-height: 1.25;
            vertical-align: bottom;
        }

        .mat-table th.th-grupo {
            background: #b8b8b8;
            font-size: 7px;
        }

        .mat-table td {
            border: 0.6px solid #bbb;
            padding: 2.5px 5px;
            vertical-align: middle;
        }

        .mat-table td.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .mat-table td.cen {
            text-align: center;
        }

        /* Filas especiales */
        tr.row-asignacion td {
            background: #f8fafc;
            font-weight: 700;
            font-size: 9px;
        }

        tr.row-op td {
            background: #ffffff;
        }

        tr.row-op:hover td {
            background: #eff6ff !important;
        }

        tr.row-cancel td {
            background: #fafafa;
            color: #555;
            font-size: 8.5px;
        }

        tr.row-cancel:hover td {
            background: #f0f9ff !important;
        }

        tr.row-saldo-int td {
            background: #f8fafc;
            font-size: 8px;
            color: #64748b;
        }

        tr.row-total-mes td {
            background: #dde3ea !important;
            font-weight: 800;
            font-size: 9px;
            border-top: 1.5px solid #333;
        }

        tr.row-acumulado td {
            background: #bec8d4 !important;
            font-weight: 800;
            font-size: 9px;
            border-top: 1.5px solid #333;
        }

        tr.row-no-data td {
            text-align: center;
            font-style: italic;
            color: #94a3b8;
            padding: 12px;
            font-size: 9.5px;
        }

        /* Input de número de registro */
        .nr-input {
            width: 52px;
            padding: 1px 4px;
            font-size: 8px;
            font-weight: 700;
            border: 1px solid #93c5fd;
            border-radius: 3px;
            background: #fff;
            text-align: center;
            font-family: inherit;
        }

        .nr-input:focus {
            outline: none;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .nr-input.saving {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .nr-input.saved {
            background: #d1fae5;
            border-color: #059669;
        }

        /* Detalle editable */
        .detalle-input {
            border: 1px dashed transparent;
            background: transparent;
            font-size: 9px;
            font-weight: 400;
            font-family: inherit;
            width: 100%;
            box-sizing: border-box;
            padding: 1px 3px;
            border-radius: 3px;
            color: #000;
            text-transform: uppercase;
            transition: background .12s, border-color .12s;
        }

        .detalle-input:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        .detalle-input:focus {
            outline: none;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .detalle-input.saving {
            background: #fef3c7;
        }

        .detalle-input.saved {
            background: #d1fae5;
        }

        .print-det-val {
            display: none !important;
        }

        /* Input de disminución en columna CRÉDITO PRESUPUESTARIO ACTUALIZADO */
        .dismin-input {
            border: 1px dashed #fbbf24;
            background: #fffbeb;
            font-size: 9px;
            font-weight: 700;
            font-family: inherit;
            width: 82px;
            box-sizing: border-box;
            padding: 1px 3px;
            border-radius: 3px;
            color: #92400e;
            text-align: right;
            transition: background .12s, border-color .12s;
        }

        .dismin-input:hover {
            border-color: #f59e0b;
            background: #fef3c7;
        }

        .dismin-input:focus {
            outline: none;
            border-color: #d97706;
            background: #fde68a;
        }

        .dismin-input.saving {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .dismin-input.saved {
            background: #d1fae5;
            border-color: #059669;
        }

        /* Input de aumento (partidas de destino en traspasos) */
        .aumento-input {
            border: 1px dashed #86efac !important;
            background: #f0fdf4 !important;
            color: #15803d !important;
        }

        .aumento-input:hover {
            border-color: #22c55e !important;
            background: #dcfce7 !important;
        }

        .aumento-input:focus {
            outline: none;
            border-color: #16a34a !important;
            background: #bbf7d0 !important;
        }

        /* Fila de traspaso externa */
        tr.row-traspaso-ext td {
            background: #fcfdfe;
        }

        tr.row-traspaso-ext:hover td {
            background: #f0fdf4 !important;
        }

        /* Monto numérico editable */
        .mat-num-input {
            border: 1px dashed transparent;
            background: transparent;
            font-size: 9px;
            font-weight: 700;
            font-family: inherit;
            width: 75px;
            box-sizing: border-box;
            padding: 1px 3px;
            border-radius: 3px;
            color: #000;
            text-align: right;
            transition: background .12s, border-color .12s;
        }

        .mat-num-input:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        .mat-num-input:focus {
            outline: none;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .mat-num-input.saving {
            background: #fef3c7;
        }

        .mat-num-input.saved {
            background: #d1fae5;
        }

        /* Detalle de cancelación editable */
        .mat-det-input {
            border: 1px dashed transparent;
            background: transparent;
            font-size: 8.5px;
            font-weight: 600;
            font-family: inherit;
            width: 100%;
            box-sizing: border-box;
            padding: 1px 3px;
            border-radius: 3px;
            color: #555;
            text-transform: uppercase;
            transition: background .12s, border-color .12s;
        }

        .mat-det-input:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        .mat-det-input:focus {
            outline: none;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .mat-det-input.saving {
            background: #fef3c7;
        }

        .mat-det-input.saved {
            background: #d1fae5;
        }

        .print-mat-val {
            display: none !important;
        }

        /* Crédito Original editable (admin) */
        .credito-edit-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .credito-input {
            width: 120px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 700;
            border: 1.5px solid #2563eb;
            border-radius: 4px;
            background: #eff6ff;
            text-align: right;
            font-family: 'Inter', sans-serif;
            font-variant-numeric: tabular-nums;
        }

        .credito-input:focus {
            outline: none;
            border-color: #1d4ed8;
            background: #dbeafe;
        }

        .credito-input.saving {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .credito-input.saved {
            background: #d1fae5;
            border-color: #059669;
        }

        .credito-input.error {
            background: #fee2e2;
            border-color: #dc2626;
        }

        .credito-save-btn {
            padding: 2px 8px;
            font-size: 9px;
            font-weight: 700;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
            transition: background .12s;
        }

        .credito-save-btn:hover {
            background: #1d4ed8;
        }

        .credito-status {
            font-size: 8px;
            color: #64748b;
        }

        /* ── SELECTOR DE PARTIDA ── */
        .selector-card {
            background: #ffffff;
            border: 1.5px solid #000000;
            border-radius: 6px;
            padding: 12px 16px;
            max-width: 1380px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 50;
        }

        .selector-card-top {
            margin-bottom: 16px;
            margin-top: 4px;
        }

        .selector-card-bottom {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .selector-label {
            font-size: 11px;
            font-weight: 800;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 8px;
        }

        .selector-label svg {
            color: #000000;
        }

        .selector-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .partida-select {
            padding: 8px 12px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #000000;
            border-radius: 4px;
            background: #ffffff;
            color: #000000;
            outline: none;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }

        .partida-select:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.15);
            background: #ffffff;
        }

        .partida-select option {
            background: #ffffff;
            color: #000000;
        }

        .sel-small {
            max-width: 140px !important;
            min-width: 110px !important;
        }

        /* ── SEARCHABLE PARTIDA SELECT COMPONENT (BLACK & WHITE WITH RESTRAINED BLUE) ── */
        .partida-searchable-select {
            position: relative;
            flex: 1;
            min-width: 320px;
        }

        .partida-searchable-select.open {
            z-index: 1000;
        }

        .psd-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 7px 12px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #000000;
            border-radius: 4px;
            background: #ffffff;
            color: #000000;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s ease;
            box-sizing: border-box;
            user-select: none;
        }

        .psd-trigger:hover {
            border-color: #1e40af;
            background: #f8fafc;
        }

        .psd-trigger:focus,
        .partida-searchable-select.open .psd-trigger {
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.18);
            background: #ffffff;
            outline: none;
        }

        .psd-trigger-badge {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #94a3b8;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            letter-spacing: 0.5px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .psd-trigger-text {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #000000;
            font-weight: 600;
        }

        .psd-trigger-arrow {
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, color 0.2s ease;
            flex-shrink: 0;
        }

        .partida-searchable-select.open .psd-trigger-arrow {
            transform: rotate(180deg);
            color: #000000;
        }

        /* Dropdown panel */
        .psd-dropdown-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            min-width: 460px;
            max-width: 700px;
            background: #ffffff;
            border: 1.5px solid #000000;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: psdFadeIn 0.12s ease-out;
        }

        .psd-dropdown-panel.opens-up {
            top: auto;
            bottom: calc(100% + 4px);
            animation: psdFadeInUp 0.12s ease-out;
        }

        @keyframes psdFadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes psdFadeInUp {
            from {
                opacity: 0;
                transform: translateY(4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Filter header at the top of the dropdown */
        .psd-filter-header {
            background: #f8fafc;
            padding: 8px 10px;
            border-bottom: 1.5px solid #000000;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-shrink: 0;
        }

        .psd-search-box {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .psd-search-icon {
            position: absolute;
            left: 9px;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .psd-filter-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid #64748b;
            border-radius: 4px;
            padding: 7px 30px 7px 30px;
            font-size: 12px;
            font-weight: 500;
            color: #000000;
            font-family: inherit;
            outline: none;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }

        .psd-filter-input:focus {
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.18);
        }

        .psd-filter-input::placeholder {
            color: #64748b;
            font-weight: normal;
        }

        .psd-clear-btn {
            position: absolute;
            right: 6px;
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 16px;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 3px;
            line-height: 1;
            transition: all 0.12s ease;
        }

        .psd-clear-btn:hover {
            color: #000000;
            background: #e2e8f0;
        }

        .psd-filter-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5px;
            padding: 0 2px;
        }

        .psd-results-count {
            color: #0f172a;
            font-weight: 700;
        }

        .psd-hint-kbd {
            color: #64748b;
            font-size: 9.5px;
        }

        /* Options list */
        .psd-options-list {
            overflow-y: auto;
            max-height: 320px;
            padding: 4px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            background: #ffffff;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f8fafc;
        }

        .psd-options-list::-webkit-scrollbar {
            width: 6px;
        }

        .psd-options-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .psd-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.1s, border-color 0.1s;
            user-select: none;
            border: 1px solid transparent;
            background: #ffffff;
        }

        .psd-option:hover,
        .psd-option.psd-focused {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .psd-option.selected {
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .psd-option-badge {
            background: #f8fafc;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.1s ease;
        }

        .psd-option:hover .psd-option-badge,
        .psd-option.psd-focused .psd-option-badge {
            background: #ffffff;
            border-color: #64748b;
            color: #000000;
        }

        .psd-option.selected .psd-option-badge {
            background: #1e40af;
            border-color: #1e40af;
            color: #ffffff;
        }

        .psd-option-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
            gap: 2px;
        }

        .psd-option-denom {
            font-size: 11.5px;
            color: #0f172a;
            font-weight: 500;
            line-height: 1.35;
            word-break: break-word;
        }

        .psd-option:hover .psd-option-denom,
        .psd-option.psd-focused .psd-option-denom {
            color: #000000;
        }

        .psd-option.selected .psd-option-denom {
            color: #1e3a8a;
            font-weight: 700;
        }

        .psd-option-grupo {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .psd-option-check {
            color: #1e40af;
            opacity: 0;
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }

        .psd-option.selected .psd-option-check {
            opacity: 1;
        }

        .psd-mark {
            background: #fef08a;
            color: #713f12;
            border-radius: 2px;
            padding: 0 2px;
            font-weight: 700;
        }

        .psd-no-results {
            padding: 20px 16px;
            text-align: center;
            color: #64748b;
        }

        .psd-no-results-title {
            font-size: 12px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 4px;
        }

        .psd-no-results-desc {
            font-size: 11px;
            color: #64748b;
        }

        .btn-go {
            background: #1e40af;
            color: #ffffff;
            border: 1px solid #1e3a8a;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .btn-go:hover {
            background: #1e3a8a;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        /* ── Footer ── */
        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            line-height: 1.8;
        }

        .site-footer strong {
            color: #e3f2fd;
        }

        /* ── Print ── */
        @media print {
            @page {
                size: A4 landscape;
                margin: 6mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .site-header,
            #sidebar,
            .sidebar,
            .site-footer {
                display: none !important;
            }

            .layout {
                display: block !important;
            }

            .main-content {
                padding: 0 !important;
            }

            .action-row {
                display: none !important;
            }

            .selector-card {
                display: none !important;
            }

            .page-title,
            .page-subtitle {
                display: none !important;
            }

            .doc-wrapper {
                border: none;
                box-shadow: none;
                padding: 3mm;
                margin: 0;
                max-width: none;
                border-radius: 0;
            }

            .mat-table {
                font-size: 7pt;
                min-width: 0;
            }

            .mat-table th {
                font-size: 6pt;
            }

            .nr-input {
                border: none;
                background: transparent;
                font-size: 7pt;
                width: auto;
            }

            .detalle-input,
            .mat-num-input,
            .mat-det-input {
                display: none !important;
            }

            .print-det-val,
            .print-mat-val {
                display: inline !important;
            }

            .credito-edit-wrap {
                display: none !important;
            }

            .print-credito-val {
                display: inline !important;
            }
        }
    </style>
</head>

<body>

    <!-- ═══ HEADER DEL SISTEMA ════════════════════════════════════════════ -->
    <header class="site-header no-print">
        <img src="/sistema/assets/img/logo.png" alt="Logo Contraloría">
        <div class="brand">
            <span class="brand-title">Contraloría MSR</span>
            <span class="brand-sub">Sistema de Gestión Presupuestaria</span>
        </div>
        <div class="header-right">
            <span class="hdr-date"><?php echo date('d/m/Y'); ?></span>
            <span
                class="hdr-sub"><?php echo htmlspecialchars(isset($_SESSION['nombre']) ? $_SESSION['nombre'] : $_SESSION['usuario']); ?></span>
        </div>
    </header>

    <!-- ═══ LAYOUT ════════════════════════════════════════════════════════ -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN -->
        <main class="main-content">

            <h1 class="page-title no-print">Registro de la Ejecución Financiera</h1>
            <p class="page-subtitle no-print">
                Vista individual por partida &mdash;
                <strong><?php echo htmlspecialchars($nombre_mes . ' ' . $anio_sel); ?></strong>
            </p>

            <!-- ── Botones de acción ── -->
            <div class="action-row no-print">
                <a href="index.php?mes=<?php echo $mes_sel; ?>&amp;anio=<?php echo $anio_sel; ?>" class="btn-secondary"
                    id="btn-volver-reporte">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12" />
                        <polyline points="12 19 5 12 12 5" />
                    </svg>
                    Reporte Mensual
                </a>
                <button type="button" class="btn-asignar-partida-top" id="btn-asignar-traspaso"
                    onclick="abrirTraspasoDesdeMatriz()"
                    title="Asignar o registrar un Traspaso de Crédito Presupuestario">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                    Asignar Traspaso
                </button>
                <button class="btn-print" onclick="window.print()" id="btn-imprimir-matriz">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    Imprimir / PDF
                </button>
            </div>

            <!-- ══════════════════════════════════════════════════════════
                 DOCUMENTO OFICIAL
                 ══════════════════════════════════════════════════════════ -->
            <div class="doc-wrapper">

                <!-- Título del documento -->
                <div class="doc-title-bar">
                    Registro de la Ejecución Financiera del Presupuesto de Gastos
                </div>

                <!-- Encabezado institucional -->
                <div class="doc-header">
                    <img src="/sistema/assets/img/logo.png" alt="Logo" class="doc-logo-left">
                    <div class="doc-institution">
                        República Bolivariana de Venezuela<br>
                        Contraloría del Municipio Simón Rodríguez<br>
                        Planificación y Presupuesto<br>
                        <strong>Unidad Ejecutora</strong>
                    </div>
                    <img src="/sistema/assets/img/sncf.png" alt="SNCF" class="doc-logo-sncf">
                    <div class="hoja-box">
                        <span class="hoja-label">HOJA&nbsp;Nº</span>
                        <span class="hoja-value">1</span>
                    </div>
                </div>

                <!-- Código Presupuestario -->
                <div class="codigo-block">
                    <table class="codigo-table">
                        <thead>
                            <tr>
                                <th rowspan="2">CÓDIGO PRESUPUESTARIO</th>
                                <th colspan="2">SUB-PARTIDA</th>
                                <th rowspan="2">ORDINAL</th>
                            </tr>
                            <tr>
                                <th>GEN</th>
                                <th>ESP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td id="disp-partida"><?php echo htmlspecialchars($codigo_partes['partida']); ?></td>
                                <td id="disp-gen"><?php echo htmlspecialchars($codigo_partes['gen']); ?></td>
                                <td id="disp-esp"><?php echo htmlspecialchars($codigo_partes['esp']); ?></td>
                                <td id="disp-ordinal"><?php echo htmlspecialchars($codigo_partes['ordinal']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Denominación + Crédito Monto Original -->
                <div class="denom-credito-row">
                    <span class="denom-text" id="disp-denominacion">
                        <?php echo htmlspecialchars($partida_info ? $partida_info['denom'] : '—'); ?>
                    </span>
                    <div class="credito-original-box">
                        <span class="credito-label">Crédito Presupuestario<br>Monto Original</span>
                        <span class="credito-value" id="disp-credito-original">
                            <?php if ($es_admin): ?>
                                <div class="credito-edit-wrap no-print">
                                    <?php if ($credito_original_partida <= 0): ?>
                                        <button type="button" class="btn-asignar-credito-pulsing"
                                            onclick="var inp = document.getElementById('credito-original-input'); if(inp){ inp.focus(); inp.select(); }"
                                            title="Esta partida no tiene crédito asignado. Haz clic para ingresar el monto">
                                            ⚡ Asignar Crédito
                                        </button>
                                    <?php endif; ?>
                                    <input type="text" inputmode="decimal" id="credito-original-input" class="credito-input"
                                        value="<?php echo fmt_m($credito_original_partida); ?>"
                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                        data-comp-acum="<?php echo $comp_acumulado; ?>"
                                        title="Crédito Original (editable — se guarda en partidas.credito_original)">
                                    <button type="button" class="credito-save-btn no-print" id="btn-save-credito"
                                        onclick="guardarCredito()">Guardar</button>
                                    <span class="credito-status" id="credito-status"></span>
                                </div>
                                <span class="print-credito-val"><?php echo fmt_m($credito_original_partida); ?></span>
                            <?php else: ?>
                                <?php echo fmt_m($credito_aprobado); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- ── TABLA PRINCIPAL DE MOVIMIENTOS ── -->
                <div class="mat-table-wrap">
                    <table class="mat-table" id="mat-main-table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="width:44px;">Nº DE<br>REGISTRO</th>
                                <th rowspan="2" style="width:62px;">FECHA</th>
                                <th rowspan="2" style="min-width:190px;text-align:left;padding-left:6px;">DETALLE</th>
                                <th rowspan="2" style="width:92px;">CRÉDITO<br>PRESUPUESTARIO<br>ACTUALIZADO</th>
                                <th rowspan="2" style="width:82px;">COMPROMISOS</th>
                                <th rowspan="2" style="width:92px;">SALDO PARA<br>COMPROMETER DEL<br>CRÉDITO
                                    PRESUPUESTARIO</th>
                                <th colspan="2" class="th-grupo">GASTOS CAUSADOS</th>
                                <th colspan="2" class="th-grupo">PAGOS</th>
                            </tr>
                            <tr>
                                <th style="width:44px;">Nº DE<br>REGISTRO</th>
                                <th style="width:82px;">MONTO</th>
                                <th style="width:44px;">Nº DE<br>REGISTRO</th>
                                <th style="width:82px;">MONTO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- ASIGNACION INICIAL -->
                            <tr class="row-asignacion">
                                <td class="cen">&nbsp;</td>
                                <td class="cen">&nbsp;</td>
                                <td style="padding-left:6px;"><strong>ASIGNACION INICIAL</strong></td>
                                <td class="num cell-cred-act" id="cred-act-asignacion">
                                    <?php echo fmt_m($credito_actualizado); ?>
                                </td>
                                <td class="num">&nbsp;</td>
                                <td class="num cell-saldo" id="saldo-asignacion">
                                    <?php echo fmt_m($credito_actualizado); ?>
                                </td>
                                <td class="cen">&nbsp;</td>
                                <td class="num">-</td>
                                <td class="cen">&nbsp;</td>
                                <td class="num">-</td>
                            </tr>

                            <?php
                            $saldo_corriente = $credito_actualizado;
                            $comp_hasta_aqui = 0.0;
                            $dismin_hasta_aqui = 0.0;
                            $nr_auto = 0;

                            if (empty($ops_mes) && empty($traspasos_externos)):
                                ?>
                                <tr class="row-no-data">
                                    <td colspan="10" style="padding:8px 12px; text-align:left;">
                                        <span style="color:#64748b; font-size:11px; margin-right:12px;">Sin movimientos de
                                            Órdenes de Pago para este período.</span>
                                        <button type="button" class="btn-asignar-traspaso-inline"
                                            onclick="abrirTraspasoDesdeMatriz()"
                                            title="Asignar o registrar un traspaso a esta partida">
                                            ⇄ Asignar Traspaso
                                        </button>
                                    </td>
                                </tr>
                                <?php
                            else:
                                if (!empty($ops_mes)):
                                    foreach ($ops_mes as $op):
                                        $oid = (int) $op['id'];
                                        $monto_op = isset($op_montos[$oid]) ? $op_montos[$oid] : 0.0;
                                        $monto_pago = $monto_op;
                                        /* Para destino-tipo usamos aumento_map; para origen/normal usamos dismin_map */
                                        $op_tr_tipo_r = isset($op_traspaso_tipo[$oid]) ? $op_traspaso_tipo[$oid] : null;
                                        $dismin_op = 0.0;
                                        if ($op_tr_tipo_r === 'destino') {
                                            $dismin_op = 0.0;
                                        } else {
                                            $dismin_op = isset($dismin_map[$oid]) ? $dismin_map[$oid] : 0.0;
                                            $dismin_hasta_aqui += $dismin_op;
                                        }
                                        $comp_hasta_aqui += $monto_op;
                                        $credito_act_hasta_aqui = $credito_actualizado - $dismin_hasta_aqui;
                                        $saldo_corriente = $credito_act_hasta_aqui - $comp_hasta_aqui;
                                        $nr_auto++;

                                        $nr_guardado = isset($nr_map[$oid]) ? $nr_map[$oid] : '';
                                        $nr_display = ($nr_guardado !== '') ? $nr_guardado : $nr_auto;
                                        $fecha_fmt = date('n/j/Y', strtotime($op['fecha']));

                                        $det_default = get_op_default_detalle($op);
                                        $det_custom = isset($det_map[$oid]) ? $det_map[$oid] : '';
                                        /* Si no hay detalle custom pero el OP es un traspaso, usar el texto del traspaso */
                                        if (($det_custom === null || $det_custom === '') && isset($traspaso_texto_map[$oid])) {
                                            $det_custom = $traspaso_texto_map[$oid];
                                        }
                                        $det_final = ($det_custom !== null && $det_custom !== '') ? $det_custom : $det_default;

                                        $num_op_clean = ltrim(isset($op['numero']) ? $op['numero'] : '', '0');
                                        if (empty($num_op_clean))
                                            $num_op_clean = isset($op['numero']) ? $op['numero'] : '0';
                                        $num_op_fmt = sprintf('%03d', (int) $num_op_clean);
                                        if ((int) $num_op_clean == 0)
                                            $num_op_fmt = isset($op['numero']) ? $op['numero'] : '0';

                                        $old_generic_os = 'O/P Nº ' . $num_op_fmt . ' OS';
                                        $old_generic_oc = 'O/P Nº ' . $num_op_fmt . ' OC';
                                        $old_generic_doc = 'O/P Nº ' . $num_op_fmt . ' ' . strtoupper(trim(isset($op['doc_tipo']) ? $op['doc_tipo'] : ''));
                                        if (
                                            $det_custom === $old_generic_os || $det_custom === $old_generic_oc ||
                                            ($det_custom === $old_generic_doc && in_array(strtoupper(trim(isset($op['doc_tipo']) ? $op['doc_tipo'] : '')), array('OS', 'OC', 'ORDEN DE COMPRA', 'ORDEN DE SERVICIO')))
                                        ) {
                                            $det_final = $det_default;
                                        }

                                        $det_final = normalizar_texto_traspaso($det_final);

                                        $cancel_custom = isset($det_cancel_map[$oid]) ? $det_cancel_map[$oid] : '';
                                        $cancel_final = ($cancel_custom !== null && $cancel_custom !== '') ? $cancel_custom : '';
                                        ?>
                                        <!-- Fila compromiso -->
                                        <tr class="row-op" data-op-id="<?php echo $oid; ?>"
                                            data-comp-hasta-aqui="<?php echo $comp_hasta_aqui; ?>">
                                            <td class="cen">
                                                <?php if ($es_admin): ?>
                                                    <input type="text" class="nr-input" id="nr-<?php echo $oid; ?>"
                                                        value="<?php echo htmlspecialchars($nr_guardado); ?>"
                                                        placeholder="<?php echo $nr_auto; ?>" data-op-id="<?php echo $oid; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        title="Número de registro (editable, se guarda automáticamente)">
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($nr_display); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="cen"><?php echo $fecha_fmt; ?></td>
                                            <td style="padding-left:4px; padding-right:4px;">
                                                <?php if ($es_admin): ?>
                                                    <input type="text" class="detalle-input" id="det-<?php echo $oid; ?>"
                                                        value="<?php echo htmlspecialchars($det_final); ?>"
                                                        placeholder="<?php echo htmlspecialchars($det_default); ?>"
                                                        data-op-id="<?php echo $oid; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        title="Detalle de la O/P (editable, se guarda automáticamente)">
                                                    <span class="print-det-val"><?php echo htmlspecialchars($det_final); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($det_final); ?>
                                                <?php endif; ?>
                                            </td>
                                            <!-- CRÉDITO PRESUPUESTARIO ACTUALIZADO: campo de disminución/aumento por OP -->
                                            <?php
                                            $op_tr_tipo = isset($op_traspaso_tipo[$oid]) ? $op_traspaso_tipo[$oid] : null;
                                            $campo_cred_val = ($op_tr_tipo === 'destino')
                                                ? (isset($aumento_map[$oid]) ? $aumento_map[$oid] : 0.0)
                                                : $dismin_op;
                                            ?>
                                            <td class="num"
                                                style="<?php echo ($op_tr_tipo === 'destino') ? 'background:#f0fdf4;' : ''; ?>">
                                                <?php if ($es_admin): ?>
                                                    <input type="text"
                                                        class="dismin-input<?php echo ($op_tr_tipo === 'destino') ? ' aumento-input' : ''; ?>"
                                                        id="dismin-<?php echo $oid; ?>"
                                                        value="<?php echo ($campo_cred_val > 0) ? fmt_m($campo_cred_val) : ''; ?>"
                                                        placeholder="0.00" data-op-id="<?php echo $oid; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        data-cred-aprobado="<?php echo $credito_base_raw; ?>"
                                                        data-traspaso-tipo="<?php echo htmlspecialchars($op_tr_tipo ?? ''); ?>"
                                                        title="<?php echo ($op_tr_tipo === 'destino') ? 'Aumento del Crédito Presupuestario — Partida DESTINO de traspaso' : 'Disminución del Crédito Presupuestario (editable — se guarda y refleja en el reporte mensual)'; ?>">
                                                    <span
                                                        class="print-mat-val"><?php echo ($campo_cred_val > 0) ? fmt_m($campo_cred_val) : '-'; ?></span>
                                                <?php else: ?>
                                                    <?php echo ($campo_cred_val > 0) ? fmt_m($campo_cred_val) : '-'; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num">
                                                <?php if ($es_admin): ?>
                                                    <input type="text" class="mat-num-input comp-monto-input"
                                                        id="comp-monto-<?php echo $oid; ?>" value="<?php echo fmt_m($monto_op); ?>"
                                                        data-op-id="<?php echo $oid; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        title="Monto de Compromisos (editable, se guarda automáticamente)">
                                                    <span class="print-mat-val"><?php echo fmt_m($monto_op); ?></span>
                                                <?php else: ?>
                                                    <?php echo fmt_m($monto_op); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num cell-saldo" data-saldo-op-id="<?php echo $oid; ?>">
                                                <?php echo fmt_m($saldo_corriente); ?>
                                            </td>
                                            <td class="cen nr-gc-<?php echo $oid; ?>"><?php echo htmlspecialchars($nr_display); ?>
                                            </td>
                                            <td class="num">
                                                <?php
                                                if ($es_partida_iva && isset($op_gc_iva[$oid])):
                                                    $gc_display = $op_gc_iva[$oid];
                                                    $tipo_iva_op = isset($op_tipo_iva[$oid]) ? $op_tipo_iva[$oid] : 'privado';
                                                    ?>
                                                    <?php if ($es_admin && $tipo_iva_op === 'seniat'): ?>
                                                        <input type="text" class="mat-num-input pago-monto-input"
                                                            id="pago-monto-<?php echo $oid; ?>" value="<?php echo fmt_m($gc_display); ?>"
                                                            placeholder="<?php echo fmt_m($retencion_pendiente_mes_ant); ?>"
                                                            data-op-id="<?php echo $oid; ?>"
                                                            data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                            data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                            title="Gastos Causados SENIAT — enteramiento (editable, pre-llenado con retenci&#243;n pendiente del mes anterior)">
                                                        <span class="print-mat-val"><?php echo fmt_m($gc_display); ?></span>
                                                    <?php else: ?>
                                                        <span
                                                            class="gc-monto-display-<?php echo $oid; ?>"><?php echo fmt_m($gc_display); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span
                                                        class="gc-monto-display-<?php echo $oid; ?>"><?php echo fmt_m($monto_op); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="cen">&nbsp;</td>
                                            <td class="num"><?php echo $es_partida_iva ? fmt_m($monto_op) : '-'; ?></td>
                                        </tr>
                                        <?php
                                        $is_traspaso_mov = ($op_tr_tipo !== null || stripos($det_final, 'TRASPASO') === 0);
                                        if (!$is_traspaso_mov):
                                            ?>
                                            <!-- Fila cancelación / pago -->
                                            <tr class="row-cancel" data-comp-hasta-aqui="<?php echo $comp_hasta_aqui; ?>">
                                                <td class="cen">&nbsp;</td>
                                                <td class="cen">&nbsp;</td>
                                                <td style="padding-left:4px; padding-right:4px;">
                                                    <?php if ($es_admin): ?>
                                                        <input type="text" class="mat-det-input cancel-det-input"
                                                            id="cancel-det-<?php echo $oid; ?>"
                                                            value="<?php echo htmlspecialchars($cancel_final); ?>" placeholder=""
                                                            data-op-id="<?php echo $oid; ?>"
                                                            data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                            data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                            title="Detalle de cancelación (editable, se guarda automáticamente)">
                                                        <span class="print-mat-val"><?php echo htmlspecialchars($cancel_final); ?></span>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars($cancel_final); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="num">&nbsp;</td>
                                                <td class="num">&nbsp;</td>
                                                <td class="num cell-saldo"><?php echo fmt_m($saldo_corriente); ?></td>
                                                <td class="cen">&nbsp;</td>
                                                <td class="num">&nbsp;</td>
                                                <td class="cen nr-pago-<?php echo $oid; ?>"><?php echo htmlspecialchars($nr_display); ?>
                                                </td>
                                                <td class="num">
                                                    <?php if ($es_admin): ?>
                                                        <input type="text" class="mat-num-input pago-monto-input"
                                                            id="pago-monto-<?php echo $oid; ?>"
                                                            value="<?php echo ($monto_pago > 0 ? fmt_m($monto_pago) : ''); ?>"
                                                            placeholder="0.00" data-op-id="<?php echo $oid; ?>"
                                                            data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                            data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                            title="Monto de Pago (editable, se guarda automáticamente)">
                                                        <span class="print-mat-val"><?php echo fmt_m($monto_pago); ?></span>
                                                    <?php else: ?>
                                                        <?php echo fmt_m($monto_pago); ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <!-- Fila saldo intermedio -->
                                            <tr class="row-saldo-int" data-comp-hasta-aqui="<?php echo $comp_hasta_aqui; ?>">
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                                <td class="num cell-saldo"><?php echo fmt_m($saldo_corriente); ?></td>
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                                <td>&nbsp;</td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php /* ── FILAS DE TRASPASO EXTERNAS (de otras partidas que involucran esta) ── */
                                if (!empty($traspasos_externos)):
                                    foreach ($traspasos_externos as $ext_tr):
                                        $ext_op_id = (int) $ext_tr['op_id'];
                                        $ext_tipo = $ext_tr['tipo'];
                                        $ext_monto = (float) $ext_tr['monto'];
                                        $ext_texto = normalizar_texto_traspaso($ext_tr['texto_completo']);
                                        $ext_fecha = date('n/j/Y', strtotime($ext_tr['fecha']));
                                        $ext_num_fmt = sprintf('%03d', (int) ltrim($ext_tr['numero'] ?? '0', '0'));

                                        if ($ext_tipo === 'destino') {
                                            $ext_dismin = isset($aumento_map[$ext_op_id]) ? $aumento_map[$ext_op_id] : $ext_monto;
                                        } else {
                                            $ext_dismin = isset($dismin_map[$ext_op_id]) ? $dismin_map[$ext_op_id] : $ext_monto;
                                        }
                                        $ext_label = ($ext_tipo === 'destino') ? 'Aumento del Crédito Presupuestario — Partida DESTINO del traspaso' : 'Disminución del Crédito Presupuestario — Partida ORIGEN del traspaso';

                                        $nr_auto++;
                                        $nr_guardado = isset($nr_map[$ext_op_id]) ? $nr_map[$ext_op_id] : '';
                                        $nr_display = ($nr_guardado !== '') ? $nr_guardado : $nr_auto;
                                        ?>
                                        <tr class="row-op row-traspaso-ext" data-op-id="<?php echo $ext_op_id; ?>">
                                            <td class="cen">
                                                <?php if ($es_admin): ?>
                                                    <input type="text" class="nr-input" id="nr-<?php echo $ext_op_id; ?>"
                                                        value="<?php echo htmlspecialchars($nr_guardado); ?>"
                                                        placeholder="<?php echo $nr_auto; ?>" data-op-id="<?php echo $ext_op_id; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        title="Número de registro (editable, se guarda automáticamente)">
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($nr_display); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="cen"><?php echo $ext_fecha; ?></td>
                                            <td style="padding-left:4px; padding-right:4px;">
                                                <?php if ($es_admin): ?>
                                                    <input type="text" class="detalle-input" id="det-<?php echo $ext_op_id; ?>"
                                                        value="<?php echo htmlspecialchars($ext_texto); ?>"
                                                        data-op-id="<?php echo $ext_op_id; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        title="Texto del traspaso (editable, se guarda automáticamente)">
                                                    <span class="print-det-val"><?php echo htmlspecialchars($ext_texto); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($ext_texto); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num"
                                                style="<?php echo ($ext_tipo === 'destino') ? 'background:#f0fdf4;' : ''; ?>">
                                                <?php if ($es_admin): ?>
                                                    <input type="text"
                                                        class="dismin-input<?php echo ($ext_tipo === 'destino') ? ' aumento-input' : ''; ?>"
                                                        id="dismin-<?php echo $ext_op_id; ?>"
                                                        value="<?php echo ($ext_dismin > 0) ? fmt_m($ext_dismin) : ''; ?>"
                                                        placeholder="0.00" data-op-id="<?php echo $ext_op_id; ?>"
                                                        data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                                        data-mes="<?php echo $mes_sel; ?>" data-anio="<?php echo $anio_sel; ?>"
                                                        data-cred-aprobado="<?php echo $credito_base_raw; ?>"
                                                        data-traspaso-tipo="<?php echo $ext_tipo; ?>" title="<?php echo $ext_label; ?>">
                                                    <span
                                                        class="print-mat-val"><?php echo ($ext_dismin > 0) ? fmt_m($ext_dismin) : '-'; ?></span>
                                                <?php else: ?>
                                                    <?php echo ($ext_dismin > 0) ? fmt_m($ext_dismin) : '-'; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num">0.00</td>
                                            <td class="num cell-saldo" data-saldo-op-id="<?php echo $ext_op_id; ?>">
                                                <?php echo fmt_m($saldo_corriente); ?>
                                            </td>
                                            <td class="cen nr-gc-<?php echo $ext_op_id; ?>">
                                                <?php echo htmlspecialchars($nr_display); ?>
                                            </td>
                                            <td class="num">0.00</td>
                                            <td class="cen">&nbsp;</td>
                                            <td class="num">-</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- TOTAL MES -->
                            <tr class="row-total-mes" data-total-comp-mes="<?php echo $total_comp_mes; ?>"
                                data-total-dismin-mes="<?php echo $total_dismin_mes; ?>"
                                data-cred-base="<?php echo $credito_base_raw; ?>">
                                <td class="cen">&nbsp;</td>
                                <td class="cen">&nbsp;</td>
                                <td style="padding-left:6px;"><strong>TOTAL
                                        <?php echo strtoupper($nombre_mes); ?></strong></td>
                                <td class="num cell-cred-act" id="cred-act-total-mes">
                                    <?php echo fmt_m($credito_actualizado); ?>
                                </td>
                                <td class="num"><?php echo fmt_m($total_comp_mes); ?></td>
                                <td class="num cell-saldo" id="saldo-total-mes">
                                    <?php echo fmt_m($credito_actualizado - $total_comp_mes); ?>
                                </td>
                                <td class="cen">&nbsp;</td>
                                <td class="num">
                                    <?php echo $es_partida_iva ? fmt_m($total_gc_iva_mes) : fmt_m($total_comp_mes); ?>
                                </td>
                                <td class="cen">&nbsp;</td>
                                <td class="num">
                                    <?php echo $es_partida_iva ? fmt_m($total_pago_iva_mes) : fmt_m($total_pagos_mes); ?>
                                </td>
                            </tr>

                            <!-- ACUMULADO -->
                            <tr class="row-acumulado" data-comp-acumulado="<?php echo $comp_acumulado; ?>"
                                data-cred-base="<?php echo $credito_base_raw; ?>"
                                data-total-dismin-mes="<?php echo $total_dismin_mes; ?>">
                                <td class="cen">&nbsp;</td>
                                <td class="cen">&nbsp;</td>
                                <td style="padding-left:6px;"><strong>ACUMULADO</strong></td>
                                <td class="num cell-cred-act" id="cred-act-acumulado">
                                    <?php echo fmt_m($credito_actualizado); ?>
                                </td>
                                <td class="num"><?php echo fmt_m($comp_acumulado); ?></td>
                                <td class="num cell-saldo" id="saldo-acumulado">
                                    <?php echo fmt_m($credito_actualizado - $comp_acumulado); ?>
                                </td>
                                <td class="cen">&nbsp;</td>
                                <td class="num">
                                    <?php echo $es_partida_iva ? fmt_m($gc_acumulado_iva) : fmt_m($comp_acumulado); ?>
                                </td>
                                <td class="cen">&nbsp;</td>
                                <td class="num">
                                    <?php echo $es_partida_iva ? fmt_m($pago_acumulado_iva) : fmt_m($pago_acumulado); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- fin mat-table-wrap -->

            </div>
            <!-- fin doc-wrapper -->

            <?php if ($es_partida_iva): ?>
                <?php
                /* Retención pendiente al cierre del mes seleccionado */
                $retencion_calc = max(0.0, $comp_acumulado - $gc_acumulado_iva);
                $cod_esc_ret = mysqli_real_escape_string($conn, $cod_sel);
                $res_ret_ovr = mysqli_query(
                    $conn,
                    "SELECT monto_custom FROM matriz_retencion_overrides
                     WHERE codificacion = '$cod_esc_ret' AND mes = $mes_sel AND anio = $anio_sel LIMIT 1"
                );
                $ret_ovr_row = ($res_ret_ovr) ? mysqli_fetch_assoc($res_ret_ovr) : null;
                $retencion_custom_override = ($ret_ovr_row && $ret_ovr_row['monto_custom'] !== null) ? (float) $ret_ovr_row['monto_custom'] : null;
                $retencion_pendiente_actual = ($retencion_custom_override !== null) ? $retencion_custom_override : $retencion_calc;

                $mes_sig_num = ($mes_sel < 12) ? ($mes_sel + 1) : 1;
                $anio_sig = ($mes_sel < 12) ? $anio_sel : ($anio_sel + 1);
                $meses_es_iva = array(
                    1 => 'Enero',
                    2 => 'Febrero',
                    3 => 'Marzo',
                    4 => 'Abril',
                    5 => 'Mayo',
                    6 => 'Junio',
                    7 => 'Julio',
                    8 => 'Agosto',
                    9 => 'Septiembre',
                    10 => 'Octubre',
                    11 => 'Noviembre',
                    12 => 'Diciembre'
                );
                $nombre_mes_sig = (isset($meses_es_iva[$mes_sig_num]) ? $meses_es_iva[$mes_sig_num] : $mes_sig_num) . ' ' . $anio_sig;
                ?>
                <div class="iva-retencion-box no-print" id="iva-retencion-box" style="
                    max-width: 1380px;
                    margin: 8px 0 14px 0;
                    background: #f1f5f9;
                    border: 1px solid #cbd5e1;
                    border-left: 4px solid #0f172a;
                    border-radius: 8px;
                    padding: 8px 16px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 16px;
                    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.06);
                    flex-wrap: wrap;
                ">
                    <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 240px;">
                        <div style="
                            width: 28px; height: 28px; border-radius: 6px;
                            background: #ffffff; border: 1px solid #cbd5e1;
                            display: flex; align-items: center; justify-content: center;
                            color: #0f172a; flex-shrink: 0;
                        ">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                        </div>
                        <div>
                            <div style="font-size: 11px; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span>Retenci&#243;n Pendiente por Enterar al SENIAT</span>
                                <span style="font-size: 10px; font-weight: 600; color: #475569; background: #ffffff; border: 1px solid #cbd5e1; padding: 1px 6px; border-radius: 4px; text-transform: none;">
                                    en <?php echo htmlspecialchars($nombre_mes_sig); ?>
                                </span>
                            </div>
                            <div style="font-size: 10px; color: #64748b; margin-top: 1px;">
                                F&#243;rmula: Comprometido Acumulado (<?php echo fmt_m($comp_acumulado); ?>) &minus; Causado Acumulado (<?php echo fmt_m($gc_acumulado_iva); ?>)
                                <span id="badge-retencion-manual" style="<?php echo ($retencion_custom_override !== null) ? 'display:inline-block;' : 'display:none;'; ?> margin-left: 6px; color: #0f172a; font-weight: 600; font-size: 9.5px; background: #ffffff; border: 1px solid #cbd5e1; padding: 0 5px; border-radius: 3px;">
                                    Modificado manualmente (Auto: <?php echo fmt_m($retencion_calc); ?>)
                                </span>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="text-align: right;">
                            <span style="font-size: 9px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Monto</span>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="font-size: 12px; font-weight: 700; color: #0f172a;">Bs.</span>
                                <?php if ($es_admin): ?>
                                    <input type="text"
                                           id="retencion-seniat-custom-input"
                                           class="mat-num-input"
                                           value="<?php echo fmt_m($retencion_pendiente_actual); ?>"
                                           placeholder="<?php echo fmt_m($retencion_calc); ?>"
                                           data-cod="<?php echo htmlspecialchars($cod_sel); ?>"
                                           data-mes="<?php echo $mes_sel; ?>"
                                           data-anio="<?php echo $anio_sel; ?>"
                                           data-calc-val="<?php echo fmt_m($retencion_calc); ?>"
                                           title="Monto editable. Haz clic para modificar. Presiona Enter o sal del campo para guardar."
                                           style="
                                               font-size: 14px;
                                               font-weight: 800;
                                               color: #0f172a;
                                               width: 140px;
                                               text-align: right;
                                               padding: 3px 8px;
                                               border: 1px solid #cbd5e1;
                                               border-radius: 6px;
                                               background: #ffffff;
                                               font-variant-numeric: tabular-nums;
                                           ">
                                    <button type="button"
                                            id="btn-reset-retencion"
                                            onclick="resetRetencionCalculada()"
                                            title="Restaurar c&#225;lculo autom&#225;tico (<?php echo fmt_m($retencion_calc); ?>)"
                                            style="
                                                background: #ffffff;
                                                border: 1px solid #cbd5e1;
                                                border-radius: 6px;
                                                color: #475569;
                                                padding: 4px 8px;
                                                cursor: pointer;
                                                font-size: 11px;
                                                display: flex;
                                                align-items: center;
                                                gap: 4px;
                                                height: 27px;
                                                transition: background 0.15s, border-color 0.15s;
                                            "
                                            onmouseover="this.style.background='#f1f5f9';this.style.borderColor='#94a3b8';"
                                            onmouseout="this.style.background='#f8fafc';this.style.borderColor='#cbd5e1';">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="1 4 1 10 7 10"></polyline>
                                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                        </svg>
                                        <span style="font-size: 10px; font-weight: 600;">Auto</span>
                                    </button>
                                <?php else: ?>
                                    <span style="font-size: 15px; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums;">
                                        <?php echo fmt_m($retencion_pendiente_actual); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════════════════════
                 SELECTOR DE PARTIDA / MES / AÑO
                 ══════════════════════════════════════════════════════════ -->
            <?php render_selector_card($catalogo, $cod_sel, $partida_info, $mes_sel, $meses_es, $anio_sel); ?>

        </main>
        <!-- fin main-content -->
    </div>
    <!-- fin layout -->

    <!-- ═══ FOOTER ════════════════════════════════════════════════════════ -->
    <footer class="site-footer no-print">
        <strong>Contraloría del Municipio Simón Rodríguez</strong> &mdash;
        Sistema de Gestión Presupuestaria &copy; <?php echo date('Y'); ?>
    </footer>

    <!-- ═══ SCRIPT: auto-guardado del número de registro (admin) ════════ -->
    <?php if ($es_admin): ?>
        <script>
            (function () {
                var inputs = document.querySelectorAll('.nr-input');
                var saveTimer = {};

                function saveNR(input) {
                    var opId = input.getAttribute('data-op-id');
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var nr = input.value.trim();
                    var disp = nr !== '' ? nr : input.getAttribute('placeholder');

                    input.classList.add('saving');
                    input.classList.remove('saved');

                    /* Actualizar celdas de Gastos Causados Nº y Pagos Nº en tiempo real */
                    var gcCells = document.querySelectorAll('.nr-gc-' + opId);
                    var pagoCells = document.querySelectorAll('.nr-pago-' + opId);
                    for (var i = 0; i < gcCells.length; i++) gcCells[i].textContent = disp;
                    for (var i = 0; i < pagoCells.length; i++) pagoCells[i].textContent = disp;

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            input.classList.remove('saving');
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.ok) {
                                    input.classList.add('saved');
                                    setTimeout(function () { input.classList.remove('saved'); }, 1800);
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send(
                        'action=save_nr'
                        + '&op_id=' + encodeURIComponent(opId)
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&nr=' + encodeURIComponent(nr)
                    );
                }

                for (var i = 0; i < inputs.length; i++) {
                    (function (inp) {
                        inp.addEventListener('input', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(saveTimer[id]);
                            saveTimer[id] = setTimeout(function () { saveNR(inp); }, 800);
                        });
                        inp.addEventListener('blur', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(saveTimer[id]);
                            saveNR(inp);
                        });
                    })(inputs[i]);
                }

                /* ── Auto-guardado del detalle de la O/P ──────────────────────────── */
                var detInputs = document.querySelectorAll('.detalle-input');
                var detSaveTimer = {};

                function saveDetalle(input) {
                    var opId = input.getAttribute('data-op-id');
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var det = input.value.trim();

                    input.classList.add('saving');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            input.classList.remove('saving');
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.ok) {
                                    input.classList.add('saved');
                                    var printSpan = input.parentNode.querySelector('.print-det-val');
                                    if (printSpan) printSpan.textContent = det;
                                    setTimeout(function () { input.classList.remove('saved'); }, 1800);
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send(
                        'action=save_detalle'
                        + '&op_id=' + encodeURIComponent(opId)
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&det=' + encodeURIComponent(det)
                    );
                }

                for (var j = 0; j < detInputs.length; j++) {
                    (function (inp) {
                        inp.addEventListener('input', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(detSaveTimer[id]);
                            detSaveTimer[id] = setTimeout(function () { saveDetalle(inp); }, 800);
                        });
                        inp.addEventListener('blur', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(detSaveTimer[id]);
                            saveDetalle(inp);
                        });
                    })(detInputs[j]);
                }
                /* ── Auto-guardado de Monto de Compromisos ────────────────────────── */
                var compInputs = document.querySelectorAll('.comp-monto-input');
                var compSaveTimer = {};

                function saveMontoComp(input) {
                    var opId = input.getAttribute('data-op-id');
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var valor = input.value.trim();

                    input.classList.add('saving');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            input.classList.remove('saving');
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.ok) {
                                    input.classList.add('saved');
                                    var printSpan = input.parentNode.querySelector('.print-mat-val');
                                    if (printSpan) printSpan.textContent = valor;
                                    var gcDisplay = document.querySelector('.gc-monto-display-' + opId);
                                    if (gcDisplay) gcDisplay.textContent = valor;
                                    setTimeout(function () { location.reload(); }, 400);
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send(
                        'action=save_monto_comp'
                        + '&op_id=' + encodeURIComponent(opId)
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&valor=' + encodeURIComponent(valor)
                    );
                }

                for (var k = 0; k < compInputs.length; k++) {
                    (function (inp) {
                        inp.addEventListener('blur', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(compSaveTimer[id]);
                            saveMontoComp(inp);
                        });
                        inp.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                inp.blur();
                            }
                        });
                    })(compInputs[k]);
                }

                /* ── Auto-guardado de Monto de Pago ───────────────────────────────── */
                var pagoInputs = document.querySelectorAll('.pago-monto-input');
                var pagoSaveTimer = {};

                function saveMontoPago(input) {
                    var opId = input.getAttribute('data-op-id');
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var valor = input.value.trim();

                    input.classList.add('saving');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            input.classList.remove('saving');
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.ok) {
                                    input.classList.add('saved');
                                    var printSpan = input.parentNode.querySelector('.print-mat-val');
                                    if (printSpan) printSpan.textContent = valor;
                                    setTimeout(function () { location.reload(); }, 400);
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send(
                        'action=save_monto_pago'
                        + '&op_id=' + encodeURIComponent(opId)
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&valor=' + encodeURIComponent(valor)
                    );
                }

                for (var m = 0; m < pagoInputs.length; m++) {
                    (function (inp) {
                        inp.addEventListener('blur', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(pagoSaveTimer[id]);
                            saveMontoPago(inp);
                        });
                        inp.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                inp.blur();
                            }
                        });
                    })(pagoInputs[m]);
                }

                /* ── Auto-guardado de Detalle de Cancelación ─────────────────────── */
                var cancelInputs = document.querySelectorAll('.cancel-det-input');
                var cancelSaveTimer = {};

                function saveDetalleCancel(input) {
                    var opId = input.getAttribute('data-op-id');
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var det = input.value.trim();

                    input.classList.add('saving');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            input.classList.remove('saving');
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.ok) {
                                    input.classList.add('saved');
                                    var printSpan = input.parentNode.querySelector('.print-mat-val');
                                    if (printSpan) printSpan.textContent = det;
                                    setTimeout(function () { input.classList.remove('saved'); }, 1800);
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send(
                        'action=save_detalle_cancel'
                        + '&op_id=' + encodeURIComponent(opId)
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&det=' + encodeURIComponent(det)
                    );
                }

                for (var n = 0; n < cancelInputs.length; n++) {
                    (function (inp) {
                        inp.addEventListener('input', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(cancelSaveTimer[id]);
                            cancelSaveTimer[id] = setTimeout(function () { saveDetalleCancel(inp); }, 800);
                        });
                        inp.addEventListener('blur', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(cancelSaveTimer[id]);
                            saveDetalleCancel(inp);
                        });
                    })(cancelInputs[n]);
                }

                /* ── Auto-guardado de Disminución (CRÉDITO PRESUPUESTARIO ACTUALIZADO) ── */
                var disminInputs = document.querySelectorAll('.dismin-input');
                var disminSaveTimer = {};

                function saveDisminucion(input) {
                    var opId = input.getAttribute('data-op-id');
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var rawVal = input.value.replace(/,/g, '').trim();
                    var valor = (rawVal === '') ? '0' : rawVal;
                    var trTipo = input.getAttribute('data-traspaso-tipo') || '';

                    input.classList.add('saving');
                    input.classList.remove('saved');

                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState !== 4) return;
                        input.classList.remove('saving');
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.ok) {
                                input.classList.add('saved');
                                /* Visual feedback diferente para aumentos */
                                if (resp.tipo === 'destino') {
                                    input.style.color = '#15803d';
                                    input.style.fontWeight = 'bold';
                                    input.title = 'Aumento del Crédito Presupuestario — guardado ✓';
                                }
                                setTimeout(function () {
                                    input.classList.remove('saved');
                                    if (resp.tipo === 'destino') {
                                        input.style.color = '';
                                        input.style.fontWeight = '';
                                    }
                                }, 2000);
                                recalcularSaldosConDismin();
                            }
                        } catch (e) { }
                    };
                    xhr.send(
                        'action=save_disminucion'
                        + '&op_id=' + encodeURIComponent(opId)
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&valor=' + encodeURIComponent(valor)
                    );
                }

                function recalcularSaldosConDismin() {
                    /* Leer crédito aprobado base */
                    var firstDismin = document.querySelector('.dismin-input');
                    var credBase = firstDismin ? parseFloat(firstDismin.getAttribute('data-cred-aprobado') || '0') : 0;

                    /* Sumar disminuciones (origen) y aumentos (destino) separadamente */
                    var totalDismin = 0;
                    var totalAumento = 0;
                    var allDismin = document.querySelectorAll('.dismin-input');
                    for (var d = 0; d < allDismin.length; d++) {
                        var dv = parseFloat(allDismin[d].value.replace(/,/g, '').trim()) || 0;
                        var dTipo = allDismin[d].getAttribute('data-traspaso-tipo') || '';
                        if (dTipo === 'destino') {
                            totalAumento += dv;
                        } else {
                            totalDismin += dv;
                        }
                    }

                    /* credAct = credBase + aumentos - disminuciones */
                    var credAct = credBase + totalAumento - totalDismin;

                    /* Calcular saldos acumulados por OP */
                    var compAcum = 0;
                    var disminAcum = 0;
                    var aumentoAcum = 0;
                    var opRows = document.querySelectorAll('tr.row-op');
                    for (var r = 0; r < opRows.length; r++) {
                        var row = opRows[r];
                        var oid = row.getAttribute('data-op-id');
                        /* Dismin/aumento de este OP */
                        var dInp = document.getElementById('dismin-' + oid) || document.getElementById('dismin-ext-' + oid);
                        if (dInp) {
                            var dVal = parseFloat(dInp.value.replace(/,/g, '').trim()) || 0;
                            var dTipoRow = dInp.getAttribute('data-traspaso-tipo') || '';
                            if (dTipoRow === 'destino') {
                                aumentoAcum += dVal;
                            } else {
                                disminAcum += dVal;
                            }
                        }
                        /* Compromiso de este OP */
                        var cInp = document.getElementById('comp-monto-' + oid);
                        var cVal = cInp ? (parseFloat(cInp.value.replace(/,/g, '').trim()) || 0) : 0;
                        compAcum += cVal;
                        /* Saldo = (credBase + aumentoAcum - disminAcum) - compAcum */
                        var saldoAqui = (credBase + aumentoAcum - disminAcum) - compAcum;
                        /* Actualizar cell-saldo del row-op */
                        var saldoCell = document.querySelector('td[data-saldo-op-id="' + oid + '"]');
                        if (saldoCell) saldoCell.textContent = fmtNum(saldoAqui);
                    }

                    /* Actualizar ASIGNACION INICIAL */
                    var asigCredAct = document.getElementById('cred-act-asignacion');
                    var asigSaldo = document.getElementById('saldo-asignacion');
                    if (asigCredAct) asigCredAct.textContent = fmtNum(credAct);
                    if (asigSaldo) asigSaldo.textContent = fmtNum(credAct);

                    /* Actualizar TOTAL MES */
                    var totCredAct = document.getElementById('cred-act-total-mes');
                    var totSaldo = document.getElementById('saldo-total-mes');
                    var rowTot = document.querySelector('.row-total-mes');
                    var totalCompMes = rowTot ? (parseFloat(rowTot.getAttribute('data-total-comp-mes')) || 0) : 0;
                    if (totCredAct) totCredAct.textContent = fmtNum(credAct);
                    if (totSaldo) totSaldo.textContent = fmtNum(credAct - totalCompMes);

                    /* Actualizar ACUMULADO */
                    var acumCredAct = document.getElementById('cred-act-acumulado');
                    var acumSaldo = document.getElementById('saldo-acumulado');
                    var rowAcum = document.querySelector('.row-acumulado');
                    var compAcumulado = rowAcum ? (parseFloat(rowAcum.getAttribute('data-comp-acumulado')) || 0) : 0;
                    if (acumCredAct) acumCredAct.textContent = fmtNum(credAct);
                    if (acumSaldo) acumSaldo.textContent = fmtNum(credAct - compAcumulado);
                }

                function fmtNum(v) {
                    var n = parseFloat(v);
                    if (isNaN(n)) return '0.00';
                    /* Format with comma thousands separator */
                    var parts = Math.abs(n).toFixed(2).split('.');
                    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    return (n < 0 ? '-' : '') + parts[0] + '.' + parts[1];
                }

                for (var dIdx = 0; dIdx < disminInputs.length; dIdx++) {
                    (function (inp) {
                        inp.addEventListener('input', function () {
                            recalcularSaldosConDismin(); /* actualizar live mientras escribe */
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(disminSaveTimer[id]);
                            disminSaveTimer[id] = setTimeout(function () { saveDisminucion(inp); }, 900);
                        });
                        inp.addEventListener('blur', function () {
                            var id = inp.getAttribute('data-op-id');
                            clearTimeout(disminSaveTimer[id]);
                            saveDisminucion(inp);
                        });
                        inp.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
                        });
                    })(disminInputs[dIdx]);
                }
            })();
        </script>
    <?php endif; ?>

    <?php if ($es_admin): ?>
        <script>
            /* ─── Guardar Crédito Original ──────────────────────────────────────────── */
            (function () {
                /* Ocultar print-credito-val en pantalla */
                var printVal = document.querySelector('.print-credito-val');
                if (printVal) printVal.style.display = 'none';
            })();

            
            /* ── Auto-guardado de Retención Pendiente SENIAT ── */
            (function () {
                var retInput = document.getElementById('retencion-seniat-custom-input');
                if (!retInput) return;

                function formatMoneyString(raw) {
                    if (raw === null || raw === undefined) return '';
                    var str = raw.toString().trim();
                    if (str === '') return '';
                    if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
                        str = str.replace(/,/g, '');
                    } else if (str.indexOf(',') !== -1) {
                        str = str.replace(/,/g, '.');
                    }
                    var num = parseFloat(str);
                    if (isNaN(num)) return '';
                    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                function saveRetencionCustom(input, isReset) {
                    var cod = input.getAttribute('data-cod');
                    var mes = input.getAttribute('data-mes');
                    var anio = input.getAttribute('data-anio');
                    var rawVal = isReset ? '' : input.value.trim();

                    if (!isReset && rawVal !== '') {
                        var formatted = formatMoneyString(rawVal);
                        if (formatted !== '') {
                            input.value = formatted;
                            rawVal = formatted;
                        }
                    }

                    input.classList.add('saving');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'matriz.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4) {
                            input.classList.remove('saving');
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.ok) {
                                    input.classList.add('saved');
                                    var badge = document.getElementById('badge-retencion-manual');
                                    if (isReset) {
                                        input.value = input.getAttribute('data-calc-val');
                                        if (badge) badge.style.display = 'none';
                                    } else {
                                        if (badge) badge.style.display = 'inline-block';
                                    }
                                    setTimeout(function () { input.classList.remove('saved'); }, 1500);
                                }
                            } catch (e) { }
                        }
                    };
                    xhr.send(
                        'action=save_retencion_seniat'
                        + '&cod=' + encodeURIComponent(cod)
                        + '&mes=' + encodeURIComponent(mes)
                        + '&anio=' + encodeURIComponent(anio)
                        + '&valor=' + encodeURIComponent(rawVal)
                    );
                }

                retInput.addEventListener('focus', function () {
                    setTimeout(function () { retInput.select(); }, 50);
                });

                retInput.addEventListener('blur', function () {
                    saveRetencionCustom(retInput, false);
                });

                retInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        retInput.blur();
                    }
                });

                window.resetRetencionCalculada = function () {
                    if (!confirm('¿Desea restaurar el cálculo automático de retención pendiente (' + retInput.getAttribute('data-calc-val') + ')?')) {
                        return;
                    }
                    saveRetencionCustom(retInput, true);
                };
            })();

            function guardarCredito() {
                var input = document.getElementById('credito-original-input');
                var status = document.getElementById('credito-status');
                if (!input) return;

                var rawVal = input.value.replace(/,/g, '').trim();
                var nuevo = parseFloat(rawVal);

                if (isNaN(nuevo) || nuevo < 0) {
                    input.classList.add('error');
                    status.textContent = 'Valor inválido';
                    setTimeout(function () {
                        input.classList.remove('error');
                        status.textContent = '';
                    }, 2500);
                    return;
                }

                var cod = input.getAttribute('data-cod');
                input.classList.add('saving');
                status.textContent = 'Guardando…';

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'matriz.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    input.classList.remove('saving');
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp.ok) {
                            /* Confirmar visualmente */
                            input.classList.add('saved');
                            status.textContent = '✓ Guardado';

                            /* Actualizar print-credito-val */
                            var printVal = document.querySelector('.print-credito-val');
                            if (printVal) printVal.textContent = formatMoney(resp.nuevo);

                            /* Recalcular saldo ASIGNACION INICIAL inline */
                            recalcularSaldoConCredito(resp.nuevo);

                            setTimeout(function () {
                                input.classList.remove('saved');
                                status.textContent = '';
                            }, 2500);
                        } else {
                            input.classList.add('error');
                            status.textContent = '✗ ' + (resp.msg || 'Error');
                            setTimeout(function () {
                                input.classList.remove('error');
                                status.textContent = '';
                            }, 3000);
                        }
                    } catch (e) {
                        input.classList.add('error');
                        status.textContent = '✗ Error de respuesta';
                        setTimeout(function () {
                            input.classList.remove('error');
                            status.textContent = '';
                        }, 3000);
                    }
                };
                xhr.send(
                    'action=save_credito'
                    + '&cod=' + encodeURIComponent(cod)
                    + '&valor=' + encodeURIComponent(rawVal)
                );
            }

            function formatMoney(v) {
                var n = parseFloat(v);
                if (isNaN(n)) return '0.00';
                return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function recalcularSaldoConCredito(nuevoCred) {
                /* 1. Fila ASIGNACION INICIAL */
                var rowAsig = document.querySelector('.row-asignacion');
                if (rowAsig) {
                    var credAct = rowAsig.querySelector('.cell-cred-act');
                    var saldo = rowAsig.querySelector('.cell-saldo');
                    if (credAct) credAct.textContent = formatMoney(nuevoCred);
                    if (saldo) saldo.textContent = formatMoney(nuevoCred);
                }

                /* 2. Filas de movimiento (row-op, row-cancel, row-saldo-int) */
                var movRows = document.querySelectorAll('tr[data-comp-hasta-aqui]');
                for (var i = 0; i < movRows.length; i++) {
                    var compHastaAqui = parseFloat(movRows[i].getAttribute('data-comp-hasta-aqui')) || 0;
                    var saldoCell = movRows[i].querySelector('.cell-saldo');
                    if (saldoCell) {
                        saldoCell.textContent = formatMoney(nuevoCred - compHastaAqui);
                    }
                }

                /* 3. Fila TOTAL MES */
                var rowTot = document.querySelector('.row-total-mes');
                if (rowTot) {
                    var totCompMes = parseFloat(rowTot.getAttribute('data-total-comp-mes')) || 0;
                    var credActTot = rowTot.querySelector('.cell-cred-act');
                    var saldoTot = rowTot.querySelector('.cell-saldo');
                    if (credActTot) credActTot.textContent = formatMoney(nuevoCred);
                    if (saldoTot) saldoTot.textContent = formatMoney(nuevoCred - totCompMes);
                }

                /* 4. Fila ACUMULADO */
                var rowAcum = document.querySelector('.row-acumulado');
                if (rowAcum) {
                    var compAcum = parseFloat(rowAcum.getAttribute('data-comp-acumulado')) || 0;
                    var credActAcum = rowAcum.querySelector('.cell-cred-act');
                    var saldoAcum = rowAcum.querySelector('.cell-saldo');
                    if (credActAcum) credActAcum.textContent = formatMoney(nuevoCred);
                    if (saldoAcum) saldoAcum.textContent = formatMoney(nuevoCred - compAcum);
                }
            }
        </script>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════
     MODAL ASISTENTE DE TRASPASO DE CRÉDITO PRESUPUESTARIO
     ══════════════════════════════════════════════════════════ -->
    <div id="traspaso-overlay" style="display:none;" onclick="if(event.target===this)closeTraspasoModal()"></div>
    <div id="traspaso-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="traspaso-title">
        <div class="trm-header">
            <div>
                <div class="trm-icon-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                </div>
                <span id="traspaso-title">Asistente de Traspaso de Cr&eacute;dito Presupuestario</span>
            </div>
            <button class="trm-close" onclick="closeTraspasoModal()" title="Cerrar" aria-label="Cerrar">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="trm-body">
            <!-- Fila 1: Nº Resolución + Fecha -->
            <div class="trm-row3">
                <div class="trm-field">
                    <label class="trm-label" for="trm-resolucion">N&ordm; de Resoluci&oacute;n</label>
                    <input type="text" id="trm-resolucion" class="trm-input" placeholder="ej: 016-2026"
                        oninput="updateTraspasoPreview()">
                </div>
                <div class="trm-field">
                    <label class="trm-label" for="trm-fecha">Fecha</label>
                    <input type="date" id="trm-fecha" class="trm-input" value="<?php echo sprintf('%04d-%02d-01', $anio_sel, $mes_sel); ?>"
                        oninput="updateTraspasoPreview()">
                </div>
                <div class="trm-field">
                    <label class="trm-label" for="trm-monto">Monto Traspaso (Bs.)</label>
                    <input type="text" id="trm-monto" class="trm-input" placeholder="ej: 35.000,00"
                        style="font-family:monospace;font-weight:600;" oninput="formatTraspasoMonto(this)">
                </div>
            </div>

            <!-- DE LAS PARTIDAS -->
            <div class="trm-field">
                <label class="trm-label">
                    <span>DE LAS PARTIDAS</span>
                    <span class="trm-hint">(Partidas de origen &mdash; selecci&oacute;n m&uacute;ltiple)</span>
                </label>
                <div class="trm-picker-wrap">
                    <input type="text" id="trm-search-from" class="trm-search" placeholder="Buscar partida de origen..."
                        oninput="filterTraspasoList('from')">
                    <div id="trm-list-from" class="trm-list">
                        <?php foreach ($catalogo as $ci):
                            $cod_punto = get_codigo_punto($ci['cod']);
                            ?>
                            <label class="trm-item" data-cod="<?php echo htmlspecialchars($ci['cod']); ?>"
                                data-punto="<?php echo htmlspecialchars($cod_punto); ?>">
                                <input type="checkbox" value="<?php echo htmlspecialchars($cod_punto); ?>"
                                    class="trm-chk-from" onchange="updateTraspasoPreview()">
                                <span class="trm-item-code"><?php echo htmlspecialchars($cod_punto); ?></span>
                                <span class="trm-item-denom"><?php echo htmlspecialchars($ci['denom']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="trm-tags-from" class="trm-tags"></div>
            </div>

            <!-- A LAS PARTIDAS -->
            <div class="trm-field">
                <label class="trm-label">
                    <span>A LAS PARTIDA(S)</span>
                    <span class="trm-hint">(Partidas de destino)</span>
                </label>
                <div class="trm-picker-wrap">
                    <input type="text" id="trm-search-to" class="trm-search" placeholder="Buscar partida de destino..."
                        oninput="filterTraspasoList('to')">
                    <div id="trm-list-to" class="trm-list">
                        <?php foreach ($catalogo as $ci):
                            $cod_punto = get_codigo_punto($ci['cod']);
                            ?>
                            <label class="trm-item" data-cod="<?php echo htmlspecialchars($ci['cod']); ?>"
                                data-punto="<?php echo htmlspecialchars($cod_punto); ?>">
                                <input type="checkbox" value="<?php echo htmlspecialchars($cod_punto); ?>"
                                    class="trm-chk-to" onchange="updateTraspasoPreview()">
                                <span class="trm-item-code"><?php echo htmlspecialchars($cod_punto); ?></span>
                                <span class="trm-item-denom"><?php echo htmlspecialchars($ci['denom']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="trm-tags-to" class="trm-tags"></div>
            </div>

            <!-- Preview del texto generado (Editable manualmente) -->
            <div class="trm-preview-wrap">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                    <label class="trm-label" for="trm-preview" style="margin-bottom:0;">Vista Previa del Texto Generado</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span id="trm-manual-badge" style="display:none;font-size:9.5px;font-weight:600;color:#0f172a;background:#e2e8f0;border:1px solid #cbd5e1;padding:1px 6px;border-radius:4px;">Editado manualmente</span>
                        <button type="button" onclick="regenerarTraspasoText()" title="Regenerar desde los campos del asistente" style="background:none;border:none;color:#64748b;font-size:10.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:3px;padding:2px 4px;border-radius:4px;" onmouseover="this.style.color='#0f172a';this.style.background='#e2e8f0'" onmouseout="this.style.color='#64748b';this.style.background='none'">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                            <span>Regenerar</span>
                        </button>
                    </div>
                </div>
                <textarea id="trm-preview" class="trm-preview" rows="3" placeholder="Completa los campos para ver el texto generado o escribe/edita directamente aqu&iacute;..."></textarea>
            </div>
        </div>

        <div class="trm-footer">
            <button type="button" id="trm-btn-delete" class="trm-btn-delete" style="display:none;"
                onclick="eliminarTraspasoDesdeModal()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <span>Eliminar Traspaso</span>
            </button>
            <button type="button" class="trm-btn-cancel" onclick="closeTraspasoModal()">Cancelar</button>
            <button type="button" class="trm-btn-apply" onclick="applyTraspasoText()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>Insertar en Detalle</span>
            </button>
        </div>
    </div>

    <style>
        /* ── Traspaso Modal (Cleaner, Modern, Spaced, Black Header/CTA & Gray Data Bg) ── */
        #traspaso-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .65);
            z-index: 20000;
            backdrop-filter: blur(4px);
        }

        #traspaso-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 20001;
            width: 700px;
            max-width: 95vw;
            max-height: 90vh;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #27272a;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }

        /* TOP: Keep Background Black */
        .trm-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 13px 20px;
            background: #09090b;
            border-bottom: 1px solid #27272a;
            color: #ffffff;
            flex-shrink: 0;
        }

        .trm-header>div {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: .2px;
            color: #ffffff;
        }

        .trm-icon-badge {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #18181b;
            border: 1px solid #27272a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            flex-shrink: 0;
        }

        .trm-close {
            background: #18181b;
            border: 1px solid #27272a;
            color: #a1a1aa;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
        }

        .trm-close:hover {
            background: #27272a;
            color: #ffffff;
            border-color: #3f3f46;
        }

        /* DATA BACKGROUND: Soft Clean Gray (#f8fafc) */
        .trm-body {
            padding: 18px 22px;
            overflow-y: auto;
            flex: 1;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .trm-row2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .trm-row3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1.2fr;
            gap: 12px;
        }

        .trm-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .trm-label {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: .4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .trm-hint {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            text-transform: none;
            letter-spacing: 0;
        }

        .trm-input {
            padding: 8px 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 7px;
            font-size: 12.5px;
            font-family: inherit;
            color: #0f172a;
            background: #ffffff;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .trm-input:focus {
            border-color: #09090b;
            box-shadow: 0 0 0 3px rgba(9, 9, 11, .08);
        }

        .trm-picker-wrap {
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .trm-search {
            width: 100%;
            padding: 8px 12px;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
            font-family: inherit;
            outline: none;
            background: #f1f5f9;
            color: #0f172a;
            box-sizing: border-box;
            transition: background .15s;
        }

        .trm-search:focus {
            background: #ffffff;
        }

        .trm-list {
            max-height: 135px;
            overflow-y: auto;
            padding: 4px 0;
            background: #ffffff;
        }

        .trm-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px;
            cursor: pointer;
            transition: background .1s;
            font-size: 11.5px;
            user-select: none;
        }

        .trm-item:hover {
            background: #f1f5f9;
        }

        .trm-item input[type=checkbox] {
            accent-color: #09090b;
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            cursor: pointer;
        }

        .trm-item-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }

        .trm-item-denom {
            color: #475569;
            font-size: 11px;
        }

        .trm-item.hidden {
            display: none;
        }

        .trm-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 3px;
            min-height: 0;
        }

        .trm-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            background: #e2e8f0;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .trm-tag-x {
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
            color: #64748b;
            transition: color .15s;
        }

        .trm-tag-x:hover {
            color: #ef4444;
        }

        .trm-preview-wrap {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Preview box with soft data gray background */
        .trm-preview {
            background: #f1f5f9;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 11.5px;
            color: #0f172a;
            line-height: 1.6;
            min-height: 58px;
            font-weight: 500;
            font-family: 'JetBrains Mono', monospace, sans-serif;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
            width: 100%;
            box-sizing: border-box;
            resize: vertical;
            outline: none;
            transition: border-color .15s, background .15s, box-shadow .15s;
        }

        .trm-preview:focus {
            background: #ffffff;
            border-color: #09090b;
            box-shadow: 0 0 0 3px rgba(9, 9, 11, .08);
        }

        .trm-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 13px 22px;
            border-top: 1px solid #e2e8f0;
            flex-shrink: 0;
            background: #ffffff;
        }

        .trm-btn-delete {
            padding: 8px 14px;
            border: 1.5px solid #fecaca;
            border-radius: 7px;
            background: #fef2f2;
            color: #991b1b;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
            margin-right: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .trm-btn-delete:hover {
            background: #fee2e2;
            border-color: #f87171;
            color: #7f1d1d;
        }

        .trm-btn-cancel {
            padding: 8px 18px;
            border: 1.5px solid #cbd5e1;
            border-radius: 7px;
            background: #ffffff;
            color: #334155;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }

        .trm-btn-cancel:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        /* CTA: Keep Background Black */
        .trm-btn-apply {
            padding: 8px 20px;
            border: 1px solid #27272a;
            border-radius: 7px;
            background: #09090b;
            color: #ffffff;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background .15s, box-shadow .15s, transform .1s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .trm-btn-apply:hover {
            background: #18181b;
            border-color: #3f3f46;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
        }

        .trm-btn-apply:active {
            transform: translateY(1px);
        }
    </style>

    <script>
        /* ── Asistente de Traspaso ── */
        (function () {
            var _activeDetInput = null;  /* referencia al input detalle que disparó el modal */
            var _isManualPreviewEdit = false;

            document.addEventListener('DOMContentLoaded', function () {
                var prev = document.getElementById('trm-preview');
                if (prev) {
                    prev.addEventListener('input', function () {
                        _isManualPreviewEdit = true;
                        var badge = document.getElementById('trm-manual-badge');
                        if (badge) badge.style.display = 'inline-block';
                    });
                }
            });

            window.regenerarTraspasoText = function () {
                _isManualPreviewEdit = false;
                var badge = document.getElementById('trm-manual-badge');
                if (badge) badge.style.display = 'none';
                updateTraspasoPreview(true);
            };

            /* Formatear fecha DD/MM/YYYY */
            function fmtFecha(val) {
                if (!val) return '';
                var parts = val.split('-');
                if (parts.length !== 3) return val;
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            }

            /* Leer códigos cortos de las partidas marcadas */
            function getCheckedCodes(cls) {
                var chks = document.querySelectorAll('.' + cls + ':checked');
                var codes = [];
                for (var i = 0; i < chks.length; i++) codes.push(chks[i].value);
                return codes;
            }

            window.filterTraspasoList = function (side) {
                var query = document.getElementById('trm-search-' + side).value.toLowerCase();
                var items = document.querySelectorAll('#trm-list-' + side + ' .trm-item');
                for (var i = 0; i < items.length; i++) {
                    var text = items[i].textContent.toLowerCase();
                    items[i].classList.toggle('hidden', query !== '' && text.indexOf(query) === -1);
                }
            };

            /* Actualizar tags y vista previa */
            window.updateTraspasoPreview = function (force) {
                ['from', 'to'].forEach(function (side) {
                    var cls = side === 'from' ? 'trm-chk-from' : 'trm-chk-to';
                    var chks = document.querySelectorAll('.' + cls + ':checked');
                    var tagsEl = document.getElementById('trm-tags-' + side);
                    tagsEl.innerHTML = '';
                    for (var i = 0; i < chks.length; i++) {
                        (function (chk) {
                            var tag = document.createElement('span');
                            tag.className = 'trm-tag';
                            tag.innerHTML = chk.value + ' <span class="trm-tag-x" title="Quitar">&times;</span>';
                            tag.querySelector('.trm-tag-x').onclick = function () {
                                chk.checked = false;
                                updateTraspasoPreview();
                            };
                            tagsEl.appendChild(tag);
                        })(chks[i]);
                    }
                });

                if (_isManualPreviewEdit && !force) return;

                /* Generar texto */
                var res = document.getElementById('trm-resolucion').value.trim().toUpperCase();
                var fecha = fmtFecha(document.getElementById('trm-fecha').value);
                var fromCodes = getCheckedCodes('trm-chk-from');
                var toCodes = getCheckedCodes('trm-chk-to');

                var text = 'TRASPASO DE CREDITO PRESUPUESTARIO';
                if (res) text += ' RESOLUCIÓN N° ' + res;
                if (fecha) text += ' DE FECHA ' + fecha;
                if (fromCodes.length) text += ' DE LAS PARTIDAS; ' + fromCodes.join(', ');
                if (toCodes.length) text += ' A LAS PARTIDA ' + toCodes.join(', ');

                var pEl = document.getElementById('trm-preview'); if (pEl) { pEl.value = text; }
            };

            window.formatTraspasoMonto = function (input) {
                var val = input.value.replace(/[^0-9.,]/g, '');
                input.value = val;
            };

            window.openTraspasoModal = function (detInput) {
                _activeDetInput = detInput;
                document.getElementById('traspaso-overlay').style.display = 'block';
                document.getElementById('traspaso-modal').style.display = 'flex';
                updateTraspasoPreview();
                document.getElementById('trm-resolucion').focus();
            };

            window.abrirTraspasoDesdeMatriz = function () {
                _activeDetInput = null; // Siempre crear un traspaso independiente
                var delBtn = document.getElementById('trm-btn-delete');
                if (delBtn) delBtn.style.display = 'none';
                document.getElementById('traspaso-overlay').style.display = 'block';
                document.getElementById('traspaso-modal').style.display = 'flex';
                document.getElementById('trm-resolucion').value = '';
                var mInp = document.getElementById('trm-monto');
                if (mInp) mInp.value = '';
                var chks = document.querySelectorAll('.trm-chk-from, .trm-chk-to');
                for (var i = 0; i < chks.length; i++) chks[i].checked = false;
                _isManualPreviewEdit = false;
                var mBadge = document.getElementById('trm-manual-badge');
                if (mBadge) mBadge.style.display = 'none';
                updateTraspasoPreview(true);
                document.getElementById('trm-resolucion').focus();
            };

            window.closeTraspasoModal = function () {
                document.getElementById('traspaso-overlay').style.display = 'none';
                document.getElementById('traspaso-modal').style.display = 'none';
                _activeDetInput = null;
            };

            window.applyTraspasoText = function () {
                var pEl = document.getElementById('trm-preview');
                var text = (pEl ? (pEl.value !== undefined ? pEl.value : pEl.textContent) : '').trim();
                if (!text || text === 'Completa los campos para ver el texto generado…') return;

                var resolucion = document.getElementById('trm-resolucion') ? document.getElementById('trm-resolucion').value.trim() : '';
                var fecha = document.getElementById('trm-fecha') ? document.getElementById('trm-fecha').value : '';
                var monto = document.getElementById('trm-monto') ? document.getElementById('trm-monto').value.trim() : '';
                var fromCodes = getCheckedCodes('trm-chk-from');
                var toCodes = getCheckedCodes('trm-chk-to');

                var cod = '<?php echo htmlspecialchars($cod_sel); ?>';
                var selP = document.getElementById('sel-partida');
                if (selP && selP.value) cod = selP.value;

                var mes = '<?php echo $mes_sel; ?>';
                var anio = '<?php echo $anio_sel; ?>';

                var btnApply = document.querySelector('.trm-btn-apply');
                if (btnApply) {
                    btnApply.disabled = true;
                    btnApply.innerHTML = '<span>Guardando...</span>';
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'matriz.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.ok) {
                                closeTraspasoModal();
                                var redirectMes = resp.mes || mes;
                                var redirectAnio = resp.anio || anio;
                                window.location.href = 'matriz.php?cod=' + encodeURIComponent(cod) + '&mes=' + redirectMes + '&anio=' + redirectAnio;
                                return;
                            } else {
                                alert(resp.msg || 'Error al guardar el traspaso');
                                if (btnApply) {
                                    btnApply.disabled = false;
                                    btnApply.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Insertar en Detalle</span>';
                                }
                            }
                        } catch (e) {
                            console.error('Error parseando respuesta:', e, xhr.responseText);
                            closeTraspasoModal();
                            window.location.reload();
                        }
                    }
                };
                xhr.send(
                    'action=crear_traspaso_directo'
                    + '&cod=' + encodeURIComponent(cod)
                    + '&mes=' + encodeURIComponent(mes)
                    + '&anio=' + encodeURIComponent(anio)
                    + '&texto=' + encodeURIComponent(text)
                    + '&fecha=' + encodeURIComponent(fecha)
                    + '&resolucion=' + encodeURIComponent(resolucion)
                    + '&monto=' + encodeURIComponent(monto)
                    + '&from_codes=' + encodeURIComponent(fromCodes.join(','))
                    + '&to_codes=' + encodeURIComponent(toCodes.join(','))
                    + (_activeDetInput ? ('&op_id=' + encodeURIComponent(_activeDetInput.getAttribute('data-op-id') || '')) : '')
                );
            };

            /* Escuchar "traspaso" en todos los inputs de detalle */
            document.addEventListener('DOMContentLoaded', function () {
                document.addEventListener('input', function (e) {
                    var el = e.target;
                    if (!el.classList.contains('detalle-input')) return;
                    var val = el.value.toLowerCase().trim();
                    if (val === 'traspaso' || val === 'traspaso ' || val.startsWith('traspaso ')) {
                        /* Solo abrir si aún no está abierto */
                        if (document.getElementById('traspaso-modal').style.display === 'none') {
                            openTraspasoModal(el);
                        }
                    }
                });

                /* ESC cierra el modal */
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeTraspasoModal();
                });
            });
        })();
    </script>

    <!-- ── Traspaso: campo multi-línea + botón editar ── -->
    <style>
        .det-traspaso-wrap {
            display: flex;
            flex-direction: column;
            gap: 3px;
            width: 100%;
        }

        .det-traspaso-display {
            font-size: 10px;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.5;
            padding: 4px 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            font-family: inherit;
            cursor: default;
        }

        .det-traspaso-edit-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            font-size: 9.5px;
            font-weight: 600;
            color: #1d4ed8;
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
            align-self: flex-start;
            margin-top: 1px;
            white-space: nowrap;
        }

        .det-traspaso-edit-btn:hover {
            background: #bfdbfe;
            border-color: #60a5fa;
            color: #1e3a8a;
        }

        .det-traspaso-edit-btn .trm-icon-sm {
            font-size: 11px;
        }

        .det-traspaso-delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            font-size: 9.5px;
            font-weight: 600;
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: 5px;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
            white-space: nowrap;
        }

        .det-traspaso-delete-btn:hover {
            background: #fee2e2;
            border-color: #f87171;
            color: #7f1d1d;
        }
    </style>

    <script>
        (function () {
            function normalizeTraspasoText(str) {
                if (!str) return str;
                return str.replace(/01-08-00-00-51-(\d{3})-(\d{2})-(\d{2})-(\d{2})/g, function (m, p, g, e, o) {
                    var ramo = p.substr(0, 1);
                    var sub = p.substr(1, 2);
                    return ramo + '.' + sub + '.' + g + '.' + e + '.' + o;
                });
            }

            /* ── Upgrade a detalle input: hide it, show wrapped text + edit button ── */
            function upgradeField(inp, text) {
                if (!inp || !inp.parentNode) return;
                text = normalizeTraspasoText(text);
                if (inp.value !== text) {
                    inp.value = text;
                }

                /* Don't double-wrap */
                if (inp.parentNode.classList.contains('det-traspaso-wrap')) {
                    var disp = inp.parentNode.querySelector('.det-traspaso-display');
                    if (disp) disp.textContent = text;
                    return;
                }

                var wrap = document.createElement('div');
                wrap.className = 'det-traspaso-wrap';
                inp.parentNode.insertBefore(wrap, inp);
                inp.style.display = 'none';
                wrap.appendChild(inp);

                /* Multi-line display */
                var disp = document.createElement('div');
                disp.className = 'det-traspaso-display';
                disp.textContent = text;
                wrap.appendChild(disp);

                /* Container for action buttons */
                var btnBox = document.createElement('div');
                btnBox.style.cssText = 'display:flex; gap:6px; align-items:center; margin-top:2px;';

                /* Edit button */
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'det-traspaso-edit-btn';
                btn.title = 'Editar Traspaso de Crédito Presupuestario';
                btn.innerHTML = '<span class="trm-icon-sm">⇄</span> Editar traspaso';
                btn.onclick = function () { openTraspasoModal(inp); };
                btnBox.appendChild(btn);

                /* Delete button */
                var delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.className = 'det-traspaso-delete-btn';
                delBtn.title = 'Eliminar este Traspaso de Crédito Presupuestario de esta casilla';
                delBtn.innerHTML = '<span class="trm-icon-sm">🗑</span> Eliminar';
                delBtn.onclick = function () {
                    if (confirm('¿Estás seguro de eliminar este Traspaso de Crédito Presupuestario de esta casilla?')) {
                        eliminarTraspasoDirecto(inp);
                    }
                };
                btnBox.appendChild(delBtn);

                wrap.appendChild(btnBox);
            }

            /* ── Parse existing traspaso text back into the modal fields ── */
            function parseTraspasoIntoModal(text) {
                text = normalizeTraspasoText(text);
                /* Reset all checkboxes and search fields */
                var chks = document.querySelectorAll('.trm-chk-from, .trm-chk-to');
                for (var i = 0; i < chks.length; i++) chks[i].checked = false;
                document.getElementById('trm-search-from').value = '';
                document.getElementById('trm-search-to').value = '';
                filterTraspasoList('from');
                filterTraspasoList('to');

                /* N° Resolución */
                var rM = text.match(/RESOLUCIÓN N°\s+(.*?)\s+DE FECHA/);
                document.getElementById('trm-resolucion').value = rM ? rM[1] : '';

                /* Fecha DD/MM/YYYY → YYYY-MM-DD */
                var fM = text.match(/DE FECHA\s+(\d{1,2})\/(\d{1,2})\/(\d{4})/);
                if (fM) {
                    var dd = fM[1].length < 2 ? '0' + fM[1] : fM[1];
                    var mm = fM[2].length < 2 ? '0' + fM[2] : fM[2];
                    document.getElementById('trm-fecha').value = fM[3] + '-' + mm + '-' + dd;
                }

                /* Función helper para encontrar el checkbox adecuado */
                function findAndCheck(cls, codeStr) {
                    var c = codeStr.trim();
                    if (!c) return;
                    var chk = document.querySelector('.' + cls + '[value="' + c + '"]');
                    if (chk) { chk.checked = true; return; }
                    /* Buscar por coincidencia parcial o por data-cod */
                    var all = document.querySelectorAll('.' + cls);
                    for (var i = 0; i < all.length; i++) {
                        var pVal = all[i].value;
                        var pLabel = all[i].closest('.trm-item');
                        var dCod = pLabel ? pLabel.getAttribute('data-cod') : '';
                        if (pVal === c || pVal.indexOf(c) === 0 || c.indexOf(pVal) === 0 || dCod === c || dCod.indexOf(c) !== -1) {
                            all[i].checked = true;
                            return;
                        }
                    }
                }

                /* DE LAS PARTIDAS */
                var fromM = text.match(/DE LAS PARTIDAS;\s*(.*?)\s+A LAS PARTIDA/i);
                if (fromM) {
                    fromM[1].split(',').forEach(function (c) {
                        findAndCheck('trm-chk-from', c);
                    });
                }

                /* A LAS PARTIDA(S) */
                var toM = text.match(/A LAS PARTIDAS?\s+([\s\S]+?)$/i);
                if (toM) {
                    toM[1].split(',').forEach(function (c) {
                        findAndCheck('trm-chk-to', c);
                    });
                }
            }

            /* ── Patch openTraspasoModal to pre-fill modal when editing ── */
            var _origOpen = window.openTraspasoModal;
            window.openTraspasoModal = function (inp) {
                var delBtnModal = document.getElementById('trm-btn-delete');
                if (inp && inp.value && inp.value.toUpperCase().indexOf('TRASPASO') === 0) {
                    parseTraspasoIntoModal(inp.value);
                    if (delBtnModal) delBtnModal.style.display = 'inline-flex';
                    var opId = inp.getAttribute('data-op-id');
                    if (opId) {
                        var dInp = document.getElementById('dismin-' + opId);
                        if (dInp && dInp.value) {
                            var mInp = document.getElementById('trm-monto');
                            if (mInp) mInp.value = dInp.value;
                        }
                    }
                } else {
                    if (delBtnModal) delBtnModal.style.display = 'none';
                    var mInp = document.getElementById('trm-monto');
                    if (mInp && !inp) mInp.value = '';
                }
                _origOpen(inp);
            };

            window.eliminarTraspasoDirecto = function (inp) {
                if (!inp) return;
                var opId = inp.getAttribute('data-op-id');
                var cod = inp.getAttribute('data-cod') || '<?php echo htmlspecialchars($cod_sel); ?>';
                var mes = inp.getAttribute('data-mes') || '<?php echo $mes_sel; ?>';
                var anio = inp.getAttribute('data-anio') || '<?php echo $anio_sel; ?>';

                if (!opId) return;

                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'matriz.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.ok) {
                                window.location.reload();
                            } else {
                                alert(res.msg || 'Error al eliminar el traspaso');
                            }
                        } catch (e) {
                            window.location.reload();
                        }
                    }
                };
                xhr.send('action=eliminar_traspaso&op_id=' + opId + '&cod=' + encodeURIComponent(cod) + '&mes=' + mes + '&anio=' + anio);
            };

            window.eliminarTraspasoDesdeModal = function () {
                if (_activeDetInput) {
                    if (confirm('¿Estás seguro de eliminar este Traspaso de Crédito Presupuestario de esta partida?')) {
                        closeTraspasoModal();
                        eliminarTraspasoDirecto(_activeDetInput);
                    }
                }
            };

            /* ── Patch applyTraspasoText to also upgrade the field ── */
            var _origApply = window.applyTraspasoText;
            window.applyTraspasoText = function () {
                /* Read active input reference BEFORE origApply nulls it */
                var pEl = document.getElementById('trm-preview'); var preview = (pEl ? (pEl.value !== undefined ? pEl.value : pEl.textContent) : '').trim();
                /* Call original (sets value + triggers save + closes modal) */
                _origApply();
                /* Find recently updated detalle inputs that now contain traspaso */
                setTimeout(function () {
                    var inputs = document.querySelectorAll('.detalle-input');
                    for (var i = 0; i < inputs.length; i++) {
                        if (inputs[i].value.toUpperCase().indexOf('TRASPASO') === 0) {
                            upgradeField(inputs[i], inputs[i].value);
                        }
                    }
                }, 50);
            };

            /* ── On load: upgrade any detalle fields that already have traspaso text ── */
            document.addEventListener('DOMContentLoaded', function () {
                var inputs = document.querySelectorAll('.detalle-input');
                for (var i = 0; i < inputs.length; i++) {
                    if (inputs[i].value.toUpperCase().indexOf('TRASPASO') === 0) {
                        upgradeField(inputs[i], inputs[i].value);
                    }
                }
            });
        })();

        /* ============================================================
           SELECTOR BUSCABLE DE PARTIDAS (DROPDOWN CON FILTRO SUPERIOR)
           ============================================================ */
        function initPartidaSearchableSelects() {
            var widgets = document.querySelectorAll('.partida-searchable-select');
            if (!widgets.length) return;

            function norm(str) {
                if (!str) return '';
                return str.toString()
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .trim();
            }

            widgets.forEach(function(widget) {
                var trigger = widget.querySelector('.psd-trigger');
                var panel = widget.querySelector('.psd-dropdown-panel');
                var filterInput = widget.querySelector('.psd-filter-input');
                var clearBtn = widget.querySelector('.psd-clear-btn');
                var countEl = widget.querySelector('.psd-results-count');
                var optionsList = widget.querySelector('.psd-options-list');
                var options = Array.prototype.slice.call(widget.querySelectorAll('.psd-option'));
                var noResults = widget.querySelector('.psd-no-results');
                var nativeSelect = widget.querySelector('.partida-select-native');
                var form = widget.closest('form');

                // Precompute search metadata on each option for lightning-fast filtering
                options.forEach(function(opt) {
                    var val = opt.getAttribute('data-value') || '';
                    var punto = opt.getAttribute('data-punto') || '';
                    var denom = opt.getAttribute('data-denom') || '';
                    var grupo = opt.getAttribute('data-grupo') || '';
                    
                    var digitsOnly = (val + ' ' + punto).replace(/[^0-9]/g, '');
                    var haystack = norm(val + ' ' + punto + ' ' + denom + ' ' + grupo + ' ' + digitsOnly);
                    opt._haystack = haystack;
                    opt._digits = digitsOnly;
                    opt._puntoNorm = norm(punto);
                    opt._denomNorm = norm(denom);
                    opt._originalDenom = denom;
                    opt._originalPunto = punto;
                });

                var focusedIdx = -1;

                function getVisibleOptions() {
                    return options.filter(function(opt) {
                        return opt.style.display !== 'none';
                    });
                }

                function setFocusedOption(idx) {
                    var vis = getVisibleOptions();
                    vis.forEach(function(opt) { opt.classList.remove('psd-focused'); });
                    if (idx >= 0 && idx < vis.length) {
                        focusedIdx = idx;
                        vis[focusedIdx].classList.add('psd-focused');
                        vis[focusedIdx].scrollIntoView({ block: 'nearest' });
                    } else {
                        focusedIdx = -1;
                    }
                }

                function highlightText(text, terms) {
                    if (!terms.length) return text;
                    var escaped = terms.map(function(t) {
                        return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    }).filter(Boolean);
                    if (!escaped.length) return text;
                    try {
                        var re = new RegExp('(' + escaped.join('|') + ')', 'gi');
                        return text.replace(re, '<mark class="psd-mark">$1</mark>');
                    } catch(e) {
                        return text;
                    }
                }

                function filterOptions(query) {
                    query = norm(query);
                    if (clearBtn) {
                        clearBtn.style.display = query.length > 0 ? 'block' : 'none';
                    }

                    var terms = query.split(/\s+/).filter(Boolean);
                    var matchCount = 0;

                    options.forEach(function(opt) {
                        var matches = true;
                        if (terms.length > 0) {
                            matches = terms.every(function(term) {
                                var termDigits = term.replace(/[^0-9]/g, '');
                                if (opt._haystack.indexOf(term) !== -1) return true;
                                if (termDigits && opt._digits.indexOf(termDigits) !== -1) return true;
                                return false;
                            });
                        }

                        if (matches) {
                            opt.style.display = 'flex';
                            matchCount++;
                            var denomEl = opt.querySelector('.psd-option-denom');
                            var badgeEl = opt.querySelector('.psd-option-badge');
                            if (terms.length > 0) {
                                if (denomEl) denomEl.innerHTML = highlightText(opt._originalDenom, terms);
                                if (badgeEl) badgeEl.innerHTML = highlightText(opt._originalPunto, terms);
                            } else {
                                if (denomEl) denomEl.textContent = opt._originalDenom;
                                if (badgeEl) badgeEl.textContent = opt._originalPunto;
                            }
                        } else {
                            opt.style.display = 'none';
                        }
                    });

                    if (countEl) {
                        if (terms.length === 0) {
                            countEl.textContent = 'Mostrando ' + matchCount + ' partidas';
                        } else {
                            countEl.textContent = matchCount + ' partida' + (matchCount === 1 ? '' : 's') + ' encontrada' + (matchCount === 1 ? '' : 's');
                        }
                    }

                    if (noResults) {
                        noResults.style.display = matchCount === 0 ? 'block' : 'none';
                    }

                    setFocusedOption(matchCount > 0 ? 0 : -1);
                }

                function openDropdown() {
                    document.querySelectorAll('.partida-searchable-select.open').forEach(function(w) {
                        if (w !== widget) closeDropdownWidget(w);
                    });

                    widget.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                    panel.style.display = 'flex';

                    // Check if space below is too small, then open upwards
                    var rect = trigger.getBoundingClientRect();
                    var spaceBelow = window.innerHeight - rect.bottom;
                    var panelHeight = 360;
                    if (spaceBelow < panelHeight && rect.top > panelHeight) {
                        panel.classList.add('opens-up');
                    } else {
                        panel.classList.remove('opens-up');
                    }

                    filterInput.value = '';
                    filterOptions('');

                    setTimeout(function() {
                        filterInput.focus();
                        var sel = widget.querySelector('.psd-option.selected');
                        if (sel) {
                            sel.scrollIntoView({ block: 'center' });
                            var vis = getVisibleOptions();
                            var sIdx = vis.indexOf(sel);
                            if (sIdx !== -1) setFocusedOption(sIdx);
                        }
                    }, 30);
                }

                function selectOption(opt) {
                    if (!opt) return;
                    var val = opt.getAttribute('data-value');
                    var punto = opt.getAttribute('data-punto');
                    var denom = opt.getAttribute('data-denom');

                    var badge = trigger.querySelector('.psd-trigger-badge');
                    var text = trigger.querySelector('.psd-trigger-text');
                    if (badge) badge.textContent = punto;
                    if (text) text.textContent = denom;

                    options.forEach(function(o) {
                        o.classList.remove('selected');
                        o.setAttribute('aria-selected', 'false');
                    });
                    opt.classList.add('selected');
                    opt.setAttribute('aria-selected', 'true');

                    if (nativeSelect) {
                        nativeSelect.value = val;
                    }

                    closeDropdownWidget(widget);

                    if (form) {
                        form.submit();
                    }
                }

                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (widget.classList.contains('open')) {
                        closeDropdownWidget(widget);
                    } else {
                        openDropdown();
                    }
                });

                filterInput.addEventListener('input', function() {
                    filterOptions(this.value);
                });

                if (clearBtn) {
                    clearBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        filterInput.value = '';
                        filterOptions('');
                        filterInput.focus();
                    });
                }

                options.forEach(function(opt) {
                    opt.addEventListener('click', function(e) {
                        e.stopPropagation();
                        selectOption(this);
                    });
                });

                filterInput.addEventListener('keydown', function(e) {
                    var vis = getVisibleOptions();
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (vis.length > 0) {
                            var nextIdx = (focusedIdx + 1) % vis.length;
                            setFocusedOption(nextIdx);
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (vis.length > 0) {
                            var prevIdx = (focusedIdx - 1 + vis.length) % vis.length;
                            setFocusedOption(prevIdx);
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (focusedIdx >= 0 && focusedIdx < vis.length) {
                            selectOption(vis[focusedIdx]);
                        } else if (vis.length === 1) {
                            selectOption(vis[0]);
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeDropdownWidget(widget);
                        trigger.focus();
                    } else if (e.key === 'Tab') {
                        closeDropdownWidget(widget);
                    }
                });
            });

            function closeDropdownWidget(w) {
                w.classList.remove('open');
                var tr = w.querySelector('.psd-trigger');
                var pan = w.querySelector('.psd-dropdown-panel');
                if (tr) tr.setAttribute('aria-expanded', 'false');
                if (pan) pan.style.display = 'none';
            }

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.partida-searchable-select')) {
                    document.querySelectorAll('.partida-searchable-select.open').forEach(closeDropdownWidget);
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.partida-searchable-select.open').forEach(closeDropdownWidget);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initPartidaSearchableSelects();
        });
    </script>

</body>

</html>