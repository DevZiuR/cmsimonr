<?php
/**
 * partidas/guardar.php — Crea o actualiza una partida presupuestaria del catálogo.
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /sistema/src/Views/partidas/index.php');
    exit;
}
require_once '../../../config/conexion.php';

$id          = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$codigo      = isset($_POST['codigo']) ? trim($_POST['codigo']) : '';
$denominacion = isset($_POST['denominacion']) ? trim($_POST['denominacion']) : '';
$grupo       = isset($_POST['grupo']) ? trim($_POST['grupo']) : 'OTRAS PARTIDAS';

$codigo_esc = mysqli_real_escape_string($conn, $codigo);
$denom_esc  = mysqli_real_escape_string($conn, $denominacion);
$grupo_esc  = mysqli_real_escape_string($conn, $grupo);

if ($codigo_esc === '' || $denom_esc === '') {
    $back = "index.php?error=campos_requeridos"
        . "&codigo=" . urlencode($codigo)
        . "&denominacion=" . urlencode($denominacion)
        . "&grupo=" . urlencode($grupo);
    header("Location: " . $back);
    exit;
}

/* Verificar duplicado (excluyendo la propia partida al editar) */
$excluir = ($id > 0) ? " AND id <> $id" : "";
$res_check = mysqli_query($conn, "SELECT id FROM partidas WHERE codigo = '$codigo_esc'$excluir LIMIT 1");
if ($res_check && mysqli_num_rows($res_check) > 0) {
    $back = "index.php?error=duplicado"
        . "&codigo=" . urlencode($codigo)
        . "&denominacion=" . urlencode($denominacion)
        . "&grupo=" . urlencode($grupo);
    header("Location: " . $back);
    exit;
}

if ($id > 0) {
    $res = mysqli_query($conn, "UPDATE partidas SET codigo = '$codigo_esc', denominacion = '$denom_esc', grupo = '$grupo_esc' WHERE id = $id");
    $redirect = ($res) ? "index.php?ok=2" : "index.php?error=db_error";
} else {
    $res = mysqli_query($conn, "INSERT INTO partidas (codigo, denominacion, grupo, activa) VALUES ('$codigo_esc', '$denom_esc', '$grupo_esc', 1)");
    $redirect = ($res) ? "index.php?ok=1" : "index.php?error=db_error";
}

header("Location: " . $redirect);
exit;
