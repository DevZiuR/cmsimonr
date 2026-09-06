<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once '../config/conexion.php';

// Parse filter parameters
$tipo_filtro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'mes';
$mes = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');
$dia = isset($_GET['dia']) ? $_GET['dia'] : date('Y-m-d');
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

// Basic validation / cleaning
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
    $dia = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
    $fecha_inicio = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_fin = date('Y-m-t');
}

if ($tipo_filtro === 'dia') {
    $mes_inicio = $dia;
    $mes_fin = $dia;
} elseif ($tipo_filtro === 'rango') {
    $mes_inicio = $fecha_inicio;
    $mes_fin = $fecha_fin;
} else { // 'mes'
    $mes_inicio = $mes . '-01';
    $mes_fin = date('Y-m-t', strtotime($mes_inicio));
}

/* 1. Total OC */
$r = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM ordenes_compra
     WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin'"
);
$row = ($r !== false) ? mysqli_fetch_assoc($r) : array();
$stat_oc = isset($row['total']) ? (int) $row['total'] : 0;

/* 2. Total OP */
$r = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM ordenes_pago
     WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin'"
);
$row = ($r !== false) ? mysqli_fetch_assoc($r) : array();
$stat_op = isset($row['total']) ? (int) $row['total'] : 0;

/* 3. Monto total pagado del mes (ordenes_pago) */
$r = mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(monto_neto_pagar), 0) AS monto FROM ordenes_pago
     WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin'"
);
$row = ($r !== false) ? mysqli_fetch_assoc($r) : array();
$stat_pagado = isset($row['monto']) ? (float) $row['monto'] : 0;

/* 4. Órdenes Pendientes (combinado OC + OP + OS) */
$r = mysqli_query(
    $conn,
    "SELECT (
        (SELECT COUNT(*) FROM ordenes_compra WHERE status = 'pendiente' AND deleted_at IS NULL) +
        (SELECT COUNT(*) FROM ordenes_pago WHERE status = 'pendiente' AND deleted_at IS NULL) +
        (SELECT COUNT(*) FROM ordenes_servicio WHERE status = 'pendiente' AND deleted_at IS NULL)
    ) AS total"
);
$row = ($r !== false) ? mysqli_fetch_assoc($r) : array();
$stat_pendiente = isset($row['total']) ? (int) $row['total'] : 0;

/* ═══════════════════════════════════════════════════════════════════════
   ÚLTIMAS ÓRDENES DE COMPRA DEL PERÍODO
   ═══════════════════════════════════════════════════════════════════════ */
$ultimas_oc = mysqli_query(
    $conn,
    "SELECT oc.id, oc.numero_orden, oc.fecha, oc.total_general, oc.status,
            COALESCE(p.razon_social, '— Sin proveedor —') AS proveedor
     FROM ordenes_compra oc
     LEFT JOIN proveedores p ON p.id = oc.proveedor_id
     WHERE oc.fecha BETWEEN '$mes_inicio' AND '$mes_fin'
     ORDER BY oc.id DESC
     LIMIT 5"
);

/* ═══════════════════════════════════════════════════════════════════════
   ÚLTIMAS ÓRDENES DE PAGO DEL PERÍODO
   Fix: proveedor_id=0 for BANAVIH/IVSS doesn't match proveedores, so
   fall back to rif_beneficiario (which stores the institutional name)
   ═══════════════════════════════════════════════════════════════════════ */
$ultimas_op = mysqli_query(
    $conn,
    "SELECT op.id, op.numero, op.fecha, op.monto_neto_pagar, op.status,
            COALESCE(NULLIF(p.razon_social,''), NULLIF(op.rif_beneficiario,''), '— Sin beneficiario —') AS beneficiario
     FROM ordenes_pago op
     LEFT JOIN proveedores p ON p.id = op.proveedor_id AND op.proveedor_id > 0
     WHERE op.fecha BETWEEN '$mes_inicio' AND '$mes_fin'
     ORDER BY op.id DESC
     LIMIT 5"
);

/* ═══════════════════════════════════════════════════════════════════════
   BARRAS DE GASTO POR PARTIDA
   ═══════════════════════════════════════════════════════════════════════ */
$partidas_res = mysqli_query(
    $conn,
    "SELECT ocp.partida, SUM(ocp.monto) AS total
     FROM oc_partidas ocp
     INNER JOIN ordenes_compra oc ON oc.id = ocp.oc_id
     WHERE oc.fecha BETWEEN '$mes_inicio' AND '$mes_fin'
     GROUP BY ocp.partida
     ORDER BY total DESC"
);

$partidas = array();
$partidas_max = 0;
if ($partidas_res) {
    while ($p = mysqli_fetch_assoc($partidas_res)) {
        $partidas[] = $p;
        if ((float) $p['total'] > $partidas_max) {
            $partidas_max = (float) $p['total'];
        }
    }
}

