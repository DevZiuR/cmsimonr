<?php
/*
 * restaurar_oc.php
 * Restaura una OC de la papelera poniendo deleted_at = NULL.
 * GET: id = ordenes_compra.id
 * PHP 5.6 — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_compra/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $num_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT numero_orden FROM ordenes_compra WHERE id = $id"));
    $stmt = mysqli_prepare($conn, "UPDATE ordenes_compra SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
    $numero_orden = ($num_row && isset($num_row['numero_orden'])) ? $num_row['numero_orden'] : '';
    log_activity((int) $_SESSION['usuario_id'], 'RESTAURAR_OC', 'OC', 'OC ' . $numero_orden . ' (id ' . $id . ') restaurada de la papelera');
}

header("Location: ../../Views/ordenes_compra/index.php?papelera=1&msg=restaurado");
exit();
?>
