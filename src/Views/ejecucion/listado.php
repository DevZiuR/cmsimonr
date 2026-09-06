<?php
/**
 * src/Views/ejecucion/listado.php
 * Listado general de ejecuciones presupuestarias por período (Mes/Año)
 * Contraloría del Municipio Simón Rodríguez
 * PHP 5.6 compatible
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
$es_supervisor = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'supervisor');

require_once '../../../config/conexion.php';
require_once __DIR__ . '/../../Models/catalogo_partidas.php';

$anio_filtro = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int) $_GET['anio'] : 0;
$where_anio = ($anio_filtro > 0) ? "WHERE anio = $anio_filtro" : "";

/* ── Exportar a Excel (CSV) ───────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $meses_es_exp = array(
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    );

    $filename = 'listado_ejecucion_presupuestaria_' . ($anio_filtro > 0 ? 'anio_' . $anio_filtro . '_' : '') . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($output, array('Período', 'Año', 'Mes', 'Partidas', 'Crédito Aprobado (Bs.)', 'Crédito Actualizado (Bs.)', 'Gastos Causados (Bs.)', 'Pagos Acumulados (Bs.)', 'Disponibilidad (Bs.)', '% Ejecución'), ';');
    $sql = "SELECT anio, mes,
                   COUNT(*) AS total_partidas,
                   SUM(credito_aprobado) AS tot_aprobado,
                   SUM(COALESCE(credito_actualizado_override, credito_aprobado + COALESCE(credito_adicional_override, 0) + aumentos - disminuciones)) AS tot_actualizado,
                   SUM(COALESCE(gastos_causados_override, COALESCE(compromiso_acumulado_override, 0))) AS tot_gastos,
                   SUM(COALESCE(pago_acumulado_override, 0)) AS tot_pago,
                   SUM(COALESCE(disponibilidad_override, (credito_aprobado + COALESCE(credito_adicional_override, 0) + aumentos - disminuciones) - COALESCE(compromiso_acumulado_override, 0))) AS tot_disponibilidad
            FROM ejecucion_presupuestaria
            $where_anio
            GROUP BY anio, mes
            ORDER BY anio DESC, mes DESC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $m_n = (int) $row['mes'];
            $nom_m = isset($meses_es_exp[$m_n]) ? $meses_es_exp[$m_n] : "Mes $m_n";
            $tot_act = (float) $row['tot_actualizado'];
            $tot_gas = (float) $row['tot_gastos'];
            $pct = ($tot_act != 0) ? ($tot_gas / $tot_act * 100) : 0;
            fputcsv($output, array(
                $nom_m . ' ' . $row['anio'],
                $row['anio'],
                $nom_m,
                $row['total_partidas'],
                number_format((float) $row['tot_aprobado'], 2, '.', ','),
                number_format((float) $row['tot_actualizado'], 2, '.', ','),
                number_format((float) $row['tot_gastos'], 2, '.', ','),
                number_format((float) $row['tot_pago'], 2, '.', ','),
                number_format((float) $row['tot_disponibilidad'], 2, '.', ','),
                number_format($pct, 2, '.', ',') . '%'
            ), ';');
        }
    }
    fclose($output);
    exit;
}

/* ── Auto-crear tabla si no existe ── */
mysqli_query(
    $conn,
    "CREATE TABLE IF NOT EXISTS ejecucion_presupuestaria (
        id INT(11) NOT NULL AUTO_INCREMENT,
        codificacion VARCHAR(50) NOT NULL,
        denominacion VARCHAR(300) NOT NULL,
        credito_aprobado DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        aumentos DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        disminuciones DECIMAL(18,2) NOT NULL DEFAULT 0.00,
        mes TINYINT(2) NOT NULL,
        anio SMALLINT(4) NOT NULL,
        credito_actualizado_override DECIMAL(18,2) NULL,
        compromiso_mensual_override DECIMAL(18,2) NULL,
        compromiso_acumulado_override DECIMAL(18,2) NULL,
        gastos_causados_override DECIMAL(18,2) NULL,
        pago_acumulado_override DECIMAL(18,2) NULL,
        compromisos_pagar_override DECIMAL(18,2) NULL,
        disponibilidad_override DECIMAL(18,2) NULL,
        credito_adicional_override DECIMAL(18,2) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_codificacion_mes_anio (codificacion, mes, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
);

$meses_es = array(
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
);

$anio_filtro = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int) $_GET['anio'] : 0;
$where_anio = ($anio_filtro > 0) ? "WHERE anio = $anio_filtro" : "";

