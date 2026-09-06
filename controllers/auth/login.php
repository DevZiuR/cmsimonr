<?php
/**
 * login.php — Controlador de autenticación
 * Compatible con PHP 5.4+
 */
session_start();

// Cargar la conexión a la base de datos de manera segura
$conn = null;
$conexion_path = '../../config/conexion.php';
if (file_exists($conexion_path)) {
    @include_once $conexion_path;
}

$usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($usuario === '' || $password === '') {
    header('Location: ../../public/login.php?error=1');
    exit;
}

// 1. Si hay conexión a la base de datos, intentar consultar la tabla 'usuarios'
if ($conn) {
    // Consulta con prepared statement compatible con PHP 5.4+
    $stmt = @mysqli_prepare($conn, "SELECT id, nombre, password, rol, foto FROM usuarios WHERE usuario = ? LIMIT 1");
    if ($stmt) {
        @mysqli_stmt_bind_param($stmt, 's', $usuario);
        if (@mysqli_stmt_execute($stmt)) {
            $res = @mysqli_stmt_get_result($stmt);
            $row = ($res !== false) ? @mysqli_fetch_assoc($res) : null;
            @mysqli_stmt_close($stmt);

            if ($row) {
                // Comprobar contraseña usando MD5
                if ($row['password'] === md5($password)) {
                    $_SESSION['usuario_id'] = $row['id'];
                    $_SESSION['usuario'] = $usuario;
                    $_SESSION['usuario_nombre'] = $row['nombre'];
                    $_SESSION['rol'] = isset($row['rol']) ? $row['rol'] : 'admin';
                    $_SESSION['foto'] = isset($row['foto']) ? $row['foto'] : '';
                    $_SESSION['usuario_foto'] = isset($row['foto']) ? $row['foto'] : '';
                    require_once dirname(__DIR__) . '/../src/Models/audit_log.php';
                    log_activity($row['id'], 'LOGIN', 'auth', 'Inicio de sesión');
                    header('Location: ../../public/index.php');
                    exit;
                }
            }
        }
    }
}

// 2. Sin credenciales válidas en la BD: denegar acceso
// Redirigir con error
header('Location: ../../public/login.php?error=1');
exit;
?>
