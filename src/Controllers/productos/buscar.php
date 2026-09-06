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
require_once '../../../config/conexion.php';

$q = mysqli_real_escape_string($conn, isset($_GET['q']) ? $_GET['q'] : '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$result = mysqli_query($conn,
    "SELECT descripcion, imput_presupuestaria 
     FROM productos 
     WHERE descripcion LIKE '%$q%' 
     LIMIT 10"
);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);