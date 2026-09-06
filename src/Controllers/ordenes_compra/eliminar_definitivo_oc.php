<?php
/*
 * eliminar_definitivo_oc.php
 * Elimina permanentemente una OC específica que se encuentra en la papelera.
 * Borra en orden: oc_partidas → oc_renglones → ordenes_compra.
 * Compatible PHP 5.6
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_compra/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $num_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT numero_orden FROM ordenes_compra WHERE id = $id"));
    /* 1. Eliminar partidas */
    $s1 = mysqli_prepare($conn, "DELETE FROM oc_partidas WHERE oc_id = ?");
    if ($s1) {
        mysqli_stmt_bind_param($s1, 'i', $id);
        mysqli_stmt_execute($s1);
    }

    /* 2. Eliminar renglones */
    $s2 = mysqli_prepare($conn, "DELETE FROM oc_renglones WHERE oc_id = ?");
    if ($s2) {
        mysqli_stmt_bind_param($s2, 'i', $id);
        mysqli_stmt_execute($s2);
    }

    /* 3. Eliminar la orden de compra */
    $s3 = mysqli_prepare($conn, "DELETE FROM ordenes_compra WHERE id = ? AND deleted_at IS NOT NULL");
    if ($s3) {
        mysqli_stmt_bind_param($s3, 'i', $id);
        mysqli_stmt_execute($s3);
    }
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
    $numero_orden = ($num_row && isset($num_row['numero_orden'])) ? $num_row['numero_orden'] : '';
    log_activity((int) $_SESSION['usuario_id'], 'ELIMINAR_OC', 'OC', 'OC ' . $numero_orden . ' (id ' . $id . ') eliminada definitivamente');
}

header("Location: ../../Views/ordenes_compra/index.php?papelera=1&msg=eliminado");
exit();
?>
