<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = ($_SESSION['rol'] === 'admin');
$es_supervisor = ($_SESSION['rol'] === 'supervisor');

require_once '../../../config/conexion.php';

/* ── Exportar a Excel (CSV) ───────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'activas';
    $q_safe = $q !== '' ? '%' . mysqli_real_escape_string($conn, $q) . '%' : '';

    $filename = ($tab === 'papelera' ? 'papelera_ordenes_pago_' : 'listado_ordenes_pago_') . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    if ($tab === 'papelera') {
        fputcsv($output, array('N° Pago', 'Fecha', 'Beneficiario', 'Monto Neto Bs.', 'Movido a', 'Expira en (días)', 'Status'), ';');
        $where_q = $q_safe ? "AND (op.numero LIKE '$q_safe' OR op.rif_beneficiario LIKE '$q_safe' OR p.razon_social LIKE '$q_safe' OR op.status LIKE '$q_safe')" : '';
        $sql = "SELECT op.numero, op.fecha, COALESCE(p.razon_social, op.rif_beneficiario, '— Sin beneficiario —') AS beneficiario, op.monto_neto_pagar, op.deleted_at, op.status
                FROM ordenes_pago op
                LEFT JOIN proveedores p ON p.id = op.proveedor_id
                WHERE op.deleted_at IS NOT NULL $where_q
                ORDER BY op.deleted_at DESC";
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $del_ts = strtotime($row['deleted_at']);
                $exp_ts = $del_ts + (14 * 86400);
                $dias_rest = (int) ceil(($exp_ts - time()) / 86400);
                if ($dias_rest < 0) { $dias_rest = 0; }
                $fecha_fmt = (!empty($row['fecha'])) ? date('d/m/Y', strtotime($row['fecha'])) : '';
                $del_fmt = (!empty($row['deleted_at'])) ? date('d/m/Y H:i', strtotime($row['deleted_at'])) : '';
                fputcsv($output, array(
                    $row['numero'],
                    $fecha_fmt,
                    $row['beneficiario'],
                    number_format((float)$row['monto_neto_pagar'], 2, '.', ','),
                    $del_fmt,
                    $dias_rest,
                    ucfirst($row['status'])
                ), ';');
            }
        }
    } else {
        fputcsv($output, array('N° Pago', 'Fecha', 'Beneficiario', 'Monto Neto Bs.', 'Status'), ';');
        $mes_filtro = isset($_GET['mes']) ? trim($_GET['mes']) : '';
        $ver_todos  = (isset($_GET['ver_todos']) && $_GET['ver_todos'] == '1');
        $where_mes  = '';
        if (!$ver_todos && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes_filtro)) {
            $where_mes = "AND (op.fecha BETWEEN '{$mes_filtro}-01' AND LAST_DAY('{$mes_filtro}-01'))";
        }
        $where_q = $q_safe ? "AND (op.numero LIKE '$q_safe' OR op.rif_beneficiario LIKE '$q_safe' OR p.razon_social LIKE '$q_safe' OR op.status LIKE '$q_safe')" : '';
        $sql = "SELECT op.numero, op.fecha, COALESCE(p.razon_social, op.rif_beneficiario, '— Sin beneficiario —') AS beneficiario, op.monto_neto_pagar, op.status
                FROM ordenes_pago op
                LEFT JOIN proveedores p ON p.id = op.proveedor_id
                WHERE op.deleted_at IS NULL $where_mes $where_q
                ORDER BY op.id DESC";
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $fecha_fmt = (!empty($row['fecha'])) ? date('d/m/Y', strtotime($row['fecha'])) : '';
                $st_clean  = in_array(strtolower($row['status']), array('pagado', 'pagada', 'aprobada')) ? 'PAGADO' : 'PENDIENTE';
                fputcsv($output, array(
                    $row['numero'],
                    $fecha_fmt,
                    $row['beneficiario'],
                    number_format((float)$row['monto_neto_pagar'], 2, '.', ','),
                    $st_clean
                ), ';');
            }
        }
    }
    fclose($output);
    exit;
}

/* ── Asegurar columna deleted_at ── */
$check_col = mysqli_query($conn, "SHOW COLUMNS FROM `ordenes_pago` LIKE 'deleted_at'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($conn, "ALTER TABLE ordenes_pago ADD COLUMN deleted_at DATETIME NULL");
}

/* ── Purga automática al cargar la página (OP en papelera > 14 días) ── */
$res_viejas = mysqli_query(
    $conn,
    "SELECT id FROM ordenes_pago
     WHERE deleted_at IS NOT NULL
       AND deleted_at <= NOW() - INTERVAL 14 DAY"
);
while ($vr = mysqli_fetch_assoc($res_viejas)) {
    $vid = (int) $vr['id'];
    mysqli_query($conn, "DELETE FROM op_retenciones WHERE op_id = $vid");
    mysqli_query($conn, "DELETE FROM ordenes_pago WHERE id = $vid");
}

