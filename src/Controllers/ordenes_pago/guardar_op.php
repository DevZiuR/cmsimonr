<?php
if (session_id() === '') { session_start(); }
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: ../../Views/ordenes_pago/index.php');
    exit;
}
/*
 * guardar_op.php
 * Recibe POST con los datos del formulario nueva_op.php,
 * inserta o actualiza ordenes_pago y op_retenciones, y redirige a la lista.
 * Compatible PHP 5.6 — sin ??
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';
require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';

/* ── Asegurar columnas para O.S. y tipo de documento y auditoría ── */
@mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN os_id INT NULL AFTER oc_id");
@mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN doc_tipo VARCHAR(10) DEFAULT 'OC' AFTER oc_id");
@mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN created_by VARCHAR(100) NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN updated_by VARCHAR(100) NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN created_at DATETIME NULL");
@mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN updated_at DATETIME NULL");

$current_user = obtener_usuario_auditoria($conn);
if (!function_exists('parse_monto_php')) {
    function parse_monto_php($val) {
        if (!isset($val) || $val === '' || $val === null) return 0.0;
        $str = trim((string)$val);
        if (strpos($str, ',') !== false && strpos($str, '.') !== false) {
            $lastDot   = strrpos($str, '.');
            $lastComma = strrpos($str, ',');
            if ($lastComma > $lastDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif (strpos($str, ',') !== false) {
            $parts = explode(',', $str);
            if (count($parts) === 2 && strlen($parts[1]) === 3) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        }
        return (float)$str;
    }
}

$ahora = date('Y-m-d H:i:s');

/* ── Recoger datos básicos ── */
$op_id_input        = (int)   (isset($_POST['op_id'])            ? $_POST['op_id']            : 0);
$numero_op          = mysqli_real_escape_string($conn, isset($_POST['numero_op'])         ? $_POST['numero_op']         : '');
$fecha              = mysqli_real_escape_string($conn, isset($_POST['fecha'])             ? $_POST['fecha']             : '');
$proveedor_id       = (int)   (isset($_POST['proveedor_id'])     ? $_POST['proveedor_id']     : 0);
$rif_beneficiario   = mysqli_real_escape_string($conn, isset($_POST['rif_beneficiario'])  ? $_POST['rif_beneficiario']  : '');
$concepto           = mysqli_real_escape_string($conn, isset($_POST['concepto'])          ? $_POST['concepto']          : '');
$banco              = mysqli_real_escape_string($conn, isset($_POST['banco'])             ? $_POST['banco']             : '');
$numero_cuenta      = mysqli_real_escape_string($conn, isset($_POST['numero_cuenta'])     ? $_POST['numero_cuenta']     : '');
$oc_id              = (int)   (isset($_POST['oc_id'])            ? $_POST['oc_id']            : 0);
$os_id              = (int)   (isset($_POST['os_id'])            ? $_POST['os_id']            : 0);
$doc_tipo           = mysqli_real_escape_string($conn, isset($_POST['doc_tipo'])          ? $_POST['doc_tipo']          : ($os_id > 0 ? 'OS' : 'OC'));
$monto_bs           = isset($_POST['monto_bs'])          ? parse_monto_php($_POST['monto_bs'])          : 0.0;
$monto_letras       = mysqli_real_escape_string($conn, isset($_POST['monto_letras'])      ? $_POST['monto_letras']      : '');
$total_retenciones  = isset($_POST['total_retenciones'])  ? parse_monto_php($_POST['total_retenciones'])  : 0.0;
$monto_neto         = isset($_POST['monto_neto'])         ? parse_monto_php($_POST['monto_neto'])         : 0.0;
$recibe_firma       = mysqli_real_escape_string($conn, isset($_POST['recibe_firma'])      ? $_POST['recibe_firma']      : '');
$recibe_cedula      = mysqli_real_escape_string($conn, isset($_POST['recibe_cedula'])     ? $_POST['recibe_cedula']     : '');
$cont_raw = isset($_POST['cont_json']) ? $_POST['cont_json'] : '[]';
$cont_decoded = json_decode($cont_raw, true);
if (!is_array($cont_decoded)) {
    $cont_decoded = json_decode(stripslashes($cont_raw), true);
}
$cont_json = is_array($cont_decoded) ? json_encode($cont_decoded, JSON_UNESCAPED_UNICODE) : '[]';

/* ── Arrays de retenciones ── */
$ret_desc     = isset($_POST['ret_desc'])     ? $_POST['ret_desc']     : array();
$ret_codigo   = isset($_POST['ret_codigo'])   ? $_POST['ret_codigo']   : array();
$ret_tasa     = isset($_POST['ret_tasa'])     ? $_POST['ret_tasa']     : array();
$ret_comision = isset($_POST['ret_comision']) ? $_POST['ret_comision'] : array();

$oc_id_val        = $oc_id > 0 ? $oc_id : null;
$os_id_val        = $os_id > 0 ? $os_id : null;
$recibe_fecha_val = !empty($recibe_fecha) ? $recibe_fecha : null;

if ($op_id_input > 0) {
    /* ── UPDATE orden de pago en borrador ── */
    $sql = "UPDATE ordenes_pago SET
            numero=?, fecha=?, proveedor_id=?, rif_beneficiario=?, banco=?, numero_cuenta=?,
            oc_id=?, os_id=?, doc_tipo=?, concepto=?, monto_bruto=?, monto_letras=?, monto_retencion=?, monto_neto_pagar=?,
            recibe_firma=?, recibe_cedula=?, recibe_fecha=?, cont_json=?, updated_by=?, updated_at=?
            WHERE id=? AND status='pendiente'";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { die('Error preparando UPDATE OP: ' . mysqli_error($conn)); }
    mysqli_stmt_bind_param(
        $stmt, 'ssisssiisdsddssssssi',
        $numero_op, $fecha, $proveedor_id, $rif_beneficiario, $banco, $numero_cuenta,
        $oc_id_val, $os_id_val, $doc_tipo, $concepto, $monto_bs, $monto_letras,
        $total_retenciones, $monto_neto, $recibe_firma, $recibe_cedula, $recibe_fecha_val,
        $cont_json, $current_user, $ahora, $op_id_input
    );
    mysqli_stmt_execute($stmt);
    mysqli_query($conn, "DELETE FROM op_retenciones WHERE op_id = $op_id_input");
    $op_id = $op_id_input;
} else {
    /* ── INSERT nueva orden de pago ── */
    $sql = "INSERT INTO ordenes_pago
            (numero, fecha, proveedor_id, rif_beneficiario, banco, numero_cuenta,
             oc_id, os_id, doc_tipo, concepto, monto_bruto, monto_letras, monto_retencion, monto_neto_pagar,
             recibe_firma, recibe_cedula, recibe_fecha, cont_json, status, created_by, created_at, updated_by, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { die('Error preparando INSERT OP: ' . mysqli_error($conn)); }
    mysqli_stmt_bind_param(
        $stmt, 'ssisssiisdsddsssssssss',
        $numero_op, $fecha, $proveedor_id, $rif_beneficiario, $banco, $numero_cuenta,
        $oc_id_val, $os_id_val, $doc_tipo, $concepto, $monto_bs, $monto_letras,
        $total_retenciones, $monto_neto, $recibe_firma, $recibe_cedula, $recibe_fecha_val,
        $cont_json, $current_user, $ahora, $current_user, $ahora
    );
    mysqli_stmt_execute($stmt);
    $op_id = mysqli_insert_id($conn);
    if (!$op_id) { die('Error al guardar la orden de pago: ' . mysqli_error($conn)); }
}

/* ── Insertar retenciones ── */
if (!empty($ret_desc) && is_array($ret_desc)) {
    $stmt2 = mysqli_prepare($conn,
        "INSERT INTO op_retenciones (op_id, descripcion, codigo_presupuestario, tasa, monto_comision)
         VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt2) {
        foreach ($ret_desc as $k => $desc) {
            $desc2   = mysqli_real_escape_string($conn, isset($ret_desc[$k])     ? $ret_desc[$k]     : '');
            $codigo  = mysqli_real_escape_string($conn, isset($ret_codigo[$k])   ? $ret_codigo[$k]   : '');
            $tasa    = (float) (isset($ret_tasa[$k])     ? $ret_tasa[$k]     : 0);
            $comRaw  = isset($ret_comision[$k]) ? $ret_comision[$k] : '0';
            $com     = parse_monto_php($comRaw);

            if (empty($desc2) && empty($codigo) && $tasa == 0 && $com == 0) continue;

            mysqli_stmt_bind_param($stmt2, 'issdd', $op_id, $desc2, $codigo, $tasa, $com);
            mysqli_stmt_execute($stmt2);
        }
    }
}

require_once dirname(dirname(dirname(__DIR__))) . '/src/Models/audit_log.php';
log_activity((int) $_SESSION['usuario_id'], ($op_id_input > 0 ? 'EDITAR_OP' : 'CREAR_OP'), 'OP', 'OP ' . $numero_op . ' (id ' . $op_id . ') | Bs ' . number_format($monto_neto, 2, ',', '.'));
header("Location: ../../Views/ordenes_pago/index.php?ok=1");
exit();
?>
