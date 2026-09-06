<?php
/*
 * buscar_op.php
 * Devuelve JSON con total_general, fecha, iva_monto, proveedor_id y array de partidas de una OC u OS.
 * GET: id = ordenes_compra.id o ordenes_servicio.id
 * GET: tipo = 'oc' o 'os' (default: 'oc')
 * Compatible PHP 5.6 — sin ??
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
header('Content-Type: application/json');

require_once dirname(dirname(dirname(__DIR__))) . '/config/conexion.php';

$id   = isset($_GET['id'])   ? (int) $_GET['id']   : 0;
$tipo = isset($_GET['tipo']) ? strtolower(trim($_GET['tipo'])) : 'oc';

if (!$id) {
    echo json_encode(array('error' => 'id requerido', 'total_general' => 0, 'partidas' => array()));
    exit();
}

if ($tipo === 'os') {
    /* ── Total general, fecha y monto de IVA de la orden de servicio ── */
    $res = mysqli_query($conn, "SELECT monto_total AS total_general, base_imponible, fecha, iva_monto, proveedor_id, lugar_entrega, numero_os, sat_monto FROM ordenes_servicio WHERE id = $id");
    $os  = mysqli_fetch_assoc($res);

    if (!$os) {
        echo json_encode(array('error' => 'no encontrado', 'total_general' => 0, 'fecha' => '', 'iva_monto' => 0, 'partidas' => array()));
        exit();
    }

    $total_general      = (float) $os['total_general'];
    $base_imponible     = (float) (isset($os['base_imponible']) ? $os['base_imponible'] : $total_general);
    $fecha_doc          = isset($os['fecha'])          ? $os['fecha']          : '';
    $iva_monto          = (float) (isset($os['iva_monto'])  ? $os['iva_monto']  : 0);
    $proveedor_id       = (int)   (isset($os['proveedor_id']) ? $os['proveedor_id'] : 0);
    $lugar_entrega_os   = isset($os['lugar_entrega'])  ? $os['lugar_entrega']  : '';
    $numero_os_val      = isset($os['numero_os'])      ? $os['numero_os']      : '';
    $sat_monto          = (float) (isset($os['sat_monto'])  ? $os['sat_monto']  : ($base_imponible * 0.001));

    /* ── Renglones de la OS: concatenar descripciones para detección de ARRENDAMIENTO ── */
    $res_reng   = mysqli_query($conn, "SELECT descripcion FROM os_renglones WHERE os_id = $id ORDER BY id ASC");
    $desc_parts = array();
    if ($lugar_entrega_os !== '') {
        $desc_parts[] = $lugar_entrega_os;
    }
    while ($reng = mysqli_fetch_assoc($res_reng)) {
        if (!empty($reng['descripcion'])) {
            $desc_parts[] = $reng['descripcion'];
        }
    }
    $descripcion_os = implode(' ', $desc_parts);

    /* ── Partidas de la OS ── */
    $res2     = mysqli_query($conn, "SELECT partida, monto FROM os_partidas WHERE os_id = $id ORDER BY id ASC");
    $partidas = array();
    $sum_partidas = 0;

    while ($row = mysqli_fetch_assoc($res2)) {
        $m = (float) (isset($row['monto']) ? $row['monto'] : 0);
        $sum_partidas += $m;
        $partidas[] = array(
            'partida' => (isset($row['partida']) ? $row['partida'] : ''),
            'monto'   => $m
        );
    }

    if (empty($partidas) || ($base_imponible > 0 && $sum_partidas < ($base_imponible * 0.5))) {
        $partidas = array();
        $res_reng_p = mysqli_query($conn, "SELECT imput_presupuestaria AS partida, SUM(total) AS monto FROM os_renglones WHERE os_id = $id AND imput_presupuestaria IS NOT NULL AND imput_presupuestaria != '' GROUP BY imput_presupuestaria ORDER BY id ASC");
        while ($rp = mysqli_fetch_assoc($res_reng_p)) {
            $partidas[] = array(
                'partida' => $rp['partida'],
                'monto'   => (float) $rp['monto']
            );
        }
        if ($iva_monto > 0) {
            $partidas[] = array('partida' => '4.03.18.01.00', 'monto' => $iva_monto);
        }
        if ($sat_monto > 0) {
            $partidas[] = array('partida' => '4.03.18.99.00', 'monto' => $sat_monto);
        }
    }
} else {
    /* ── Total general, base imponible, fecha y montos de la orden de compra ── */
    $res = mysqli_query($conn, "SELECT total_general, base_imponible, sat_monto, fecha, iva_monto, proveedor_id, numero_orden FROM ordenes_compra WHERE id = $id");
    $oc  = mysqli_fetch_assoc($res);

    if (!$oc) {
        echo json_encode(array('error' => 'no encontrado', 'total_general' => 0, 'fecha' => '', 'iva_monto' => 0, 'partidas' => array()));
        exit();
    }

    $total_general  = (float) $oc['total_general'];
    $base_imponible = (float) (isset($oc['base_imponible']) ? $oc['base_imponible'] : $total_general);
    $fecha_doc      = isset($oc['fecha']) ? $oc['fecha'] : '';
    $iva_monto      = (float) (isset($oc['iva_monto']) ? $oc['iva_monto'] : 0);
    $sat_monto      = (float) (isset($oc['sat_monto']) ? $oc['sat_monto'] : ($base_imponible * 0.001));
    $proveedor_id   = (int)   (isset($oc['proveedor_id']) ? $oc['proveedor_id'] : 0);
    $numero_oc_val  = isset($oc['numero_orden']) ? $oc['numero_orden'] : '';

    /* ── Partidas de la OC ── */
    $res2     = mysqli_query($conn, "SELECT partida, monto FROM oc_partidas WHERE oc_id = $id ORDER BY id ASC");
    $partidas = array();
    $sum_partidas = 0;

    while ($row = mysqli_fetch_assoc($res2)) {
        $m = (float) (isset($row['monto']) ? $row['monto'] : 0);
        $sum_partidas += $m;
        $partidas[] = array(
            'partida' => (isset($row['partida']) ? $row['partida'] : ''),
            'monto'   => $m
        );
    }

    if (empty($partidas) || ($base_imponible > 0 && $sum_partidas < ($base_imponible * 0.5))) {
        $partidas = array();
        $res_reng_p = mysqli_query($conn, "SELECT imput_presupuestaria AS partida, SUM(total) AS monto FROM oc_renglones WHERE oc_id = $id AND imput_presupuestaria IS NOT NULL AND imput_presupuestaria != '' GROUP BY imput_presupuestaria ORDER BY id ASC");
        while ($rp = mysqli_fetch_assoc($res_reng_p)) {
            $partidas[] = array(
                'partida' => $rp['partida'],
                'monto'   => (float) $rp['monto']
            );
        }
        if ($iva_monto > 0) {
            $partidas[] = array('partida' => '4.03.18.01.00', 'monto' => $iva_monto);
        }
        if ($sat_monto > 0) {
            $partidas[] = array('partida' => '4.03.18.99.00', 'monto' => $sat_monto);
        }
    }
}

echo json_encode(array(
    'total_general'  => $total_general,
    'base_imponible' => isset($base_imponible) ? $base_imponible : $total_general,
    'fecha'          => $fecha_doc,
    'iva_monto'      => $iva_monto,
    'proveedor_id'   => $proveedor_id,
    'partidas'       => $partidas,
    'descripcion_os' => isset($descripcion_os) ? $descripcion_os : '',
    'numero_os'      => isset($numero_os_val)  ? $numero_os_val  : '',
    'numero_oc'      => isset($numero_oc_val)  ? $numero_oc_val  : '',
    'sat_monto'      => isset($sat_monto)      ? $sat_monto      : 0
));

