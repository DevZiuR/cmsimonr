<?php
/**
 * src/Controllers/ejecucion/eliminar_todo.php
 * Elimina TODAS las filas de ejecución presupuestaria de un mes/año dado.
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

$mes  = isset($_POST['mes'])  ? (int) $_POST['mes']  : 0;
$anio = isset($_POST['anio']) ? (int) $_POST['anio'] : 0;

if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
    echo json_encode(array('success' => false, 'error' => 'Mes o año inválido'));
    exit;
}

$ok = mysqli_query($conn, "DELETE FROM ejecucion_presupuestaria WHERE mes = $mes AND anio = $anio");
if ($ok) {
    echo json_encode(array('success' => true, 'deleted' => mysqli_affected_rows($conn)));
} else {
    echo json_encode(array('success' => false, 'error' => mysqli_error($conn)));
}
