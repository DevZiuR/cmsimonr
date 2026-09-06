<?php
/**
 * src/Models/audit_log.php
 * Registro de auditoría (log_activity).
 * Inserta un evento en la tabla `audit_log`. Nunca rompe el flujo principal:
 * si la conexión falla o el INSERT falla, simplemente se ignora.
 */

function log_activity($user_id, $action, $module, $detail = null) {
    global $conn;

    if (!isset($conn) || !$conn) {
        $conexion_path = __DIR__ . '/../../config/conexion.php';
        if (file_exists($conexion_path)) {
            @include_once $conexion_path;
        }
        if (!isset($conn) || !$conn) {
            return false;
        }
    }

    $user_id = (int) $user_id;
    $action  = mysqli_real_escape_string($conn, (string) $action);
    $module  = mysqli_real_escape_string($conn, (string) $module);
    $detail  = ($detail !== null) ? mysqli_real_escape_string($conn, (string) $detail) : null;
    $ip      = mysqli_real_escape_string($conn, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $nombre  = isset($_SESSION['usuario_nombre']) ? mysqli_real_escape_string($conn, (string) $_SESSION['usuario_nombre']) : '';

    $sql = "INSERT INTO audit_log (user_id, usuario_nombre, action, module, detail, ip_address)
            VALUES ($user_id, '$nombre', '$action', '$module', " . ($detail === null ? 'NULL' : "'$detail'") . ", '$ip')";
    $res = @mysqli_query($conn, $sql);

    return ($res !== false);
}
