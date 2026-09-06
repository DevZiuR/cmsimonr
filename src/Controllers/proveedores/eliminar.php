<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/proveedores/index.php');
    exit;
}
require_once '../../../config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header("Location: ../../Views/proveedores/index.php");
    exit;
}

// 1. Verificar si tiene órdenes de compra asociadas
$query_oc = "SELECT COUNT(*) AS total FROM ordenes_compra WHERE proveedor_id = $id";
$res_oc   = mysqli_query($conn, $query_oc);
$row_oc   = $res_oc ? mysqli_fetch_assoc($res_oc) : array();
$total_oc = isset($row_oc['total']) ? (int) $row_oc['total'] : 0;

// 2. Verificar si tiene órdenes de pago asociadas
$query_op = "SELECT COUNT(*) AS total FROM ordenes_pago WHERE proveedor_id = $id";
$res_op   = mysqli_query($conn, $query_op);
$row_op   = $res_op ? mysqli_fetch_assoc($res_op) : array();
$total_op = isset($row_op['total']) ? (int) $row_op['total'] : 0;

if ($total_oc > 0 || $total_op > 0) {
    // Si tiene órdenes asociadas, redirigir con error
    header("Location: ../../Views/proveedores/index.php?error=tiene_ordenes");
    exit;
}

// 3. Proceder a la eliminación
$query_delete = "DELETE FROM proveedores WHERE id = $id";
$res_delete   = mysqli_query($conn, $query_delete);

if ($res_delete) {
    header("Location: ../../Views/proveedores/index.php?ok_eliminar=1");
    exit;
} else {
    header("Location: ../../Views/proveedores/index.php?error=db_error");
    exit;
}
?>
