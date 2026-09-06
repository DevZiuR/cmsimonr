<?php
/**
 * src/Controllers/ejecucion/guardar_overrides.php
 * Guarda (INSERT o UPDATE) créditos, aumentos, disminuciones, overrides, codificacion y denominacion vía AJAX
 * Admin only — PHP 5.6 compatible — sin ??
 */
if (session_id() === '') { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(array('success' => false, 'error' => 'No autenticado'));
    exit;
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('success' => false, 'error' => 'Acceso denegado'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => 'Método no permitido'));
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$row_id = isset($_POST['row_id']) ? (int) $_POST['row_id'] : 0;
$mes    = isset($_POST['mes'])    ? (int) $_POST['mes']    : (int) date('n');
$anio   = isset($_POST['anio'])   ? (int) $_POST['anio']   : (int) date('Y');

$codificacion = isset($_POST['codificacion']) ? trim(mysqli_real_escape_string($conn, $_POST['codificacion'])) : '';
$denominacion = isset($_POST['denominacion']) ? trim(mysqli_real_escape_string($conn, $_POST['denominacion'])) : '';

function clean_money($val) {
    if ($val === null) return 0.0;
    $v = trim((string)$val);
    if ($v === '') return 0.0;
    // Remove thousands commas if formatted like 1,488,000.00
    if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
        $v = str_replace(',', '', $v);
    } elseif (strpos($v, ',') !== false) {
        // e.g. 1488000,50
        $v = str_replace(',', '.', $v);
    }
    return (float) $v;
}

$credito_aprobado = isset($_POST['credito_aprobado']) ? clean_money($_POST['credito_aprobado']) : 0.0;
$aumentos         = isset($_POST['aumentos'])         ? clean_money($_POST['aumentos'])         : 0.0;
$disminuciones    = isset($_POST['disminuciones'])    ? clean_money($_POST['disminuciones'])    : 0.0;

$override_fields = array(
    'credito_adicional_override',
    'credito_actualizado_override',
    'compromiso_mensual_override',
    'compromiso_acumulado_override',
    'gastos_causados_override',
    'pago_acumulado_override',
    'compromisos_pagar_override',
    'disponibilidad_override',
);

if ($row_id <= 0) {
    // INSERT new row
    if ($codificacion === '') {
        echo json_encode(array('success' => false, 'error' => 'La codificación es requerida'));
        exit;
    }
    
    // Check if (codificacion, mes, anio) already exists
    $check = mysqli_query($conn, "SELECT id FROM ejecucion_presupuestaria WHERE codificacion = '$codificacion' AND mes = $mes AND anio = $anio");
    if ($check && mysqli_num_rows($check) > 0) {
        $existing = mysqli_fetch_assoc($check);
        $row_id = (int)$existing['id'];
    } else {
        $cols = array('`codificacion`', '`denominacion`', '`credito_aprobado`', '`aumentos`', '`disminuciones`', '`mes`', '`anio`');
        $vals = array("'$codificacion'", "'$denominacion'", $credito_aprobado, $aumentos, $disminuciones, $mes, $anio);
        
        foreach ($override_fields as $field) {
            if (isset($_POST[$field]) && trim($_POST[$field]) !== '') {
                $cols[] = "`$field`";
                $vals[] = clean_money($_POST[$field]);
            }
        }
        
        $sql = "INSERT INTO ejecucion_presupuestaria (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $ok = mysqli_query($conn, $sql);
        if ($ok) {
            $new_id = mysqli_insert_id($conn);
            echo json_encode(array('success' => true, 'id' => $new_id, 'is_new' => true));
            exit;
        } else {
            echo json_encode(array('success' => false, 'error' => mysqli_error($conn)));
            exit;
        }
    }
}

// UPDATE existing row
$set_parts = array();
if ($codificacion !== '') {
    $set_parts[] = "`codificacion` = '$codificacion'";
}
if ($denominacion !== '') {
    $set_parts[] = "`denominacion` = '$denominacion'";
}
if (isset($_POST['credito_aprobado'])) {
    $set_parts[] = "`credito_aprobado` = $credito_aprobado";
}
if (isset($_POST['aumentos'])) {
    $set_parts[] = "`aumentos` = $aumentos";
}
if (isset($_POST['disminuciones'])) {
    $set_parts[] = "`disminuciones` = $disminuciones";
}

foreach ($override_fields as $field) {
    if (isset($_POST[$field])) {
        $val = trim($_POST[$field]);
        if ($val === '') {
            $set_parts[] = "`$field` = NULL";
        } else {
            $num = clean_money($val);
            $set_parts[] = "`$field` = $num";
        }
    }
}

if (!empty($set_parts)) {
    $sql = "UPDATE ejecucion_presupuestaria SET " . implode(', ', $set_parts) . " WHERE id = $row_id";
    $ok = mysqli_query($conn, $sql);
    if ($ok) {
        /* Sincronizar partidas.credito_original como fuente de verdad única */
        if (isset($_POST['credito_aprobado']) && $credito_aprobado >= 0) {
            $tgt_cod = $codificacion;
            if ($tgt_cod === '' && $row_id > 0) {
                $r_tgt = mysqli_query($conn, "SELECT codificacion FROM ejecucion_presupuestaria WHERE id = $row_id LIMIT 1");
                if ($r_tgt && $rw_tgt = mysqli_fetch_assoc($r_tgt)) {
                    $tgt_cod = $rw_tgt['codificacion'];
                }
            }
            if ($tgt_cod !== '') {
                $tgt_esc = mysqli_real_escape_string($conn, $tgt_cod);
                $res_ant = mysqli_query($conn, "SELECT credito_original FROM partidas WHERE codigo = '$tgt_esc' LIMIT 1");
                $ant_row = $res_ant ? mysqli_fetch_assoc($res_ant) : null;
                $anterior = $ant_row ? (float)$ant_row['credito_original'] : 0.0;

                mysqli_query($conn, "UPDATE partidas SET credito_original = $credito_aprobado WHERE codigo = '$tgt_esc'");

                if ($ant_row && (float)$anterior !== (float)$credito_aprobado) {
                    require_once dirname(dirname(__DIR__)) . '/Models/audit_log.php';
                    $uid = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : 0;
                    $detalle = 'Crédito original de [' . $tgt_esc . '] (vía Reporte Mensual): anterior=' . number_format($anterior, 2, '.', ',') . ' → nuevo=' . number_format($credito_aprobado, 2, '.', ',');
                    log_activity($uid, 'EDITAR_CREDITO_ORIGINAL', 'Ejecución Presupuestaria', $detalle);
                }
            }
        }
        echo json_encode(array('success' => true, 'id' => $row_id, 'affected' => mysqli_affected_rows($conn)));
    } else {
        echo json_encode(array('success' => false, 'error' => mysqli_error($conn)));
    }
} else {
    echo json_encode(array('success' => true, 'id' => $row_id, 'message' => 'Sin cambios'));
}
