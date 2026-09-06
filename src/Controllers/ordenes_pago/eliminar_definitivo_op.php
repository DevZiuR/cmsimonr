<?php
/*
 * eliminar_definitivo_op.php
 * Elimina permanentemente una OP específica que se encuentra en la papelera.
 * Borra en orden: op_retenciones → ordenes_pago.
 * Compatible PHP 5.6
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_pago/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $num_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT numero FROM ordenes_pago WHERE id = $id"));
    /* 1. Eliminar retenciones */
    $s1 = mysqli_prepare($conn, "DELETE FROM op_retenciones WHERE op_id = ?");
    if ($s1) {
        mysqli_stmt_bind_param($s1, 'i', $id);
        mysqli_stmt_execute($s1);
    }

    /* 2. Eliminar la orden de pago */
    $s2 = mysqli_prepare($conn, "DELETE FROM ordenes_pago WHERE id = ? AND deleted_at IS NOT NULL");
    if ($s2) {
        mysqli_stmt_bind_param($s2, 'i', $id);
        mysqli_stmt_execute($s2);
    }
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
    $numero_op = ($num_row && isset($num_row['numero'])) ? $num_row['numero'] : '';
    log_activity((int) $_SESSION['usuario_id'], 'ELIMINAR_OP', 'OP', 'OP ' . $numero_op . ' (id ' . $id . ') eliminada definitivamente');
}

header("Location: ../../Views/ordenes_pago/index.php?papelera=1&msg=eliminado");
exit();
?>
