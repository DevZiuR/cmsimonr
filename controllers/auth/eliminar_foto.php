<?php
session_start();

if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: ../../public/login.php');
    exit;
}

$conexion_path = '../../config/conexion.php';
if (file_exists($conexion_path)) {
    require_once $conexion_path;
}

$usuario_id = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;
$upload_dir = '../../assets/img/avatars/';

if (isset($conn) && $conn && $usuario_id > 0) {
    $q_old = @mysqli_query($conn, "SELECT foto FROM usuarios WHERE id = $usuario_id LIMIT 1");
    if ($q_old && $r_old = @mysqli_fetch_assoc($q_old)) {
        if (!empty($r_old['foto']) && file_exists($upload_dir . $r_old['foto'])) {
            @unlink($upload_dir . $r_old['foto']);
        }
    }
    @mysqli_query($conn, "UPDATE usuarios SET foto = NULL WHERE id = $usuario_id");
}

$_SESSION['foto'] = '';
$_SESSION['usuario_foto'] = '';

header('Location: ../../src/Views/perfil/index.php?ok=1');
exit;
