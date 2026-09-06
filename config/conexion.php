<?php
// Configurar zona horaria local de Caracas (-04:00)
date_default_timezone_set('America/Caracas');

$conn = mysqli_connect('localhost', 'root', '', 'contraloria_db');

if (!$conn) {
    die('Error de conexion: ' . mysqli_connect_error());
}

// Asegurar columna foto en tabla usuarios
$res_col = @mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'foto'");
if ($res_col && @mysqli_num_rows($res_col) == 0) {
    @mysqli_query($conn, "ALTER TABLE usuarios ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
}

/* Ruta absoluta al sidebar compartido */
if (!defined('SIDEBAR_PATH')) {
    define('SIDEBAR_PATH', dirname(__DIR__) . '/includes/sidebar.php');
}

/*
 * Nombre real del usuario actual para auditoría (created_by / updated_by).
 * Se resuelve desde la tabla usuarios usando $_SESSION['usuario_id']
 * y solo cae a la sesión/`Sistema` como último recurso.
 * Compatible PHP 5.4+.
 */
function obtener_usuario_auditoria($conn) {
    if (isset($_SESSION['usuario_id']) && (int) $_SESSION['usuario_id'] > 0) {
        $uid = (int) $_SESSION['usuario_id'];
        $res = @mysqli_query($conn, "SELECT nombre FROM usuarios WHERE id = $uid LIMIT 1");
        if ($res !== false) {
            $fila = @mysqli_fetch_assoc($res);
            if ($fila !== null && !empty($fila['nombre'])) {
                return $fila['nombre'];
            }
        }
    }
    return isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Sistema'));
}

?>