// Build trend data for charts
$dates_map = array();
$oc_trend_res = mysqli_query($conn, "SELECT fecha, SUM(total_general) AS total FROM ordenes_compra WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin' GROUP BY fecha ORDER BY fecha ASC");
if ($oc_trend_res) {
    while ($tr = mysqli_fetch_assoc($oc_trend_res)) {
        $dates_map[$tr['fecha']]['oc'] = (float) $tr['total'];
    }
}
$op_trend_res = mysqli_query($conn, "SELECT fecha, SUM(monto_neto_pagar) AS total FROM ordenes_pago WHERE fecha BETWEEN '$mes_inicio' AND '$mes_fin' GROUP BY fecha ORDER BY fecha ASC");
if ($op_trend_res) {
    while ($tr = mysqli_fetch_assoc($op_trend_res)) {
        $dates_map[$tr['fecha']]['op'] = (float) $tr['total'];
    }
}
ksort($dates_map);
$trend_dates = array_keys($dates_map);
$trend_oc = array();
$trend_op = array();
foreach ($dates_map as $d => $vals) {
    $trend_oc[] = isset($vals['oc']) ? $vals['oc'] : 0.0;
    $trend_op[] = isset($vals['op']) ? $vals['op'] : 0.0;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $formatted_oc = array();
    if ($ultimas_oc) {
        mysqli_data_seek($ultimas_oc, 0);
        while ($row = mysqli_fetch_assoc($ultimas_oc)) {
            $formatted_oc[] = array(
                'id' => $row['id'],
                'numero_orden' => isset($row['numero_orden']) && $row['numero_orden'] != '' ? $row['numero_orden'] : $row['id'],
                'fecha' => $row['fecha'],
                'proveedor' => $row['proveedor'],
                'total_general' => (float) $row['total_general'],
                'total_formatted' => number_format((float) $row['total_general'], 2, ',', '.'),
                'status' => $row['status']
            );
        }
    }

    $formatted_op = array();
    if ($ultimas_op) {
        mysqli_data_seek($ultimas_op, 0);
        while ($row = mysqli_fetch_assoc($ultimas_op)) {
            $formatted_op[] = array(
                'id' => $row['id'],
                'numero' => isset($row['numero']) && $row['numero'] != '' ? $row['numero'] : $row['id'],
                'fecha' => $row['fecha'],
                'beneficiario' => $row['beneficiario'],
                'monto_neto_pagar' => (float) $row['monto_neto_pagar'],
                'monto_formatted' => number_format((float) $row['monto_neto_pagar'], 2, ',', '.'),
                'status' => $row['status']
            );
        }
    }

    $formatted_partidas = array();
    foreach ($partidas as $p) {
        $formatted_partidas[] = array(
            'partida' => $p['partida'],
            'total' => (float) $p['total'],
            'total_formatted' => number_format((float) $p['total'], 2, ',', '.')
        );
    }

    echo json_encode(array(
        'success' => true,
        'stats' => array(
            'oc_count' => $stat_oc,
            'op_count' => $stat_op,
            'os_count' => $stat_op, // service orders count placeholder matches OP count as in original
            'monto_pagado' => $stat_pagado,
            'monto_pagado_formatted' => 'Bs. ' . number_format($stat_pagado, 2, ',', '.'),
            'pendiente_count' => $stat_pendiente,
            'borrador_count' => $stat_pendiente
        ),
        'partidas' => $formatted_partidas,
        'partidas_max' => $partidas_max,
        'ultimas_oc' => $formatted_oc,
        'ultimas_op' => $formatted_op,
        'trend' => array(
            'labels' => $trend_dates,
            'oc' => $trend_oc,
            'op' => $trend_op
        )
    ));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es-VE">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Panel principal del Sistema de Gestión Interna - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Panel Principal – Contraloría MSR</title>
    <style>
        /* Premium typography: IBM Plex Sans headings, Inter body */
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

        /* ── Reset & base ──────────────────────────────────────────────── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Geist', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ────────────────────────────────────────────────────── */
        .site-header {
            background: #070707ff;
            color: white;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-bottom: 3px solid #0d47a1;
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
            letter-spacing: 0.2px;
        }

        .site-header .header-right {
            margin-left: auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .header-right .hdr-date {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }

        .site-header .header-right .hdr-sub {
            font-size: 10.5px;
            color: #94a3b8;
        }

        /* ── Layout: sidebar + main ────────────────────────────────────── */
        .layout {
            display: flex;
            flex: 1;
        }

        /* ── Sidebar CSS is now managed centrally in includes/sidebar.php ── */

        @keyframes fadeInDashboard {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ── Main content ──────────────────────────────────────────────── */
        .main-content {
            flex: 1;
            padding: 36px 40px;
            overflow-x: auto;
            animation: fadeInDashboard 1.1s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }




        /* ── Page title ────────────────────────────────────────────────── */
        .page-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            text-align: left;
        }

        .page-subtitle {
            display: none;
        }

        /* ── Stats cards ──────────────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
            border-top: 4px solid #3b82f6;
            /* Neutral consistent accent color */
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100%;
            background: linear-gradient(180deg, rgba(59, 130, 246, 0.02) 0%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
        }

        .stat-label {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.2;
        }

        /* ── Accesos rápidos (compact row) ───────────────────────── */
        .quick-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 36px;
        }

        .quick-btn {
            padding: 10px 18px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.15s ease;
            white-space: nowrap;
            letter-spacing: 0.2px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .quick-btn:hover {
            transform: translateY(-1px);
        }

        .quick-btn.primary {
            background: #05080fff;
            color: #ffffff;
            border: 1px solid #2563eb;
        }

        .quick-btn.primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.15);
        }

        .quick-btn.secondary {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .quick-btn.secondary:hover {
            background: #f1f5f9;
            color: #1e293b;
            border-color: #94a3b8;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.03);
        }

        /* ── Section cards (panel blanco) ──────────────────────────────── */
        .panel {
            background: #fff;
            border: none;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .panel-head {
            background: transparent;
            padding: 18px 22px;
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1d2530ff;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            letter-spacing: 0.4px;
        }

        .panel-head .ph-title {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .panel-head .ph-icon {
            color: #4d78d3ff;
            display: flex;
            align-items: center;
        }

        .panel-head a {
            font-size: 11.5px;
            color: #4d78d3ff;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .panel-head a:hover {
            color: #1d4ed8;
        }

        .panel-body {
            padding: 20px 22px;
        }

        /* ── Dropdown Hover for Nueva O.P. ────────────────────────── */
        .quick-dropdown-wrapper {
            position: relative;
            display: inline-flex;
        }

        .quick-dropdown-trigger {
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
        }

        .dropdown-chevron {
            transition: transform 0.2s ease;
            margin-left: 2px;
        }

        .quick-dropdown-wrapper:hover .dropdown-chevron,
        .quick-dropdown-wrapper:focus-within .dropdown-chevron {
            transform: rotate(180deg);
        }

        .quick-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            padding-top: 6px;
            background: transparent;
            z-index: 60;
            opacity: 0;
            visibility: hidden;
            transform: translateY(6px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s;
            pointer-events: none;
            min-width: 270px;
        }

        .quick-dropdown-wrapper:hover .quick-dropdown-menu,
        .quick-dropdown-wrapper:focus-within .quick-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }

        .quick-dropdown-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .quick-dropdown-item {
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #334155;
            transition: background 0.15s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .quick-dropdown-item:last-child {
            border-bottom: none;
        }

        .quick-dropdown-item:hover {
            background: #f8fafc;
        }

        .qdi-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .qdi-icon-op {
            background: #eff6ff;
            color: #2563eb;
        }

        .qdi-icon-banavih {
            background: #f0fdf4;
            color: #16a34a;
        }

        .qdi-icon-ivss {
            background: #faf5ff;
            color: #9333ea;
        }

        .qdi-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .qdi-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
        }

        .quick-dropdown-item:hover .qdi-title {
            color: #2563eb;
        }

        .qdi-desc {
            font-size: 10.5px;
            color: #64748b;
            line-height: 1.2;
        }

        /* ── Tabla últimas OC y OP (Dark headers & Slightly Gray Contrast Rows) ── */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12px;
            border-radius: 8px;
            overflow: hidden;
        }

        #tbl-ultimas-oc,
        #tbl-ultimas-op {
            width: 100% !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
            font-size: 12px !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
        }

        #tbl-ultimas-oc thead th,
        #tbl-ultimas-op thead th,
        table th {
            background: #0f172a !important;
            color: #f8fafc !important;
            border: none !important;
            border-bottom: 2px solid #1e293b !important;
            padding: 12px 14px !important;
            text-align: left !important;
            font-size: 10.5px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .5px !important;
        }

        #tbl-ultimas-oc thead th:first-child,
        #tbl-ultimas-op thead th:first-child,
        table thead th:first-child {
            border-top-left-radius: 8px !important;
        }

        #tbl-ultimas-oc thead th:last-child,
        #tbl-ultimas-op thead th:last-child,
        table thead th:last-child {
            border-top-right-radius: 8px !important;
        }

        table td {
            border: none;
            border-bottom: 1px solid #cbd5e1;
            padding: 12px 14px;
            vertical-align: middle;
            color: #0f172a;
            background: #f8fafc;
        }

        #tbl-ultimas-oc td,
        #tbl-ultimas-op td {
            border: none !important;
            border-bottom: 1px solid #cbd5e1 !important;
            padding: 12px 14px !important;
            vertical-align: middle !important;
            color: #0f172a !important;
            background: #f8fafc !important;
            font-weight: 500 !important;
            transition: background 0.15s ease !important;
        }

        #tbl-ultimas-oc tbody tr:nth-child(even) td,
        #tbl-ultimas-op tbody tr:nth-child(even) td {
            background: #f1f5f9 !important;
        }

        #tbl-ultimas-oc tbody tr:hover td,
        #tbl-ultimas-op tbody tr:hover td,
        table tr:hover td {
            background: #e2e8f0 !important;
        }

        table tbody tr:last-child td,
        #tbl-ultimas-oc tbody tr:last-child td,
        #tbl-ultimas-op tbody tr:last-child td {
            border-bottom: none !important;
        }

        /* ── Badges ────────────────────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            font-size: 9px;
            font-weight: 700;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-pendiente {
            background: #fffbeb;
            color: #b45309;
        }

        .badge-aprobada,
        .badge-pagada,
        .badge-pagado {
            background: #ecfdf5;
            color: #047857;
        }

        .badge-rechazado,
        .badge-anulada {
            background: #fef2f2;
            color: #b91c1c;
        }

        /* ── Botón ver ─────────────────────────────────────────────────── */
        .btn-ver {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #dbeafe;
            padding: 5px 12px;
            font-size: 10.5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 700;
            border-radius: 6px;
            transition: all 0.15s;
        }

        .btn-ver:hover {
            background: #dbeafe;
        }

        .sin-datos {
            text-align: center;
            color: #94a3b8;
            padding: 48px 20px;
            font-size: 12px;
        }

        .sin-datos .sin-icon {
            margin-bottom: 12px;
            color: #cbd5e1;
            display: flex;
            justify-content: center;
        }

        /* ── DOS columnas en la parte inferior ────────────────────────── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* ── Footer ────────────────────────────────────────────────────── */
        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            line-height: 1.8;
        }

        .site-footer strong {
            color: #e3f2fd;
        }

        /* ── Filter Card ──────────────────────────────────────────────── */
        .filter-card {
            background: #05080fff;
            border: 1px solid #1e293b;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
        }

        .filter-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            font-weight: 700;
            color: #bccbd1ff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .filter-title svg {
            color: #38bdf8;
        }

        .filter-sep {
            color: #334155;
            font-size: 16px;
        }

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .filter-group label {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            white-space: nowrap;
        }

        .filter-select,
        .filter-input {
            padding: 7px 12px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #334155;
            border-radius: 8px;
            background: #1e293b;
            color: #f8fafc;
            outline: none;
            transition: all 0.15s;
        }

        .filter-select:focus,
        .filter-input:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

        .btn-reset-filter {
            background: transparent;
            color: #94a3b8;
            border: 1px solid #334155;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            transition: all 0.15s;
        }

        .btn-reset-filter:hover {
            background: #1e293b;
            color: #38bdf8;
            border-color: #475569;
        }

        .btn-apply-filter {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
            transition: background 0.15s;
        }

        .btn-apply-filter:hover {
            background: #1d4ed8;
        }

        /* ── Single Input Spanish Date & Month Picker CSS ───────────── */
        .sp-picker-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .sp-picker-input {
            cursor: pointer;
            padding-right: 32px !important;
            min-width: 140px;
            text-align: center;
            font-weight: 600;
            background: #1e293b !important;
            color: #f8fafc !important;
            border: 1px solid #334155 !important;
            border-radius: 8px !important;
            transition: all 0.15s ease;
        }

        .sp-picker-input:hover {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        .sp-picker-icon {
            position: absolute;
            right: 10px;
            color: #38bdf8;
            pointer-events: none;
        }

        .sp-picker-popover {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            z-index: 9999;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
            width: 220px;
            animation: fadeInDashboard 0.15s ease;
        }

        .sp-pop-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 0 2px;
        }

        .sp-pop-title {
            font-size: 12px;
            font-weight: 700;
            color: #f8fafc;
            text-transform: capitalize;
        }

        .sp-pop-nav {
            background: #1e293b;
            border: 1px solid #334155;
            color: #94a3b8;
            border-radius: 6px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            line-height: 1;
            transition: all 0.12s;
        }

        .sp-pop-nav:hover {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .sp-month-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }

        .sp-month-btn {
            background: #1e293b;
            border: 1px solid #334155;
            color: #cbd5e1;
            padding: 8px 4px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: all 0.12s;
        }

        .sp-month-btn:hover {
            background: #334155;
            color: #38bdf8;
            border-color: #38bdf8;
        }

        .sp-month-btn.selected {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .sp-day-names {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
        }

        .sp-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }

        .sp-day-empty {
            height: 24px;
        }

        .sp-day-btn {
            height: 25px;
            background: #1e293b;
            border: 1px solid transparent;
            color: #cbd5e1;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.12s;
        }

        .sp-day-btn:hover {
            background: #334155;
            color: #38bdf8;
            border-color: #38bdf8;
        }

        .sp-day-btn.selected {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        /* ── Premium Stats Cards Upgrade ───────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #ffffff;
            border: none;
            border-radius: 16px;
            padding: 26px 24px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px -10px rgba(15, 23, 42, 0.18);
        }

        .stat-icon {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon-oc {
            color: #2563eb;
            background: #080616;
        }

        .stat-icon-op {
            color: #059669;
            background: #080616;
        }

        .stat-icon-os {
            color: #5d00ffff;
            background: #080616;
        }

        .stat-icon-monto {
            color: #00ff22ff;
            background: #080616;
        }

        .stat-icon-pendiente {
            color: #d97706;
            background: #fffbeb;
        }

        .stat-label {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-bottom: 8px;
            padding-right: 48px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.2;
            transition: all 0.3s ease;
        }

        .stat-value.verde {
            color: #059669;
        }

        .stat-value.naranja {
            color: #d97706;
        }

        .stat-value.amarillo {
            color: #dc2626;
        }

        .stat-value.small {
            font-size: 16px;
            word-break: break-all;
        }

        /* Skeleton loader classes */
        .skeleton {
            background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
            background-size: 200% 100%;
            animation: loading-shimmer 1.5s infinite;
            border-radius: 4px;
            display: inline-block;
        }

        .skeleton-text {
            height: 28px;
            width: 60px;
        }

        .skeleton-row {
            height: 14px;
            margin: 6px 0;
            width: 100%;
        }

        @keyframes loading-shimmer {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* ── Banner de bienvenida ──────────────────────────────────────── */
        @keyframes wb-float-a {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(18px, -14px) scale(1.08);
            }
        }

        @keyframes wb-float-b {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(-14px, 12px) scale(1.05);
            }
        }

        @keyframes wb-fadein {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes wb-wave {
            0% {
                transform: rotate(0deg);
            }

            15% {
                transform: rotate(18deg);
            }

            30% {
                transform: rotate(-8deg);
            }

            45% {
                transform: rotate(16deg);
            }

            60% {
                transform: rotate(-4deg);
            }

            75% {
                transform: rotate(10deg);
            }

            100% {
                transform: rotate(0deg);
            }
        }

        @keyframes wb-name-shimmer {
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        .welcome-banner {
            background-image: url('/sistema/assets/img/panel-bg.png');
            background-size: cover;
            background-position: center;
            color: #fff;
            padding: 40px 28px;
            border-radius: 16px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 14px 32px -12px rgba(30, 64, 175, 0.45);
            position: relative;
            overflow: hidden;
        }

        /* Soft floating glow orbs — behind content */
        .welcome-banner::before,
        .welcome-banner::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(52px);
            opacity: 0.22;
        }

        .welcome-banner::before {
            width: 280px;
            height: 280px;
            background: #a5b4fc;
            top: -60px;
            right: 60px;
            animation: wb-float-a 9s ease-in-out infinite;
        }

        .welcome-banner::after {
            width: 200px;
            height: 200px;
            background: #67e8f9;
            bottom: -50px;
            left: 30px;
            animation: wb-float-b 12s ease-in-out infinite;
        }

        .welcome-greet {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 1;
        }

        /* Waving hand emoji */
        .welcome-wave {
            font-size: 32px;
            line-height: 1;
            display: inline-block;
            transform-origin: 70% 80%;
            animation:
                wb-fadein 0.6s cubic-bezier(0.22, 1, 0.36, 1) both,
                wb-wave 1.6s ease-in-out 0.7s 1 both;
            will-change: transform;
            flex-shrink: 0;
        }

        .welcome-text-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .welcome-banner h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            color: #fff;
            animation: wb-fadein 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
        }

        /* Soft shimmer on the user name */
        .wb-name {
            background: linear-gradient(90deg,
                    #e2e8f0 20%,
                    #ffffff 40%,
                    #bfdbfe 60%,
                    #e2e8f0 80%);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: wb-name-shimmer 3.2s linear 1s 1 both;
        }

        .welcome-banner p {
            font-size: 12.5px;
            color: rgba(203, 213, 225, 0.85);
            margin: 0;
            animation: wb-fadein 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.3s both;
        }

        /* ── Buscador rápido (Dark Slate Accent) ───────────────────────── */
        .dash-search {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #05080fff;
            border: 1px solid #1e293b;
            border-radius: 14px;
            padding: 10px 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.12);
            transition: all 0.2s ease;
        }

        .dash-search:focus-within {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.15);
        }

        .dash-search .search-ic {
            color: #38bdf8;
            flex-shrink: 0;
            display: flex;
        }

        .dash-search-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            font-size: 13px;
            color: #f8fafc;
            font-family: inherit;
        }

        .dash-search-input::placeholder {
            color: #64748b;
        }

        .dash-search-tabs {
            display: flex;
            background: #1e293b;
            border-radius: 10px;
            padding: 3px;
            flex-shrink: 0;
        }

        .dash-search-tab {
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            background: transparent;
            color: #94a3b8;
            border-radius: 8px;
            transition: all 0.15s;
            letter-spacing: 0.3px;
            font-family: inherit;
        }

        .dash-search-tab.active {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
        }

        .dash-search-btn {
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 7px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            flex-shrink: 0;
            font-family: inherit;
            transition: background 0.15s;
        }

        .dash-search-btn:hover {
            background: #1d4ed8;
        }

        @media (max-width: 900px) {
            .two-col {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 24px 20px;
            }
        }
    </style>