/* ── Tab activa (por defecto 'activas') ── */
$tab_activa = (isset($_GET['papelera']) && $_GET['papelera'] == '1') ? 'papelera' : 'activas';

/* ── Selección de mes para Estadísticas y Listado ────────────────── */
$meses_es = array(
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
);

$mes_filtro = isset($_GET['mes']) ? trim($_GET['mes']) : date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes_filtro)) {
    $mes_filtro = date('Y-m');
}
$partes_mes = explode('-', $mes_filtro);
$anio_sel   = (int)$partes_mes[0];
$num_mes    = (int)$partes_mes[1];
$nombre_mes = isset($meses_es[$num_mes]) ? $meses_es[$num_mes] : date('F', strtotime($mes_filtro . '-01'));

$mes_inicio = $mes_filtro . '-01';
$mes_fin    = date('Y-m-t', strtotime($mes_inicio));

$ver_todos  = (isset($_GET['ver_todos']) && $_GET['ver_todos'] == '1');

/* ── Estadísticas del mes seleccionado (excluir papelera) ─────────── */
$r = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM ordenes_pago
     WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin'
       AND deleted_at IS NULL"
);
$row = mysqli_fetch_assoc($r);
$total_mes = isset($row['total']) ? $row['total'] : 0;

$r = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM ordenes_pago
     WHERE status IN ('pagada', 'pagado', 'aprobada')
       AND fecha BETWEEN '$mes_inicio' AND '$mes_fin'
       AND deleted_at IS NULL"
);
$row = mysqli_fetch_assoc($r);
$total_pagadas = isset($row['total']) ? $row['total'] : 0;

$r = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM ordenes_pago
     WHERE status = 'pendiente'
       AND fecha BETWEEN '$mes_inicio' AND '$mes_fin'
       AND deleted_at IS NULL"
);
$row = mysqli_fetch_assoc($r);
$total_pendiente = isset($row['total']) ? $row['total'] : 0;

$r = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(monto_neto_pagar), 0) AS monto FROM ordenes_pago
     WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin'
       AND deleted_at IS NULL"
);
$row = mysqli_fetch_assoc($r);
$monto_total = isset($row['monto']) ? $row['monto'] : 0;

/* ── Búsqueda por ?q= ─────────────────────────────────────────────── */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$q_safe = $q !== '' ? '%' . mysqli_real_escape_string($conn, $q) . '%' : '';

/* ── Órdenes activas (filtradas por mes o todas si ver_todos=1) ────── */
$where_q = $q_safe ? "AND (op.numero LIKE '$q_safe' OR op.rif_beneficiario LIKE '$q_safe' OR p.razon_social LIKE '$q_safe')" : '';
$where_fecha = $ver_todos ? '' : "AND (op.fecha BETWEEN '$mes_inicio' AND '$mes_fin')";
$ordenes = mysqli_query(
    $conn,
    "SELECT op.id, op.numero, op.fecha, op.monto_neto_pagar, op.status, op.doc_tipo,
            COALESCE(p.razon_social, op.rif_beneficiario, '— Sin beneficiario —') AS beneficiario
     FROM ordenes_pago op
     LEFT JOIN proveedores p ON p.id = op.proveedor_id
     WHERE op.deleted_at IS NULL $where_fecha $where_q
     ORDER BY op.id DESC"
    . (($q_safe || !$ver_todos) ? '' : ' LIMIT 50')
);

