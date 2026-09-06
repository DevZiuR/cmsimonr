<?php
session_start();

if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

if (!$es_admin) {
    header('Location: /sistema/public/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sistema/src/Views/perfil/usuarios.php');
    exit;
}

$usuario_id = isset($_POST['usuario_id']) ? intval($_POST['usuario_id']) : 0;

if ($usuario_id <= 0) {
    header('Location: /sistema/src/Views/perfil/usuarios.php');
    exit;
}

require_once '../../config/conexion.php';

/* ──  Randoom Secure Password (PHP 5.6) ── */
$chars_lower   = 'abcdefghjkmnpqrstuvwxyz';   // no i/l/o (ambiguous)
$chars_upper   = 'ABCDEFGHJKMNPQRSTUVWXYZ';
$chars_digits  = '23456789';                   // no 0/1 (ambiguous)
$chars_special = '@#$%!';

// Garantizar al menos uno de cada grupo
$password  = '';
$password .= substr($chars_upper,   mt_rand(0, strlen($chars_upper)   - 1), 1);
$password .= substr($chars_upper,   mt_rand(0, strlen($chars_upper)   - 1), 1);
$password .= substr($chars_lower,   mt_rand(0, strlen($chars_lower)   - 1), 1);
$password .= substr($chars_lower,   mt_rand(0, strlen($chars_lower)   - 1), 1);
$password .= substr($chars_lower,   mt_rand(0, strlen($chars_lower)   - 1), 1);
$password .= substr($chars_digits,  mt_rand(0, strlen($chars_digits)  - 1), 1);
$password .= substr($chars_digits,  mt_rand(0, strlen($chars_digits)  - 1), 1);
$password .= substr($chars_digits,  mt_rand(0, strlen($chars_digits)  - 1), 1);
$password .= substr($chars_special, mt_rand(0, strlen($chars_special) - 1), 1);
$password .= substr($chars_special, mt_rand(0, strlen($chars_special) - 1), 1);

// Llenar los 2 caracteres restantes del pool combinado
$chars_all = $chars_lower . $chars_upper . $chars_digits . $chars_special;
$password .= substr($chars_all, mt_rand(0, strlen($chars_all) - 1), 1);
$password .= substr($chars_all, mt_rand(0, strlen($chars_all) - 1), 1);

// Barajar para que los grupos de garantía no aparezcan en orden predecible
$password = str_shuffle($password);

$nueva_pass_hash = md5($password);

$stmt = mysqli_prepare($conn, "UPDATE usuarios SET password = ? WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'si', $nueva_pass_hash, $usuario_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Store plain password in session for one-time display
$_SESSION['reset_flash_pass'] = $password;

header('Location: /sistema/src/Views/perfil/usuarios.php?ok=' . $usuario_id);
exit;
