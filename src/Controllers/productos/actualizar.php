<?php
/**
 * actualizar.php — Controller to update an existing producto record
 * Receives POST data, validates, updates DB, and redirects.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sistema/src/Views/productos/index.php');
    exit;
}

$id                  = isset($_POST['id'])                  ? (int) $_POST['id']                  : 0;
$descripcion         = isset($_POST['descripcion'])         ? trim($_POST['descripcion'])          : '';
$imput_presupuestaria = isset($_POST['imput_presupuestaria']) ? trim($_POST['imput_presupuestaria']) : '';

if ($id <= 0 || $descripcion === '' || $imput_presupuestaria === '') {
    $back = '/sistema/src/Views/productos/editar.php?id=' . $id . '&error=campos_requeridos';
    header('Location: ' . $back);
    exit;
}

$id_esc    = mysqli_real_escape_string($conn, $id);
$desc_esc  = mysqli_real_escape_string($conn, $descripcion);
$imput_esc = mysqli_real_escape_string($conn, $imput_presupuestaria);

$sql = "UPDATE productos
           SET descripcion         = '$desc_esc',
               imput_presupuestaria = '$imput_esc'
         WHERE id = '$id_esc'
         LIMIT 1";

$result = mysqli_query($conn, $sql);

if ($result) {
    header('Location: /sistema/src/Views/productos/index.php?ok_editar=1');
} else {
    $back = '/sistema/src/Views/productos/editar.php?id=' . $id . '&error=error_actualizacion';
    header('Location: ' . $back);
}
exit;
?>
