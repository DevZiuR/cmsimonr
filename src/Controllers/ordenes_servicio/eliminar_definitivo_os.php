<?php
/*
 * eliminar_definitivo_os.php
 * Elimina permanentemente una OS específica que se encuentra en la papelera.
 * Borra en orden: os_partidas → os_renglones → ordenes_servicio.
 * Compatible PHP 5.6
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_servicio/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $num_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT numero_os FROM ordenes_servicio WHERE id = $id"));
    /* 1. Eliminar partidas */
    $s1 = mysqli_prepare($conn, "DELETE FROM os_partidas WHERE os_id = ?");
    if ($s1) {
        mysqli_stmt_bind_param($s1, 'i', $id);
        mysqli_stmt_execute($s1);
    }

    /* 2. Eliminar renglones */
    $s2 = mysqli_prepare($conn, "DELETE FROM os_renglones WHERE os_id = ?");
    if ($s2) {
        mysqli_stmt_bind_param($s2, 'i', $id);
        mysqli_stmt_execute($s2);
    }

    /* 3. Eliminar la orden de servicio */
    $s3 = mysqli_prepare($conn, "DELETE FROM ordenes_servicio WHERE id = ? AND deleted_at IS NOT NULL");
    if ($s3) {
        mysqli_stmt_bind_param($s3, 'i', $id);
        mysqli_stmt_execute($s3);
    }
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
    $numero_os = ($num_row && isset($num_row['numero_os'])) ? $num_row['numero_os'] : '';
    log_activity((int) $_SESSION['usuario_id'], 'ELIMINAR_OS', 'OS', 'OS ' . $numero_os . ' (id ' . $id . ') eliminada definitivamente');
}

header("Location: ../../Views/ordenes_servicio/index.php?papelera=1&msg=eliminado");
exit();
?>
