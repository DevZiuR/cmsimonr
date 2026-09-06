<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/productos/index.php');
    exit;
}
header('Content-Type: application/json');
require_once '../../../config/conexion.php';

$desc  = mysqli_real_escape_string($conn, isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '');
$imput = mysqli_real_escape_string($conn, isset($_POST['imput'])       ? trim($_POST['imput'])       : '');

if (!$desc || !$imput) {
    echo json_encode(array('ok' => false, 'error' => 'Descripción e Imputación son requeridas.'));
    exit;
}

$check = mysqli_query($conn, "SELECT id FROM productos WHERE descripcion = '$desc'");
if ($check && mysqli_num_rows($check) > 0) {
    echo json_encode(array('ok' => false, 'existe' => true));
    exit;
}

$res = mysqli_query($conn,
    "INSERT INTO productos (descripcion, imput_presupuestaria) VALUES ('$desc', '$imput')"
);

if ($res) {
    echo json_encode(array('ok' => true));
} else {
    echo json_encode(array('ok' => false, 'error' => mysqli_error($conn)));
}
?>