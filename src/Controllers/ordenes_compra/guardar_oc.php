<?php
if (session_id() === '') { session_start(); }
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_compra/index.php');
    exit;
}
require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';

// Asegurar columnas de auditoría
@mysqli_query($conn, "ALTER TABLE ordenes_compra ADD COLUMN created_by VARCHAR(100) NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_compra ADD COLUMN updated_by VARCHAR(100) NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_compra ADD COLUMN created_at DATETIME NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_compra ADD COLUMN updated_at DATETIME NULL");

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

// Recoger datos del formulario
$oc_id_input     = isset($_POST['oc_id']) ? (int) $_POST['oc_id'] : 0;
$proveedor_id    = (int) $_POST['proveedor_id'];
$numero_orden    = mysqli_real_escape_string($conn, $_POST['numero_orden']);
$fecha           = mysqli_real_escape_string($conn, $_POST['fecha']);
$base_imponible  = parse_float_input(isset($_POST['base_imponible']) ? $_POST['base_imponible'] : 0);
$sat_monto       = parse_float_input(isset($_POST['sat_monto']) ? $_POST['sat_monto'] : 0);
$iva_monto       = parse_float_input(isset($_POST['iva_monto']) ? $_POST['iva_monto'] : 0);
$total_general   = parse_float_input(isset($_POST['total_general']) ? $_POST['total_general'] : 0);
$monto_letras    = mysqli_real_escape_string($conn, $_POST['monto_letras']);
$renglones       = json_decode($_POST['renglones_json'], true);
$tipo            = (isset($_POST['tipo']) && $_POST['tipo'] === 'farmacia') ? 'farmacia' : 'normal';

// Partidas (arrays paralelos)
$partidas        = isset($_POST['partida'])       ? $_POST['partida']       : array();
$montos_partida  = isset($_POST['monto_partida']) ? $_POST['monto_partida'] : array();

// iva_porcentaje = 0 si es farmacia (exento), 16 si es normal
$iva_pct = ($tipo === 'farmacia') ? 0 : 16;

if ($oc_id_input > 0) {
    $oc_id = $oc_id_input;
    $sql = "UPDATE ordenes_compra SET
            numero_orden = ?, fecha = ?, proveedor_id = ?, base_imponible = ?,
            sat_monto = ?, iva_porcentaje = $iva_pct, iva_monto = ?, total_general = ?, monto_letras = ?, tipo = ?,
            updated_by = ?, updated_at = ?
            WHERE id = ? AND status = 'pendiente'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssiddddssssi',
        $numero_orden,
        $fecha,
        $proveedor_id,
        $base_imponible,
        $sat_monto,
        $iva_monto,
        $total_general,
        $monto_letras,
        $tipo,
        $current_user,
        $ahora,
        $oc_id
    );
    mysqli_stmt_execute($stmt);

    // Borrar renglones y partidas viejas para reemplazarlas
    mysqli_query($conn, "DELETE FROM oc_renglones WHERE oc_id = $oc_id");
    mysqli_query($conn, "DELETE FROM oc_partidas WHERE oc_id = $oc_id");
} else {
    $sql = "INSERT INTO ordenes_compra 
            (numero_orden, fecha, proveedor_id, base_imponible, sat_porcentaje, sat_monto, iva_porcentaje, iva_monto, total_general, monto_letras, status, tipo, created_by, created_at, updated_by, updated_at)
            VALUES (?, ?, ?, ?, 0.1, ?, $iva_pct, ?, ?, ?, 'pendiente', ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssiddddssssss',
        $numero_orden,
        $fecha,
        $proveedor_id,
        $base_imponible,
        $sat_monto,
        $iva_monto,
        $total_general,
        $monto_letras,
        $tipo,
        $current_user,
        $ahora,
        $current_user,
        $ahora
    );
    mysqli_stmt_execute($stmt);
    $oc_id = mysqli_insert_id($conn);
}

if (!$oc_id) {
    die("Error al guardar la orden: " . mysqli_error($conn));
}

//  renglones
if (is_array($renglones)) {
    $stmt2 = mysqli_prepare($conn,
        "INSERT INTO oc_renglones (oc_id, descripcion, imput_presupuestaria, unidad, cantidad, precio_unitario, total, exento)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
	foreach ($renglones as $r) {
		$desc   = isset($r['desc'])   ? $r['desc']   : '';
		$imput  = isset($r['imput'])  ? $r['imput']  : '';
		$unidad = isset($r['unidad']) ? $r['unidad'] : '1';
		$cant   = (float) (isset($r['cant']) ? $r['cant'] : 0);
		$pu     = (float) (isset($r['pu'])   ? $r['pu']   : 0);
		$tot    = $cant * $pu;
		$exento = (isset($r['exento']) && $r['exento']) ? 1 : 0;
		mysqli_stmt_bind_param($stmt2, 'isssdddi', $oc_id, $desc, $imput, $unidad, $cant, $pu, $tot, $exento);
		mysqli_stmt_execute($stmt2);
	}
}

//  partidas
if (!empty($partidas)) {
    $stmt3 = mysqli_prepare($conn,
        "INSERT INTO oc_partidas (oc_id, partida, monto) VALUES (?, ?, ?)"
    );
    foreach ($partidas as $k => $partida) {
        if (empty($partida)) continue;
        $monto_p = parse_float_input(isset($montos_partida[$k]) ? $montos_partida[$k] : 0);
        mysqli_stmt_bind_param($stmt3, 'isd', $oc_id, $partida, $monto_p);
        mysqli_stmt_execute($stmt3);
    }
}

// Redirigir a la lista
log_activity((int) $_SESSION['usuario_id'], ($oc_id_input > 0 ? 'EDITAR_OC' : 'CREAR_OC'), 'OC', 'OC ' . $numero_orden . ' (id ' . $oc_id . ') | Bs ' . number_format($total_general, 2, ',', '.'));
header("Location: ../../Views/ordenes_compra/index.php?ok=1");
exit();
?>
