<?php
if (session_id() === '') { session_start(); }
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_servicio/index.php');
    exit;
}
/*
 * guardar_os.php
 * Inserta (nueva) o actualiza (borrador existente) una Orden de Servicio.
 * Compatible PHP 5.6 — sin ??
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';

// Asegurar columnas de auditoría
@mysqli_query($conn, "ALTER TABLE ordenes_servicio ADD COLUMN created_by VARCHAR(100) NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_servicio ADD COLUMN updated_by VARCHAR(100) NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_servicio ADD COLUMN created_at DATETIME NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_servicio ADD COLUMN updated_at DATETIME NULL");

$current_user = obtener_usuario_auditoria($conn);
$ahora = date('Y-m-d H:i:s');

if (!function_exists('parse_float_input')) {
    function parse_float_input($val) {
        if (is_numeric($val)) return (float) $val;
        $val = trim((string) $val);
        if ($val === '') return 0.0;
        if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
            if (strrpos($val, '.') > strrpos($val, ',')) {
                $val = str_replace(',', '', $val);
            } else {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            }
        } elseif (strpos($val, ',') !== false) {
            $val = str_replace(',', '.', $val);
        }
        $val = preg_replace('/[^0-9.-]/', '', $val);
        return (float) $val;
    }
}

$os_id_edit      = (int) (isset($_POST['os_id']) ? $_POST['os_id'] : 0);
$proveedor_id    = (int) (isset($_POST['proveedor_id']) ? $_POST['proveedor_id'] : 0);
$numero_os       = mysqli_real_escape_string($conn, isset($_POST['numero_os']) ? $_POST['numero_os'] : (isset($_POST['numero_orden']) ? $_POST['numero_orden'] : ''));
$fecha           = mysqli_real_escape_string($conn, isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d'));
$lugar_entrega   = mysqli_real_escape_string($conn, isset($_POST['lugar_entrega']) ? $_POST['lugar_entrega'] : '');
$base_imponible  = parse_float_input(isset($_POST['base_imponible']) ? $_POST['base_imponible'] : 0);
$sat_monto       = parse_float_input(isset($_POST['sat_monto']) ? $_POST['sat_monto'] : 0);
$iva_monto       = parse_float_input(isset($_POST['iva_monto']) ? $_POST['iva_monto'] : 0);
$monto_total     = parse_float_input(isset($_POST['monto_total']) ? $_POST['monto_total'] : (isset($_POST['total_general']) ? $_POST['total_general'] : 0));
$monto_letras    = mysqli_real_escape_string($conn, isset($_POST['monto_letras']) ? $_POST['monto_letras'] : '');
$renglones_json  = isset($_POST['renglones_json']) ? $_POST['renglones_json'] : '[]';
$renglones       = json_decode($renglones_json, true);

$partidas        = isset($_POST['partida'])       ? $_POST['partida']       : array();
$montos_partida  = isset($_POST['monto_partida']) ? $_POST['monto_partida'] : array();

if ($os_id_edit > 0) {
    /* ── UPDATE de una OS en borrador ── */
    $stmt = mysqli_prepare($conn,
        "UPDATE ordenes_servicio
         SET numero_os=?, fecha=?, proveedor_id=?, lugar_entrega=?,
             base_imponible=?, sat_porcentaje=0.1, sat_monto=?,
             iva_porcentaje=16.0, iva_monto=?, monto_total=?, monto_letras=?,
             updated_by=?, updated_at=?
         WHERE id=? AND status='pendiente'"
    );
    if (!$stmt) { die("Error preparando UPDATE OS: " . mysqli_error($conn)); }
    mysqli_stmt_bind_param($stmt, 'ssisddddsssi',
        $numero_os, $fecha, $proveedor_id, $lugar_entrega,
        $base_imponible, $sat_monto, $iva_monto, $monto_total, $monto_letras,
        $current_user, $ahora,
        $os_id_edit
    );
    mysqli_stmt_execute($stmt);

    // Eliminar renglones y partidas anteriores
    mysqli_query($conn, "DELETE FROM os_renglones WHERE os_id = $os_id_edit");
    mysqli_query($conn, "DELETE FROM os_partidas  WHERE os_id = $os_id_edit");
    $os_id = $os_id_edit;
} else {
    /* ── INSERT nueva OS ── */
    $stmt = mysqli_prepare($conn,
        "INSERT INTO ordenes_servicio
         (numero_os, fecha, proveedor_id, lugar_entrega, base_imponible, sat_porcentaje, sat_monto, iva_porcentaje, iva_monto, monto_total, monto_letras, status, created_by, created_at, updated_by, updated_at)
         VALUES (?, ?, ?, ?, ?, 0.1, ?, 16.0, ?, ?, ?, 'pendiente', ?, ?, ?, ?)"
    );
    if (!$stmt) { die("Error preparando consulta OS: " . mysqli_error($conn)); }
    mysqli_stmt_bind_param($stmt, 'ssisddddsssss',
        $numero_os, $fecha, $proveedor_id, $lugar_entrega,
        $base_imponible, $sat_monto, $iva_monto, $monto_total, $monto_letras,
        $current_user, $ahora, $current_user, $ahora
    );
    mysqli_stmt_execute($stmt);
    $os_id = mysqli_insert_id($conn);
    if (!$os_id) { die("Error al guardar la orden de servicio: " . mysqli_error($conn)); }
}


// Insertar renglones
if (is_array($renglones) && !empty($renglones)) {
    $stmt2 = mysqli_prepare($conn,
        "INSERT INTO os_renglones (os_id, descripcion, imput_presupuestaria, unidad, cantidad, precio_unitario, total)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt2) {
        foreach ($renglones as $r) {
            $desc   = isset($r['desc'])   ? $r['desc']   : '';
            $imput  = isset($r['imput'])  ? $r['imput']  : '';
            $unidad = isset($r['unidad']) ? $r['unidad'] : '1';
            $cant   = (float) (isset($r['cant']) ? $r['cant'] : 0);
            $pu     = (float) (isset($r['pu'])   ? $r['pu']   : 0);
            $tot    = $cant * $pu;
            mysqli_stmt_bind_param($stmt2, 'isssddd', $os_id, $desc, $imput, $unidad, $cant, $pu, $tot);
            mysqli_stmt_execute($stmt2);
        }
    }
}

// Insertar partidas
if (!empty($partidas)) {
    $stmt3 = mysqli_prepare($conn,
        "INSERT INTO os_partidas (os_id, partida, monto) VALUES (?, ?, ?)"
    );
    if ($stmt3) {
        foreach ($partidas as $k => $partida_val) {
            if (empty($partida_val)) continue;
            $monto_p_raw = isset($montos_partida[$k]) ? $montos_partida[$k] : '0';
            $monto_p     = parse_float_input($monto_p_raw);
            mysqli_stmt_bind_param($stmt3, 'isd', $os_id, $partida_val, $monto_p);
            mysqli_stmt_execute($stmt3);
        }
    }
}

require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
log_activity((int) $_SESSION['usuario_id'], ($os_id_edit > 0 ? 'EDITAR_OS' : 'CREAR_OS'), 'OS', 'OS ' . $numero_os . ' (id ' . $os_id . ') | Bs ' . number_format($monto_total, 2, ',', '.'));
header("Location: ../../Views/ordenes_servicio/index.php?ok=1");
exit();
?>
