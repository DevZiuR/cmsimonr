<?php
/**
 * eliminar.php — Controller to delete a producto record
 * Receives GET id, checks for dependencies, deletes from DB, and redirects.
 * PHP 5.6 compatible.
 */

session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /sistema/src/Views/productos/index.php');
    exit;
}

require_once __DIR__ . '/../../../config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /sistema/src/Views/productos/index.php');
    exit;
}

$id_esc = mysqli_real_escape_string($conn, $id);

// Check if the product is referenced in ordenes_compra items
$res_oc = mysqli_query($conn, "SELECT COUNT(*) AS total FROM items_oc WHERE producto_id = '$id_esc'");
if ($res_oc) {
    $row_oc   = mysqli_fetch_assoc($res_oc);
    $total_oc = isset($row_oc['total']) ? (int) $row_oc['total'] : 0;
    if ($total_oc > 0) {
        header('Location: /sistema/src/Views/productos/index.php?error=tiene_ordenes');
        exit;
    }
}

// Proceed to delete
$res_delete = mysqli_query($conn, "DELETE FROM productos WHERE id = '$id_esc' LIMIT 1");

if ($res_delete) {
    header('Location: /sistema/src/Views/productos/index.php?ok_eliminar=1');
} else {
    header('Location: /sistema/src/Views/productos/index.php?error=db_error');
}
exit;
?>
