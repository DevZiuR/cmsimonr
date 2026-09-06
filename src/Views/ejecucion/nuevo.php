<?php
/**
 * src/Views/ejecucion/nuevo.php
 * Formulario para crear o editar una partida presupuestaria
 * La codificacion se elige desde un dropdown con búsqueda en tiempo real
 * PHP 5.6 compatible — sin ??
 */
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
    exit;
}
$es_admin = true;

require_once '../../../config/conexion.php';

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

require_once __DIR__ . '/../../Models/catalogo_partidas.php';

/* ── Contexto del filtro ── */
$mes_sel = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
$anio_sel = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
if ($mes_sel < 1 || $mes_sel > 12)
    $mes_sel = (int) date('n');
if ($anio_sel < 2000 || $anio_sel > 2100)
    $anio_sel = (int) date('Y');

/* ── Modo edición ── */
$editar_id = isset($_GET['editar']) ? (int) $_GET['editar'] : 0;
$row = null;
if ($editar_id > 0) {
    $res = mysqli_query($conn, "SELECT * FROM ejecucion_presupuestaria WHERE id = $editar_id LIMIT 1");
    if ($res)
        $row = mysqli_fetch_assoc($res);
}

/* ── Datos de formulario (prefill) ── */
$f_codificacion = $row ? htmlspecialchars($row['codificacion']) : '';
$f_denominacion = $row ? htmlspecialchars($row['denominacion']) : '';
$f_credito_aprobado = $row ? $row['credito_aprobado'] : '0.00';
$f_aumentos = $row ? $row['aumentos'] : '0.00';
$f_disminuciones = $row ? $row['disminuciones'] : '0.00';
$f_mes = $row ? $row['mes'] : $mes_sel;
$f_anio = $row ? $row['anio'] : $anio_sel;

/* ════════════════════════════════════════════════════════════════════════
   CARGAR PARTIDAS DISPONIBLES
   Combina catálogo maestro + ejecucion_presupuestaria + oc_partidas + os_partidas
   ════════════════════════════════════════════════════════════════════════ */

$partidas_disponibles = array();
$denominaciones_map = array();

// 1. Cargar catálogo maestro
$cat = get_catalogo_partidas($conn);
foreach ($cat as $ci) {
    $partidas_disponibles[$ci['cod']] = '';
    $denominaciones_map[$ci['cod']] = $ci['denom'];
}

// 2. Cargar partidas desde órdenes de compra y de servicio
$res_partidas = mysqli_query(
    $conn,
    "SELECT DISTINCT partida AS codificacion
     FROM (
         SELECT partida FROM oc_partidas WHERE partida IS NOT NULL AND partida <> ''
         UNION
         SELECT partida FROM os_partidas WHERE partida IS NOT NULL AND partida <> ''
     ) AS combined
     ORDER BY codificacion ASC"
);
if ($res_partidas) {
    while ($pr = mysqli_fetch_assoc($res_partidas)) {
        $c = $pr['codificacion'];
        if (!isset($partidas_disponibles[$c])) {
            $partidas_disponibles[$c] = '';
        }
    }
}

// 3. Cargar las denominaciones registradas en ejecucion_presupuestaria
$res_den = mysqli_query(
    $conn,
    "SELECT codificacion, denominacion
     FROM ejecucion_presupuestaria
     ORDER BY id DESC"
);
if ($res_den) {
    while ($dr = mysqli_fetch_assoc($res_den)) {
        $c = $dr['codificacion'];
        if (!empty($dr['denominacion'])) {
            $denominaciones_map[$c] = $dr['denominacion'];
        }
    }
}