/* ── Órdenes en papelera ───────────────────────────────────────────── */
$papelera = mysqli_query(
    $conn,
    "SELECT op.id, op.numero, op.fecha, op.monto_neto_pagar, op.status, op.deleted_at, op.doc_tipo,
            COALESCE(p.razon_social, op.rif_beneficiario, '— Sin beneficiario —') AS beneficiario
     FROM ordenes_pago op
     LEFT JOIN proveedores p ON p.id = op.proveedor_id
     WHERE op.deleted_at IS NOT NULL
     ORDER BY op.deleted_at DESC"
);
$total_papelera = mysqli_num_rows($papelera);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description"
        content="Listado de Órdenes de Pago - Contraloría del Municipio Simón Rodríguez, Estado Anzoátegui">
    <link
        href="/sistema/assets/fonts/fonts.css"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Órdenes de Pago – Contraloría MSR</title>
    <style>
        /* Premium typography: Geist headings, Inter body */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .page-title,
        .section-title,
        .card-title,
        .panel-title,
        .hdr-title,
        .brand-title,
        .brand-sub,
        .title-cell,
        .title-cell h2,
        .sb-section-label,
        .sb-parent-label,
        .sb-user-name,
        .sb-user-badge {
            font-family: 'Geist', sans-serif !important;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .site-header {
            background: #070707;
            color: white;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-bottom: 3px solid #059669;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .site-header img {
            height: 52px;
            width: auto;
            display: block;
        }

        .site-header .brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .brand-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .site-header .brand-sub {
            font-size: 12.5px;
            color: #bbdefb;
        }

        .site-header .header-right {
            margin-left: auto;
            font-size: 11px;
            color: #e3f2fd;
            text-align: right;
        }

        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
        }

        .container {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            max-width: 1080px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            line-height: 1.8;
            margin-top: auto;
        }

        .site-footer strong {
            color: #e3f2fd;
        }

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-image: url('/sistema/assets/img/banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 18px 20px;
            margin: 14px 0 20px;
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
            min-height: 80px;
        }

        .top-bar::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(15, 50, 150, 0.82) 0%, rgba(10, 30, 100, 0.55) 100%);
            border-radius: inherit;
            pointer-events: none;
        }

        .top-bar>* {
            position: relative;
            z-index: 1;
        }

        .top-bar h1 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .btn-nueva {
            background: rgba(255, 255, 255, 0.95);
            color: #1e3a8a;
            border: 1px solid rgba(255, 255, 255, 0.6);
            padding: 7px 15px;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
            transition: all 0.15s ease;
        }

        .btn-nueva:hover {
            background: #ffffff;
            color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }

        .stat-card {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-top: 3px solid #101A36;
            border-radius: 6px;
            padding: 12px 14px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(15, 23, 42, 0.08);
        }

        .stat-card .stat-label {
            font-size: 10px;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
        }

        .stat-card .stat-value.verde {
            color: #059669;
        }

        .stat-card .stat-value.amarillo {
            color: #d97706;
        }

        .stat-card .stat-value.monto {
            font-size: 16px;
            color: #0f172a;
        }

        .section-title {
            background: #101A36;
            color: #ffffff;
            font-weight: 700;
            font-size: 11px;
            text-align: center;
            padding: 7px 10px;
            border: 1px solid #101A36;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0;
            border-radius: 4px 4px 0 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        table th {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #334155;
            padding: 6px 6px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            vertical-align: middle;
            color: #1e293b;
        }

        table tr:nth-child(even) td {
            background: #f8fafc;
        }

        table tr:hover td {
            background: #f1f5f9;
        }

        .badge-pendiente {
            background: #fff8e1;
            color: #b45309;
            border: 1px solid #fcd34d;
        }

        .badge-pagada,
        .badge-aprobada,
        .badge-pagado {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #43a047;
        }

        .badge-rechazado,
        .badge-anulada {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #e53935;
        }

        .btn-ver,
        .btn-anular {
            border: none;
            padding: 3px 8px;
            font-size: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 3px;
        }

        .btn-ver {
            background: #1565c0;
            color: white;
        }

        .btn-ver:hover {
            background: #0d47a1;
        }

        .btn-anular {
            background: #c62828;
            color: white;
        }

        .btn-anular:hover {
            background: #b71c1c;
        }

        .btn-editar {
            background: #f57c00;
            color: white;
            border: none;
            padding: 3px 8px;
            font-size: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 3px;
            border-radius: 2px;
        }

        .btn-editar:hover {
            background: #e65100;
        }

        .btn-restaurar {
            background: #f57f17;
            color: white;
            border: none;
            padding: 3px 8px;
            font-size: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
            margin-right: 3px;
        }

        .btn-restaurar:hover {
            background: #e65100;
        }

        .btn-eliminar-def {
            background: #c62828;
            color: white;
            border: none;
            padding: 3px 8px;
            font-size: 10px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
        }

        .btn-eliminar-def:hover {
            background: #b71c1c;
        }

        .sin-datos {
            text-align: center;
            color: #888;
            padding: 20px;
            font-style: italic;
            border: 1px solid #ccc;
            border-top: none;
        }

        .nota-mes {
            font-size: 10px;
            color: #555;
            margin-bottom: 10px;
        }

        /* Tabs & Search */
        .tabs-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ccc;
            margin-bottom: 16px;
            margin-top: 8px;
            padding-bottom: 4px;
        }

        .search-input {
            width: 300px;
            padding: 6px 10px 6px 30px;
            font-size: 11.5px;
            border: 1px solid #ccc;
            border-radius: 3px;
            outline: none;
            font-family: 'Inter', sans-serif;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 10px center;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .search-input:focus {
            border-color: #1565c0;
            box-shadow: 0 0 4px rgba(21, 101, 192, 0.2);
        }

        .tab-btn {
            padding: 7px 18px;
            font-size: 11px;
            font-weight: bold;
            font-family: 'Inter', sans-serif;
            border: none;
            background: none;
            cursor: pointer;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: color 0.15s, border-color 0.15s;
        }

        .tab-btn.activa {
            color: #1565c0;
            border-bottom-color: #1565c0;
        }

        .tab-btn .tab-badge {
            display: inline-block;
            background: #c62828;
            color: white;
            border-radius: 10px;
            font-size: 9px;
            padding: 1px 6px;
            margin-left: 5px;
            font-weight: bold;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.activa {
            display: block;
        }

        .papelera-notice {
            background: #fff3e0;
            border: 1px solid #ffe0b2;
            color: #e65100;
            padding: 8px 12px;
            font-size: 11px;
            margin-bottom: 12px;
        }

        .dias-restantes {
            font-weight: bold;
            font-size: 10px;
        }

        .dias-ok {
            color: #f57f17;
        }

        /* ── Barra de encabezado de estadísticas y selector de mes ────────── */
        .stats-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .stats-header-left {
            display: flex;
            align-items: center;
        }

        .nota-mes {
            font-size: 11.5px;
            color: #334155;
            margin-bottom: 0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .nota-mes strong {
            color: #0f172a;
            font-size: 12.5px;
            text-transform: capitalize;
            font-weight: 700;
        }

        .form-mes-picker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .lbl-mes-picker {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
        }

        .input-mes-picker {
            padding: 5px 9px;
            font-size: 11.5px;
            border: 1.5px solid #cbd5e1;
            border-radius: 5px;
            font-family: inherit;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
            cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .input-mes-picker:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
            background: #ffffff;
        }

        .btn-mes-shortcut {
            font-size: 10.5px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            color: #2563eb;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            transition: all 0.15s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
        }

        .btn-mes-shortcut:hover {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-mes-shortcut.btn-mes-activo {
            background: #2563eb;
            color: #ffffff;
            border-color: #1d4ed8;
        }

        /* ── Botones de exportación (PDF / Excel) ────────────────────────── */
        .export-btn-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 5px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
            font-family: inherit;
        }

        .btn-export.btn-pdf {
            background: #dc2626;
            color: #ffffff;
            border-color: #b91c1c;
        }

        .btn-export.btn-pdf:hover {
            background: #b91c1c;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(220, 38, 38, 0.25);
        }

        .btn-export.btn-excel {
            background: #15803d;
            color: #ffffff;
            border-color: #166534;
        }

        .btn-export.btn-excel:hover {
            background: #166534;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(21, 128, 61, 0.25);
        }

        /* ── Encabezado Institucional ────────────────────────────────────── */
        .doc-header-institutional {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 6px;
        }

        .doc-header-institutional .doc-logo-left img,
        .doc-header-institutional .doc-logo-right img {
            width: 85px;
            height: auto;
            display: block;
        }

        .doc-header-institutional .header {
            text-align: center;
            flex-grow: 1;
            padding: 0 15px;
        }

        .doc-header-institutional .header p {
            font-size: 11px;
            line-height: 1.5;
            color: #1e293b;
        }

        .doc-header-institutional .header h2 {
            font-size: 15px;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #0f172a;
            font-weight: 800;
        }

        /* ── Estilos de Impresión / PDF ──────────────────────────────────── */
        @media print {
            @page {
                size: letter landscape;
                margin: 8mm 8mm 8mm 8mm;
            }

            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Inter', Arial, sans-serif !important;
                font-size: 8.5pt !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .site-header,
            .site-footer,
            .sidebar,
            .sb-user-card,
            .top-bar,
            .stats-grid,
            .tabs-bar,
            .search-input,
            .export-btn-group,
            .btn-nueva,
            .btn-ver,
            .btn-editar,
            .btn-anular,
            .btn-eliminar-def,
            .btn-restaurar,
            .alert-success,
            .modal-overlay,
            .papelera-notice,
            .nota-mes,
            th:last-child,
            td:last-child,
            .no-print {
                display: none !important;
            }

            .layout,
            .main-content,
            .container {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }

            .tab-panel {
                display: none !important;
            }

            .tab-panel.activa {
                display: block !important;
            }

            .doc-header-institutional {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                border-bottom: 2px solid #0f172a !important;
                margin-bottom: 12px !important;
                padding-bottom: 6px !important;
            }

            .doc-header-institutional .doc-logo-left img,
            .doc-header-institutional .doc-logo-right img {
                width: 70px !important;
                height: auto !important;
                display: block !important;
            }

            .doc-header-institutional .header p {
                font-size: 8.5pt !important;
                line-height: 1.3 !important;
                color: #000000 !important;
            }

            .doc-header-institutional .header h2 {
                font-size: 12pt !important;
                color: #000000 !important;
                margin-top: 4px !important;
            }

            .section-title {
                background: #f1f5f9 !important;
                color: #000000 !important;
                border: 1px solid #64748b !important;
                border-bottom: none !important;
                font-size: 8.5pt !important;
                padding: 4px 6px !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 8.5pt !important;
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
            }

            table th {
                background: #e2e8f0 !important;
                color: #000000 !important;
                border: 1px solid #64748b !important;
                padding: 5px 6px !important;
                font-weight: 700 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            table td {
                border: 1px solid #94a3b8 !important;
                padding: 4px 6px !important;
                color: #000000 !important;
                background: transparent !important;
            }

            tr[style*="display: none"] {
                display: none !important;
            }

            select.badge {
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
                border: none !important;
                background: none !important;
                color: #000000 !important;
                font-weight: 600 !important;
                font-size: 8.5pt !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body>

    <div class="layout">
        <?php $active = 'op-lista';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container">

                <!-- ══ ENCABEZADO INSTITUCIONAL ══════════════════════════════════════ -->
                <div class="doc-header-institutional">
                    <div class="doc-logo-left">
                        <img src="/sistema/assets/img/logo.png" alt="Logo Contraloría">
                    </div>
                    <div class="header">
                        <p><strong>REPÚBLICA BOLIVARIANA DE VENEZUELA</strong><br>
                            ESTADO ANZOÁTEGUI<br>
                            <strong>CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</strong>
                        </p>
                        <h2 id="print-report-title"><?php echo ($tab_activa === 'papelera') ? 'Listado de Órdenes de Pago (Papelera)' : 'Listado de Órdenes de Pago'; ?></h2>
                    </div>
                    <div class="doc-logo-right">
                        <img src="/sistema/assets/img/sncf.png"
                            alt="NCF">
                    </div>
                </div>

                <!-- ══ BARRA DE TÍTULO Y BOTÓN DE ACCIÓN ═════════════════════════════ -->
                <div class="top-bar">
                    <h1>LISTADO DE ÓRDENES DE PAGO</h1>
                    <?php if ($es_admin): ?>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <a href="nueva_op.php" class="btn-nueva">+ NUEVA ORDEN DE PAGO</a>
                            <a href="nueva_op_banavih.php" class="btn-nueva">+ NUEVA OP BANAVIH</a>
                            <a href="nueva_op_ivss.php" class="btn-nueva">+ NUEVA OP IVSS Y PF</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ══ MENSAJES DE ALERTA ════════════════════════════════════════════ -->
                <?php if (isset($_GET['ok'])): ?>
                    <div class="alert-success">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                        <div><strong>¡ORDEN DE PAGO GUARDADA CORRECTAMENTE!</strong></div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['msg'])): ?>
                    <?php if ($_GET['msg'] === 'movido'): ?>
                        <div class="alert-success" style="background:#fff3e0; color:#e65100; border-color:#ffe0b2;">
                            <div>🗑️ La orden de pago ha sido movida a la <strong>Papelera</strong>. Permanecerá allí durante 14
                                días.</div>
                        </div>
                    <?php elseif ($_GET['msg'] === 'restaurado'): ?>
                        <div class="alert-success">
                            <div>↩ La orden de pago ha sido <strong>restaurada</strong> exitosamente a la lista activa.</div>
                        </div>
                    <?php elseif ($_GET['msg'] === 'eliminado'): ?>
                        <div class="alert-success" style="background:#ffebee; color:#c62828; border-color:#ef9a9a;">
                            <div>🗑️ La orden de pago ha sido <strong>eliminada permanentemente</strong> de la base de datos.
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                                   <!-- ══ TARJETAS DE ESTADÍSTICAS Y SELECTOR DE MES ════════════════════ -->
                <div class="stats-header-bar">
                    <div class="stats-header-left">
                        <p class="nota-mes">
                            Estadísticas del mes: <strong><?php echo $nombre_mes . ' ' . $anio_sel; ?></strong>
                        </p>
                    </div>
                    <div class="stats-header-right no-print">
                        <form method="GET" action="index.php" id="form-filtro-mes" class="form-mes-picker">
                            <?php if (isset($_GET['tab']) && $_GET['tab'] === 'papelera'): ?>
                                <input type="hidden" name="tab" value="papelera">
                            <?php endif; ?>
                            <?php if ($q !== ''): ?>
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                            <?php endif; ?>
                            <label for="mes-picker" class="lbl-mes-picker">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <span>Mes:</span>
                            </label>
                            <input type="month" id="mes-picker" name="mes" value="<?php echo htmlspecialchars($mes_filtro); ?>" onchange="document.getElementById('form-filtro-mes').submit();" class="input-mes-picker">
                            <?php if ($mes_filtro !== date('Y-m') || $ver_todos): ?>
                                <a href="index.php<?php echo ($tab_activa === 'papelera' ? '?tab=papelera' : ''); ?>" class="btn-mes-shortcut" title="Ir al mes actual">Mes Actual</a>
                            <?php endif; ?>
                            <?php if ($ver_todos): ?>
                                <a href="index.php?mes=<?php echo urlencode($mes_filtro); ?><?php echo ($tab_activa === 'papelera' ? '&tab=papelera' : ''); ?>" class="btn-mes-shortcut btn-mes-activo" title="Filtrar por el mes seleccionado">Filtrar por Mes</a>
                            <?php else: ?>
                                <a href="index.php?ver_todos=1<?php echo ($tab_activa === 'papelera' ? '&tab=papelera' : ''); ?>" class="btn-mes-shortcut" title="Ver todas las órdenes sin filtrar por mes">Ver Todas</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Órdenes del Mes</div>
                        <div class="stat-value"><?php echo $total_mes; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Órdenes Pagadas</div>
                        <div class="stat-value verde"><?php echo $total_pagadas; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Órdenes Pendientes</div>
                        <div class="stat-value amarillo"><?php echo $total_pendiente; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Monto Total Pagado (Bs.)</div>
                        <div class="stat-value monto">Bs.&nbsp;<?php echo number_format($monto_total, 2, '.', ','); ?>
                        </div>
                    </div>
                </div>

                <!-- ══ NAVEGACIÓN POR TABS Y BÚSQUEDA ═══════════════════════════════ -->
                <div class="tabs-bar no-print">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <button class="tab-btn <?php echo ($tab_activa === 'activas') ? 'activa' : ''; ?>"
                            onclick="cambiarTab('activas')">
                            📋 Órdenes Activas
                        </button>
                        <button class="tab-btn <?php echo ($tab_activa === 'papelera') ? 'activa' : ''; ?>"
                            onclick="cambiarTab('papelera')">
                            🗑️ Papelera
                            <?php if ($total_papelera > 0): ?>
                                <span class="tab-badge"><?php echo $total_papelera; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <input type="text" id="busqueda-tabla" class="search-input"
                            placeholder="🔍 Buscar N° pago, beneficiario, fecha, status..."
                            onkeyup="filtrarTablas(this.value)">
                        <div class="export-btn-group">
                            <button type="button" class="btn-export btn-pdf" onclick="imprimirPDF()" title="Imprimir / Guardar en PDF">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                <span>PDF</span>
                            </button>
                            <button type="button" class="btn-export btn-excel" onclick="exportarExcel()" title="Descargar reporte en Excel / CSV">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13h8"/><path d="M8 17h8"/><path d="M10 9h4"/></svg>
                                <span>Excel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ───────────────────── TAB: ÓRDENES ACTIVAS ───────────────────── -->
                <div id="tab-activas" class="tab-panel <?php echo ($tab_activa === 'activas') ? 'activa' : ''; ?>">
                    <div class="section-title"><?php echo $ver_todos ? 'Todas las Órdenes de Pago Activas' : 'Órdenes de Pago Activas — ' . $nombre_mes . ' ' . $anio_sel; ?></div>

                    <?php if (!$ordenes || mysqli_num_rows($ordenes) === 0): ?>
                        <div class="sin-datos">No hay órdenes de pago registradas <?php echo $ver_todos ? 'activas' : 'en ' . $nombre_mes . ' ' . $anio_sel; ?>.</div>
                    <?php else: ?>
                        <table id="tabla-ordenes">
                            <thead>
                                <tr>
                                    <th style="width:10%">N° PAGO</th>
                                    <th style="width:10%">FECHA</th>
                                    <th style="width:34%">BENEFICIARIO</th>
                                    <th style="width:16%">MONTO NETO Bs.</th>
                                    <th style="width:10%">STATUS</th>
                                    <th style="width:20%">ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($op = mysqli_fetch_assoc($ordenes)): ?>
                                    <?php
                                    $raw_status = isset($op['status']) ? $op['status'] : 'pendiente';
                                    $status_val = in_array(strtolower($raw_status), array('pagado', 'pagada', 'aprobada')) ? 'pagado' : 'pendiente';
                                    $badge_cls  = ($status_val === 'pagado') ? 'badge-pagado' : 'badge-pendiente';

                                    $fecha_fmt = '';
                                    if (isset($op['fecha']) && $op['fecha'] != '') {
                                        $ts = strtotime($op['fecha']);
                                        $fecha_fmt = $ts ? date('d/m/Y', $ts) : htmlspecialchars($op['fecha']);
                                    }
                                    $num_pago = isset($op['numero']) && $op['numero'] != '' ? $op['numero'] : $op['id'];
                                    ?>
                                    <tr>
                                        <td style="text-align:center;"><?php echo htmlspecialchars($num_pago); ?></td>
                                        <td style="text-align:center;"><?php echo $fecha_fmt; ?></td>
                                        <td><?php echo htmlspecialchars(isset($op['beneficiario']) ? $op['beneficiario'] : ''); ?>
                                        </td>
                                        <td style="text-align:right;">
                                            Bs.
                                            <?php echo number_format(isset($op['monto_neto_pagar']) ? (float) $op['monto_neto_pagar'] : 0, 2, '.', ','); ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php if ($es_admin || $es_supervisor): ?>
                                                <select class="badge <?php echo $badge_cls; ?>"
                                                    onchange="cambiarStatusRapido('ordenes_pago', <?php echo (int) $op['id']; ?>, this.value, this)"
                                                    style="cursor:pointer; outline:none; font-family:inherit;">
                                                    <option value="pendiente" <?php echo ($status_val === 'pendiente') ? 'selected' : ''; ?>>PENDIENTE</option>
                                                    <option value="pagado" <?php echo ($status_val === 'pagado') ? 'selected' : ''; ?>>PAGADO</option>
                                                </select>
                                            <?php else: ?>
                                                <span class="badge <?php echo $badge_cls; ?>"><?php echo strtoupper($status_val); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <?php
                                            $doc_tipo_row = isset($op['doc_tipo']) ? $op['doc_tipo'] : '';
                                            if ($doc_tipo_row === 'BANAVIH') {
                                                $op_href = 'nueva_op_banavih.php';
                                            } elseif ($doc_tipo_row === 'IVSS') {
                                                $op_href = 'nueva_op_ivss.php';
                                            } else {
                                                $op_href = 'nueva_op.php';
                                            }
                                            ?>
                                            <a href="<?php echo $op_href; ?>?id=<?php echo (int) $op['id']; ?>"
                                                class="btn-ver">Ver</a>
                                            <?php if ($es_admin && $status_val === 'pendiente'): ?>
                                                <a href="<?php echo $op_href; ?>?id=<?php echo (int) $op['id']; ?>"
                                                    class="btn-editar" title="Editar esta orden de pago">✏️ Editar</a>
                                                <a href="../../controllers/ordenes_pago/papelera_op.php?id=<?php echo (int) $op['id']; ?>"
                                                    class="btn-anular"
                                                    onclick="return confirm('¿Mover la orden N° <?php echo htmlspecialchars($num_pago); ?> a la papelera?');">
                                                    🗑 Papelera
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div><!-- /#tab-activas -->

                <!-- ───────────────────── TAB: PAPELERA ───────────────────── -->
                <div id="tab-papelera" class="tab-panel <?php echo ($tab_activa === 'papelera') ? 'activa' : ''; ?>">
                    <div class="papelera-notice">
                        🕒 Las órdenes de pago en la papelera se eliminan definitivamente de forma automática después de
                        <strong>14 días</strong>.
                        Puede restaurarlas en cualquier momento antes de que expiren.
                    </div>

                    <?php if ($total_papelera === 0): ?>
                        <div class="sin-datos">La papelera está vacía.</div>
                    <?php else: ?>
                        <table id="tabla-papelera">
                            <thead>
                                <tr>
                                    <th style="width:10%">N° PAGO</th>
                                    <th style="width:10%">FECHA</th>
                                    <th style="width:30%">BENEFICIARIO</th>
                                    <th style="width:15%">MONTO NETO Bs.</th>
                                    <th style="width:12%">MOVIDO A</th>
                                    <th style="width:11%">EXPIRA EN</th>
                                    <th style="width:12%">ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pt = mysqli_fetch_assoc($papelera)): ?>
                                    <?php
                                    $num_pt = isset($pt['numero']) && $pt['numero'] != '' ? $pt['numero'] : $pt['id'];
                                    $del_ts = strtotime($pt['deleted_at']);
                                    $exp_ts = $del_ts + (14 * 86400);
                                    $dias_rest = (int) ceil(($exp_ts - time()) / 86400);
                                    if ($dias_rest < 0) {
                                        $dias_rest = 0;
                                    }
                                    $dias_cls = ($dias_rest <= 3) ? 'dias-warn' : 'dias-ok';

                                    $fecha_del_fmt = date('d/m/Y H:i', $del_ts);
                                    $fecha_op_fmt = '';
                                    if (isset($pt['fecha']) && $pt['fecha'] != '') {
                                        $ts2 = strtotime($pt['fecha']);
                                        $fecha_op_fmt = $ts2 ? date('d/m/Y', $ts2) : htmlspecialchars($pt['fecha']);
                                    }
                                    ?>
                                    <tr>
                                        <td style="text-align:center;"><?php echo htmlspecialchars($num_pt); ?></td>
                                        <td style="text-align:center;"><?php echo $fecha_op_fmt; ?></td>
                                        <td><?php echo htmlspecialchars(isset($pt['beneficiario']) ? $pt['beneficiario'] : ''); ?>
                                        </td>
                                        <td style="text-align:right;">Bs.
                                            <?php echo number_format(isset($pt['monto_neto_pagar']) ? (float) $pt['monto_neto_pagar'] : 0, 2, '.', ','); ?>
                                        </td>
                                        <td style="text-align:center; font-size:10px;"><?php echo $fecha_del_fmt; ?></td>
                                        <td style="text-align:center;">
                                            <span class="dias-restantes <?php echo $dias_cls; ?>">
                                                <?php echo $dias_rest; ?> día<?php echo ($dias_rest !== 1) ? 's' : ''; ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <?php if ($es_admin): ?>
                                                <a href="../../controllers/ordenes_pago/restaurar_op.php?id=<?php echo (int) $pt['id']; ?>"
                                                    class="btn-restaurar"
                                                    onclick="return confirm('¿Restaurar la orden N° <?php echo htmlspecialchars($num_pt); ?>?');">
                                                    ↩ Restaurar
                                                </a>
                                                <a href="../../controllers/ordenes_pago/eliminar_definitivo_op.php?id=<?php echo (int) $pt['id']; ?>"
                                                    class="btn-eliminar-def"
                                                    onclick="return confirm('¿Eliminar PERMANENTEMENTE la orden N° <?php echo htmlspecialchars($num_pt); ?>? Esta acción no se puede deshacer.');">
                                                    🗑️ Eliminar
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                </div><!-- /#tab-papelera -->

            </div><!-- /.container -->
        </main>
    </div><!-- /.layout --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?><script>
        var currentTab = '<?php echo $tab_activa; ?>';

        function filtrarTablas(valor) {
            var term = (valor || '').toLowerCase().trim();
            var ids = ['tabla-ordenes', 'tabla-papelera'];
            for (var k = 0; k < ids.length; k++) {
                var table = document.getElementById(ids[k]);
                if (!table) continue;
                var tbody = table.querySelector('tbody');
                if (!tbody) continue;
                var rows = tbody.querySelectorAll('tr');
                for (var i = 0; i < rows.length; i++) {
                    var rowText = (rows[i].textContent || rows[i].innerText || '').toLowerCase();
                    rows[i].style.display = (rowText.indexOf(term) !== -1) ? '' : 'none';
                }
            }
        }

        function cambiarTab(tab) {
            currentTab = tab;
            document.getElementById('tab-activas').classList.remove('activa');
            document.getElementById('tab-papelera').classList.remove('activa');
            var btns = document.querySelectorAll('.tab-btn');
            for (var i = 0; i < btns.length; i++) { btns[i].classList.remove('activa'); }

            document.getElementById('tab-' + tab).classList.add('activa');
            for (var j = 0; j < btns.length; j++) {
                if (btns[j].getAttribute('onclick').indexOf("'" + tab + "'") !== -1) {
                    btns[j].classList.add('activa');
                }
            }

            var titleEl = document.getElementById('print-report-title');
            if (titleEl) {
                titleEl.innerText = (tab === 'papelera') ? 'Listado de Órdenes de Pago (Papelera)' : 'Listado de Órdenes de Pago';
            }
        }

        /* Export actions */
        function imprimirPDF() {
            window.print();
        }

        function exportarExcel() {
            var searchEl = document.getElementById('busqueda-tabla');
            var q = searchEl ? encodeURIComponent(searchEl.value.trim()) : '';
            var mesEl = document.getElementById('mes-picker');
            var mes = mesEl ? encodeURIComponent(mesEl.value.trim()) : '';
            var verTodos = <?php echo $ver_todos ? '1' : '0'; ?>;
            window.location.href = 'index.php?export=excel&tab=' + encodeURIComponent(currentTab) + '&q=' + q + '&mes=' + mes + (verTodos ? '&ver_todos=1' : '');
        }

        /* Quick Status Change */
        function cambiarStatusRapido(tabla, id, nuevoStatus, selectEl) {
            if (!confirm('¿Desea cambiar el estado de la orden a "' + nuevoStatus.toUpperCase() + '"?')) {
                location.reload();
                return;
            }
            const fd = new FormData();
            fd.append('tabla', tabla);
            fd.append('id', id);
            fd.append('nuevo_status', nuevoStatus);

            fetch('/sistema/src/Controllers/cambiar_status.php', {
                method: 'POST',
                body: fd
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al actualizar estado: ' + (data.error || 'Desconocido'));
                        location.reload();
                    }
                })
                .catch(err => {
                    alert('Error de conexión');
                    location.reload();
                });
        }
    </script>
</body>

</html>