<?php
/*
 * papelera_os.php
 * Mueve una OS a la papelera (soft delete) estableciendo deleted_at = NOW().
 * GET: id = ordenes_servicio.id
 * PHP 5.6 — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_servicio/index.php');
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

@mysqli_query($conn, "ALTER TABLE ordenes_servicio ADD COLUMN deleted_at DATETIME NULL");

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $num_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT numero_os FROM ordenes_servicio WHERE id = $id"));
    $stmt = mysqli_prepare($conn, "UPDATE ordenes_servicio SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
    }
    require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
    $numero_os = ($num_row && isset($num_row['numero_os'])) ? $num_row['numero_os'] : '';
    log_activity((int) $_SESSION['usuario_id'], 'ANULAR_OS', 'OS', 'OS ' . $numero_os . ' (id ' . $id . ') movida a papelera');
}

header("Location: ../../Views/ordenes_servicio/index.php?papelera=1&msg=movido");
exit();
?>
