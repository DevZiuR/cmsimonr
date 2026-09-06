<?php
/**
 * src/Controllers/ejecucion/eliminar.php
 * Elimina una fila de ejecución presupuestaria vía AJAX o POST
 * Admin only — PHP 5.6 compatible
 */
if (session_id() === '') { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(array('success' => false, 'error' => 'No autenticado'));
    exit;
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(array('success' => false, 'error' => 'Acceso denegado'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => 'Método no permitido'));
    exit;
}

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('success' => false, 'error' => 'ID inválido'));
    exit;
}

$ok = mysqli_query($conn, "DELETE FROM ejecucion_presupuestaria WHERE id = $id");
if ($ok) {
    echo json_encode(array('success' => true));
} else {
    echo json_encode(array('success' => false, 'error' => mysqli_error($conn)));
}
