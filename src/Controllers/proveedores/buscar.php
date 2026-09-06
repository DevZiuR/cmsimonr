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
require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$result = mysqli_query($conn, "SELECT rif, direccion, telefono FROM proveedores WHERE id = $id");
$row    = mysqli_fetch_assoc($result);

header('Content-Type: application/json');
echo json_encode($row ? $row : array('rif' => '', 'direccion' => '', 'telefono' => ''));
?>