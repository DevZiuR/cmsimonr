<?php
/**
 * crear.php — Controller to insert a new producto record
 * Receives POST data, validates, inserts into DB, and redirects.
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

$descripcion         = isset($_POST['descripcion'])         ? trim($_POST['descripcion'])         : '';
$imput_presupuestaria = isset($_POST['imput_presupuestaria']) ? trim($_POST['imput_presupuestaria']) : '';

if ($descripcion === '' || $imput_presupuestaria === '') {
    $back = '/sistema/src/Views/productos/nuevo.php?error=campos_requeridos'
          . '&descripcion='         . urlencode($descripcion)
          . '&imput_presupuestaria=' . urlencode($imput_presupuestaria);
    header('Location: ' . $back);
    exit;
}

$desc_esc  = mysqli_real_escape_string($conn, $descripcion);
$imput_esc = mysqli_real_escape_string($conn, $imput_presupuestaria);

$res = mysqli_query($conn,
    "INSERT INTO productos (descripcion, imput_presupuestaria) VALUES ('$desc_esc', '$imput_esc')"
);

if ($res) {
    header('Location: /sistema/src/Views/productos/index.php?ok=1');
} else {
    $back = '/sistema/src/Views/productos/nuevo.php?error=error_insercion'
          . '&descripcion='         . urlencode($descripcion)
          . '&imput_presupuestaria=' . urlencode($imput_presupuestaria);
    header('Location: ' . $back);
}
exit;
?>