/* ── Obtener todos los períodos agrupados por año y mes ── */
$sql_periodos = "SELECT anio, mes,
                        COUNT(*) AS total_partidas,
                        SUM(credito_aprobado) AS tot_aprobado,
                        SUM(aumentos) AS tot_aumentos,
                        SUM(disminuciones) AS tot_disminuciones,
                        SUM(COALESCE(credito_adicional_override, 0)) AS tot_adicional,
                        SUM(COALESCE(credito_actualizado_override, credito_aprobado + COALESCE(credito_adicional_override, 0) + aumentos - disminuciones)) AS tot_actualizado,
                        SUM(COALESCE(compromiso_mensual_override, 0)) AS tot_comp_mensual,
                        SUM(COALESCE(compromiso_acumulado_override, 0)) AS tot_comp_acumulado,
                        SUM(COALESCE(gastos_causados_override, COALESCE(compromiso_acumulado_override, 0))) AS tot_gastos,
                        SUM(COALESCE(pago_acumulado_override, 0)) AS tot_pago,
                        SUM(COALESCE(disponibilidad_override, (credito_aprobado + COALESCE(credito_adicional_override, 0) + aumentos - disminuciones) - COALESCE(compromiso_acumulado_override, 0))) AS tot_disponibilidad
                 FROM ejecucion_presupuestaria
                 $where_anio
                 GROUP BY anio, mes
                 ORDER BY anio DESC, mes DESC";

$res_periodos = mysqli_query($conn, $sql_periodos);
$periodos = array();
$kpi_total_periodos = 0;
$kpi_total_actualizado = 0;
$kpi_total_gastos = 0;
$kpi_total_disponibilidad = 0;

if ($res_periodos) {
    while ($p = mysqli_fetch_assoc($res_periodos)) {
        $periodos[] = $p;
        $kpi_total_periodos++;
        $kpi_total_actualizado += (float) $p['tot_actualizado'];
        $kpi_total_gastos += (float) $p['tot_gastos'];
        $kpi_total_disponibilidad += (float) $p['tot_disponibilidad'];
    }
}

/* ── Años disponibles para selector ── */
$res_anios = mysqli_query($conn, "SELECT DISTINCT anio FROM ejecucion_presupuestaria ORDER BY anio DESC");
$anios_disponibles = array();
if ($res_anios) {
    while ($ay = mysqli_fetch_assoc($res_anios)) {
        $anios_disponibles[] = (int) $ay['anio'];
    }
}
if (empty($anios_disponibles)) {
    $anios_disponibles[] = (int) date('Y');
}

function fmt_m($v)
{
    return number_format((float) $v, 2, '.', ',');
}

