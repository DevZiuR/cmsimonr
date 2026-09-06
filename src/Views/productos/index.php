<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once __DIR__ . '/../../../config/conexion.php';

/* ── Exportar a Excel (CSV) ───────────────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $q_safe = $q !== '' ? '%' . mysqli_real_escape_string($conn, $q) . '%' : '';

    $filename = 'listado_productos_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($output, array('ID', 'Descripción', 'Imputación Presupuestaria'), ';');
    $where_q = $q_safe ? "WHERE (descripcion LIKE '$q_safe' OR imput_presupuestaria LIKE '$q_safe')" : '';
    $sql = "SELECT id, descripcion, imput_presupuestaria FROM productos $where_q ORDER BY descripcion ASC";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            fputcsv($output, array(
                $row['id'],
                $row['descripcion'],
                $row['imput_presupuestaria']
            ), ';');
        }
    }
    fclose($output);
    exit;
}

$query = "SELECT id, descripcion, imput_presupuestaria FROM productos ORDER BY descripcion ASC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Listado de productos del sistema de gestión - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Listado de Productos – Contraloría MSR</title>
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
            background: #f8fafc;
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
            border-bottom: 3px solid #ea580c;
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

        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 32px 36px;
            overflow-x: auto;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-image: url('/sistema/assets/img/banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 20px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
            position: relative;
            overflow: hidden;
            min-height: 90px;
        }

        .action-bar>* {
            position: relative;
            z-index: 1;
        }

        .page-title-ic {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title-ic .title-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .page-title {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .btn-new {
            background: #ffffff;
            color: #34302eff;
            text-decoration: none;
            padding: 9px 16px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .btn-new:hover {
            background: #fff7ed;
            color: #9a3412;
            transform: translateY(-1px);
        }

        .search-input {
            width: 320px;
            padding: 8px 12px 8px 34px;
            font-size: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            font-family: 'Inter', sans-serif;
            background: #f1f5f9 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E") no-repeat 12px center;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }

        .search-input:focus {
            background: #ffffff;
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .alert {
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 6px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .panel {
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .panel-head {
            background: #0d152bff;
            padding: 16px 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffffff;
            border-bottom: 1px solid #1e293b;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .panel-head .ph-title {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            color: #ffffff;
        }

        .panel-head .ph-icon {
            color: #fb923c;
            display: flex;
            align-items: center;
        }

        .panel-body {
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        table th {
            background: #1e293b;
            border-bottom: 2px solid #334155;
            padding: 12px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #f8fafc;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table th .th-icon {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            vertical-align: middle;
            color: #f8fafc;
        }

        table th .th-icon svg {
            color: #cbd5e1;
        }

        table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 14px;
            vertical-align: middle;
            color: #1e293b;
        }

        table tr:nth-child(even) td {
            background: #f8fafc;
        }

        table tr:hover td {
            background: #f1f5f9;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            font-size: 11px;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            color: white;
            margin-right: 4px;
            transition: all 0.15s ease;
        }

        .btn-action svg {
            width: 12px;
            height: 12px;
        }

        .btn-edit {
            background: #6366f1;
        }

        .btn-edit:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #ef4444;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-1px);
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

        .site-footer {
            background: #070707;
            color: #90a4ae;
            text-align: center;
            padding: 12px 20px;
            font-size: 10.5px;
            border-top: 3px solid #ea580c;
            line-height: 1.8;
            margin-top: auto;
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
            @page {
                size: letter portrait;
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

            .site-header,
            .site-footer,
            .sidebar,
            .sb-user-card,
            .action-bar,
            .search-input,
            .export-btn-group,
            .btn-new,
            .btn-action,
            .btn-edit,
            .btn-delete,
            .alert,
            .alert-success,
            .alert-danger,
            th:last-child,
            td:last-child,
            .no-print {
                display: none !important;
            }

            .layout,
            .main-content,
            .panel,
            .panel-body {
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

            .panel-head {
                background: #f1f5f9 !important;
                color: #000000 !important;
                border: 1px solid #64748b !important;
                border-bottom: none !important;
                font-size: 8.5pt !important;
                padding: 4px 6px !important;
            }

            .panel-head .ph-title {
                color: #000000 !important;
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

            table th .th-icon {
                color: #000000 !important;
            }

            table th .th-icon svg {
                display: none !important;
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
        }
    </style>
    <script>
        function confirmarEliminacion(url) {
            if (confirm('¿Seguro desea eliminar este producto? Esta acción no se puede deshacer.')) {
                window.location.href = url;
            }
        }

        function filtrarTablaProductos(valor) {
            var term = (valor || '').toLowerCase().trim();
            var table = document.getElementById('tabla-productos');
            if (!table) return;
            var tbody = table.querySelector('tbody');
            if (!tbody) return;
            var rows = tbody.querySelectorAll('tr');
            for (var i = 0; i < rows.length; i++) {
                var rowText = (rows[i].textContent || rows[i].innerText || '').toLowerCase();
                rows[i].style.display = (rowText.indexOf(term) !== -1) ? '' : 'none';
            }
        }

        function imprimirPDF() {
            window.print();
        }

        function exportarExcel() {
            var searchEl = document.getElementById('busqueda-tabla');
            var q = searchEl ? encodeURIComponent(searchEl.value.trim()) : '';
            window.location.href = 'index.php?export=excel&q=' + q;
        }
    </script>
</head>

<body>

    <!-- LAYOUT: SIDEBAR + MAIN -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php $active = 'prod-lista';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

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
                    <h2 id="print-report-title">Listado de Productos</h2>
                </div>
                <div class="doc-logo-right">
                    <img src="/sistema/assets/img/sncf.png" alt="NCF">
                </div>
            </div>

            <!-- Title & Action Bar -->
            <div class="action-bar no-print">
                <div class="page-title-ic">
                    <span class="title-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                            <line x1="12" y1="22.08" x2="12" y2="12" />
                        </svg>
                    </span>
                    <h1 class="page-title">Productos</h1>
                </div>
                <?php if ($es_admin): ?>
                    <a href="nuevo.php" class="btn-new" id="btn-nuevo-producto">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Nuevo Producto
                    </a>
                <?php endif; ?>
            </div>

            <!-- Alerts -->
            <?php if (isset($_GET['ok']) && $_GET['ok'] == '1'): ?>
                <div class="alert alert-success">Producto registrado exitosamente.</div>
            <?php endif; ?>

            <?php if (isset($_GET['ok_editar']) && $_GET['ok_editar'] == '1'): ?>
                <div class="alert alert-success">Producto actualizado exitosamente.</div>
            <?php endif; ?>

            <?php if (isset($_GET['ok_eliminar']) && $_GET['ok_eliminar'] == '1'): ?>
                <div class="alert alert-success">Producto eliminado exitosamente.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <?php $err = $_GET['error']; ?>
                <?php if ($err === 'db_error'): ?>
                    <div class="alert alert-danger">Error: Ocurrio un error en la base de datos.</div>
                <?php elseif ($err === 'tiene_ordenes'): ?>
                    <div class="alert alert-danger">Error: No se puede eliminar el producto porque esta asociado a ordenes
                        existentes.</div>
                <?php else: ?>
                    <div class="alert alert-danger">Error al procesar la solicitud.</div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Table Panel -->
            <div class="panel">
                <div class="panel-head">
                    <span class="ph-title">
                        Listado de Productos Registrados
                    </span>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <input type="text" id="busqueda-tabla" class="search-input"
                            placeholder="Buscar por descripción, imputación..."
                            onkeyup="filtrarTablaProductos(this.value)">
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
                <div class="panel-body">
                    <?php if (!$result || mysqli_num_rows($result) === 0): ?>
                        <div class="sin-datos">
                            <div class="sin-icon">
                                <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path
                                        d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                    <line x1="12" y1="22.08" x2="12" y2="12" />
                                </svg>
                            </div>
                            No hay productos registrados.
                        </div>
                    <?php else: ?>
                        <table id="tabla-productos">
                            <thead>
                                <tr>
                                    <th style="width:5%; text-align:center;"><span class="th-icon"><svg width="12"
                                                height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="6" y1="3" x2="6" y2="15" />
                                                <circle cx="18" cy="6" r="3" />
                                                <circle cx="6" cy="18" r="3" />
                                                <path d="M18 9a9 9 0 0 1-9 9" />
                                            </svg>ID</span></th>
                                    <th style="width:55%;"><span class="th-icon"><svg width="12" height="12"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path
                                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                                <line x1="12" y1="22.08" x2="12" y2="12" />
                                            </svg>Descripcion</span></th>
                                    <th style="width:30%;"><span class="th-icon"><svg width="12" height="12"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="2" x2="12" y2="22" />
                                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                            </svg>Imputacion Presupuestaria</span></th>
                                    <th style="width:10%; text-align:center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php $pid = (int) $row['id']; ?>
                                    <tr>
                                        <td style="text-align:center;"><?php echo $pid; ?></td>
                                        <td><?php echo htmlspecialchars(isset($row['descripcion']) ? $row['descripcion'] : ''); ?>
                                        </td>
                                        <td style="font-family:monospace;">
                                            <?php echo htmlspecialchars(isset($row['imput_presupuestaria']) ? $row['imput_presupuestaria'] : ''); ?>
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <?php if ($es_admin): ?>
                                                <a href="editar.php?id=<?php echo $pid; ?>" class="btn-action btn-edit"
                                                    id="btn-editar-<?php echo $pid; ?>">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                    </svg>
                                                    Editar
                                                </a>
                                                <a href="javascript:void(0);"
                                                    onclick="confirmarEliminacion('/sistema/src/Controllers/productos/eliminar.php?id=<?php echo $pid; ?>')"
                                                    class="btn-action btn-delete" id="btn-eliminar-<?php echo $pid; ?>">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path
                                                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                        <line x1="10" y1="11" x2="10" y2="17" />
                                                        <line x1="14" y1="11" x2="14" y2="17" />
                                                    </svg>
                                                    Eliminar
                                                </a>
                                            <?php else: ?>
                                                <span style="color:#aaa;font-size:10px;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <!-- FOOTER --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>

</html>