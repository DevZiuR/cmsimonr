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
 * guardar_op_ivss.php
 * Recibe POST del formulario nueva_op_ivss.php
 */
require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

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

$op_id_input        = (int)   (isset($_POST['op_id'])            ? $_POST['op_id']            : 0);
$numero_op          = mysqli_real_escape_string($conn, isset($_POST['numero_op'])         ? $_POST['numero_op']         : '');
$fecha              = mysqli_real_escape_string($conn, isset($_POST['fecha'])             ? $_POST['fecha']             : '');
$concepto           = mysqli_real_escape_string($conn, isset($_POST['concepto'])          ? $_POST['concepto']          : '');
$banco              = mysqli_real_escape_string($conn, isset($_POST['banco'])             ? $_POST['banco']             : '');
$numero_cuenta      = mysqli_real_escape_string($conn, isset($_POST['numero_cuenta'])     ? $_POST['numero_cuenta']     : '');
$doc_tipo           = 'IVSS';

$monto_bs           = isset($_POST['monto_bs'])          ? parse_monto_php($_POST['monto_bs'])          : 0.0;
$monto_letras       = mysqli_real_escape_string($conn, isset($_POST['monto_letras'])      ? $_POST['monto_letras']      : '');
$total_retenciones  = isset($_POST['total_retenciones'])  ? parse_monto_php($_POST['total_retenciones'])  : 0.0;
$monto_neto         = isset($_POST['monto_neto'])         ? parse_monto_php($_POST['monto_neto'])         : 0.0;

$comprobante_transf = mysqli_real_escape_string($conn, isset($_POST['comprobante_transf']) ? $_POST['comprobante_transf'] : '');
$pagado_transf      = mysqli_real_escape_string($conn, isset($_POST['pagado_transf'])      ? $_POST['pagado_transf']      : '');
$pagado_banco       = mysqli_real_escape_string($conn, isset($_POST['pagado_banco'])       ? $_POST['pagado_banco']       : '');
$pagado_cuenta      = mysqli_real_escape_string($conn, isset($_POST['pagado_cuenta'])      ? $_POST['pagado_cuenta']      : '');

$pagado_array = array(
    'banco'  => $pagado_banco,
    'cuenta' => $pagado_cuenta,
    'transf' => $pagado_transf
);
$recibe_cedula = json_encode($pagado_array);
$recibe_firma  = $comprobante_transf;
$recibe_fecha  = date('Y-m-d');

$cont_raw = isset($_POST['cont_json']) ? $_POST['cont_json'] : '[]';
$cont_decoded = json_decode($cont_raw, true);
if (!is_array($cont_decoded)) {
    $cont_decoded = json_decode(stripslashes($cont_raw), true);
}
$cont_json = is_array($cont_decoded) ? json_encode($cont_decoded, JSON_UNESCAPED_UNICODE) : '[]';
$rif_beneficiario   = 'CONTRALORÍA MUNICIPAL / IVSS';

$ret_desc     = isset($_POST['ret_desc'])     ? $_POST['ret_desc']     : array();
$ret_codigo   = isset($_POST['ret_codigo'])   ? $_POST['ret_codigo']   : array();
$ret_comision = isset($_POST['ret_comision']) ? $_POST['ret_comision'] : array();

if ($op_id_input > 0) {
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    $sql = "UPDATE ordenes_pago SET
            numero=?, fecha=?, proveedor_id=0, rif_beneficiario=?, banco=?, numero_cuenta=?,
            doc_tipo=?, concepto=?, monto_bruto=?, monto_letras=?, monto_retencion=?, monto_neto_pagar=?,
            recibe_firma=?, recibe_cedula=?, recibe_fecha=?, cont_json=?, updated_by=?, updated_at=?
            WHERE id=? AND status='pendiente'";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1'); die('Error preparando UPDATE OP IVSS: ' . mysqli_error($conn)); }
    mysqli_stmt_bind_param(
        $stmt, 'sssssssdsddssssssi',
        $numero_op, $fecha, $rif_beneficiario, $banco, $numero_cuenta,
        $doc_tipo, $concepto, $monto_bs, $monto_letras, $total_retenciones, $monto_neto,
        $recibe_firma, $recibe_cedula, $recibe_fecha, $cont_json, $current_user, $ahora, $op_id_input
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
    if (!$ok) { die('Error ejecutando UPDATE OP IVSS: ' . mysqli_stmt_error($stmt)); }
    mysqli_query($conn, "DELETE FROM op_retenciones WHERE op_id = $op_id_input");
    $op_id = $op_id_input;
} else {
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    $sql = "INSERT INTO ordenes_pago
            (numero, fecha, proveedor_id, rif_beneficiario, banco, numero_cuenta,
             doc_tipo, concepto, monto_bruto, monto_letras, monto_retencion, monto_neto_pagar,
             recibe_firma, recibe_cedula, recibe_fecha, cont_json, status, created_by, created_at, updated_by, updated_at)
            VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1'); die('Error preparando INSERT OP IVSS: ' . mysqli_error($conn)); }
    mysqli_stmt_bind_param(
        $stmt, 'sssssssdsddssssssss',
        $numero_op, $fecha, $rif_beneficiario, $banco, $numero_cuenta,
        $doc_tipo, $concepto, $monto_bs, $monto_letras, $total_retenciones, $monto_neto,
        $recibe_firma, $recibe_cedula, $recibe_fecha, $cont_json, $current_user, $ahora, $current_user, $ahora
    );
    $ok = mysqli_stmt_execute($stmt);
    if (!$ok) {
        $stmt_err = mysqli_stmt_error($stmt);
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
        die('Error ejecutando INSERT OP IVSS: ' . $stmt_err);
    }
    $op_id = mysqli_insert_id($conn);
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
    if (!$op_id) { die('Error: insert_id=0 OP IVSS.'); }
}


if (!empty($ret_desc) && is_array($ret_desc)) {
    $stmt2 = mysqli_prepare($conn,
        "INSERT INTO op_retenciones (op_id, descripcion, codigo_presupuestario, tasa, monto_comision)
         VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt2) {
        foreach ($ret_desc as $k => $desc) {
            $desc2   = mysqli_real_escape_string($conn, isset($ret_desc[$k])     ? $ret_desc[$k]     : '');
            $codigo  = mysqli_real_escape_string($conn, isset($ret_codigo[$k])   ? $ret_codigo[$k]   : '');
            $tasa    = 0.0;
            $comRaw  = isset($ret_comision[$k]) ? $ret_comision[$k] : '0';
            $com     = parse_monto_php($comRaw);
            if (empty($desc2) && empty($codigo) && $com == 0) continue;
            mysqli_stmt_bind_param($stmt2, 'issdd', $op_id, $desc2, $codigo, $tasa, $com);
            mysqli_stmt_execute($stmt2);
        }
    }
}

header('Location: /sistema/src/Views/ordenes_pago/index.php');
exit;
?>
