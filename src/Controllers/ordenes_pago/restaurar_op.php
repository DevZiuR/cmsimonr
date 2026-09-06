<?php
/*
 * restaurar_op.php
 * Restaura una OP de la papelera poniendo deleted_at = NULL.
 * GET: id = ordenes_pago.id
 * PHP 5.6 — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_pago/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $num_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT numero FROM ordenes_pago WHERE id = $id"));
    $stmt = mysqli_prepare($conn, "UPDATE ordenes_pago SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
    }
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
    $numero_op = ($num_row && isset($num_row['numero'])) ? $num_row['numero'] : '';
    log_activity((int) $_SESSION['usuario_id'], 'RESTAURAR_OP', 'OP', 'OP ' . $numero_op . ' (id ' . $id . ') restaurada de la papelera');
}

header("Location: ../../Views/ordenes_pago/index.php?papelera=1&msg=restaurado");
exit();
?>