$active = 'ejecucion-lista';
$active_page = 'ejecucion-lista';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Listado de Ejecución Presupuestaria - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Listado de Ejecución Presupuestaria – Contraloría MSR</title>
    <style>
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
            background: #e4e9ef;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #1e293b;
        }

        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 32px 36px;
            overflow-x: auto;
        }

        /* ── Barra de título / Banner (Top Bar) ────────────────────────── */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-image: url('/sistema/assets/img/banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 18px 24px;
            margin-bottom: 24px;
            border-radius: 14px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
            min-height: 80px;
            flex-wrap: wrap;
            gap: 14px;
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
            text-transform: uppercase;
            color: #ffffff;
            margin: 0;
        }

        .top-bar p {
            font-size: 11.5px;
            opacity: 0.85;
            margin-top: 3px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
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
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            white-space: nowrap;
            font-family: inherit;
        }

        .btn-nueva:hover {
            background: #ffffff;
            color: #1d4ed8;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
            transform: translateY(-1px);
        }

        .btn-nueva-secondary {
            background: rgba(15, 23, 42, 0.45);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .btn-nueva-secondary:hover {
            background: rgba(15, 23, 42, 0.7);
            color: #38bdf8;
            border-color: #38bdf8;
        }

        /* ── KPI Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #c8d4e0;
            border-top: 4px solid #2563eb;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 8px rgba(10, 18, 40, 0.07);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(10, 18, 40, 0.12);
        }

        .stat-card.c-blue {
            border-top-color: #2563eb;
        }

        .stat-card.c-green {
            border-top-color: #059669;
        }

        .stat-card.c-amber {
            border-top-color: #d97706;
        }

        .stat-card.c-purple {
            border-top-color: #7c3aed;
        }

        .stat-card .stat-label {
            font-size: 10.5px;
            color: #475569;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }

        .stat-card .stat-sub {
            font-size: 10.5px;
            color: #94a3b8;
            margin-top: 6px;
        }

        /* ── Toolbar Card ── */
        .toolbar-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 16px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            border: 1px solid #c8d4e0;
            border-left: 4px solid #2563eb;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .06);
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: 7px 12px;
            font-family: inherit;
            font-size: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            color: #1e293b;
            outline: none;
            cursor: pointer;
        }

        .filter-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-family: inherit;
            border: none;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(37, 99, 235, .25);
        }

        .btn-success {
            background: #065f46;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* ── Table Container ── */
        .table-container {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #c8d4e0;
            box-shadow: 0 3px 10px rgba(15, 23, 42, .07);
            overflow: hidden;
        }

        .table-head-bar {
            padding: 14px 20px;
            background: #101a36;
            border-bottom: 3px solid #2563eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-head-bar h2 {
            font-size: 13px;
            font-weight: 700;
            color: #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .list-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        .list-table thead th {
            background: #1e293b;
            color: #ffffff;
            padding: 13px 16px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 1px solid #334155;
            text-align: right;
            white-space: nowrap;
        }

        .list-table thead th.text-left {
            text-align: left;
        }

        .list-table thead th.text-center {
            text-align: center;
        }

        .list-table tbody tr {
            transition: background 0.1s ease;
        }

        .list-table tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        .list-table tbody tr:nth-child(even) {
            background: #f4f7fb;
        }

        .list-table tbody tr:hover {
            background: #dbeafe;
        }

        .list-table td {
            padding: 13px 16px;
            border: 1px solid #d0dae6;
            vertical-align: middle;
            color: #1e293b;
            font-variant-numeric: tabular-nums;
        }

        .list-table td.text-left {
            text-align: left;
        }

        .list-table td.text-right {
            text-align: right;
        }

        .list-table td.text-center {
            text-align: center;
        }

        .period-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 1px;
            color: #3255c8ff;
        }

        .period-pill {
            background: #eff6ff;
            color: #171719ff;
            border: 1px solid #bfdbfe;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 600;
        }

        .pct-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
        }

        .pct-low {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .pct-mid {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .pct-high {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .btn-table {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            font-size: 10.5px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            font-family: inherit;
            transition: all 0.12s ease;
        }

        .btn-table-view {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .btn-table-view:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        .btn-table-print {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }

        .btn-table-print:hover {
            background: #059669;
            color: #ffffff;
            border-color: #059669;
        }

        .btn-table-del {
            background: #dc2626;
            color: #e7ddddff;
            border-color: #fecaca;
        }

        .btn-table-del:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state svg {
            margin-bottom: 14px;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 15px;
            color: #334155;
            margin-bottom: 6px;
        }

        /* ── Modal Cargar Período ── */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 14px;
            width: 440px;
            max-width: 92%;
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: modalIn 0.15s ease-out;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.96);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            padding: 14px 18px;
            background: #0f172a;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .modal-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 18px;
            cursor: pointer;
        }

        .modal-close:hover {
            color: #ffffff;
        }

        .modal-body {
            padding: 18px 20px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 12px;
            color: #1e293b;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 14px 20px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
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
            border-radius: 6px;
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
            display: none;
        }

        /* ── Estilos de Impresión / PDF ──────────────────────────────────── */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            @page {
                size: letter landscape;
                margin: 8mm 8mm 8mm 8mm;
            }

            html,
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Inter', Arial, sans-serif !important;
                font-size: 8.5pt !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .sidebar,
            .page-header,
            .top-bar,
            .stats-grid,
            .toolbar-card,
            .modal-backdrop,
            .export-btn-group,
            .btn-action,
            .btn-table,
            th:last-child,
            td:last-child,
            .no-print {
                display: none !important;
            }

            .layout,
            .main-content,
            .table-container {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
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

            .doc-header-institutional .doc-title-center {
                text-align: center;
                flex-grow: 1;
                padding: 0 15px;
            }

            .doc-header-institutional .doc-title-center p {
                font-size: 8.5pt !important;
                line-height: 1.3 !important;
                color: #000000 !important;
                margin: 0;
            }

            .doc-header-institutional .doc-title-center h2 {
                font-size: 12pt !important;
                color: #000000 !important;
                margin-top: 4px !important;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .table-head-bar {
                background: #f1f5f9 !important;
                color: #000000 !important;
                border: 1px solid #64748b !important;
                border-bottom: none !important;
                font-size: 8.5pt !important;
                padding: 4px 6px !important;
            }

            .table-head-bar h2 {
                color: #000000 !important;
                font-size: 9pt !important;
            }

            table.list-table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 8.5pt !important;
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
            }

            table.list-table th {
                background: #e2e8f0 !important;
                color: #000000 !important;
                border: 1px solid #64748b !important;
                padding: 5px 6px !important;
                font-weight: 700 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            table.list-table td {
                border: 1px solid #94a3b8 !important;
                padding: 4px 6px !important;
                color: #000000 !important;
                background: transparent !important;
            }

            .period-badge {
                color: #000000 !important;
            }

            .period-pill,
            .pct-badge {
                border: 1px solid #94a3b8 !important;
                background: #f8fafc !important;
                color: #000000 !important;
            }
        }
    </style>
</head>

<body>

    <div class="layout">

        <!-- SIDEBAR -->
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <!-- ══ ENCABEZADO INSTITUCIONAL (PRINT) ═════════════════════════════ -->
            <div class="doc-header-institutional">
                <div class="doc-logo-left">
                    <img src="/sistema/assets/img/logo.png" alt="Logo Contraloría">
                </div>
                <div class="doc-title-center">
                    <p><strong>REPÚBLICA BOLIVARIANA DE VENEZUELA</strong><br>
                        ESTADO ANZOÁTEGUI<br>
                        <strong>CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</strong>
                    </p>
                    <h2 id="print-report-title">Listado de Ejecución Presupuestaria</h2>
                </div>
                <div class="doc-logo-right">
                    <img src="/sistema/assets/img/sncf.png" alt="NCF">
                </div>
            </div>

            <!-- ══ BARRA DE TÍTULO Y BOTÓN DE ACCIÓN ═════════════════════════════ -->
            <div class="top-bar no-print">
                <div>
                    <h1>LISTADO DE EJECUCIÓN PRESUPUESTARIA</h1>
                    <p>Listado general de matrices mensuales y estado de ejecución del presupuesto</p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                    <?php if ($es_admin): ?>
                        <button type="button" class="btn-nueva" onclick="openModalNuevoPeriodo()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            + CARGAR NUEVO PERÍODO
                        </button>
                    <?php endif; ?>
                    <a href="/sistema/src/Views/ejecucion/index.php" class="btn-nueva btn-nueva-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                        VER MES ACTUAL
                    </a>
                </div>
            </div>

            <!-- KPI Stats Cards -->
            <div class="stats-grid no-print">
                <div class="stat-card c-blue">
                    <div class="stat-label">
                        <span>Períodos Registrados</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo $kpi_total_periodos; ?></div>
                    <div class="stat-sub">Meses con matrices presupuestarias</div>
                </div>
                <div class="stat-card c-purple">
                    <div class="stat-label">
                        <span>Crédito Actualizado</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <div class="stat-value">Bs. <?php echo fmt_m($kpi_total_actualizado); ?></div>
                    <div class="stat-sub">Presupuesto total actualizado</div>
                </div>
                <div class="stat-card c-amber">
                    <div class="stat-label">
                        <span>Gastos Causados YTD</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                            <polyline points="17 6 23 6 23 12" />
                        </svg>
                    </div>
                    <div class="stat-value">Bs. <?php echo fmt_m($kpi_total_gastos); ?></div>
                    <div class="stat-sub">Total gastos causados acumulados</div>
                </div>
                <div class="stat-card c-green">
                    <div class="stat-label">
                        <span>Disponibilidad Global</span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div class="stat-value">Bs. <?php echo fmt_m($kpi_total_disponibilidad); ?></div>
                    <div class="stat-sub">Saldo presupuestario disponible</div>
                </div>
            </div>

            <!-- Filter / Toolbar Card -->
            <div class="toolbar-card no-print">
                <form method="GET" action="listado.php" class="filter-form">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Filtrar
                        Año:</span>
                    <select name="anio" class="filter-select" onchange="this.form.submit()">
                        <option value="0" <?php echo ($anio_filtro === 0 ? 'selected' : ''); ?>>— Todos los Años —
                        </option>
                        <?php foreach ($anios_disponibles as $ay): ?>
                            <option value="<?php echo $ay; ?>" <?php echo ($anio_filtro === $ay ? 'selected' : ''); ?>>
                                Año <?php echo $ay; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($anio_filtro > 0): ?>
                        <a href="listado.php" class="btn-action btn-secondary" style="padding:6px 10px;font-size:11px;">✕
                            Quitar Filtro</a>
                    <?php endif; ?>
                </form>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:11px;color:#64748b;font-weight:600;">
                        Mostrando <?php echo count($periodos); ?> período(s) registrado(s)
                    </span>
                    <div class="export-btn-group">
                        <button type="button" class="btn-export btn-pdf" onclick="imprimirPDF()"
                            title="Imprimir / Guardar en PDF">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg>
                            <span>PDF</span>
                        </button>
                        <button type="button" class="btn-export btn-excel" onclick="exportarExcel()"
                            title="Descargar reporte en Excel / CSV">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <path d="M8 13h8" />
                                <path d="M8 17h8" />
                                <path d="M10 9h4" />
                            </svg>
                            <span>Excel</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Listado Table -->
            <div class="table-container">
                <div class="table-head-bar">
                    <h2>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                        Matrices Mensuales de Ejecución
                    </h2>
                </div>

                <?php if (empty($periodos)): ?>
                    <div class="empty-state">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                        <h3>No hay ejecuciones presupuestarias registradas</h3>
                        <p style="font-size:12px;margin-bottom:16px;">Comienza cargando las partidas presupuestarias para un
                            mes y año.</p>
                        <button type="button" class="btn-action btn-primary" onclick="openModalNuevoPeriodo()">
                            + Cargar Primer Período
                        </button>
                    </div>
                <?php else: ?>
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th class="text-left">Período</th>
                                <th class="text-center">Partidas</th>
                                <th>Crédito Aprobado</th>
                                <th>Crédito Actualizado</th>
                                <th>Gastos Causados</th>
                                <th>Pagos Acumulados</th>
                                <th>Disponibilidad</th>
                                <th class="text-center">% Ejecución</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($periodos as $p): ?>
                                <?php
                                $m_num = (int) $p['mes'];
                                $m_nombre = isset($meses_es[$m_num]) ? $meses_es[$m_num] : "Mes $m_num";
                                $y_num = (int) $p['anio'];
                                $tot_act = (float) $p['tot_actualizado'];
                                $tot_gas = (float) $p['tot_gastos'];
                                $pct = ($tot_act != 0) ? ($tot_gas / $tot_act * 100) : 0;

                                $pct_badge_class = 'pct-low';
                                if ($pct >= 80)
                                    $pct_badge_class = 'pct-high';
                                elseif ($pct >= 40)
                                    $pct_badge_class = 'pct-mid';
                                ?>
                                <tr>
                                    <td class="text-left">
                                        <div class="period-badge">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                                <line x1="16" y1="2" x2="16" y2="6" />
                                                <line x1="8" y1="2" x2="8" y2="6" />
                                                <line x1="3" y1="10" x2="21" y2="10" />
                                            </svg>
                                            <span><?php echo htmlspecialchars($m_nombre . ' ' . $y_num); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="period-pill"><?php echo (int) $p['total_partidas']; ?> partidas</span>
                                    </td>
                                    <td class="text-right">Bs. <?php echo fmt_m($p['tot_aprobado']); ?></td>
                                    <td class="text-right" style="font-weight:700;color:#0f172a;">Bs.
                                        <?php echo fmt_m($p['tot_actualizado']); ?>
                                    </td>
                                    <td class="text-right" style="font-weight:700;color:#1e40af;">Bs.
                                        <?php echo fmt_m($p['tot_gastos']); ?>
                                    </td>
                                    <td class="text-right">Bs. <?php echo fmt_m($p['tot_pago']); ?></td>
                                    <td class="text-right" style="font-weight:700;color:#047857;">Bs.
                                        <?php echo fmt_m($p['tot_disponibilidad']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="pct-badge <?php echo $pct_badge_class; ?>">
                                            <?php echo number_format($pct, 2, '.', ','); ?>%
                                        </span>
                                    </td>
                                    <td class="text-center" style="white-space:nowrap;">
                                        <a href="index.php?mes=<?php echo $m_num; ?>&anio=<?php echo $y_num; ?>"
                                            class="btn-table btn-table-view" title="Ver / Editar Matriz">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2.2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            Ver
                                        </a>
                                        <a href="imprimir.php?mes=<?php echo $m_num; ?>&anio=<?php echo $y_num; ?>"
                                            target="_blank" class="btn-table btn-table-print" title="Imprimir PDF">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2.2">
                                                <polyline points="6 9 6 2 18 2 18 9" />
                                                <path
                                                    d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                                <rect x="6" y="14" width="12" height="8" />
                                            </svg>
                                            PDF
                                        </a>
                                        <?php if ($es_admin): ?>
                                            <button type="button" class="btn-table btn-table-del"
                                                onclick="eliminarPeriodo(<?php echo $m_num; ?>, <?php echo $y_num; ?>, '<?php echo htmlspecialchars($m_nombre . ' ' . $y_num); ?>')"
                                                title="Eliminar todo el período">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.2">
                                                    <polyline points="3 6 5 6 21 6" />
                                                    <path
                                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- Modal para Cargar / Seleccionar Período -->
    <div class="modal-backdrop" id="modal-nuevo-periodo">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Cargar Período de Ejecución</h3>
                <button type="button" class="modal-close" onclick="closeModalNuevoPeriodo()">&times;</button>
            </div>
            <form method="GET" action="index.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal-mes">Mes a Gestionar:</label>
                        <select name="mes" id="modal-mes" class="form-control">
                            <?php foreach ($meses_es as $n => $nm): ?>
                                <option value="<?php echo $n; ?>" <?php echo ($n == (int) date('n') ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($nm); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal-anio">Año:</label>
                        <select name="anio" id="modal-anio" class="form-control">
                            <?php for ($y = 2024; $y <= 2030; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y == (int) date('Y') ? 'selected' : ''); ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <?php if ($es_admin): ?>
                        <div
                            style="background:#eff6ff;padding:10px 12px;border-radius:8px;font-size:11px;color:#1e40af;margin-top:10px;">
                            💡 Puedes abrir el período y hacer clic en <strong>"Precargar Catálogo Completo"</strong> para
                            inicializar las 165 partidas.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary"
                        onclick="closeModalNuevoPeriodo()">Cancelar</button>
                    <button type="submit" class="btn-action btn-primary">Abrir Período →</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModalNuevoPeriodo() {
            document.getElementById('modal-nuevo-periodo').classList.add('open');
        }
        function closeModalNuevoPeriodo() {
            document.getElementById('modal-nuevo-periodo').classList.remove('open');
        }

        function imprimirPDF() {
            window.print();
        }

        function exportarExcel() {
            var anio = '<?php echo (int) $anio_filtro; ?>';
            window.location.href = 'listado.php?export=excel&anio=' + encodeURIComponent(anio);
        }

        function eliminarPeriodo(mes, anio, periodoStr) {
            var conf = confirm('¿Estás seguro de eliminar TODAS las partidas registradas para el período ' + periodoStr + '?\n\nEsta acción no se puede deshacer.');
            if (!conf) return;

            var fd = new FormData();
            fd.append('mes', mes);
            fd.append('anio', anio);

            fetch('/sistema/src/Controllers/ejecucion/eliminar_todo.php', {
                method: 'POST',
                body: fd
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al eliminar: ' + (data.error || 'Desconocido'));
                    }
                })
                .catch(function (err) {
                    alert('Error de red: ' + err);
                });
        }
    </script>

</body>

</html>