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

$rif          = isset($_POST['rif']) ? trim($_POST['rif']) : '';
$razon_social = isset($_POST['razon_social']) ? trim($_POST['razon_social']) : '';
$direccion    = isset($_POST['direccion']) ? trim($_POST['direccion']) : '';
$telefono     = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$email        = isset($_POST['email']) ? trim($_POST['email']) : '';

// Escapar caracteres para prevenir inyecciones SQL
$rif_esc          = mysqli_real_escape_string($conn, $rif);
$razon_social_esc = mysqli_real_escape_string($conn, $razon_social);
$direccion_esc    = mysqli_real_escape_string($conn, $direccion);
$telefono_esc     = mysqli_real_escape_string($conn, $telefono);
$email_esc        = mysqli_real_escape_string($conn, $email);

$is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == 1;

if ($rif_esc === '' || $razon_social_esc === '') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'RIF y Razón Social son requeridos.']);
        exit;
    }
    $back_url = "../../Views/proveedores/nuevo.php?error=campos_requeridos"
        . "&rif=" . urlencode($rif)
        . "&razon_social=" . urlencode($razon_social)
        . "&direccion=" . urlencode($direccion)
        . "&telefono=" . urlencode($telefono)
        . "&email=" . urlencode($email);
    header("Location: " . $back_url);
    exit;
}

// Verificar si el RIF ya existe
$query_check = "SELECT id FROM proveedores WHERE rif = '$rif_esc'";
$res_check   = mysqli_query($conn, $query_check);

if ($res_check && mysqli_num_rows($res_check) > 0) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'El RIF ya se encuentra registrado.']);
        exit;
    }
    $back_url = "../../Views/proveedores/nuevo.php?error=rif_duplicado"
        . "&rif=" . urlencode($rif)
        . "&razon_social=" . urlencode($razon_social)
        . "&direccion=" . urlencode($direccion)
        . "&telefono=" . urlencode($telefono)
        . "&email=" . urlencode($email);
    header("Location: " . $back_url);
    exit;
}

// Insertar el nuevo proveedor
$query_insert = "INSERT INTO proveedores (rif, razon_social, direccion, telefono, email) 
                 VALUES ('$rif_esc', '$razon_social_esc', '$direccion_esc', '$telefono_esc', '$email_esc')";
$res_insert   = mysqli_query($conn, $query_insert);

if ($res_insert) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'id' => mysqli_insert_id($conn),
            'rif' => $rif,
            'razon_social' => $razon_social
        ]);
        exit;
    }
    header("Location: ../../Views/proveedores/index.php?ok=1");
    exit;
} else {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error al guardar en la base de datos.']);
        exit;
    }
    $back_url = "../../Views/proveedores/nuevo.php?error=error_insercion"
        . "&rif=" . urlencode($rif)
        . "&razon_social=" . urlencode($razon_social)
        . "&direccion=" . urlencode($direccion)
        . "&telefono=" . urlencode($telefono)
        . "&email=" . urlencode($email);
    header("Location: " . $back_url);
    exit;
}
?>