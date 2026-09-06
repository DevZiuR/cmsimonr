<?php
/**
 * partidas/eliminar.php — Desactiva o reactiva una partida presupuestaria (borrado suave).
 * Nunca borra el registro para no romper la ejecución presupuestaria ya vinculada.
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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$accion = isset($_GET['action']) && $_GET['action'] === 'reactivar' ? 1 : 0;

if ($id <= 0) {
    header('Location: ../../Views/partidas/index.php?error=db_error');
    exit;
}

$res = mysqli_query($conn, "UPDATE partidas SET activa = $accion WHERE id = $id");

if ($res) {
    header("Location: ../../Views/partidas/index.php?ok=" . ($accion ? '3' : '4'));
} else {
    header("Location: ../../Views/partidas/index.php?error=db_error");
}
exit;
