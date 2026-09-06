<?php
/**
 * actualizar.php — Controller to update an existing proveedor record
 * Receives POST data, validates, updates DB, and redirects.
 * PHP 5.6 compatible: no ?? operator, isset() ternary throughout.
 */

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: /sistema/src/Views/proveedores/index.php');
    exit;
}

require_once __DIR__ . '/../../../config/conexion.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sistema/src/Views/proveedores/index.php');
    exit;
}

// Read and sanitize inputs
$id           = isset($_POST['id'])           ? (int) $_POST['id']                              : 0;
$rif          = isset($_POST['rif'])          ? trim($_POST['rif'])                             : '';
$razon_social = isset($_POST['razon_social']) ? trim($_POST['razon_social'])                    : '';
$direccion    = isset($_POST['direccion'])    ? trim($_POST['direccion'])                       : '';
$telefono     = isset($_POST['telefono'])     ? trim($_POST['telefono'])                        : '';
$email        = isset($_POST['email'])        ? trim($_POST['email'])                           : '';

// Basic validation
if ($id <= 0 || $rif === '' || $razon_social === '') {
    $back = '/sistema/src/Views/proveedores/editar.php?id=' . $id . '&error=campos_requeridos';
    header('Location: ' . $back);
    exit;
}

// Escape for SQL injection prevention
$id_esc           = mysqli_real_escape_string($conn, $id);
$rif_esc          = mysqli_real_escape_string($conn, $rif);
$razon_social_esc = mysqli_real_escape_string($conn, $razon_social);
$direccion_esc    = mysqli_real_escape_string($conn, $direccion);
$telefono_esc     = mysqli_real_escape_string($conn, $telefono);
$email_esc        = mysqli_real_escape_string($conn, $email);

// Execute UPDATE
$sql = "UPDATE proveedores
           SET rif          = '$rif_esc',
               razon_social = '$razon_social_esc',
               direccion    = '$direccion_esc',
               telefono     = '$telefono_esc',
               email        = '$email_esc'
         WHERE id = '$id_esc'
         LIMIT 1";

$result = mysqli_query($conn, $sql);

if ($result) {
    header('Location: /sistema/src/Views/proveedores/index.php?ok=1');
} else {
    $back = '/sistema/src/Views/proveedores/editar.php?id=' . $id . '&error=error_actualizacion';
    header('Location: ' . $back);
}
exit;
?>
