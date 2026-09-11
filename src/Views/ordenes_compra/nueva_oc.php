<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
if (!$es_admin) {
    header('Location: index.php');
    exit;
}

require_once '../../../config/conexion.php';

/* ── Proveedores para el select ── */
$proveedores = mysqli_query($conn, "SELECT id, rif, razon_social FROM proveedores ORDER BY razon_social");

/* ── Comprobar si se está editando una OC existente (en pendiente) ── */
$oc_edit = null;
$oc_renglones_edit = array();
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($edit_id > 0) {
    $res_edit = mysqli_query($conn, "SELECT oc.*, p.rif, p.razon_social, p.direccion AS prov_direccion, p.telefono AS prov_telefono FROM ordenes_compra oc LEFT JOIN proveedores p ON p.id = oc.proveedor_id WHERE oc.id = $edit_id AND oc.status = 'pendiente'");
    if ($res_edit && mysqli_num_rows($res_edit) > 0) {
        $oc_edit = mysqli_fetch_assoc($res_edit);
        $res_r = mysqli_query($conn, "SELECT * FROM oc_renglones WHERE oc_id = $edit_id ORDER BY id ASC");
        while ($r = mysqli_fetch_assoc($res_r)) {
            $oc_renglones_edit[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Nueva Orden de Compras — Contraloría del Municipio Simón Rodríguez">
    <title>Nueva Orden de Compras — Contraloría MSR</title>
    <link href="/sistema/assets/fonts/fonts.css"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <style>
    /* Premium typography: IBM Plex Sans headings, Inter body */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        /* ── Reset y Estilos Generales ── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: #000;
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

        /* ── Layout ── */
        .layout {
            display: flex;
            flex: 1;
        }


        /* ── Main Content ── */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── Container ── */
        .container {
            background: white;
            padding: 15px;
            border: 1px solid #ccc;
            width: 100%;
            max-width: 980px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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
            margin-top: auto;
        }

        .site-footer strong {
            color: #e3f2fd;
        }

        /* ============================================================
           DOCUMENTO ORDEN DE COMPRAS (DISEÑO EXACTO SEGÚN IMAGEN)
           ============================================================ */
        .oc-document {
            border: 2px solid #000;
            background: #fff;
            font-family: 'Inter', sans-serif;
            color: #000;
            width: 100%;
            box-sizing: border-box;
        }

        /* ── Header Institucional con Logos ── */
        .oc-header-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            border-bottom: 1.5px solid #000;
        }

        .oc-logo-left img,
        .oc-logo-right img {
            height: 70px;
            width: auto;
            display: block;
        }

        .oc-header-center {
            text-align: center;
            line-height: 1.3;
        }

        .oc-header-center .oc-rep {
            font-weight: bold;
            font-size: 11.5px;
            text-transform: uppercase;
        }

        .oc-header-center .oc-est {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .oc-header-center .oc-ent {
            font-weight: 800;
            font-size: 12px;
            margin-top: 2px;
            text-transform: uppercase;
        }

        /* ── Fila Título ORDEN DE COMPRAS + N° ORDEN Y FECHA ── */
        .oc-row-title {
            display: flex;
            border-bottom: 1.5px solid #000;
        }

        .oc-title-cell {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1.5px solid #000;
            padding: 8px;
        }

        .oc-title-cell h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 0;
        }

        .oc-num-fecha-cell {
            width: 320px;
            display: flex;
            flex-direction: column;
        }

        .oc-nf-header-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid #000;
            background: #fff;
        }

        .oc-nf-header-row label {
            font-weight: bold;
            font-size: 10px;
            padding: 3px 6px;
            border-right: 1px solid #000;
        }

        .oc-nf-header-row label:last-child {
            border-right: none;
        }

        .oc-nf-val-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid #000;
        }

        .oc-nf-val-row input {
            width: 100%;
            border: none;
            padding: 3px 6px;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        .oc-nf-val-row > input:first-child {
            border-right: 1px solid #000;
        }

        .oc-nf-sub-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .oc-nf-sub-row input {
            width: 100%;
            border: none;
            padding: 3px 6px;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
            border-right: 1px solid #000;
        }

        .oc-nf-sub-row input:last-child {
            border-right: none;
        }

        /* ── Date Picker Popover (Spanish) ── */
        .sp-picker-popover {
            position: absolute;
            top: calc(100% + 4px);
            right: 0;
            z-index: 99999;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
            width: 230px;
            font-family: 'Inter', sans-serif;
            text-align: left;
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
            height: 25px;
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
            font-weight: 700;
        }
        @media print {
            .sp-picker-popover { display: none !important; }
        }

        /* ── Bloque Información Proveedor & Dirección ── */
        .oc-prov-block {
            display: flex;
            border-bottom: 1.5px solid #000;
        }

        .oc-prov-left {
            flex: 1;
            border-right: 1.5px solid #000;
            display: flex;
            flex-direction: column;
        }

        .oc-prov-right {
            width: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 10px;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .oc-prov-row {
            display: flex;
            border-bottom: 1px solid #000;
            padding: 3px 8px;
            align-items: flex-start;
            font-size: 10.5px;
        }

        .oc-prov-row:last-child {
            border-bottom: none;
        }

        .oc-prov-row label {
            font-weight: bold;
            margin-right: 6px;
            white-space: nowrap;
            padding-top: 2px;
        }

        .oc-prov-row input,
        .oc-prov-row select,
        .oc-prov-row textarea {
            flex: 1;
            border: none;
            font-size: 11px;
            font-weight: bold;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
            resize: none;
            overflow: hidden;
            line-height: 1.35;
            padding: 1px 0 0 0;
            box-sizing: border-box;
        }

        /* ── Tabla Renglones ── */
        .oc-section-bar {
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            text-align: center;
            padding: 4px;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.5px;
            background: #fff;
            text-transform: uppercase;
        }

        .oc-table-renglones {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .oc-table-renglones th {
            border-bottom: 1.5px solid #000;
            border-right: 1px solid #000;
            padding: 4px;
            font-weight: bold;
            text-align: center;
            font-size: 10.5px;
            background: #fff;
            text-transform: uppercase;
        }

        .oc-table-renglones th:last-child {
            border-right: none;
        }

        .oc-table-renglones td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 2px 4px;
        }

        .oc-table-renglones td:last-child {
            border-right: none;
        }

        .oc-table-renglones input {
            width: 100%;
            border: none;
            font-size: 10.5px;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        /* ── Autocomplete / Sugerencias de Productos ── */
        .oc-table-renglones tr {
            position: relative;
        }

        .oc-table-renglones tr:focus-within,
        .oc-table-renglones tr.has-suggestions {
            position: relative;
            z-index: 1000 !important;
        }

        .desc-cell {
            position: relative;
            vertical-align: top;
        }

        .desc-cell:focus-within,
        .desc-cell.has-suggestions {
            position: relative;
            z-index: 1001 !important;
        }

        .desc-autocomplete-wrapper {
            position: relative;
            width: 100%;
        }

        .sug-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            width: 100%;
            min-width: 340px;
            max-height: 220px;
            overflow-y: auto;
            background: #ffffff !important;
            border: 1.5px solid #2563eb !important;
            border-radius: 6px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25), 0 4px 10px rgba(0, 0, 0, 0.15) !important;
            font-size: 11px;
            z-index: 99999 !important;
        }

        .sug-item {
            padding: 7px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            background: #ffffff !important;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            transition: background 0.15s;
        }

        .sug-item:last-child {
            border-bottom: none;
        }

        .sug-item:hover {
            background: #eff6ff !important;
        }

        .sug-item strong {
            color: #0f172a;
            font-size: 11px;
            text-align: left;
        }

        .sug-item .imput-pill {
            color: #1e40af;
            background: #dbeafe;
            font-weight: 700;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }

        /* ── Monto en letras & Totales ── */
        .oc-row-totales {
            display: flex;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
        }

        .oc-monto-letras-cell {
            flex: 1;
            border-right: 1.5px solid #000;
            padding: 10px 12px;
            font-size: 11px;
            display: flex;
            align-items: center;
            line-height: 1.4;
        }

        .oc-totales-cell {
            width: 320px;
            display: flex;
            flex-direction: column;
        }

        .oc-tot-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #000;
            padding: 3px 8px;
            font-size: 11px;
        }

        .oc-tot-row:last-child {
            border-bottom: none;
        }

        .oc-tot-row label {
            font-weight: bold;
        }

        .oc-tot-row span {
            font-weight: bold;
            text-align: right;
        }

        /* ── Editable total inputs ── */
        .tot-field-wrap {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .tot-input {
            border: none;
            border-bottom: 1px dashed #aaa;
            background: transparent;
            font-size: 11px;
            font-weight: bold;
            text-align: right;
            width: 100px;
            padding: 0 2px;
            outline: none;
            color: inherit;
            cursor: text;
        }

        .tot-input:focus {
            border-bottom: 1.5px solid #1565c0;
            background: #e3f2fd;
            border-radius: 2px;
        }

        .tot-input.manual {
            border-bottom: 1.5px solid #e65100;
            background: #fff3e0;
            border-radius: 2px;
        }

        .tot-input-total {
            font-size: 12px;
            font-weight: 800;
            width: 110px;
        }

        .tot-unlock {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            padding: 0;
            line-height: 1;
            opacity: 0.7;
        }

        .tot-unlock:hover { opacity: 1; }

        @media print {
            .tot-unlock { display: none !important; }
            .tot-input {
                border: none !important;
                background: transparent !important;
            }
        }

        .oc-tot-row.total-gen {
            border-top: 1.5px solid #000;
            font-weight: 800;
            font-size: 12px;
            background: #fff;
        }

        /* ── Sección Partidas & Firmas (Split Vertical) ── */
        .oc-split-grid {
            display: flex;
            border-bottom: 1.5px solid #000;
        }

        .oc-split-left {
            flex: 1;
            border-right: 1.5px solid #000;
            display: flex;
            flex-direction: column;
        }

        .oc-split-right {
            width: 320px;
            display: flex;
            flex-direction: column;
        }

        .oc-box-section {
            border-bottom: 1px solid #000;
            padding: 6px 8px;
            min-height: 60px;
            font-size: 10.5px;
            font-weight: bold;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .oc-box-section:last-child {
            border-bottom: none;
        }

        .oc-partidas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .oc-partidas-table th {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 3px 6px;
            font-weight: bold;
            text-align: center;
        }

        .oc-partidas-table th:last-child {
            border-right: none;
        }

        .oc-partidas-table td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 3px 6px;
        }

        .oc-partidas-table td:last-child {
            border-right: none;
        }

        .oc-partidas-table input {
            width: 100%;
            border: none;
            font-size: 10.5px;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        .oc-contralora-box {
            border-top: 1.5px solid #000;
            padding: 8px;
            min-height: 70px;
            font-size: 10.5px;
            font-weight: bold;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* ── Condiciones Footer ── */
        .oc-condiciones-bar {
            border-top: 1.5px solid #000;
            border-bottom: 1px solid #000;
            text-align: center;
            padding: 4px;
            font-weight: bold;
            font-size: 10.5px;
            text-transform: uppercase;
        }

        .oc-condiciones-text {
            padding: 6px 12px;
            text-align: center;
            font-size: 11px;
            line-height: 1.3;
            text-transform: uppercase;
        }

        /* ── Botones y Elementos Interactivos ── */
        .btn-add {
            font-size: 11px;
            color: #1565c0;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
            font-weight: bold;
        }

        .btn-add:hover {
            text-decoration: underline;
        }

        .btns {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
            width: 100%;
        }

        .btn-guardar {
            background: #1565c0;
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            font-family: 'Geist', sans-serif;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(21, 101, 192, 0.3);
        }

        .btn-imprimir {
            background: #c62828;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            font-family: 'Geist', sans-serif;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(198, 40, 40, 0.3);
        }

        .btn-guardar:hover {
            background: #0d47a1;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(13, 71, 161, 0.4);
        }

        .btn-imprimir:hover {
            background: #b71c1c;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(183, 28, 28, 0.4);
        }

        /* Quita flechas en inputs number */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        /* ── Media Print ── */
        @media print {
            @page {
                size: letter portrait;
                margin: 8mm;
            }

            body {
                background: white !important;
                color: #000 !important;
                font-family: 'Inter', sans-serif !important;
                min-height: 100vh !important;
            }

            .no-print,
            .site-header,
            .site-footer,
            .sidebar,
            .btns,
            .btn-add,
            tr.row-empty,
            tr.print-empty-row,
            th.no-print,
            td.no-print {
                display: none !important;
            }

            .layout {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 100vh !important;
            }

            .main-content {
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
            }

            .container {
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
            }

            .oc-document {
                border: 2px solid #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                zoom: 0.94;
                margin: 0 auto !important;
            }

            input,
            select,
            textarea {
                border: none !important;
                background: transparent !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
                box-shadow: none !important;
                color: #000 !important;
                font-family: 'Inter', sans-serif !important;
            }

            select::-ms-expand {
                display: none;
            }
        }
    </style>
</head>

<body><div class="layout">
        <?php $active = 'oc-nueva';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="container">

                <form method="POST" action="../../controllers/ordenes_compra/guardar_oc.php" id="form-oc">

                    <!-- Formulario rápido de nuevo proveedor (oculto por defecto, no se imprime) -->
                    <div id="form-nuevo-proveedor" class="no-print"
                        style="display:none; border:1px solid #1565c0; padding:12px; margin-bottom:15px; background:#f4f8ff;">
                        <strong style="font-size:12px; color:#1565c0;">+ AGREGAR NUEVO PROVEEDOR</strong>
                        <div style="display:grid; grid-template-columns: 1fr 2fr 1fr; gap:10px; margin-top:8px;">
                            <div>
                                <label style="font-size:10px; font-weight:bold;">RIF</label>
                                <div style="display:flex; gap:4px;">
                                    <select id="np-rif-tipo" style="width:55px; border:1px solid #ccc; padding:4px; font-size:12px;">
                                        <option value="J">J</option>
                                        <option value="V">V</option>
                                        <option value="G">G</option>
                                        <option value="E">E</option>
                                        <option value="P">P</option>
                                    </select>
                                    <input type="text" id="np-rif-num" style="flex:1; border:1px solid #ccc; padding:4px;"
                                        placeholder="12345678-9">
                                </div>
                            </div>
                            <div>
                                <label style="font-size:10px; font-weight:bold;">Razón Social</label>
                                <input type="text" id="np-nombre"
                                    style="width:100%; border:1px solid #ccc; padding:4px;" placeholder="EMPRESA, C.A."
                                    oninput="this.value=this.value.toUpperCase()">
                            </div>
                            <div>
                                <label style="font-size:10px; font-weight:bold;">Teléfono</label>
                                <input type="text" id="np-telefono"
                                    style="width:100%; border:1px solid #ccc; padding:4px;" placeholder="0283-0000000"
                                    oninput="formatTelefono(this)">
                            </div>
                        </div>
                        <div style="margin-top:6px;">
                            <label style="font-size:10px; font-weight:bold;">Dirección</label>
                            <input type="text" id="np-direccion" style="width:100%; border:1px solid #ccc; padding:4px;"
                                placeholder="Av. Principal..."
                                oninput="this.value=this.value.toUpperCase()">
                        </div>
                        <div style="margin-top:10px; display:flex; gap:8px;">
                            <button type="button" onclick="guardarNuevoProveedor()"
                                style="background:#1565c0; color:white; border:none; padding:6px 14px; font-size:11px; cursor:pointer; font-weight:bold;">
                                Guardar proveedor
                            </button>
                            <button type="button" onclick="toggleFormProveedor()"
                                style="background:#666; color:white; border:none; padding:6px 14px; font-size:11px; cursor:pointer;">
                                Cancelar
                            </button>
                        </div>
                    </div>

                    <!-- ESTRUCTURA EXACTA DEL DOCUMENTO ORDEN DE COMPRAS -->
                    <div class="oc-document">

                        <!-- 1. ENCABEZADO DE LOGOS Y TEXTO INSTITUCIONAL -->
                        <div class="oc-header-grid">
                            <div class="oc-logo-left">
                                <img src="/sistema/assets/img/logo.png" alt="Logo Contraloría MSR">
                            </div>
                            <div class="oc-header-center">
                                <p class="oc-rep">REPÚBLICA BOLIVARIANA DE VENEZUELA</p>
                                <p class="oc-est">ESTADO ANZOÁTEGUI</p>
                                <p class="oc-ent">CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</p>
                            </div>
                            <div class="oc-logo-right">
                                <img src="/sistema/assets/img/sncf.png"
                                    alt="SN CF">
                            </div>
                        </div>

                        <!-- 2. TITULO ORDEN DE COMPRAS + N° ORDEN Y FECHA -->
                        <div class="oc-row-title">
                            <div class="oc-title-cell">
                                <h2><?php echo $oc_edit ? 'EDITAR ORDEN DE COMPRAS' : 'ORDEN DE COMPRAS'; ?></h2>
                            </div>
                            <div class="oc-num-fecha-cell">
                                <div class="oc-nf-header-row">
                                    <label>N° Orden:</label>
                                    <label>Fecha:</label>
                                </div>
                                <div class="oc-nf-val-row">
                                    <input type="text" name="numero_orden" id="numero_orden" required placeholder="000"
                                        value="<?php echo htmlspecialchars($oc_edit ? $oc_edit['numero_orden'] : ''); ?>"
                                        oninput="syncNumOC(this.value)">
                                    <?php
                                    $val_fecha_db = ($oc_edit && !empty($oc_edit['fecha'])) ? $oc_edit['fecha'] : date('Y-m-d');
                                    $val_fecha_disp = date('d/m/Y', strtotime($val_fecha_db));
                                    ?>
                                    <div style="position: relative; width: 100%; display: flex; align-items: center;">
                                        <input type="hidden" name="fecha" id="fecha"
                                            value="<?php echo htmlspecialchars($val_fecha_db); ?>" required>
                                        <input type="text" id="fecha_display" readonly
                                            value="<?php echo htmlspecialchars($val_fecha_disp); ?>"
                                            placeholder="DD/MM/YYYY"
                                            onclick="openDatePicker(this, document.getElementById('fecha'))"
                                            style="cursor: pointer; width: 100%; border: none; padding: 3px 6px; font-size: 11px; font-weight: bold; text-align: center; font-family: 'Inter', sans-serif; outline: none; background: transparent;"
                                            title="Seleccionar Fecha">
                                    </div>
                                </div>
                                <div class="oc-nf-sub-row">
                                    <input type="text" id="numero_orden_sub" readonly value="<?php echo htmlspecialchars($oc_edit ? $oc_edit['numero_orden'] : ''); ?>">
                                    <input type="text" readonly value="">
                                </div>
                            </div>
                        </div>

                        <!-- 3. BLOQUE PROVEEDOR, DIRECCIÓN, TELÉFONO, LUGAR DE ENTREGA -->
                        <div class="oc-prov-block">
                            <div class="oc-prov-left">
                                <div class="oc-prov-row">
                                    <label>Proveedor:</label>
                                    <select name="proveedor_id" id="sel-proveedor" required
                                        onchange="cargarProveedor(this.value)">
                                        <option value="">-- Seleccionar Proveedor --</option>
                                        <?php while ($p = mysqli_fetch_assoc($proveedores)): ?>
                                            <?php $sel = ($oc_edit && $oc_edit['proveedor_id'] == $p['id']) ? 'selected' : ''; ?>
                                            <option value="<?php echo $p['id']; ?>" <?php echo $sel; ?>>
                                                <?php echo htmlspecialchars($p['rif']); ?> -
                                                <?php echo htmlspecialchars($p['razon_social']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                        <option value="nuevo" style="font-weight:bold; color:#1565c0;">+ Agregar Nuevo
                                            Proveedor...</option>
                                    </select>
                                </div>
                                <div class="oc-prov-row">
                                    <label>Dirección:</label>
                                    <textarea name="direccion_proveedor" id="dir-prov" readonly class="auto-expand" rows="1" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()"
                                        placeholder="AV. FRANCISCO DE MIRANDA EDIF MARYUCA PISO P.B PUEBLO NUEVO SUR..."><?php echo htmlspecialchars($oc_edit ? $oc_edit['prov_direccion'] : ''); ?></textarea>
                                </div>
                                <div class="oc-prov-row">
                                    <label>Teléfono:</label>
                                    <textarea name="telefono_proveedor" id="tel-prov" readonly class="auto-expand" rows="1"
                                        placeholder="0283-0000000"><?php echo htmlspecialchars($oc_edit ? $oc_edit['prov_telefono'] : ''); ?></textarea>
                                </div>
                                <div class="oc-prov-row" style="position: relative;">
                                    <label style="display: flex; align-items: center; gap: 8px;">
                                        Lugar de Entrega:
                                        <button type="button" class="no-print" onclick="copiarDireccionProveedor()" style="background: #1565c0; color: white; border: none; padding: 2px 6px; font-size: 9px; font-weight: bold; cursor: pointer; border-radius: 3px;">
                                            Usar Dir. Proveedor
                                        </button>
                                    </label>
                                    <textarea name="lugar_entrega" id="lugar-entrega" class="auto-expand" rows="1" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()"
                                        placeholder="Agregar lugar de entrega..."><?php echo htmlspecialchars(($oc_edit && isset($oc_edit['lugar_entrega'])) ? $oc_edit['lugar_entrega'] : ''); ?></textarea>
                                </div>
                            </div>
                            <div class="oc-prov-right">
                                DIRECCIÓN DE ADMINISTRACIÓN PLANIFICACIÓN Y PRESUPUESTO
                            </div>
                        </div>

                        <!-- 4. SECCIÓN RENGLONES DE LA ORDEN DE COMPRAS -->
                        <div class="oc-section-bar">
                            RENGLONES DE LA ORDEN DE COMPRAS
                        </div>

                        <table class="oc-table-renglones" id="tabla-oc">
                            <thead>
                                <tr>
                                    <th style="width:38%">DESCRIPCIÓN</th>
                                    <th style="width:18%">IMPUT. PPTARIA.</th>
                                    <th style="width:8%">UNIDAD</th>
                                    <th style="width:8%">CANT.</th>
                                    <th style="width:14%">P. UNITARIO</th>
                                    <th style="width:14%">TOTAL Bs.</th>
                                    <th style="width:44px" class="no-print">EXENTO</th>
                                    <th style="width:30px" class="no-print"></th>
                                </tr>
                            </thead>
                            <tbody id="renglones">
                                <!-- Filas generadas dinámicamente por JS -->
                            </tbody>
                        </table>
                        <div style="padding:4px 8px; border-bottom:1.5px solid #000;" class="no-print">
                            <button type="button" class="btn-add" onclick="addRow()">+ Agregar renglón</button>
                        </div>

                        <!-- 5. MONTO EN LETRA & TOTALES -->
                        <div class="oc-row-totales">
                            <div class="oc-monto-letras-cell">
                                <div>
                                    Monto total en letra: <strong id="monto-letras-txt">—</strong>
                                </div>
                            </div>
                            <div class="oc-totales-cell">
                                <div class="oc-tot-row">
                                    <label>Base Imponible:</label>
                                    <span class="tot-field-wrap">
                                        <input type="text" id="t-base" class="tot-input" value="0,00" autocomplete="off">
                                        <button type="button" class="tot-unlock" id="unlock-base" title="Restablecer al valor calculado" style="display:none" onclick="resetTot('base')">&#128274;</button>
                                    </span>
                                </div>
                                <div class="oc-tot-row">
                                    <label>SAT 0,1%:</label>
                                    <span class="tot-field-wrap">
                                        <input type="text" id="t-sat" class="tot-input" value="0,00" autocomplete="off">
                                        <button type="button" class="tot-unlock" id="unlock-sat" title="Restablecer al valor calculado" style="display:none" onclick="resetTot('sat')">&#128274;</button>
                                    </span>
                                </div>
                                <div class="oc-tot-row">
                                    <label>Sub-Total:</label>
                                    <span class="tot-field-wrap">
                                        <input type="text" id="t-sub" class="tot-input" value="0,00" autocomplete="off">
                                        <button type="button" class="tot-unlock" id="unlock-sub" title="Restablecer al valor calculado" style="display:none" onclick="resetTot('sub')">&#128274;</button>
                                    </span>
                                </div>
                                <div class="oc-tot-row">
                                    <label id="lbl-iva">IVA 16%:</label>
                                    <span class="tot-field-wrap">
                                        <input type="text" id="t-iva" class="tot-input" value="0,00" autocomplete="off">
                                        <button type="button" class="tot-unlock" id="unlock-iva" title="Restablecer al valor calculado" style="display:none" onclick="resetTot('iva')">&#128274;</button>
                                    </span>
                                </div>
                                <div class="oc-tot-row total-gen">
                                    <label>Total General:</label>
                                    <span class="tot-field-wrap">
                                        <input type="text" id="t-total" class="tot-input tot-input-total" value="0,00" autocomplete="off">
                                        <button type="button" class="tot-unlock" id="unlock-total" title="Restablecer al valor calculado" style="display:none" onclick="resetTot('total')">&#128274;</button>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 6. PARTIDAS & FIRMAS (SPLIT GRID) -->
                        <div class="oc-split-grid">
                            <div class="oc-split-left">
                                <div class="oc-box-section">
                                    <span>Por Compras Y Servicios:</span>
                                </div>
                                <div class="oc-box-section">
                                    <span>Jefa de Planificación y Presupuesto:</span>
                                    <div style="text-align:right; font-size:9px; color:#333; margin-top:20px;">Firma y
                                        Cédula de Identidad</div>
                                </div>
                                <div class="oc-box-section">
                                    <span>Director de Administración, Planificación y Presupuesto:</span>
                                </div>
                            </div>
                            <div class="oc-split-right">
                                <!-- Tabla Partidas -->
                                <table class="oc-partidas-table">
                                    <thead>
                                        <tr>
                                            <th style="width:50%">Partida:</th>
                                            <th style="width:50%">Monto:</th>
                                        </tr>
                                    </thead>
                                    <tbody id="partidas-body">
                                        <!-- Filas generadas automáticamente por JS -->
                                    </tbody>
                                </table>
                                <div class="oc-contralora-box">
                                    <span>Contralora Municipal Provisional:</span>
                                </div>
                            </div>
                        </div>

                        <!-- 7. CONDICIONES DE LA ORDEN DE COMPRAS -->
                        <div class="oc-condiciones-bar">
                            CONDICIONES DE LA ORDEN DE COMPRAS
                        </div>
                        <div class="oc-condiciones-text">
                            EL ORGANISMO SE RESERVA EL DERECHO DE ANULAR UNILATERALMENTE LA PRESENTE ORDEN DE COMPRAS
                            SIN INDEMNIZACIÓN DE CONFORMIDAD CON LO DISPUESTO <br> EN LA LEY QUE RIGE LA MATERIA
                        </div>

                    </div><!-- /.oc-document -->

                    <!-- Campos ocultos requeridos por backend -->
                    <input type="hidden" name="oc_id" value="<?php echo $oc_edit ? (int)$oc_edit['id'] : 0; ?>">
                    <input type="hidden" name="base_imponible" id="h-base">
                    <input type="hidden" name="sat_monto" id="h-sat">
                    <input type="hidden" name="iva_monto" id="h-iva">
                    <input type="hidden" name="total_general" id="h-total">
                    <input type="hidden" name="monto_letras" id="h-letras">
                    <input type="hidden" name="renglones_json" id="h-renglones">

                    <!-- BOTONES DE ACCIÓN -->
                    <div class="btns no-print">
                        <button type="submit" class="btn-guardar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Guardar Orden de Compras
                        </button>
                        <button type="button" class="btn-imprimir" onclick="window.print()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                            Imprimir / PDF
                        </button>
                    </div>

                </form>

            </div><!-- /.container -->
        </main>
    </div><!-- /.layout --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?><script>
        var rowIdx = 0;

        /* ============================================================
           CONVERSIÓN NÚMERO A LETRAS (española)
           ============================================================ */
        var ONES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
            'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'diecisiete',
            'dieciocho', 'diecinueve', 'veinte'];
        var TENS = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        var HUNDS = ['', 'cien', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
            'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        function inWords(n) {
            n = Math.floor(n);
            if (n === 0) return 'cero';
            if (n <= 20) return ONES[n];
            if (n < 100) {
                var d = Math.floor(n / 10), u = n % 10;
                if (d === 2 && u > 0) return 'veinti' + ONES[u];
                return TENS[d] + (u ? ' y ' + ONES[u] : '');
            }
            if (n < 1000) {
                var c = Math.floor(n / 100), r = n % 100;
                if (n === 100) return 'cien';
                return HUNDS[c] + (r ? ' ' + inWords(r) : '');
            }
            if (n < 1000000) {
                var m = Math.floor(n / 1000), r2 = n % 1000;
                return (m === 1 ? 'mil' : inWords(m) + ' mil') + (r2 ? ' ' + inWords(r2) : '');
            }
            return n.toString();
        }

        function montoLetras(val) {
            var entero = Math.floor(val);
            var cents = Math.round((val - entero) * 100);
            var centsStr = cents < 10 ? '0' + cents : '' + cents;
            return inWords(entero).toUpperCase() + ' BOLIVARES CON ' + centsStr + '/100';
        }

        function fmt(n) {
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function syncNumOC(val) {
            var el = document.getElementById('numero_orden_sub');
            if (el) { el.value = val; }
        }

        /* ============================================================
           CARGAR PROVEEDOR
           ============================================================ */
        function toggleFormProveedor() {
            var f = document.getElementById('form-nuevo-proveedor');
            f.style.display = f.style.display === 'none' ? 'block' : 'none';
            if (f.style.display === 'block') {
                var inp = document.getElementById('np-rif-num');
                if (inp) {
                    inp.value = '';
                    inp.focus();
                }
                document.getElementById('np-rif-tipo').value = 'J';
            }
        }

        function guardarNuevoProveedor() {
            var rifTipo = document.getElementById('np-rif-tipo').value;
            var rifNum  = document.getElementById('np-rif-num').value.trim();
            var rif     = rifTipo + '-' + rifNum;
            var nombre = document.getElementById('np-nombre').value.trim();
            var telefono = document.getElementById('np-telefono').value.trim();
            var direccion = document.getElementById('np-direccion').value.trim();

            if (!rifNum || !nombre) { alert('RIF y Razón Social son obligatorios.'); return; }

            fetch('../../controllers/proveedores/crear.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'rif=' + encodeURIComponent(rif) +
                    '&razon_social=' + encodeURIComponent(nombre) +
                    '&telefono=' + encodeURIComponent(telefono) +
                    '&direccion=' + encodeURIComponent(direccion) +
                    '&ajax=1'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.error) { alert('Error: ' + data.error); return; }
                    alert('Proveedor creado exitosamente.');
                    var select = document.getElementById('sel-proveedor');
                    var lastOpt = select.querySelector('option[value="nuevo"]');
                    var option = document.createElement('option');
                    option.value = data.id;
                    option.textContent = rif + ' - ' + nombre;
                    if (lastOpt) { select.insertBefore(option, lastOpt); }
                    else { select.appendChild(option); }
                    select.value = data.id;

                    document.getElementById('dir-prov').value = direccion;
                    document.getElementById('tel-prov').value = telefono;
                    autoExpand(document.getElementById('dir-prov'));
                    autoExpand(document.getElementById('tel-prov'));

                    document.getElementById('np-rif-tipo').value = 'J';
                    document.getElementById('np-rif-num').value = '';
                    document.getElementById('np-nombre').value = '';
                    document.getElementById('np-telefono').value = '';
                    document.getElementById('np-direccion').value = '';
                    toggleFormProveedor();
                });
        }

        function cargarProveedor(id) {
            if (!id) return;
            if (id === 'nuevo') {
                toggleFormProveedor();
                document.getElementById('sel-proveedor').value = '';
                return;
            }

            fetch('../../controllers/proveedores/buscar.php?id=' + id)
                .then(function (r) { return r.json(); })
                .then(function (p) {
                    document.getElementById('dir-prov').value = p.direccion || '';
                    document.getElementById('tel-prov').value = p.telefono || '';
                    autoExpand(document.getElementById('dir-prov'));
                    autoExpand(document.getElementById('tel-prov'));
                });
        }

        function copiarDireccionProveedor() {
            var dir = document.getElementById('dir-prov').value;
            var lugar = document.getElementById('lugar-entrega');
            if (lugar) {
                lugar.value = dir;
                autoExpand(lugar);
            }
        }

        /* ============================================================
           BÚSQUEDA Y AGREGAR RENGLONES
           ============================================================ */
        function buscarProducto(input, i) {
            input.value = input.value.toUpperCase();
            var q = input.value;
            var sug = document.getElementById('sug' + i);
            var tr = input.closest('tr');
            var td = input.closest('td');

            if (q.length < 2) {
                if (sug) sug.style.display = 'none';
                if (tr) tr.classList.remove('has-suggestions');
                if (td) td.classList.remove('has-suggestions');
                return;
            }

            fetch('../../controllers/productos/buscar.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || data.length === 0) {
                        if (sug) sug.style.display = 'none';
                        if (tr) tr.classList.remove('has-suggestions');
                        if (td) td.classList.remove('has-suggestions');
                        return;
                    }
                    var html = '';
                    for (var j = 0; j < data.length; j++) {
                        var p = data[j];
                        var descEsc = (p.descripcion || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        var imputEsc = (p.imput_presupuestaria || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        html += '<div class="sug-item" onclick="seleccionarProducto(' + i + ', \'' + descEsc + '\', \'' + imputEsc + '\')">' +
                            '<strong>' + p.descripcion + '</strong>' +
                            '<span class="imput-pill">' + (p.imput_presupuestaria || '') + '</span>' +
                            '</div>';
                    }
                    if (sug) {
                        sug.innerHTML = html;
                        sug.style.display = 'block';
                    }
                    if (tr) tr.classList.add('has-suggestions');
                    if (td) td.classList.add('has-suggestions');
                });
        }

        function seleccionarProducto(i, desc, imput) {
            var tr = document.getElementById('r' + i);
            if (!tr) return;
            var descEl = tr.querySelector('[name="desc[]"]');
            var imputEl = tr.querySelector('[name="imput[]"]');
            if (descEl) {
                descEl.value = desc;
                if (typeof autoExpand === 'function') autoExpand(descEl);
            }
            if (imputEl) {
                imputEl.value = imput;
            }
            var sug = document.getElementById('sug' + i);
            if (sug) sug.style.display = 'none';
            tr.classList.remove('has-suggestions');
            var td = tr.querySelector('.desc-cell') || tr.querySelector('td');
            if (td) td.classList.remove('has-suggestions');
            recalc();
        }

        function addRow() {
            var i = rowIdx++;
            var tr = document.createElement('tr');
            tr.id = 'r' + i;
            tr.innerHTML =
                '<td class="desc-cell">' +
                '<div class="desc-autocomplete-wrapper">' +
                '<textarea name="desc[]" class="auto-expand" rows="1" placeholder="DESCRIPCIÓN DE PRODUCTO"' +
                ' style="text-transform:uppercase; width:100%; resize:none; overflow:hidden; border:none; background:transparent; font-family:inherit; font-size:11px; font-weight:bold; line-height:1.35; padding:2px 0;"' +
                ' oninput="autoExpand(this); buscarProducto(this, ' + i + ')" autocomplete="off"></textarea>' +
                '<div id="sug' + i + '" class="sug-dropdown"></div>' +
                '</div>' +
                '</td>' +
                '<td style="position:relative">' +
                '<input type="text" name="imput[]" placeholder="4.02.05.01.00"' +
                ' oninput="recalc()" id="imput' + i + '" style="text-align:center;">' +
                '<button type="button" onclick="guardarProducto(' + i + ')" class="no-print"' +
                ' style="position:absolute; right:2px; top:2px; font-size:9px; background:#1565c0; color:white; border:none; padding:1px 5px; cursor:pointer;">' +
                ' +</button>' +
                '</td>' +
                '<td style="text-align:center;">' +
                '<input type="text" name="unidad[]" value="1" readonly style="text-align:center;">' +
                '</td>' +
                '<td style="text-align:right;">' +
                '<input type="number" name="cant[]" value="1" min="1" step="1" oninput="calcRow(' + i + ')" style="text-align:right">' +
                '</td>' +
                '<td style="text-align:right;">' +
                '<input type="text" name="pu[]" value="0" oninput="formatearPU(this, ' + i + ')" style="text-align:right">' +
                '</td>' +
                '<td style="text-align:right;" id="tot' + i + '">0,00</td>' +
                '<td style="text-align:center;" class="no-print">' +
                '<input type="checkbox" name="exento[]" onchange="recalc()" title="Exento de IVA (ej: productos farmacéuticos)" style="transform:scale(1.15);cursor:pointer;">' +
                '</td>' +
                '<td style="text-align:center;" class="no-print">' +
                '<button type="button" onclick="delRow(' + i + ')" style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button>' +
                '</td>';
            document.getElementById('renglones').appendChild(tr);
        }

        function delRow(i) {
            var r = document.getElementById('r' + i);
            if (r) r.parentNode.removeChild(r);
            recalc();
        }

        function formatPrecioDB(val) {
            if (val === undefined || val === null || val === '') return '0,00';
            var s = val.toString().trim();
            if (s.includes(',')) {
                return s;
            }
            var num = parseFloat(s);
            if (isNaN(num)) return '0,00';
            var parts = num.toFixed(2).split('.');
            var entero = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return entero + ',' + parts[1];
        }

        function formatearPU(input, i) {
            var raw = input.value.replace(/[^0-9,]/g, '');
            var partes = raw.split(',');
            var entero = partes[0].replace(/\./g, '');
            var decimal = partes[1] !== undefined ? partes[1].substring(0, 2) : null;
            entero = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            input.value = decimal !== null ? entero + ',' + decimal : entero;
            calcRow(i);
        }

        function calcRow(i) {
            var tr = document.getElementById('r' + i);
            if (!tr) return;
            var cant = parseFloat(tr.querySelector('input[name="cant[]"]').value) || 0;
            var puRaw = tr.querySelector('input[name="pu[]"]').value.replace(/\./g, '').replace(',', '.');
            var pu = parseFloat(puRaw) || 0;
            document.getElementById('tot' + i).textContent = fmt(cant * pu);
            recalc();
        }

        function recalc() {
            var base = 0;
            var baseGravada = 0;
            var trs = document.querySelectorAll('#renglones tr');
            for (var i = 0; i < trs.length; i++) {
                var tr = trs[i];
                var cantEl = tr.querySelector('input[name="cant[]"]');
                var puEl = tr.querySelector('input[name="pu[]"]');
                var exentoEl = tr.querySelector('input[name="exento[]"]');
                var cant = parseFloat(cantEl ? cantEl.value : 0) || 0;
                var puRaw = (puEl ? puEl.value : '0').replace(/\./g, '').replace(',', '.');
                var pu = parseFloat(puRaw) || 0;
                var linea = cant * pu;
                base += linea;
                if (!(exentoEl && exentoEl.checked)) {
                    baseGravada += linea;
                }
            }

            var sat = base * 0.001;   /* SAT 0.1% */
            var sub = base + sat;     /* Subtotal = Base Imponible + SAT */
            var iva = baseGravada * 0.16;  /* IVA 16% solo sobre renglones no exentos */
            var total = base + iva;   /* Total General = Base + IVA */

            /* Only overwrite each total if the user hasn't manually edited it */
            var calcValues = { base: base, sat: sat, sub: sub, iva: iva, total: total };
            ['base','sat','sub','iva','total'].forEach(function(k){
                var el = document.getElementById('t-' + k);
                if (!el.dataset.manual) {
                    el.value = fmt(calcValues[k]);
                }
            });

            /* Use the displayed values (possibly overridden) to set hidden fields */
            var dispBase  = parseTot('base');
            var dispSat   = parseTot('sat');
            var dispIva   = parseTot('iva');
            var dispTotal = parseTot('total');

            var letras = dispTotal > 0 ? montoLetras(dispTotal) : '\u2014';
            document.getElementById('monto-letras-txt').textContent = letras;

            document.getElementById('h-base').value = dispBase.toFixed(2);
            document.getElementById('h-sat').value = dispSat.toFixed(2);
            document.getElementById('h-iva').value = dispIva.toFixed(2);
            document.getElementById('h-total').value = dispTotal.toFixed(2);
            document.getElementById('h-letras').value = letras;

            checkEmptyRows();
            actualizarPartidas();
        }

        function parseTot(k) {
            var el = document.getElementById('t-' + k);
            var raw = (el ? el.value : '0').replace(/,/g, '');
            return parseFloat(raw) || 0;
        }

        function resetTot(k) {
            var el = document.getElementById('t-' + k);
            if (el) {
                delete el.dataset.manual;
                el.classList.remove('manual');
            }
            var btn = document.getElementById('unlock-' + k);
            if (btn) btn.style.display = 'none';
            recalc();
        }

        /* Mark a total field as manually overridden on user input */
        ['base','sat','sub','iva','total'].forEach(function(k) {
            var el = document.getElementById('t-' + k);
            if (!el) return;
            el.addEventListener('input', function() {
                el.dataset.manual = '1';
                el.classList.add('manual');
                var btn = document.getElementById('unlock-' + k);
                if (btn) btn.style.display = 'inline';
                /* Sync hidden field immediately */
                var raw = el.value.replace(/,/g, '');
                var val = parseFloat(raw) || 0;
                var hidMap = { base: 'h-base', sat: 'h-sat', sub: 'h-sub', iva: 'h-iva', total: 'h-total' };
                var hid = document.getElementById(hidMap[k]);
                if (hid) hid.value = val.toFixed(2);
                /* If total changes, update monto-letras */
                if (k === 'total') {
                    var letras = val > 0 ? montoLetras(val) : '\u2014';
                    document.getElementById('monto-letras-txt').textContent = letras;
                    document.getElementById('h-letras').value = letras;
                }
            });
        });

        function actualizarPartidas() {
            var grupos = {};
            var trs = document.querySelectorAll('#renglones tr');

            for (var i = 0; i < trs.length; i++) {
                var tr = trs[i];
                var imputEl = tr.querySelector('input[name="imput[]"]');
                var cantEl = tr.querySelector('input[name="cant[]"]');
                var puEl = tr.querySelector('input[name="pu[]"]');

                var imput = imputEl ? imputEl.value.trim() : '';
                var cant = parseFloat(cantEl ? cantEl.value : 0) || 0;
                var puRaw = (puEl ? puEl.value : '0').replace(/\./g, '').replace(',', '.');
                var pu = parseFloat(puRaw) || 0;
                var total = cant * pu;

                if (!imput) continue;

                if (grupos[imput]) {
                    grupos[imput] += total;
                } else {
                    grupos[imput] = total;
                }
            }

            var tbody = document.getElementById('partidas-body');
            tbody.innerHTML = '';

            for (var k in grupos) {
                if (Object.prototype.hasOwnProperty.call(grupos, k)) {
                    var tr2 = document.createElement('tr');
                    tr2.innerHTML =
                        '<td><input type="text" name="partida[]" value="' + k + '" style="text-align:center;"></td>' +
                        '<td style="text-align:right;"><input type="text" name="monto_partida[]" value="' + fmt(grupos[k]) + '" style="text-align:right;"></td>';
                    tbody.appendChild(tr2);
                }
            }

            /* ── Partida IVA: 4.03.18.01.00 ── */
            var ivaVal = parseFloat(document.getElementById('h-iva').value) || 0;
            var trIva = document.createElement('tr');
            trIva.innerHTML =
                '<td><input type="text" name="partida[]" value="4.03.18.01.00" style="text-align:center; background:#fff8e1;"></td>' +
                '<td style="text-align:right;"><input type="text" name="monto_partida[]" value="' + fmt(ivaVal) + '" style="text-align:right; background:#fff8e1;"></td>';
            tbody.appendChild(trIva);

            /* ── Partida SAT 0,1%: 4.03.18.99.00 ── */
            var satVal = parseFloat(document.getElementById('h-sat').value) || 0;
            var trSat = document.createElement('tr');
            trSat.innerHTML =
                '<td><input type="text" name="partida[]" value="4.03.18.99.00" style="text-align:center; background:#e8f5e9;"></td>' +
                '<td style="text-align:right;"><input type="text" name="monto_partida[]" value="' + fmt(satVal) + '" style="text-align:right; background:#e8f5e9;"></td>';
            tbody.appendChild(trSat);
        }

        function guardarProducto(i) {
            var tr = document.getElementById('r' + i);
            if (!tr) return;
            var descEl = tr.querySelector('[name="desc[]"]');
            var imputEl = tr.querySelector('[name="imput[]"]');
            var desc = descEl ? descEl.value.trim() : '';
            var imput = imputEl ? imputEl.value.trim() : '';

            if (!desc || !imput) {
                alert('⚠️ Por favor completa la DESCRIPCIÓN y la IMPUTACIÓN PRESUPUESTARIA antes de guardar.');
                return;
            }

            fetch('../../controllers/productos/guardar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'descripcion=' + encodeURIComponent(desc) + '&imput=' + encodeURIComponent(imput)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        alert('✅ ¡Producto / Servicio guardado exitosamente en el catálogo!');
                    } else if (data.existe) {
                        alert('ℹ️ Este Producto / Servicio ya se encuentra registrado en el catálogo.');
                    } else {
                        alert('❌ Error al guardar el producto: ' + (data.error || 'Intente nuevamente.'));
                    }
                })
                .catch(function (err) {
                    alert('❌ Error de conexión al guardar el producto.');
                });
        }

        function autoExpand(el) {
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = (el.scrollHeight) + 'px';
        }

        function checkEmptyRows() {
            var trs = document.querySelectorAll('#renglones tr');
            for (var i = 0; i < trs.length; i++) {
                var tr = trs[i];
                var descEl = tr.querySelector('[name="desc[]"]');
                var puEl = tr.querySelector('[name="pu[]"]');

                var desc = descEl ? descEl.value.trim() : '';
                var puRaw = puEl ? puEl.value.replace(/\./g, '').replace(',', '.').trim() : '0';
                var pu = parseFloat(puRaw) || 0;

                var allEmpty = true;
                var inputs = tr.querySelectorAll('input, textarea');
                for (var j = 0; j < inputs.length; j++) {
                    if (inputs[j].type === 'checkbox') continue;
                    var val = inputs[j].value.trim();
                    if (val !== '' && val !== '0' && val !== '0,00' && val !== '1') {
                        allEmpty = false;
                        break;
                    }
                }

                if ((!desc && pu === 0) || allEmpty) {
                    tr.classList.add('row-empty');
                } else {
                    tr.classList.remove('row-empty');
                }
            }
        }

        window.addEventListener('beforeprint', checkEmptyRows);

        document.addEventListener('input', function (e) {
            if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.classList.contains('auto-expand'))) {
                autoExpand(e.target);
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('textarea').forEach(autoExpand);
            checkEmptyRows();
        });

        document.getElementById('form-oc').addEventListener('submit', function () {
            var rows = [];
            var trs = document.querySelectorAll('#renglones tr');
            for (var i = 0; i < trs.length; i++) {
                var tr = trs[i];
                var descEl = tr.querySelector('[name="desc[]"]');
                var imputEl = tr.querySelector('input[name="imput[]"]');
                var cantEl = tr.querySelector('input[name="cant[]"]');
                var puEl = tr.querySelector('input[name="pu[]"]');
                var exentoEl = tr.querySelector('input[name="exento[]"]');

                rows.push({
                    desc: descEl ? descEl.value : '',
                    imput: imputEl ? imputEl.value : '',
                    unidad: '1',
                    cant: cantEl ? cantEl.value : 0,
                    pu: (puEl ? puEl.value : '0').replace(/\./g, '').replace(',', '.'),
                    exento: (exentoEl && exentoEl.checked) ? 1 : 0
                });
            }
            document.getElementById('h-renglones').value = JSON.stringify(rows);
        });

        function addRowWithData(desc, imput, unidad, cant, pu, exento) {
            var i = rowIdx++;
            var tr = document.createElement('tr');
            tr.id = 'r' + i;
            tr.innerHTML =
                '<td class="desc-cell">' +
                '<div class="desc-autocomplete-wrapper">' +
                '<textarea name="desc[]" class="auto-expand" rows="1" placeholder="DESCRIPCIÓN DE PRODUCTO"' +
                ' style="text-transform:uppercase; width:100%; resize:none; overflow:hidden; border:none; background:transparent; font-family:inherit; font-size:11px; font-weight:bold; line-height:1.35; padding:2px 0;"' +
                ' oninput="autoExpand(this); buscarProducto(this, ' + i + ')" autocomplete="off"></textarea>' +
                '<div id="sug' + i + '" class="sug-dropdown"></div>' +
                '</div>' +
                '</td>' +
                '<td style="position:relative">' +
                '<input type="text" name="imput[]" placeholder="4.02.05.01.00"' +
                ' oninput="recalc()" id="imput' + i + '" style="text-align:center;">' +
                '<button type="button" onclick="guardarProducto(' + i + ')" class="no-print"' +
                ' style="position:absolute; right:2px; top:2px; font-size:9px; background:#1565c0; color:white; border:none; padding:1px 5px; cursor:pointer;">' +
                ' +</button>' +
                '</td>' +
                '<td style="text-align:center;">' +
                '<input type="text" name="unidad[]" value="1" readonly style="text-align:center;">' +
                '</td>' +
                '<td style="text-align:right;">' +
                '<input type="number" name="cant[]" value="1" min="1" step="1" oninput="calcRow(' + i + ')" style="text-align:right">' +
                '</td>' +
                '<td style="text-align:right;">' +
                '<input type="text" name="pu[]" value="0" oninput="formatearPU(this, ' + i + ')" style="text-align:right">' +
                '</td>' +
                '<td style="text-align:right;" id="tot' + i + '">0,00</td>' +
                '<td style="text-align:center;" class="no-print">' +
                '<input type="checkbox" name="exento[]" onchange="recalc()" title="Exento de IVA (ej: productos farmacéuticos)" style="transform:scale(1.15);cursor:pointer;">' +
                '</td>' +
                '<td style="text-align:center;" class="no-print">' +
                '<button type="button" onclick="delRow(' + i + ')" style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button>' +
                '</td>';
            document.getElementById('renglones').appendChild(tr);

            if (desc) tr.querySelector('[name="desc[]"]').value = desc;
            if (imput) tr.querySelector('[name="imput[]"]').value = imput;
            if (unidad) tr.querySelector('[name="unidad[]"]').value = unidad;
            if (cant !== undefined) tr.querySelector('[name="cant[]"]').value = cant;
            if (pu !== undefined) {
                var puInput = tr.querySelector('[name="pu[]"]');
                puInput.value = formatPrecioDB(pu);
                formatearPU(puInput, i);
            }
            if (exento) tr.querySelector('[name="exento[]"]').checked = true;
        }

        <?php if (!empty($oc_renglones_edit)): ?>
            var editRenglones = <?php echo json_encode($oc_renglones_edit); ?>;
            for (var k = 0; k < editRenglones.length; k++) {
                var r = editRenglones[k];
                addRowWithData(r.descripcion, r.imput_presupuestaria, r.unidad, r.cantidad, r.precio_unitario, r.exento);
            }
        <?php else: ?>
            addRow();
            addRow();
            addRow();
        <?php endif; ?>

        function initRifInput() {
            var rifInput = document.getElementById('np-rif-num');
            if (!rifInput) return;
            rifInput.addEventListener('input', function () {
                var digits = rifInput.value.replace(/\D/g, '');
                if (digits.length > 9) digits = digits.slice(0, 9);
                if (digits.length === 9) {
                    rifInput.value = digits.slice(0, 8) + '-' + digits.slice(8);
                } else {
                    rifInput.value = digits;
                }
            });
        }

        function formatTelefono(input) {
            var digits = input.value.replace(/\D/g, '');
            if (digits.length > 11) digits = digits.slice(0, 11);
            input.value = digits.length <= 4 ? digits : digits.slice(0, 4) + '-' + digits.slice(4);
        }

        /* ── Detección de Cambios no Guardados ── */
        var formDirty = false;
        var isSubmitting = false;

        document.addEventListener('DOMContentLoaded', function() {
            initRifInput();
            document.querySelectorAll('textarea').forEach(autoExpand);

            var formOC = document.getElementById('form-oc');
            if (formOC) {
                formOC.addEventListener('input', function () { formDirty = true; });
                formOC.addEventListener('change', function () { formDirty = true; });
                formOC.addEventListener('submit', function () { isSubmitting = true; });
            }
        });

        window.addEventListener('beforeunload', function (e) {
            if (formDirty && !isSubmitting) {
                var msg = 'Tiene información no guardada. Si sale de la página, los datos ingresados se perderán.';
                (e || window.event).returnValue = msg;
                return msg;
            }
        });

        /* ── Cerrar dropdown de sugerencias al hacer clic fuera o pulsar Escape ── */
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.desc-autocomplete-wrapper') && !e.target.closest('.sug-dropdown')) {
                document.querySelectorAll('.sug-dropdown').forEach(function (el) {
                    el.style.display = 'none';
                    var tr = el.closest('tr');
                    if (tr) tr.classList.remove('has-suggestions');
                    var td = el.closest('td');
                    if (td) td.classList.remove('has-suggestions');
                });
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.sug-dropdown').forEach(function (el) {
                    el.style.display = 'none';
                    var tr = el.closest('tr');
                    if (tr) tr.classList.remove('has-suggestions');
                    var td = el.closest('td');
                    if (td) td.classList.remove('has-suggestions');
                });
            }
        });

        /* ── Spanish Popover Date Picker ── */
        (function() {
            var activePopup = null;
            var monthsEs = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            var daysEs = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];

            function closePopup() {
                if (activePopup && activePopup.parentNode) {
                    activePopup.parentNode.removeChild(activePopup);
                }
                activePopup = null;
            }

            document.addEventListener('click', function(e) {
                if (activePopup && !activePopup.contains(e.target) && e.target.id !== 'fecha_display') {
                    closePopup();
                }
            });

            window.openDatePicker = function(displayEl, hiddenEl) {
                if (activePopup) {
                    closePopup();
                    return;
                }

                var val = hiddenEl.value || '';
                var parts = val.split('-');
                var today = new Date();
                var currYear = parts.length === 3 ? parseInt(parts[0], 10) : today.getFullYear();
                var currMonth = parts.length === 3 ? parseInt(parts[1], 10) - 1 : today.getMonth();

                var pop = document.createElement('div');
                pop.className = 'sp-picker-popover';

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

                    pop.querySelector('.sp-prev-mon').onclick = function(e) {
                        e.stopPropagation();
                        currMonth--;
                        if (currMonth < 0) { currMonth = 11; currYear--; }
                        render();
                    };
                    pop.querySelector('.sp-next-mon').onclick = function(e) {
                        e.stopPropagation();
                        currMonth++;
                        if (currMonth > 11) { currMonth = 0; currYear++; }
                        render();
                    };

                    var dBtns = pop.querySelectorAll('.sp-day-btn');
                    for (var j = 0; j < dBtns.length; j++) {
                        dBtns[j].onclick = function(e) {
                            e.stopPropagation();
                            var dayNum = parseInt(this.getAttribute('data-day'), 10);
                            var mStr = (currMonth + 1 < 10 ? '0' : '') + (currMonth + 1);
                            var dStr = (dayNum < 10 ? '0' : '') + dayNum;
                            hiddenEl.value = currYear + '-' + mStr + '-' + dStr;
                            displayEl.value = dStr + '/' + mStr + '/' + currYear;
                            formDirty = true;
                            closePopup();
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