/* ── Exportar como JSON para JS ── */
$partidas_json = array();
foreach ($partidas_disponibles as $cod => $dummy) {
    $partidas_json[] = array(
        'cod' => $cod,
        'denom' => isset($denominaciones_map[$cod]) ? $denominaciones_map[$cod] : '',
    );
}
$partidas_js = json_encode($partidas_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/* ── Últimas 5 partidas registradas ── */
$res_ultimas_partidas = mysqli_query(
    $conn,
    "SELECT id, codificacion, denominacion, credito_aprobado, mes, anio 
     FROM ejecucion_presupuestaria 
     ORDER BY id DESC LIMIT 5"
);
$ultimas_partidas = array();
if ($res_ultimas_partidas) {
    while ($pr = mysqli_fetch_assoc($res_ultimas_partidas)) {
        $ultimas_partidas[] = $pr;
    }
}

/* ── Meses ── */
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

$titulo = $editar_id > 0 ? 'Editar Partida Presupuestaria' : 'Nueva Partida Presupuestaria';
$active = 'ejecucion';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link
        href="/sistema/assets/fonts/fonts.css"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title><?php echo $titulo; ?> — Contraloría MSR</title>
    <style>
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .page-title,
        .brand-title,
        .brand-sub,
        .sb-section-label,
        .sb-parent-label,
        .sb-user-name,
        .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
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

        /* ── Header ── */
        .site-header {
            background: #070707;
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
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .site-header .brand-sub {
            font-size: 12.5px;
            color: #bbdefb;
        }

        .site-header .header-right {
            margin-left: auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .site-header .hdr-date {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
        }

        .site-header .hdr-sub {
            font-size: 10.5px;
            color: #94a3b8;
        }

        /* ── Layout ── */
        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── Form card ── */
        .form-card {
            width: 100%;
            max-width: 840px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            overflow: hidden;
            margin-bottom: 28px;
        }

        .form-card-head {
            background: linear-gradient(135deg, #1e293b 0%, #1e40af 100%);
            color: #fff;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-card-head h1 {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
        }

        .form-card-head .head-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255, 255, 255, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a5b4fc;
        }

        .form-card-body {
            padding: 28px;
        }

        /* ── Form grid ── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        label span.required {
            color: #ef4444;
            margin-left: 2px;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            padding: 10px 14px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            color: #1e293b;
            outline: none;
            transition: all 0.15s ease;
            width: 100%;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
            background: #fff;
        }

        .field-hint {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* ── Section divider ── */
        .form-section-title {
            grid-column: 1 / -1;
            font-size: 10.5px;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding-bottom: 6px;
            border-bottom: 2px solid #eff6ff;
            margin-top: 8px;
        }

        /* ════════════════════════════════════════════════════════════
           SEARCHABLE DROPDOWN COMPONENT
           ════════════════════════════════════════════════════════════ */
        .sd-wrapper {
            position: relative;
            width: 100%;
        }

        /* The visible "input" that shows the selected value / acts as search */
        .sd-input-wrap {
            display: flex;
            align-items: center;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            transition: border-color .15s, box-shadow .15s;
            overflow: hidden;
        }

        .sd-input-wrap.open,
        .sd-input-wrap:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
            background: #fff;
        }

        .sd-search {
            flex: 1;
            border: none !important;
            background: transparent !important;
            padding: 10px 12px;
            font-family: inherit;
            font-size: 13px;
            color: #1e293b;
            outline: none !important;
            box-shadow: none !important;
            width: 100%;
            min-width: 0;
        }

        .sd-search::placeholder {
            color: #94a3b8;
        }

        .sd-clear-btn {
            padding: 0 10px;
            cursor: pointer;
            color: #94a3b8;
            background: none;
            border: none;
            font-size: 16px;
            line-height: 1;
            display: none;
            flex-shrink: 0;
        }

        .sd-clear-btn:hover {
            color: #ef4444;
        }

        .sd-caret {
            padding: 0 12px;
            color: #94a3b8;
            cursor: pointer;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            transition: transform .2s;
        }

        .sd-caret.open {
            transform: rotate(180deg);
        }

        /* Dropdown panel */
        .sd-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1.5px solid #2563eb;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .14);
            z-index: 200;
            display: none;
            overflow: hidden;
        }

        .sd-panel.open {
            display: block;
        }

        .sd-count-bar {
            padding: 6px 12px;
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            letter-spacing: .3px;
        }

        .sd-list {
            max-height: 240px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .sd-list::-webkit-scrollbar {
            width: 5px;
        }

        .sd-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .sd-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            cursor: pointer;
            transition: background .1s;
            border-bottom: 1px solid #f1f5f9;
        }

        .sd-option:last-child {
            border-bottom: none;
        }

        .sd-option:hover {
            background: #eff6ff;
        }

        .sd-option.selected {
            background: #dbeafe;
        }

        .sd-option-code {
            font-family: 'IBM Plex Sans', monospace;
            font-size: 12px;
            font-weight: 700;
            color: #1e40af;
            white-space: nowrap;
            min-width: 130px;
        }

        .sd-option-denom {
            font-size: 11px;
            color: #475569;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sd-option-denom.empty {
            color: #cbd5e1;
            font-style: italic;
        }

        .sd-no-results {
            padding: 20px 12px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }

        /* Manual entry toggle */
        .sd-manual-toggle {
            padding: 8px 12px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .sd-manual-toggle button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 11px;
            color: #2563eb;
            font-weight: 600;
            font-family: inherit;
            padding: 0;
        }

        .sd-manual-toggle button:hover {
            text-decoration: underline;
        }

        /* Manual input (shown when "Ingresar manualmente" is clicked) */
        .sd-manual-row {
            display: none;
            gap: 8px;
            align-items: center;
            margin-top: 8px;
        }

        .sd-manual-row.visible {
            display: flex;
        }

        .sd-manual-row input {
            flex: 1;
            padding: 8px 12px;
            font-size: 13px;
            border: 1.5px solid #f59e0b;
            border-radius: 8px;
            background: #fffbeb;
            color: #1e293b;
            outline: none;
            font-family: inherit;
        }

        .sd-manual-row input:focus {
            border-color: #d97706;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .15);
        }

        .sd-manual-row .sd-use-manual {
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 600;
            background: #f59e0b;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            transition: background .15s;
            white-space: nowrap;
        }

        .sd-manual-row .sd-use-manual:hover {
            background: #d97706;
        }

        /* ── Calculated preview ── */
        .calc-preview {
            grid-column: 1 / -1;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .calc-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .calc-item .calc-label {
            font-size: 9.5px;
            font-weight: 700;
            color: #3b82f6;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .calc-item .calc-value {
            font-size: 16px;
            font-weight: 800;
            color: #1e40af;
        }

        /* ── Buttons ── */
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        .btn-submit {
            background: #2563eb;
            color: #fff;
            border: 1px solid #2563eb;
            padding: 10px 22px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 9px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all .15s ease;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        .btn-submit:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-cancel {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 9px;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all .15s ease;
        }

        .btn-cancel:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* ── Recent Partidas Card ─────────────────────────────────────── */
        .recent-card {
            width: 100%;
            max-width: 840px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            margin-bottom: 32px;
        }

        .recent-card-head {
            padding: 16px 24px;
            background: #fafbfc;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .recent-card-head h3 {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .recent-card-head .count-badge {
            background: #eff6ff;
            color: #1e40af;
            font-size: 10.5px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 100px;
            border: 1px solid #dbeafe;
        }

        .recent-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }

        .recent-table thead tr {
            background: #1e293b;
            color: #f8fafc;
        }

        .recent-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #334155;
        }

        .recent-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.12s ease;
        }

        .recent-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .recent-table tbody tr:hover {
            background: #eff6ff;
        }

        .recent-table td {
            padding: 10px 14px;
            color: #334155;
            vertical-align: middle;
        }

        .recent-table td.col-codif {
            font-family: 'IBM Plex Sans', monospace;
            font-weight: 700;
            color: #1e40af;
            white-space: nowrap;
        }

        .recent-table td.col-denom {
            font-weight: 600;
            color: #0f172a;
        }

        .btn-edit-recent {
            font-size: 10.5px;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
            padding: 4px 10px;
            border-radius: 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            transition: all 0.15s ease;
        }

        .btn-edit-recent:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        /* ── Alert ── */
        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 11px;
            color: #92400e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 11px;
            color: #1e40af;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Footer ── */
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

        /* Selected badge below dropdown */
        .sd-selected-badge {
            display: none;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            padding: 5px 10px;
            background: #dbeafe;
            border-radius: 6px;
            font-size: 11px;
            color: #1e40af;
            font-weight: 600;
        }

        .sd-selected-badge.visible {
            display: flex;
        }

        .sd-selected-badge .sd-badge-code {
            font-family: 'IBM Plex Sans', monospace;
            font-weight: 700;
        }
    </style>
