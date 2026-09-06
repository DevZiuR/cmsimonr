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

$nombre_actual  = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$usuario_nuevo  = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
$pass_nueva     = isset($_POST['password_nueva']) ? trim($_POST['password_nueva']) : '';
$pass_confirmar = isset($_POST['password_confirmar']) ? trim($_POST['password_confirmar']) : '';
$eliminar_foto  = isset($_POST['eliminar_foto']) && $_POST['eliminar_foto'] === '1';

$usuario_id = isset($_SESSION['usuario_id']) ? intval($_SESSION['usuario_id']) : 0;

if ($nombre_actual === '' || $usuario_nuevo === '') {
    header('Location: ../../src/Views/perfil/index.php?error=datos_vacios');
    exit;
}

if ($pass_nueva !== '' || $pass_confirmar !== '') {
    if ($pass_nueva !== $pass_confirmar) {
        header('Location: ../../src/Views/perfil/index.php?error=password_mismatch');
        exit;
    }
}

$nueva_foto = null;
$quitar_foto = false;

if ($eliminar_foto) {
    $quitar_foto = true;
    $upload_dir = '../../assets/img/avatars/';
    if (isset($conn) && $conn && $usuario_id > 0) {
        $q_old = @mysqli_query($conn, "SELECT foto FROM usuarios WHERE id = $usuario_id LIMIT 1");
        if ($q_old && $r_old = @mysqli_fetch_assoc($q_old)) {
            if (!empty($r_old['foto']) && file_exists($upload_dir . $r_old['foto'])) {
                @unlink($upload_dir . $r_old['foto']);
            }
        }
    }
} elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['foto']['tmp_name'];
    $file_name = $_FILES['foto']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    
    if (in_array($file_ext, $allowed_exts)) {
        $upload_dir = '../../assets/img/avatars/';
        if (!file_exists($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        $new_filename = 'user_' . $usuario_id . '_' . time() . '.' . $file_ext;
        $destination = $upload_dir . $new_filename;
        if (@move_uploaded_file($file_tmp, $destination)) {
            $nueva_foto = $new_filename;
            
            if (isset($conn) && $conn && $usuario_id > 0) {
                $q_old = @mysqli_query($conn, "SELECT foto FROM usuarios WHERE id = $usuario_id LIMIT 1");
                if ($q_old && $r_old = @mysqli_fetch_assoc($q_old)) {
                    if (!empty($r_old['foto']) && file_exists($upload_dir . $r_old['foto'])) {
                        @unlink($upload_dir . $r_old['foto']);
                    }
                }
            }
        }
    }
}

if (isset($conn) && $conn && $usuario_id > 0) {
    if ($quitar_foto) {
        if ($pass_nueva !== '') {
            $pass_md5 = md5($pass_nueva);
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ?, password = ?, foto = NULL WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssi', $nombre_actual, $usuario_nuevo, $pass_md5, $usuario_id);
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ?, foto = NULL WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssi', $nombre_actual, $usuario_nuevo, $usuario_id);
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
            }
        }
        $_SESSION['foto'] = '';
        $_SESSION['usuario_foto'] = '';
    } elseif ($nueva_foto !== null) {
        if ($pass_nueva !== '') {
            $pass_md5 = md5($pass_nueva);
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ?, password = ?, foto = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssi', $nombre_actual, $usuario_nuevo, $pass_md5, $nueva_foto, $usuario_id);
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ?, foto = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssi', $nombre_actual, $usuario_nuevo, $nueva_foto, $usuario_id);
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
            }
        }
        $_SESSION['foto'] = $nueva_foto;
        $_SESSION['usuario_foto'] = $nueva_foto;
    } else {
        if ($pass_nueva !== '') {
            $pass_md5 = md5($pass_nueva);
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ?, password = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssi', $nombre_actual, $usuario_nuevo, $pass_md5, $usuario_id);
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssi', $nombre_actual, $usuario_nuevo, $usuario_id);
                @mysqli_stmt_execute($stmt);
                @mysqli_stmt_close($stmt);
            }
        }
    }
}

$_SESSION['nombre'] = $nombre_actual;
$_SESSION['usuario_nombre'] = $nombre_actual;
$_SESSION['usuario'] = $usuario_nuevo;

header('Location: ../../src/Views/perfil/index.php?ok=1');
exit;