</head>

<body>

    <!-- ══════════════════════════════════════════════════════════════════════
     LAYOUT: SIDEBAR + MAIN
     ══════════════════════════════════════════════════════════════════════ -->
    <div class="layout">

        <!-- ── SIDEBAR ──────────────────────────────────────────────────── -->
        <?php $active = 'inicio';
        require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- ── MAIN CONTENT ─────────────────────────────────────────────── -->
        <main class="main-content">

            <?php
            $nombre_user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : (isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : 'Usuario')));
            $rol_user = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : (isset($_SESSION['rol']) ? $_SESSION['rol'] : 'usuario');

            // Dynamic time-of-day greeting (Good morning / afternoon / evening)
            date_default_timezone_set('America/Caracas');
            $hora = (int) date('H');
            if ($hora >= 5 && $hora < 12) {
                $saludo = 'Buenos días';
            } elseif ($hora >= 12 && $hora < 19) {
                $saludo = 'Buenas tardes';
            } else {
                $saludo = 'Buenas noches';
            }

            // Prepend formal "Lic."
            $nombre_formateado = ucwords(strtolower(trim($nombre_user)));
            if (stripos($nombre_formateado, 'Lic.') !== 0 && stripos($nombre_formateado, 'Lic ') !== 0) {
                $nombre_formateado = 'Lic. ' . $nombre_formateado;
            }
            ?>
            <!-- ── Banner de bienvenida ──────────────────────────────────────── -->
            <div class="welcome-banner">
                <div class="welcome-greet">
                    <span class="welcome-wave" aria-hidden="true">👋</span>
                    <div class="welcome-text-wrap">
                        <h2>
                            <?= htmlspecialchars($saludo) ?>,
                            <span class="wb-name"><?= htmlspecialchars($nombre_formateado) ?></span>!
                        </h2>
                        <p>Resumen institucional &middot; Rol: <?= htmlspecialchars(ucfirst($rol_user)) ?> &middot;
                            Período seleccionado.</p>
                    </div>
                </div>
            </div>


            <!-- ── Filtro compacto ──────────────────────────────────────── -->
            <div class="filter-card">
                <span class="filter-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Período
                </span>
                <span class="filter-sep">|</span>
                <?php
                $meses_es_map = array(
                    '01' => 'Enero',
                    '02' => 'Febrero',
                    '03' => 'Marzo',
                    '04' => 'Abril',
                    '05' => 'Mayo',
                    '06' => 'Junio',
                    '07' => 'Julio',
                    '08' => 'Agosto',
                    '09' => 'Septiembre',
                    '10' => 'Octubre',
                    '11' => 'Noviembre',
                    '12' => 'Diciembre'
                );

                function fmt_mes_single($val, $map)
                {
                    $p = explode('-', $val);
                    if (count($p) === 2 && isset($map[$p[1]])) {
                        return $map[$p[1]] . ' ' . $p[0];
                    }
                    return $val;
                }

                function fmt_fecha_single($val)
                {
                    $p = explode('-', $val);
                    if (count($p) === 3) {
                        return $p[2] . '/' . $p[1] . '/' . $p[0];
                    }
                    return $val;
                }

                $dis_mes = fmt_mes_single($mes, $meses_es_map);
                $dis_dia = fmt_fecha_single($dia);
                $dis_ini = fmt_fecha_single($fecha_inicio);
                $dis_fin = fmt_fecha_single($fecha_fin);
                ?>
                <form id="form-filtro" class="filter-controls" method="GET" action="index.php">
                    <div class="filter-group">
                        <label for="tipo_filtro">Ver:</label>
                        <select name="tipo_filtro" id="tipo_filtro" class="filter-select">
                            <option value="mes" <?php echo $tipo_filtro === 'mes' ? 'selected' : ''; ?>>Mes</option>
                            <option value="dia" <?php echo $tipo_filtro === 'dia' ? 'selected' : ''; ?>>Día</option>
                            <option value="rango" <?php echo $tipo_filtro === 'rango' ? 'selected' : ''; ?>>Rango</option>
                        </select>
                    </div>

                    <!-- Campo único para Mes -->
                    <div class="filter-group filter-input-wrapper" id="wrapper-mes"
                        style="<?php echo $tipo_filtro === 'mes' ? 'display:inline-flex;' : 'display:none;'; ?>">
                        <div class="sp-picker-wrap">
                            <input type="text" id="display_mes" class="filter-input sp-picker-input" readonly
                                value="<?php echo htmlspecialchars($dis_mes); ?>"
                                onclick="openMonthPicker(this, document.getElementById('filtro_mes'))">
                            <input type="hidden" name="mes" id="filtro_mes"
                                value="<?php echo htmlspecialchars($mes); ?>">
                            <svg class="sp-picker-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                    </div>

                    <!-- Campo único para Día -->
                    <div class="filter-group filter-input-wrapper" id="wrapper-dia"
                        style="<?php echo $tipo_filtro === 'dia' ? 'display:inline-flex;' : 'display:none;'; ?>">
                        <div class="sp-picker-wrap">
                            <input type="text" id="display_dia" class="filter-input sp-picker-input" readonly
                                value="<?php echo htmlspecialchars($dis_dia); ?>"
                                onclick="openDatePicker(this, document.getElementById('filtro_dia'))">
                            <input type="hidden" name="dia" id="filtro_dia"
                                value="<?php echo htmlspecialchars($dia); ?>">
                            <svg class="sp-picker-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                    </div>

                    <!-- Campos únicos para Rango -->
                    <div class="filter-group filter-input-wrapper" id="wrapper-rango"
                        style="<?php echo $tipo_filtro === 'rango' ? 'display:inline-flex;gap:8px;align-items:center;' : 'display:none;'; ?>">
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;">Desde:</span>
                        <div class="sp-picker-wrap">
                            <input type="text" id="display_inicio" class="filter-input sp-picker-input" readonly
                                value="<?php echo htmlspecialchars($dis_ini); ?>"
                                onclick="openDatePicker(this, document.getElementById('filtro_inicio'))">
                            <input type="hidden" name="fecha_inicio" id="filtro_inicio"
                                value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                            <svg class="sp-picker-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                        <span style="font-size:11px;color:#94a3b8;font-weight:600;">Hasta:</span>
                        <div class="sp-picker-wrap">
                            <input type="text" id="display_fin" class="filter-input sp-picker-input" readonly
                                value="<?php echo htmlspecialchars($dis_fin); ?>"
                                onclick="openDatePicker(this, document.getElementById('filtro_fin'))">
                            <input type="hidden" name="fecha_fin" id="filtro_fin"
                                value="<?php echo htmlspecialchars($fecha_fin); ?>">
                            <svg class="sp-picker-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                        </div>
                    </div>

                    <button type="submit" class="btn-apply-filter">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Aplicar
                    </button>
                    <button type="button" id="btn-restablecer" class="btn-reset-filter">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="1 4 1 10 7 10" />
                            <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
                        </svg>
                        Reset
                    </button>
                </form>
            </div>

            <!-- ── Buscador rápido de órdenes ─────────────────────────── -->
            <div class="dash-search" id="dashboard-search-bar">
                <span class="search-ic">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                </span>
                <input type="text" id="dash-search-input" class="dash-search-input"
                    placeholder="Buscar por número de orden o proveedor..." autocomplete="off">
                <div class="dash-search-tabs">
                    <button type="button" data-module="oc" class="dash-search-tab active">OC</button>
                    <button type="button" data-module="op" class="dash-search-tab">OP</button>
                    <button type="button" data-module="os" class="dash-search-tab">OS</button>
                </div>
                <button type="button" id="dash-search-btn" class="dash-search-btn">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    Buscar
                </button>
            </div>
            <script>
                (function () {
                    var moduleUrls = {
                        oc: '../src/Views/ordenes_compra/index.php',
                        op: '../src/Views/ordenes_pago/index.php',
                        os: '../src/Views/ordenes_servicio/index.php'
                    };
                    var activeModule = 'oc';
                    var tabs = document.querySelectorAll('.dash-search-tab');
                    tabs.forEach(function (tab) {
                        tab.addEventListener('click', function () {
                            activeModule = this.getAttribute('data-module');
                            tabs.forEach(function (t) { t.classList.remove('active'); });
                            this.classList.add('active');
                        });
                    });
                    function doSearch() {
                        var q = document.getElementById('dash-search-input').value.trim();
                        var url = moduleUrls[activeModule];
                        if (q) url += '?q=' + encodeURIComponent(q);
                        window.location.href = url;
                    }
                    document.getElementById('dash-search-btn').addEventListener('click', doSearch);
                    document.getElementById('dash-search-input').addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') doSearch();
                    });
                })();
            </script>

            <!-- ── Accesos rápidos — fila compacta ────────────────────────── -->
            <div class="quick-row">
                <?php if ($es_admin): ?>
                    <a href="../src/Views/ordenes_compra/nueva_oc.php" class="quick-btn primary" id="qr-nueva-oc">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h6m-3-3v6M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Nueva O.C
                    </a>

                    <!-- Dropdown Hover: Nueva O.P. con todas sus variantes -->
                    <div class="quick-dropdown-wrapper" id="qr-op-dropdown">
                        <div class="quick-btn primary quick-dropdown-trigger" id="qr-nueva-op-trigger" tabindex="0"
                            role="button" aria-haspopup="true">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 12H4L5 9z" />
                            </svg>
                            <span>Nueva O.P.</span>
                            <svg class="dropdown-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="quick-dropdown-menu">
                            <div class="quick-dropdown-card">
                                <a href="../src/Views/ordenes_pago/nueva_op.php" class="quick-dropdown-item"
                                    id="qr-nueva-op-item">
                                    <div class="qdi-icon qdi-icon-op">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    </div>
                                    <div class="qdi-text">
                                        <span class="qdi-title">Nueva OP (General)</span>
                                        <span class="qdi-desc">Orden de pago estándar a proveedores</span>
                                    </div>
                                </a>
                                <a href="../src/Views/ordenes_pago/nueva_op_banavih.php" class="quick-dropdown-item"
                                    id="qr-nueva-banavih-item">
                                    <div class="qdi-icon qdi-icon-banavih">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                    </div>
                                    <div class="qdi-text">
                                        <span class="qdi-title">OP BANAVIH</span>
                                        <span class="qdi-desc">Aporte Fondo de Ahorro Habitacional</span>
                                    </div>
                                </a>
                                <a href="../src/Views/ordenes_pago/nueva_op_ivss.php" class="quick-dropdown-item"
                                    id="qr-nueva-ivss-item">
                                    <div class="qdi-icon qdi-icon-ivss">
                                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <div class="qdi-text">
                                        <span class="qdi-title">OP IVSS</span>
                                        <span class="qdi-desc">Aporte Seguro Social Obligatorio</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <a href="../src/Views/ordenes_servicio/nueva_os.php" class="quick-btn primary" id="qr-nueva-os">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Nueva O.S.
                    </a>
                    <a href="../src/Views/proveedores/nuevo.php" class="quick-btn primary" id="qr-nuevo-prov">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Nuevo Proveedor
                    </a>
                <?php else: ?>
                    <a href="../src/Views/ordenes_compra/index.php" class="quick-btn primary" id="qr-ver-ordenes">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Ver Órdenes
                    </a>
                <?php endif; ?>
                <a href="../src/Views/reportes/index.php" class="quick-btn secondary" id="qr-reportes">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 17v-6m3 6v-3m3 3v-9M4 19h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v13a1 1 0 001 1z" />
                    </svg>
                    Reportes
                </a>
            </div>

            <!-- ── Tarjetas de estadísticas ─────────────────────────────── -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon stat-icon-oc">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                            <polyline points="10 9 9 9 8 9" />
                        </svg>
                    </div>
                    <div class="stat-label">Órdenes de Compra (<span
                            class="periodo-lbl"><?php echo $tipo_filtro === 'dia' ? 'día' : ($tipo_filtro === 'rango' ? 'rango' : 'mes'); ?></span>)
                    </div>
                    <div class="stat-value" id="val-oc"><?php echo $stat_oc; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-op">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <line x1="2" y1="10" x2="22" y2="10" />
                        </svg>
                    </div>
                    <div class="stat-label">Órdenes de Pago (<span
                            class="periodo-lbl"><?php echo $tipo_filtro === 'dia' ? 'día' : ($tipo_filtro === 'rango' ? 'rango' : 'mes'); ?></span>)
                    </div>
                    <div class="stat-value" id="val-op"><?php echo $stat_op; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-os">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                        </svg>
                    </div>
                    <div class="stat-label">Órdenes de Servicio (<span
                            class="periodo-lbl"><?php echo $tipo_filtro === 'dia' ? 'día' : ($tipo_filtro === 'rango' ? 'rango' : 'mes'); ?></span>)
                    </div>
                    <div class="stat-value" id="val-os"><?php echo $stat_op; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon stat-icon-monto">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="2" x2="12" y2="22" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <div class="stat-label">Monto Pagado (<span
                            class="periodo-lbl"><?php echo $tipo_filtro === 'dia' ? 'día' : ($tipo_filtro === 'rango' ? 'rango' : 'mes'); ?></span>)
                    </div>
                    <div class="stat-value" id="val-monto">
                        Bs. <?php echo number_format($stat_pagado, 2, ',', '.'); ?>
                    </div>
                </div>
                <div class="stat-card amarillo">
                    <div class="stat-icon stat-icon-pendiente">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                    </div>
                    <div class="stat-label" style="color: #b45309;">Órdenes Pendientes</div>
                    <div class="stat-value amarillo" id="val-pendiente"><?php echo $stat_pendiente; ?></div>
                </div>
            </div>

            <!-- ── Fila de Tablas: Últimas OC y Últimas OP ────────────────── -->
            <div class="two-col" style="margin-bottom: 18px;">

                <!-- Tarjeta 1 — Últimas órdenes de compra -->
                <div class="panel">
                    <div class="panel-head">
                        <span class="ph-title">
                            <span class="ph-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                            </span>
                            Últimas órdenes de compra
                        </span>
                        <a href="../src/Views/ordenes_compra/index.php">Ver todas <svg width="12" height="12"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6" />
                            </svg></a>
                    </div>
                    <div class="panel-body">
                        <?php if (!$ultimas_oc || mysqli_num_rows($ultimas_oc) === 0): ?>
                            <div class="sin-datos">
                                <div class="sin-icon">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <polyline points="14 2 14 8 20 8" />
                                        <line x1="16" y1="13" x2="8" y2="13" />
                                        <line x1="16" y1="17" x2="8" y2="17" />
                                    </svg>
                                </div>
                                No hay órdenes registradas.
                            </div>
                        <?php else: ?>
                            <table id="tbl-ultimas-oc">
                                <thead>
                                    <tr>
                                        <th>N° Orden</th>
                                        <th>Proveedor</th>
                                        <th>Total Bs.</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($oc = mysqli_fetch_assoc($ultimas_oc)): ?>
                                        <?php
                                        $status = isset($oc['status']) ? $oc['status'] : 'pendiente';
                                        $badge_cls = 'badge-pendiente';
                                        if ($status === 'aprobada' || $status === 'pagado') {
                                            $badge_cls = 'badge-aprobada';
                                        }
                                        if ($status === 'rechazado' || $status === 'anulada') {
                                            $badge_cls = 'badge-anulada';
                                        }

                                        $num_orden = isset($oc['numero_orden']) && $oc['numero_orden'] != ''
                                            ? $oc['numero_orden']
                                            : $oc['id'];
                                        ?>
                                        <tr>
                                            <td style="text-align:center;"><?php echo htmlspecialchars($num_orden); ?></td>
                                            <td><?php echo htmlspecialchars(isset($oc['proveedor']) ? $oc['proveedor'] : ''); ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <?php echo number_format(isset($oc['total_general']) ? (float) $oc['total_general'] : 0, 2, ',', '.'); ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <span
                                                    class="badge <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($status); ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tarjeta 2 — Últimas órdenes de pago -->
                <div class="panel">
                    <div class="panel-head">
                        <span class="ph-title">
                            <span class="ph-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="5" width="20" height="14" rx="2" />
                                    <line x1="2" y1="10" x2="22" y2="10" />
                                </svg>
                            </span>
                            Últimas órdenes de pago
                        </span>
                        <a href="../src/Views/ordenes_pago/index.php">Ver todas <svg width="12" height="12"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6" />
                            </svg></a>
                    </div>
                    <div class="panel-body">
                        <?php if (!$ultimas_op || mysqli_num_rows($ultimas_op) === 0): ?>
                            <div class="sin-datos">
                                <div class="sin-icon">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                        <line x1="2" y1="10" x2="22" y2="10" />
                                    </svg>
                                </div>
                                No hay órdenes registradas.
                            </div>
                        <?php else: ?>
                            <table id="tbl-ultimas-op">
                                <thead>
                                    <tr>
                                        <th>N° Pago</th>
                                        <th>Beneficiario</th>
                                        <th>Monto Neto Bs.</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($op = mysqli_fetch_assoc($ultimas_op)): ?>
                                        <?php
                                        $status = isset($op['status']) ? $op['status'] : 'pendiente';
                                        $badge_cls = 'badge-pendiente';
                                        if ($status === 'pagada' || $status === 'pagado' || $status === 'aprobada') {
                                            $badge_cls = 'badge-pagada';
                                        }
                                        if ($status === 'rechazado' || $status === 'anulada') {
                                            $badge_cls = 'badge-anulada';
                                        }

                                        $num_pago = isset($op['numero']) && $op['numero'] != ''
                                            ? $op['numero']
                                            : $op['id'];
                                        ?>
                                        <tr>
                                            <td style="text-align:center;"><?php echo htmlspecialchars($num_pago); ?></td>
                                            <td><?php echo htmlspecialchars(isset($op['beneficiario']) ? $op['beneficiario'] : ''); ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <?php echo number_format(isset($op['monto_neto_pagar']) ? (float) $op['monto_neto_pagar'] : 0, 2, ',', '.'); ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <span
                                                    class="badge <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($status); ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /.two-col -->

            <!-- Fila inferior: Gráficos de Partida y Tendencia -->
            <div class="two-col">

                <!-- Gráfico de Gasto por Partida -->
                <div class="panel">
                    <div class="panel-head">
                        <span class="ph-title">
                            <span class="ph-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.21 15.89A10 10 0 1 1 8 2.83" />
                                    <path d="M22 12A10 10 0 0 0 12 2v10z" />
                                </svg>
                            </span>
                            Gasto por Partida (<span
                                class="periodo-lbl"><?php echo $tipo_filtro === 'dia' ? 'día' : ($tipo_filtro === 'rango' ? 'rango' : 'mes'); ?></span>)
                        </span>
                        <span style="font-size:10px; color:#94a3b8; font-weight:normal;">desde oc_partidas</span>
                    </div>
                    <div class="panel-body">
                        <div id="chart-partidas-wrapper"
                            style="position: relative; height: 220px; display: <?php echo empty($partidas) ? 'none' : 'block'; ?>;">
                            <canvas id="chartPartidasCanvas"></canvas>
                        </div>
                        <div id="partidas-sin-datos" class="sin-datos"
                            style="display: <?php echo empty($partidas) ? 'block' : 'none'; ?>;">
                            <div class="sin-icon">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                                    <path
                                        d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                                </svg>
                            </div>
                            Sin datos de partidas para el período seleccionado.
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Tendencia de Transacciones -->
                <div class="panel">
                    <div class="panel-head">
                        <span class="ph-title">
                            <span class="ph-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                                    <polyline points="16 7 22 7 22 13" />
                                </svg>
                            </span>
                            Tendencia de Transacciones (Bs.)
                        </span>
                        <span style="font-size:10px; color:#94a3b8; font-weight:normal;">OC vs OP en el período</span>
                    </div>
                    <div class="panel-body">
                        <div id="chart-trend-wrapper"
                            style="position: relative; height: 220px; display: <?php echo empty($trend_dates) ? 'none' : 'block'; ?>;">
                            <canvas id="chartTrendCanvas"></canvas>
                        </div>
                        <div id="trend-sin-datos" class="sin-datos"
                            style="display: <?php echo empty($trend_dates) ? 'block' : 'none'; ?>;">
                            <div class="sin-icon">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                                    <polyline points="16 7 22 7 22 13" />
                                </svg>
                            </div>
                            Sin datos de tendencia para el período seleccionado.
                        </div>
                    </div>
                </div>

            </div><!-- /.two-col -->

        </main><!-- /.main-content -->
    </div><!-- /.layout -->

    <!-- ══════════════════════════════════════════════════════════════════════
     FOOTER
     ══════════════════════════════════════════════════════════════════════ --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?><!-- Chart.js CDN -->
    <script src="/sistema/assets/js/chart.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tipoFiltro = document.getElementById('tipo_filtro');
            const wrapperMes = document.getElementById('wrapper-mes');
            const wrapperDia = document.getElementById('wrapper-dia');
            const wrapperRango = document.getElementById('wrapper-rango');

            const inputMes = document.getElementById('filtro_mes');
            const inputDia = document.getElementById('filtro_dia');
            const inputInicio = document.getElementById('filtro_inicio');
            const inputFin = document.getElementById('filtro_fin');
            const btnReset = document.getElementById('btn-restablecer');

            // Element references
            const valOc = document.getElementById('val-oc');
            const valOp = document.getElementById('val-op');
            const valOs = document.getElementById('val-os');
            const valMonto = document.getElementById('val-monto');
            const valPendiente = document.getElementById('val-pendiente');
            const periodoLbls = document.querySelectorAll('.periodo-lbl');

            const tblOcBody = document.querySelector('#tbl-ultimas-oc tbody');
            const tblOpBody = document.querySelector('#tbl-ultimas-op tbody');

            const chartPartidasWrapper = document.getElementById('chart-partidas-wrapper');
            const partidasSinDatos = document.getElementById('partidas-sin-datos');
            const chartTrendWrapper = document.getElementById('chart-trend-wrapper');
            const trendSinDatos = document.getElementById('trend-sin-datos');

            // Chart instances
            let partidasChart = null;
            let trendChart = null;

            // Data from PHP initial render
            const initialPartidas = <?php echo json_encode(array_map(function ($p) {
                return array('partida' => $p['partida'], 'total' => (float) $p['total']);
            }, $partidas)); ?>;

            const initialTrend = {
                labels: <?php echo json_encode($trend_dates); ?>,
                oc: <?php echo json_encode($trend_oc); ?>,
                op: <?php echo json_encode($trend_op); ?>
            };

            // 1. Initialize Chart.js Graphs
            function initCharts(partidasData, trendData) {
                // Chart 1: Partidas Chart
                const ctxPartidas = document.getElementById('chartPartidasCanvas').getContext('2d');
                partidasChart = new Chart(ctxPartidas, {
                    type: 'doughnut',
                    data: {
                        labels: partidasData.map(p => p.partida),
                        datasets: [{
                            data: partidasData.map(p => p.total),
                            backgroundColor: [
                                '#1565c0', '#059669', '#d97706', '#7c3aed',
                                '#dc2626', '#0284c7', '#059669', '#4f46e5'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { font: { family: 'Inter', size: 11 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        let val = context.parsed || 0;
                                        return ' ' + context.label + ': Bs. ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                }
                            }
                        }
                    }
                });

                // Chart 2: Line/Bar Chart
                const ctxTrend = document.getElementById('chartTrendCanvas').getContext('2d');
                trendChart = new Chart(ctxTrend, {
                    type: 'bar',
                    data: {
                        labels: trendData.labels,
                        datasets: [
                            {
                                label: 'Órdenes de Compra (Bs.)',
                                data: trendData.oc,
                                backgroundColor: 'rgba(21, 101, 192, 0.75)',
                                borderColor: '#1565c0',
                                borderWidth: 1,
                                borderRadius: 4
                            },
                            {
                                label: 'Órdenes de Pago (Bs.)',
                                data: trendData.op,
                                backgroundColor: 'rgba(16, 185, 129, 0.75)',
                                borderColor: '#10b981',
                                borderWidth: 1,
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: 'Inter', size: 10 } }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    font: { family: 'Inter', size: 10 },
                                    callback: function (val) { return 'Bs. ' + val.toLocaleString('en-US'); }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { font: { family: 'Inter', size: 10 } }
                            }
                        }
                    }
                });
            }

            initCharts(initialPartidas, initialTrend);

            // 2. Filter wrapper toggles -- Control contenedor filtros
            tipoFiltro.addEventListener('change', function () {
                const val = this.value;
                wrapperMes.style.display = (val === 'mes') ? 'inline-flex' : 'none';
                wrapperDia.style.display = (val === 'dia') ? 'inline-flex' : 'none';
                wrapperRango.style.display = (val === 'rango') ? 'inline-flex' : 'none';
                fetchData();
            });

            // Trigger global de busqueda para los single pickers
            window.triggerFetchData = function () {
                fetchData();
            };

            // Reset button
            btnReset.addEventListener('click', function () {
                tipoFiltro.value = 'mes';
                wrapperMes.style.display = 'inline-flex';
                wrapperDia.style.display = 'none';
                wrapperRango.style.display = 'none';

                const now = new Date();
                const year = now.getFullYear();
                const monthNum = String(now.getMonth() + 1).padStart(2, '0');
                const dayNum = String(now.getDate()).padStart(2, '0');

                const monthsEs = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

                inputMes.value = year + '-' + monthNum;
                document.getElementById('display_mes').value = monthsEs[now.getMonth()] + ' ' + year;

                inputDia.value = year + '-' + monthNum + '-' + dayNum;
                document.getElementById('display_dia').value = dayNum + '/' + monthNum + '/' + year;

                fetchData();
            });

            // 4. Funcion Dynamic AJAX Fetch
            function fetchData() {
                const tipo = tipoFiltro.value;
                let params = new URLSearchParams({ ajax: '1', tipo_filtro: tipo });

                if (tipo === 'mes') params.append('mes', inputMes.value);
                if (tipo === 'dia') params.append('dia', inputDia.value);
                if (tipo === 'rango') {
                    params.append('fecha_inicio', inputInicio.value);
                    params.append('fecha_fin', inputFin.value);
                }

                // Show skeleton loaders on stats
                valOc.innerHTML = '<span class="skeleton skeleton-text"></span>';
                valOp.innerHTML = '<span class="skeleton skeleton-text"></span>';
                valOs.innerHTML = '<span class="skeleton skeleton-text"></span>';
                valMonto.innerHTML = '<span class="skeleton skeleton-text"></span>';

                fetch('index.php?' + params.toString())
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) return;

                        // Update dynamic period labels
                        const lblText = tipo === 'dia' ? 'día' : (tipo === 'rango' ? 'rango' : 'mes');
                        periodoLbls.forEach(el => el.textContent = lblText);

                        // Update stats with smooth counting
                        valOc.textContent = data.stats.oc_count;
                        valOp.textContent = data.stats.op_count;
                        valOs.textContent = data.stats.os_count;
                        valMonto.textContent = data.stats.monto_pagado_formatted;
                        if (valPendiente) valPendiente.textContent = (data.stats.pendiente_count !== undefined ? data.stats.pendiente_count : data.stats.borrador_count);

                        // Update OC Table
                        renderOcTable(data.ultimas_oc);

                        // Update OP Table
                        renderOpTable(data.ultimas_op);

                        // Update Charts
                        updateCharts(data.partidas, data.trend);
                    })
                    .catch(err => console.error('Error fetching dashboard data:', err));
            }

            function renderOcTable(items) {
                if (!tblOcBody) return;
                if (!items || items.length === 0) {
                    tblOcBody.innerHTML = '<tr><td colspan="4" class="sin-datos">No hay órdenes registradas para el período seleccionado.</td></tr>';
                    return;
                }
                let html = '';
                items.forEach(oc => {
                    let badgeCls = 'badge-pendiente';
                    if (oc.status === 'aprobada' || oc.status === 'pagado') badgeCls = 'badge-aprobada';
                    if (oc.status === 'rechazado' || oc.status === 'anulada') badgeCls = 'badge-anulada';
                    html += `<tr>
                    <td style="text-align:center;">${oc.numero_orden}</td>
                    <td>${oc.proveedor}</td>
                    <td style="text-align:right;">Bs. ${oc.total_formatted}</td>
                    <td style="text-align:center;"><span class="badge ${badgeCls}">${oc.status}</span></td>
                </tr>`;
                });
                tblOcBody.innerHTML = html;
            }

            function renderOpTable(items) {
                if (!tblOpBody) return;
                if (!items || items.length === 0) {
                    tblOpBody.innerHTML = '<tr><td colspan="4" class="sin-datos">No hay órdenes registradas para el período seleccionado.</td></tr>';
                    return;
                }
                let html = '';
                items.forEach(op => {
                    let badgeCls = 'badge-pendiente';
                    if (op.status === 'pagada' || op.status === 'pagado' || op.status === 'aprobada') badgeCls = 'badge-pagada';
                    if (op.status === 'rechazado' || op.status === 'anulada') badgeCls = 'badge-anulada';
                    html += `<tr>
                    <td style="text-align:center;">${op.numero}</td>
                    <td>${op.beneficiario}</td>
                    <td style="text-align:right;">Bs. ${op.monto_formatted}</td>
                    <td style="text-align:center;"><span class="badge ${badgeCls}">${op.status}</span></td>
                </tr>`;
                });
                tblOpBody.innerHTML = html;
            }

            function updateCharts(partidas, trend) {
                // Update Partidas chart
                if (partidasChart) {
                    if (!partidas || partidas.length === 0) {
                        chartPartidasWrapper.style.display = 'none';
                        partidasSinDatos.style.display = 'block';
                    } else {
                        chartPartidasWrapper.style.display = 'block';
                        partidasSinDatos.style.display = 'none';
                        partidasChart.data.labels = partidas.map(p => p.partida);
                        partidasChart.data.datasets[0].data = partidas.map(p => p.total);
                        partidasChart.update();
                    }
                }

                // Update Trend chart
                if (trendChart) {
                    if (!trend || !trend.labels || trend.labels.length === 0) {
                        chartTrendWrapper.style.display = 'none';
                        trendSinDatos.style.display = 'block';
                    } else {
                        chartTrendWrapper.style.display = 'block';
                        trendSinDatos.style.display = 'none';
                        trendChart.data.labels = trend.labels;
                        trendChart.data.datasets[0].data = trend.oc;
                        trendChart.data.datasets[1].data = trend.op;
                        trendChart.update();
                    }
                }
            }
        });

        /* ─── Single-Input Spanish Date & Month Picker Implementation ─────── */
        (function () {
            var monthsEs = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            var monthsShortEs = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            var daysEs = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];

            var activePopup = null;

            function closePopup() {
                if (activePopup && activePopup.parentNode) {
                    activePopup.parentNode.removeChild(activePopup);
                }
                activePopup = null;
            }

            document.addEventListener('click', function (e) {
                if (activePopup && !activePopup.contains(e.target) && !e.target.classList.contains('sp-picker-input')) {
                    closePopup();
                }
            });

            /* --- Month Picker Popup --- */
            window.openMonthPicker = function (displayEl, hiddenEl) {
                closePopup();

                var val = hiddenEl.value || '2026-08';
                var parts = val.split('-');
                var currYear = parseInt(parts[0], 10) || 2026;
                var currMonth = (parseInt(parts[1], 10) || 8) - 1;

                var pop = document.createElement('div');
                pop.className = 'sp-picker-popover sp-month-popover';

                function render() {
                    var html = '<div class="sp-pop-header">';
                    html += '<button type="button" class="sp-pop-nav sp-prev-year">&laquo;</button>';
                    html += '<span class="sp-pop-title">' + currYear + '</span>';
                    html += '<button type="button" class="sp-pop-nav sp-next-year">&raquo;</button>';
                    html += '</div>';
                    html += '<div class="sp-month-grid">';
                    var selValParts = (hiddenEl.value || '').split('-');
                    var selY = parseInt(selValParts[0], 10);
                    var selM = parseInt(selValParts[1], 10) - 1;

                    for (var i = 0; i < 12; i++) {
                        var isSel = (i === selM && currYear === selY);
                        html += '<button type="button" class="sp-month-btn ' + (isSel ? 'selected' : '') + '" data-m="' + i + '">' + monthsShortEs[i] + '</button>';
                    }
                    html += '</div>';
                    pop.innerHTML = html;

                    pop.querySelector('.sp-prev-year').onclick = function (e) { e.stopPropagation(); currYear--; render(); };
                    pop.querySelector('.sp-next-year').onclick = function (e) { e.stopPropagation(); currYear++; render(); };

                    var mBtns = pop.querySelectorAll('.sp-month-btn');
                    for (var j = 0; j < mBtns.length; j++) {
                        mBtns[j].onclick = function (e) {
                            e.stopPropagation();
                            var mIdx = parseInt(this.getAttribute('data-m'), 10);
                            var mStr = (mIdx + 1 < 10 ? '0' : '') + (mIdx + 1);
                            hiddenEl.value = currYear + '-' + mStr;
                            displayEl.value = monthsEs[mIdx] + ' ' + currYear;
                            closePopup();
                            if (window.triggerFetchData) window.triggerFetchData();
                        };
                    }
                }

                render();
                displayEl.parentNode.appendChild(pop);
                activePopup = pop;
            };

            /* --- Day/Date Picker Popup --- */
            window.openDatePicker = function (displayEl, hiddenEl) {
                closePopup();

                var val = hiddenEl.value || '2026-08-25';
                var parts = val.split('-');
                var currYear = parseInt(parts[0], 10) || 2026;
                var currMonth = (parseInt(parts[1], 10) || 8) - 1;

                var pop = document.createElement('div');
                pop.className = 'sp-picker-popover sp-date-popover';

                function render() {
                    var html = '<div class="sp-pop-header">';
                    html += '<button type="button" class="sp-pop-nav sp-prev-mon">&lsaquo;</button>';
                    html += '<span class="sp-pop-title">' + monthsEs[currMonth] + ' ' + currYear + '</span>';
                    html += '<button type="button" class="sp-pop-nav sp-next-mon">&rsaquo;</button>';
                    html += '</div>';

                    html += '<div class="sp-day-names">';
                    for (var d = 0; d < 7; d++) {
                        html += '<span>' + daysEs[d] + '</span>';
                    }
                    html += '</div>';

                    var firstDay = new Date(currYear, currMonth, 1).getDay(); // 0 is Sun
                    var startCol = (firstDay === 0 ? 6 : firstDay - 1); // Mon=0 .. Sun=6
                    var daysInMonth = new Date(currYear, currMonth + 1, 0).getDate();

                    var selParts = (hiddenEl.value || '').split('-');
                    var selY = parseInt(selParts[0], 10);
                    var selM = parseInt(selParts[1], 10) - 1;
                    var selD = parseInt(selParts[2], 10);

                    html += '<div class="sp-days-grid">';
                    for (var empty = 0; empty < startCol; empty++) {
                        html += '<span class="sp-day-empty"></span>';
                    }
                    for (var day = 1; day <= daysInMonth; day++) {
                        var isSel = (day === selD && currMonth === selM && currYear === selY);
                        html += '<button type="button" class="sp-day-btn ' + (isSel ? 'selected' : '') + '" data-day="' + day + '">' + day + '</button>';
                    }
                    html += '</div>';

                    pop.innerHTML = html;

                    pop.querySelector('.sp-prev-mon').onclick = function (e) {
                        e.stopPropagation();
                        currMonth--;
                        if (currMonth < 0) { currMonth = 11; currYear--; }
                        render();
                    };
                    pop.querySelector('.sp-next-mon').onclick = function (e) {
                        e.stopPropagation();
                        currMonth++;
                        if (currMonth > 11) { currMonth = 0; currYear++; }
                        render();
                    };

                    var dBtns = pop.querySelectorAll('.sp-day-btn');
                    for (var j = 0; j < dBtns.length; j++) {
                        dBtns[j].onclick = function (e) {
                            e.stopPropagation();
                            var dayNum = parseInt(this.getAttribute('data-day'), 10);
                            var mStr = (currMonth + 1 < 10 ? '0' : '') + (currMonth + 1);
                            var dStr = (dayNum < 10 ? '0' : '') + dayNum;
                            hiddenEl.value = currYear + '-' + mStr + '-' + dStr;
                            displayEl.value = dStr + '/' + mStr + '/' + currYear;
                            closePopup();
                            if (window.triggerFetchData) window.triggerFetchData();
                        };
                    }
                }

                render();
                displayEl.parentNode.appendChild(pop);
                activePopup = pop;
            };
        })();
    </script>
</body>

</html>