</head>

<body><div class="layout">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">

            <?php if ($editar_id > 0 && !$row): ?>
                <div class="alert-warning">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path
                            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    Partida no encontrada. Se mostrará el formulario de creación.
                </div>
            <?php endif; ?>

            <?php if (empty($partidas_disponibles) && $editar_id === 0): ?>
                <div class="alert-info">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    No hay partidas en las órdenes de compra/servicio aún. Puedes ingresar la codificación manualmente.
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="form-card-head">
                    <div class="head-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                    </div>
                    <div>
                        <h1><?php echo htmlspecialchars($titulo); ?></h1>
                        <p style="font-size:11px;color:#cbd5e1;margin-top:3px;">
                            Período: <?php echo isset($meses_es[$f_mes]) ? $meses_es[$f_mes] : $f_mes; ?>
                            <?php echo $f_anio; ?>
                            &nbsp;·&nbsp; <?php echo count($partidas_disponibles); ?> codificación(es) disponibles
                        </p>
                    </div>
                </div>

                <div class="form-card-body">
                    <form method="POST" action="/sistema/src/Controllers/ejecucion/guardar.php" id="form-partida"
                        onsubmit="return validarFormulario()">

                        <?php if ($editar_id > 0 && $row): ?>
                            <input type="hidden" name="partida_id" value="<?php echo $editar_id; ?>">
                        <?php endif; ?>

                        <!-- Hidden real codificacion submitted with form -->
                        <input type="hidden" id="codificacion" name="codificacion"
                            value="<?php echo $f_codificacion; ?>" required>

                        <div class="form-grid">

                            <!-- ══════════════════════════════════════════ -->
                            <div class="form-section-title">Identificación de la Partida</div>

                            <!-- CODIFICACIÓN — Searchable dropdown -->
                            <div class="form-group full">
                                <label for="sd-search-input">
                                    Codificación Presupuestaria
                                    <span class="required">*</span>
                                </label>

                                <?php if ($editar_id > 0 && $row): ?>
                                    <!-- Edit mode: read-only display -->
                                    <input type="text" value="<?php echo $f_codificacion; ?>" readonly
                                        style="background:#f1f5f9;color:#64748b;font-family:'IBM Plex Sans',monospace;font-weight:700;">
                                    <span class="field-hint">La codificación no se puede cambiar al editar. Crea una nueva
                                        partida si necesitas otra codificación.</span>
                                <?php else: ?>
                                    <!-- Create mode: searchable dropdown -->
                                    <div class="sd-wrapper" id="sd-wrapper">

                                        <!-- Search input row -->
                                        <div class="sd-input-wrap" id="sd-input-wrap">
                                            <input type="text" id="sd-search-input" class="sd-search"
                                                placeholder="Buscar por código (ej: 01-08-00-00-51-401 o 401) o nombre..." autocomplete="off"
                                                spellcheck="false">
                                            <button type="button" class="sd-clear-btn" id="sd-clear-btn"
                                                title="Limpiar selección">✕</button>
                                            <span class="sd-caret" id="sd-caret">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <polyline points="6 9 12 15 18 9" />
                                                </svg>
                                            </span>
                                        </div>

                                        <!-- Dropdown panel -->
                                        <div class="sd-panel" id="sd-panel">
                                            <div class="sd-count-bar" id="sd-count-bar">
                                                <?php echo count($partidas_disponibles); ?> codificación(es) — escribe para
                                                filtrar
                                            </div>
                                            <div class="sd-list" id="sd-list">
                                                <!-- Options rendered by JS -->
                                            </div>
                                            <div class="sd-no-results" id="sd-no-results" style="display:none;">
                                                Sin resultados para ese término
                                            </div>
                                            <!-- Manual entry option -->
                                            <div class="sd-manual-toggle">
                                                <button type="button" id="sd-manual-btn">
                                                    ✏️ Ingresar codificación manualmente
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Manual entry row (shown on demand) -->
                                        <div class="sd-manual-row" id="sd-manual-row">
                                            <input type="text" id="sd-manual-input" placeholder="Ej: 01-08-00-00-51-401-01-01-00"
                                                spellcheck="false">
                                            <button type="button" class="sd-use-manual" id="sd-use-manual-btn">
                                                Usar este código
                                            </button>
                                        </div>

                                        <!-- Selected value badge -->
                                        <div class="sd-selected-badge" id="sd-selected-badge">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12" />
                                            </svg>
                                            Seleccionado: <span class="sd-badge-code" id="sd-badge-code"></span>
                                        </div>
                                    </div>

                                    <span class="field-hint">
                                        Elige una codificación de las órdenes registradas, o ingrésala manualmente.
                                        Si tiene denominación guardada anteriormente, se llenará automáticamente.
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- DENOMINACIÓN -->
                            <div class="form-group full">
                                <label for="denominacion">Denominación <span class="required">*</span></label>
                                <input type="text" id="denominacion" name="denominacion"
                                    value="<?php echo $f_denominacion; ?>"
                                    placeholder="Ej: Sueldos básicos personal fijo a tiempo completo" required>
                                <span class="field-hint" id="denom-hint" style="display:none;color:#059669;">
                                    ✓ Denominación cargada automáticamente desde el historial — puedes modificarla.
                                </span>
                            </div>

                            <!-- ══════════════════════════════════════════ -->
                            <div class="form-section-title">Créditos y Traspasos</div>

                            <div class="form-group">
                                <label for="credito_aprobado">Crédito Aprobado MES <span
                                        class="required">*</span></label>
                                <input type="number" step="0.01" min="0" id="credito_aprobado" name="credito_aprobado"
                                    value="<?php echo $f_credito_aprobado; ?>" required oninput="recalcular()">
                            </div>

                            <div class="form-group">
                                <label for="aumentos">Traspasos — Aumentos</label>
                                <input type="number" step="0.01" min="0" id="aumentos" name="aumentos"
                                    value="<?php echo $f_aumentos; ?>" oninput="recalcular()">
                            </div>

                            <div class="form-group">
                                <label for="disminuciones">Traspasos — Disminuciones</label>
                                <input type="number" step="0.01" min="0" id="disminuciones" name="disminuciones"
                                    value="<?php echo $f_disminuciones; ?>" oninput="recalcular()">
                            </div>

                            <!-- Calculated preview -->
                            <div class="calc-preview">
                                <div class="calc-item">
                                    <span class="calc-label">Crédito Actualizado</span>
                                    <span class="calc-value" id="prev-actualizado">0,00</span>
                                </div>
                                <div style="color:#bfdbfe;font-size:18px;">= Aprobado + Aumentos − Disminuciones</div>
                            </div>

                            <!-- ══════════════════════════════════════════ -->
                            <div class="form-section-title">Período</div>

                            <div class="form-group">
                                <label for="sel-mes">Mes <span class="required">*</span></label>
                                <select id="sel-mes" name="mes" required>
                                    <?php foreach ($meses_es as $n => $nm): ?>
                                        <option value="<?php echo $n; ?>" <?php echo ($n == $f_mes ? ' selected' : ''); ?>>
                                            <?php echo htmlspecialchars($nm); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="sel-anio">Año <span class="required">*</span></label>
                                <select id="sel-anio" name="anio" required>
                                    <?php for ($y = 2024; $y <= 2030; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y == $f_anio ? ' selected' : ''); ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                        </div><!-- /.form-grid -->

                        <div class="btn-row">
                            <button type="submit" class="btn-submit" id="btn-guardar-partida">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                    <polyline points="17 21 17 13 7 13 7 21" />
                                    <polyline points="7 3 7 8 15 8" />
                                </svg>
                                <?php echo $editar_id > 0 ? 'Actualizar Partida' : 'Guardar Partida'; ?>
                            </button>
                            <a href="index.php?mes=<?php echo $mes_sel; ?>&anio=<?php echo $anio_sel; ?>"
                                class="btn-cancel">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="19" y1="12" x2="5" y2="12" />
                                    <polyline points="12 19 5 12 12 5" />
                                </svg>
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div><!-- /.form-card -->

            <!-- ══ ÚLTIMAS 5 PARTIDAS REGISTRADAS ══════════════════════════════ -->
            <div class="recent-card">
                <div class="recent-card-head">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="3" y1="9" x2="21" y2="9" />
                            <line x1="3" y1="15" x2="21" y2="15" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                            <line x1="15" y1="3" x2="15" y2="21" />
                        </svg>
                        Últimas 5 Partidas Registradas
                    </h3>
                    <span class="count-badge"><?php echo count($ultimas_partidas); ?> recientemente</span>
                </div>
                <?php if (!empty($ultimas_partidas)): ?>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Codificación</th>
                                <th>Denominación</th>
                                <th>Crédito Aprobado</th>
                                <th>Mes / Año</th>
                                <th style="text-align:right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimas_partidas as $pt): ?>
                                <tr>
                                    <td class="col-codif"><?php echo htmlspecialchars($pt['codificacion']); ?></td>
                                    <td class="col-denom"><?php echo htmlspecialchars($pt['denominacion']); ?></td>
                                    <td style="font-weight:600;"><?php echo number_format((float)$pt['credito_aprobado'], 2, '.', ','); ?></td>
                                    <td><?php echo (isset($meses_es[(int)$pt['mes']]) ? $meses_es[(int)$pt['mes']] : $pt['mes']) . ' ' . $pt['anio']; ?></td>
                                    <td style="text-align:right;">
                                        <a href="nuevo.php?editar=<?php echo $pt['id']; ?>" class="btn-edit-recent" title="Editar Partida">
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding: 24px; color:#94a3b8; font-size:12px;">
                        No hay partidas registradas aún.
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?><!-- ═══════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════ -->
    <script>
        /* ── Calculated preview ── */
        function fmtNum(n) {
            return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
        function recalcular() {
            var ap = parseFloat(document.getElementById('credito_aprobado').value) || 0;
            var au = parseFloat(document.getElementById('aumentos').value) || 0;
            var di = parseFloat(document.getElementById('disminuciones').value) || 0;
            document.getElementById('prev-actualizado').textContent = fmtNum(ap + au - di);
        }
        recalcular();

        /* ── Partidas data from PHP ── */
        var PARTIDAS = <?php echo $partidas_js; ?>;

        <?php if ($editar_id === 0): ?>
                /* ════════════════════════════════════════════════════════════════════
                   SEARCHABLE DROPDOWN LOGIC
                   ════════════════════════════════════════════════════════════════════ */
                (function () {
                    var hiddenInput = document.getElementById('codificacion');
                    var searchInput = document.getElementById('sd-search-input');
                    var panel = document.getElementById('sd-panel');
                    var list = document.getElementById('sd-list');
                    var noResults = document.getElementById('sd-no-results');
                    var countBar = document.getElementById('sd-count-bar');
                    var inputWrap = document.getElementById('sd-input-wrap');
                    var caretEl = document.getElementById('sd-caret');
                    var clearBtn = document.getElementById('sd-clear-btn');
                    var badge = document.getElementById('sd-selected-badge');
                    var badgeCode = document.getElementById('sd-badge-code');
                    var denomInput = document.getElementById('denominacion');
                    var denomHint = document.getElementById('denom-hint');
                    var manualBtn = document.getElementById('sd-manual-btn');
                    var manualRow = document.getElementById('sd-manual-row');
                    var manualInput = document.getElementById('sd-manual-input');
                    var useManualBtn = document.getElementById('sd-use-manual-btn');

                    var isOpen = false;
                    var selectedCod = '';

                    /* ── Render options into list ── */
                    function renderOptions(filter) {
                        filter = (filter || '').toLowerCase().trim();
                        list.innerHTML = '';
                        var shown = 0;
                        var i;
                        for (i = 0; i < PARTIDAS.length; i++) {
                            var item = PARTIDAS[i];
                            var matchCod = item.cod.toLowerCase().indexOf(filter) !== -1;
                            var matchDen = (item.denom || '').toLowerCase().indexOf(filter) !== -1;
                            if (filter && !matchCod && !matchDen) continue;
                            shown++;
                            var opt = document.createElement('div');
                            opt.className = 'sd-option' + (item.cod === selectedCod ? ' selected' : '');
                            opt.setAttribute('data-cod', item.cod);
                            opt.setAttribute('data-denom', item.denom);

                            var codeEl = document.createElement('span');
                            codeEl.className = 'sd-option-code';
                            codeEl.textContent = item.cod;

                            var denomEl = document.createElement('span');
                            denomEl.className = 'sd-option-denom' + (item.denom ? '' : ' empty');
                            denomEl.textContent = item.denom || '(sin denominación guardada)';

                            opt.appendChild(codeEl);
                            opt.appendChild(denomEl);

                            opt.addEventListener('mousedown', function (e) {
                                e.preventDefault(); /* keep focus on search */
                                selectOption(this.getAttribute('data-cod'), this.getAttribute('data-denom'));
                            });
                            list.appendChild(opt);
                        }

                        noResults.style.display = (shown === 0 ? 'block' : 'none');
                        countBar.textContent = shown + ' codificación' + (shown !== 1 ? 'es' : '') +
                            (filter ? ' — mostrando coincidencias' : ' — escribe para filtrar');
                    }

                    /* ── Select an option ── */
                    function selectOption(cod, denom) {
                        selectedCod = cod;
                        hiddenInput.value = cod;
                        searchInput.value = cod;
                        clearBtn.style.display = 'inline';
                        badge.classList.add('visible');
                        badgeCode.textContent = cod;

                        /* Auto-fill denominacion if available */
                        if (denom && denom.trim() !== '') {
                            denomInput.value = denom;
                            denomHint.style.display = 'block';
                        } else {
                            denomHint.style.display = 'none';
                        }

                        closePanel();
                        renderOptions('');
                    }

                    /* ── Open/close panel ── */
                    function openPanel() {
                        if (isOpen) return;
                        isOpen = true;
                        panel.classList.add('open');
                        inputWrap.classList.add('open');
                        caretEl.classList.add('open');
                        renderOptions(searchInput.value);
                    }
                    function closePanel() {
                        if (!isOpen) return;
                        isOpen = false;
                        panel.classList.remove('open');
                        inputWrap.classList.remove('open');
                        caretEl.classList.remove('open');
                    }

                    /* ── Clear selection ── */
                    function clearSelection() {
                        selectedCod = '';
                        hiddenInput.value = '';
                        searchInput.value = '';
                        clearBtn.style.display = 'none';
                        badge.classList.remove('visible');
                        badgeCode.textContent = '';
                        denomHint.style.display = 'none';
                        renderOptions('');
                    }

                    /* ── Events ── */
                    searchInput.addEventListener('focus', function () { openPanel(); });
                    searchInput.addEventListener('input', function () {
                        /* While typing: don't keep a selection */
                        if (selectedCod && this.value !== selectedCod) {
                            hiddenInput.value = '';
                            selectedCod = '';
                            clearBtn.style.display = 'none';
                            badge.classList.remove('visible');
                            denomHint.style.display = 'none';
                        }
                        renderOptions(this.value);
                        if (!isOpen) openPanel();
                    });
                    searchInput.addEventListener('blur', function () {
                        /* Delay close so mousedown on option fires first */
                        setTimeout(function () { closePanel(); }, 160);
                    });

                    caretEl.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        if (isOpen) { closePanel(); } else { searchInput.focus(); openPanel(); }
                    });

                    clearBtn.addEventListener('click', function () { clearSelection(); searchInput.focus(); });

                    /* ── Manual entry ── */
                    manualBtn.addEventListener('click', function () {
                        manualRow.classList.toggle('visible');
                        if (manualRow.classList.contains('visible')) {
                            manualInput.focus();
                        }
                    });
                    useManualBtn.addEventListener('click', function () {
                        var val = manualInput.value.trim();
                        if (!val) { manualInput.focus(); return; }
                        hiddenInput.value = val;
                        searchInput.value = val;
                        selectedCod = val;
                        clearBtn.style.display = 'inline';
                        badge.classList.add('visible');
                        badgeCode.textContent = val;
                        manualRow.classList.remove('visible');
                        manualInput.value = '';
                        denomHint.style.display = 'none';
                        renderOptions('');
                    });
                    manualInput.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') { e.preventDefault(); useManualBtn.click(); }
                    });

                    /* ── Close panel when clicking outside ── */
                    document.addEventListener('mousedown', function (e) {
                        var wrapper = document.getElementById('sd-wrapper');
                        if (wrapper && !wrapper.contains(e.target)) { closePanel(); }
                    });

                    /* ── Form validation ── */
                    window.validarFormulario = function () {
                        if (!hiddenInput.value.trim()) {
                            searchInput.focus();
                            searchInput.style.borderColor = '#ef4444';
                            setTimeout(function () { searchInput.style.borderColor = ''; }, 2000);
                            alert('Por favor selecciona o ingresa una codificación presupuestaria.');
                            return false;
                        }
                        return true;
                    };

                    /* ── Initial render ── */
                    renderOptions('');

                    /* Pre-select if value was set (e.g. after failed submit) */
                    var preVal = hiddenInput.value;
                    if (preVal) {
                        searchInput.value = preVal;
                        selectedCod = preVal;
                        clearBtn.style.display = 'inline';
                        badge.classList.add('visible');
                        badgeCode.textContent = preVal;
                    }
                })();
        <?php else: ?>
            /* Edit mode — no dropdown needed */
            window.validarFormulario = function () { return true; };
        <?php endif; ?>
    </script>

</body>

</html>