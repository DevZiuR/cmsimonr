<?php
/*
 * anular_oc.php
 * Redirige al controlador de papelera — ya no "anula" en base de datos
 * directamente, sino que mueve la OC a la papelera para eliminación
 * suave (soft-delete via deleted_at).
 * PHP 5.6 — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_compra/index.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
header("Location: papelera_oc.php?id=" . $id);
exit();
?>
