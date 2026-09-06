<?php
/**
 * src/Controllers/cambiar_status.php
 * Cambia el status de una OC, OP u OS.
 * Admin: cualquier status.
 * Supervisor: solo aprobada o rechazado.
 * PHP 5.6 compatible — sin ??
 */
if (session_id() === '') { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(array('success' => false, 'error' => 'No autenticado'));
    exit;
}

$rol = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
if (!in_array($rol, array('admin', 'supervisor'))) {
    echo json_encode(array('success' => false, 'error' => 'Sin permiso'));
    exit;
}

require_once dirname(dirname(__DIR__)) . '/config/conexion.php';
require_once dirname(dirname(__DIR__)) . '/src/Models/audit_log.php';

$id     = isset($_POST['id'])           ? (int) $_POST['id']           : 0;
$tabla  = isset($_POST['tabla'])        ? trim($_POST['tabla'])         : '';
$nuevo  = isset($_POST['nuevo_status']) ? trim($_POST['nuevo_status'])  : '';

/* ── Validar tabla ── */
$tablas_permitidas = array('ordenes_compra', 'ordenes_pago', 'ordenes_servicio');
if (!in_array($tabla, $tablas_permitidas) || $id <= 0 || $nuevo === '') {
    echo json_encode(array('success' => false, 'error' => 'Parámetros inválidos'));
    exit;
}

/* ── Validar status permitido (solo pendiente y pagado) ── */
$todos_los_status = array('pendiente', 'pagado');

if (!in_array($nuevo, $todos_los_status)) {
    echo json_encode(array('success' => false, 'error' => 'Status no válido. Solo se permite pendiente o pagado'));
    exit;
}

/* ── Ejecutar UPDATE ── */
$tabla_safe  = $tabla;   /* Already validated above */
$nuevo_safe  = mysqli_real_escape_string($conn, $nuevo);
$usuario     = mysqli_real_escape_string($conn, $_SESSION['usuario']);
$ahora       = date('Y-m-d H:i:s');

/* ── Datos previos para auditoría ── */
$numero_col  = array('ordenes_compra' => 'numero_orden', 'ordenes_pago' => 'numero', 'ordenes_servicio' => 'numero_os');
$ref_col     = $numero_col[$tabla];
$info_prev   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status, `$ref_col` AS numero FROM `$tabla_safe` WHERE id=$id AND deleted_at IS NULL"));
$status_ant  = ($info_prev && isset($info_prev['status'])) ? $info_prev['status'] : '';
$numero_ref  = ($info_prev && isset($info_prev['numero'])) ? $info_prev['numero'] : '';

$sql = "UPDATE `$tabla_safe` SET status='$nuevo_safe', updated_by='$usuario', updated_at='$ahora' WHERE id=$id AND deleted_at IS NULL";
$ok  = mysqli_query($conn, $sql);

if ($ok) {
    $modulo_map = array('ordenes_compra' => 'OC', 'ordenes_pago' => 'OP', 'ordenes_servicio' => 'OS');
    if (mysqli_affected_rows($conn) > 0) {
        log_activity((int) $_SESSION['usuario_id'], 'CAMBIAR_STATUS', $modulo_map[$tabla], $modulo_map[$tabla] . ' ' . $numero_ref . ' (id ' . $id . '): ' . $status_ant . ' -> ' . $nuevo);
    }

    /* ── Si una Orden de Pago cambia a 'pagado'/'pagada', actualizar la O.C u O.S vinculada a 'pagado' ── */
    if ($tabla === 'ordenes_pago' && in_array(strtolower($nuevo), array('pagado', 'pagada'))) {
        $op_link_res = mysqli_query($conn, "SELECT oc_id, os_id FROM ordenes_pago WHERE id=$id");
        if ($op_link_res && ($op_link = mysqli_fetch_assoc($op_link_res))) {
            if (!empty($op_link['oc_id']) && (int)$op_link['oc_id'] > 0) {
                $oc_id_linked = (int)$op_link['oc_id'];
                mysqli_query($conn, "UPDATE ordenes_compra SET status='pagado', updated_by='$usuario', updated_at='$ahora' WHERE id=$oc_id_linked AND deleted_at IS NULL");
                log_activity((int) $_SESSION['usuario_id'], 'CAMBIAR_STATUS', 'OC', "OC vinculada (id $oc_id_linked) actualizada a PAGADO automáticamente por pago de OP $numero_ref");
            }
            if (!empty($op_link['os_id']) && (int)$op_link['os_id'] > 0) {
                $os_id_linked = (int)$op_link['os_id'];
                mysqli_query($conn, "UPDATE ordenes_servicio SET status='pagado', updated_by='$usuario', updated_at='$ahora' WHERE id=$os_id_linked AND deleted_at IS NULL");
                log_activity((int) $_SESSION['usuario_id'], 'CAMBIAR_STATUS', 'OS', "OS vinculada (id $os_id_linked) actualizada a PAGADO automáticamente por pago de OP $numero_ref");
            }
        }
    }

    echo json_encode(array('success' => true, 'nuevo_status' => $nuevo));
} else {
    echo json_encode(array('success' => false, 'error' => mysqli_error($conn)));
}
