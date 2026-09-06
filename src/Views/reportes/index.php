<?php
session_start();
if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

require_once __DIR__ . '/../../../config/conexion.php';
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $tipo_reporte = isset($_GET['tipo_reporte']) ? $_GET['tipo_reporte'] : 'oc';
    $fecha_desde  = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
    $fecha_hasta  = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
    $status_fil   = isset($_GET['status']) ? $_GET['status'] : 'todas';

    $filename = 'reporte_' . $tipo_reporte . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8 in Excel

    if ($tipo_reporte === 'oc') {
        fputcsv($output, array('N° Orden', 'Fecha', 'Tipo', 'Proveedor', 'Base Imponible (Bs.)', 'IVA (Bs.)', 'Total General (Bs.)', 'Status'), ';');
        $where = "WHERE oc.deleted_at IS NULL";
        if ($fecha_desde !== '') {
            $where .= " AND oc.fecha >= '" . mysqli_real_escape_string($conn, $fecha_desde) . "'";
        }
        if ($fecha_hasta !== '') {
            $where .= " AND oc.fecha <= '" . mysqli_real_escape_string($conn, $fecha_hasta) . "'";
        }
        if ($status_fil !== 'todas' && $status_fil !== '') {
            if ($status_fil === 'pagado' || $status_fil === 'aprobada') {
                $where .= " AND oc.status IN ('pagado', 'pagada', 'aprobada')";
            } else {
                $where .= " AND oc.status = 'pendiente'";
            }
        }
        $query = "SELECT oc.numero_orden, oc.fecha, oc.tipo, COALESCE(p.razon_social, '— Sin proveedor —') AS proveedor, oc.base_imponible, oc.iva_monto, oc.total_general, oc.status FROM ordenes_compra oc LEFT JOIN proveedores p ON p.id = oc.proveedor_id $where ORDER BY oc.fecha DESC, oc.id DESC";
        $res = mysqli_query($conn, $query);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $st_clean = in_array(strtolower($row['status']), array('pagado', 'pagada', 'aprobada')) ? 'PAGADO' : 'PENDIENTE';
                fputcsv($output, array(
                    $row['numero_orden'],
                    $row['fecha'],
                    $row['tipo'],
                    $row['proveedor'],
                    number_format($row['base_imponible'], 2, '.', ''),
                    number_format($row['iva_monto'], 2, '.', ''),
                    number_format($row['total_general'], 2, '.', ''),
                    $st_clean
                ), ';');
            }
        }
    } elseif ($tipo_reporte === 'op') {
        fputcsv($output, array('N° Pago', 'Fecha', 'Beneficiario', 'Monto Bruto (Bs.)', 'Retención (Bs.)', 'Monto Neto a Pagar (Bs.)', 'Status'), ';');
        $where = "WHERE op.deleted_at IS NULL";
        if ($fecha_desde !== '') {
            $where .= " AND op.fecha >= '" . mysqli_real_escape_string($conn, $fecha_desde) . "'";
        }
        if ($fecha_hasta !== '') {
            $where .= " AND op.fecha <= '" . mysqli_real_escape_string($conn, $fecha_hasta) . "'";
        }
        if ($status_fil !== 'todas' && $status_fil !== '') {
            if ($status_fil === 'pagado' || $status_fil === 'aprobada') {
                $where .= " AND op.status IN ('pagado', 'pagada', 'aprobada')";
            } else {
                $where .= " AND op.status = 'pendiente'";
            }
        }
        $query = "SELECT op.numero, op.fecha, COALESCE(p.razon_social, op.rif_beneficiario, '— Sin beneficiario —') AS beneficiario, op.monto_bruto, op.monto_retencion, op.monto_neto_pagar, op.status FROM ordenes_pago op LEFT JOIN proveedores p ON p.id = op.proveedor_id $where ORDER BY op.fecha DESC, op.id DESC";
        $res = mysqli_query($conn, $query);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $st_clean = in_array(strtolower($row['status']), array('pagado', 'pagada', 'aprobada')) ? 'PAGADO' : 'PENDIENTE';
                fputcsv($output, array(
                    $row['numero'],
                    $row['fecha'],
                    $row['beneficiario'],
                    number_format($row['monto_bruto'], 2, '.', ''),
                    number_format($row['monto_retencion'], 2, '.', ''),
                    number_format($row['monto_neto_pagar'], 2, '.', ''),
                    $st_clean
                ), ';');
            }
        }
    } elseif ($tipo_reporte === 'os') {
        fputcsv($output, array('N° OS', 'Fecha', 'Proveedor', 'Base Imponible (Bs.)', 'IVA (Bs.)', 'Monto Total (Bs.)', 'Status'), ';');
        $where = "WHERE os.deleted_at IS NULL";
        if ($fecha_desde !== '') {
            $where .= " AND os.fecha >= '" . mysqli_real_escape_string($conn, $fecha_desde) . "'";
        }
        if ($fecha_hasta !== '') {
            $where .= " AND os.fecha <= '" . mysqli_real_escape_string($conn, $fecha_hasta) . "'";
        }
        if ($status_fil !== 'todas' && $status_fil !== '') {
            if ($status_fil === 'pagado' || $status_fil === 'aprobada') {
                $where .= " AND os.status IN ('pagado', 'pagada', 'aprobada')";
            } else {
                $where .= " AND os.status = 'pendiente'";
            }
        }
        $query = "SELECT os.numero_os, os.fecha, COALESCE(p.razon_social, '— Sin proveedor —') AS proveedor, os.base_imponible, os.iva_monto, os.monto_total, os.status FROM ordenes_servicio os LEFT JOIN proveedores p ON p.id = os.proveedor_id $where ORDER BY os.fecha DESC, os.id DESC";
        $res = mysqli_query($conn, $query);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                fputcsv($output, array(
                    $row['numero_os'],
                    $row['fecha'],
                    $row['proveedor'],
                    number_format($row['base_imponible'], 2, '.', ''),
                    number_format($row['iva_monto'], 2, '.', ''),
                    number_format($row['monto_total'], 2, '.', ''),
                    $row['status']
                ), ';');
            }
        }
    }
    fclose($output);
    exit;
}

// Get search filters for normal view
$tipo_reporte = isset($_GET['tipo_reporte']) ? $_GET['tipo_reporte'] : 'oc';
$fecha_desde  = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fecha_hasta  = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$status_fil   = isset($_GET['status']) ? $_GET['status'] : 'todas';
$has_searched = isset($_GET['buscar']);

$resultados = null;
$total_registros = 0;
$monto_sum_general = 0;

if ($has_searched) {
    if ($tipo_reporte === 'oc') {
        $where = "WHERE oc.deleted_at IS NULL";
        if ($fecha_desde !== '') {
            $where .= " AND oc.fecha >= '" . mysqli_real_escape_string($conn, $fecha_desde) . "'";
        }
        if ($fecha_hasta !== '') {
            $where .= " AND oc.fecha <= '" . mysqli_real_escape_string($conn, $fecha_hasta) . "'";
        }
        if ($status_fil !== 'todas' && $status_fil !== '') {
            if ($status_fil === 'pagado' || $status_fil === 'aprobada') {
                $where .= " AND oc.status IN ('pagado', 'pagada', 'aprobada')";
            } else {
                $where .= " AND oc.status = 'pendiente'";
            }
        }
        $query = "SELECT oc.id, oc.numero_orden, oc.fecha, oc.tipo, COALESCE(p.razon_social, '— Sin proveedor —') AS proveedor, oc.base_imponible, oc.iva_monto, oc.total_general, oc.status FROM ordenes_compra oc LEFT JOIN proveedores p ON p.id = oc.proveedor_id $where ORDER BY oc.fecha DESC, oc.id DESC";
        $resultados = mysqli_query($conn, $query);
    } elseif ($tipo_reporte === 'op') {
        $where = "WHERE op.deleted_at IS NULL";
        if ($fecha_desde !== '') {
            $where .= " AND op.fecha >= '" . mysqli_real_escape_string($conn, $fecha_desde) . "'";
        }
        if ($fecha_hasta !== '') {
            $where .= " AND op.fecha <= '" . mysqli_real_escape_string($conn, $fecha_hasta) . "'";
        }
        if ($status_fil !== 'todas' && $status_fil !== '') {
            if ($status_fil === 'pagado' || $status_fil === 'aprobada') {
                $where .= " AND op.status IN ('pagado', 'pagada', 'aprobada')";
            } else {
                $where .= " AND op.status = 'pendiente'";
            }
        }
        $query = "SELECT op.id, op.numero, op.fecha, COALESCE(p.razon_social, op.rif_beneficiario, '— Sin beneficiario —') AS beneficiario, op.monto_bruto, op.monto_retencion, op.monto_neto_pagar, op.status FROM ordenes_pago op LEFT JOIN proveedores p ON p.id = op.proveedor_id $where ORDER BY op.fecha DESC, op.id DESC";
        $resultados = mysqli_query($conn, $query);
    } elseif ($tipo_reporte === 'os') {
        $where = "WHERE os.deleted_at IS NULL";
        if ($fecha_desde !== '') {
            $where .= " AND os.fecha >= '" . mysqli_real_escape_string($conn, $fecha_desde) . "'";
        }
        if ($fecha_hasta !== '') {
            $where .= " AND os.fecha <= '" . mysqli_real_escape_string($conn, $fecha_hasta) . "'";
        }
        if ($status_fil !== 'todas' && $status_fil !== '') {
            if ($status_fil === 'pagado' || $status_fil === 'aprobada') {
                $where .= " AND os.status IN ('pagado', 'pagada', 'aprobada')";
            } else {
                $where .= " AND os.status = 'pendiente'";
            }
        }
        $query = "SELECT os.id, os.numero_os, os.fecha, COALESCE(p.razon_social, '— Sin proveedor —') AS proveedor, os.base_imponible, os.iva_monto, os.monto_total, os.status FROM ordenes_servicio os LEFT JOIN proveedores p ON p.id = os.proveedor_id $where ORDER BY os.fecha DESC, os.id DESC";
        $resultados = mysqli_query($conn, $query);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Reportes – Contraloría MSR</title>
    <style>
    /* Premium typography: Geist headings, Inter body */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'Geist', sans-serif !important;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; font-size: 12px; background: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; }
        .site-header { background: #070707; color: white; display: flex; align-items: center; gap: 14px; padding: 8px 20px; border-bottom: 3px solid #4f46e5; position: sticky; top: 0; z-index: 100; }
        .site-header img { height: 52px; width: auto; }
        .site-header .brand { display: flex; flex-direction: column; gap: 2px; }
        .site-header .brand-title { font-size: 18px; font-weight: bold; letter-spacing: 0.7px; text-transform: uppercase; }
        .site-header .brand-sub { font-size: 12.5px; color: #bbdefb; }
        .site-header .header-right { margin-left: auto; text-align: right; display: flex; flex-direction: column; gap: 2px; }
        .site-header .header-right .hdr-date { font-size: 13px; font-weight: 700; color: #ffffff; }
        .site-header .header-right .hdr-sub { font-size: 10.5px; color: #94a3b8; }
        .layout { display: flex; flex: 1; }
        .main-content { flex: 1; padding: 20px; overflow-x: auto; }
        .container { background: white; border: 1px solid #cbd5e1; padding: 24px; border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .page-banner {
            background-image: url('/sistema/assets/img/banner.png');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 24px 28px;
            border-radius: 16px;
            margin-bottom: 22px;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.35);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 110px;
            position: relative;
            overflow: hidden;
        }
        .page-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(8,12,40,0.72) 0%, rgba(15,30,80,0.45) 100%);
            border-radius: inherit;
        }
        .page-banner > * { position: relative; z-index: 1; }
        .page-title { font-size: 20px; font-weight: 800; color: #ffffff; margin-bottom: 4px; letter-spacing: 0.5px; }
        .page-sub { font-size: 12px; color: #94a3b8; margin: 0; }

        /* Filter Form */
        .filter-form { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap: 12px; align-items: end; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-family: 'Inter', sans-serif; background: #fff; color: #1e293b; transition: all 0.15s ease; }
        .filter-group input:focus, .filter-group select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
        .btn-filter { background: #101A36; color: white; border: none; padding: 9px 20px; font-size: 12px; font-weight: 700; border-radius: 6px; cursor: pointer; transition: all 0.15s ease; height: 36px; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15); }
        .btn-filter:hover { background: #1e293b; transform: translateY(-1px); }

        /* Action buttons bar */
        .actions-bar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; justify-content: space-between; background: #f1f5f9; padding: 10px 14px; border-radius: 6px; border: 1px solid #cbd5e1; }
        .actions-bar .left-info { font-size: 12px; font-weight: 600; color: #1e293b; }
        .btn-export-pdf { background: #ef4444; color: white; border: none; padding: 7px 14px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: background 0.15s ease; }
        .btn-export-pdf:hover { background: #dc2626; }
        .btn-export-excel { background: #10b981; color: white; border: none; padding: 7px 14px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: background 0.15s ease; }
        .btn-export-excel:hover { background: #059669; }

        /* Results Table */
        .table-responsive { width: 100%; overflow-x: auto; border-radius: 6px; border: 1px solid #cbd5e1; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table th { background: #1e293b; color: #f8fafc; border-bottom: 2px solid #334155; padding: 10px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        table td { border-bottom: 1px solid #e2e8f0; padding: 9px 10px; vertical-align: middle; color: #1e293b; }
        table tr:nth-child(even) td { background: #f8fafc; }
        table tr:hover td { background: #f1f5f9; }
        .badge { display: inline-block; padding: 3px 8px; font-size: 10px; font-weight: 700; border-radius: 4px; text-transform: uppercase; }
        .badge-pendiente { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .badge-aprobada, .badge-pagada { background: #d1fae5; color: #059669; border: 1px solid #6ee7b7; }
        .badge-pagado { background: #d1fae5; color: #059669; border: 1px solid #6ee7b7; }
        .badge-rechazado, .badge-anulada { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .badge-farmacia { background: #d1fae5; color: #047857; border: 1px solid #6ee7b7; font-size: 9.5px; }
        .badge-normal { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; font-size: 9.5px; }

        .sin-datos { text-align: center; color: #94a3b8; padding: 40px; font-style: italic; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 12px; }
        .print-header { display: none; }

        .site-footer { background: #070707; color: #90a4ae; text-align: center; padding: 12px 20px; font-size: 10.5px; border-top: 3px solid #4f46e5; margin-top: auto; }

        /* Print styles */
        @media print {
            body { background: white !important; font-size: 11pt !important; }
            .site-header, .layout > nav, .filter-form, .actions-bar, .site-footer, .page-banner { display: none !important; }
            .layout { display: block !important; }
            .main-content { padding: 0 !important; margin: 0 !important; width: 100% !important; }
            .container { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
            .print-header h2 { font-size: 16pt; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
            .print-header p { font-size: 10pt; color: #333; line-height: 1.4; }
            table { width: 100% !important; border-collapse: collapse !important; font-size: 10pt !important; }
            table th { background: #222 !important; color: white !important; border: 1px solid #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table td { border: 1px solid #666 !important; padding: 6px !important; }
        }
    </style>
</head>
<body>

<div class="layout">
    <?php $active = 'reportes'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">

            <div class="print-header">
                <img src="/sistema/assets/img/logo.png" alt="Logo" style="height:50px; margin-bottom:8px;">
                <p>REPÚBLICA BOLIVARIANA DE VENEZUELA · ESTADO ANZOÁTEGUI<br>
                <strong>CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</strong></p>
                <h2>
                    <?php
                    $tipo_tit = 'Órdenes de Compra';
                    if ($tipo_reporte === 'op') { $tipo_tit = 'Órdenes de Pago'; }
                    elseif ($tipo_reporte === 'os') { $tipo_tit = 'Órdenes de Servicio'; }
                    echo 'Reporte de ' . $tipo_tit;
                    ?>
                </h2>
                <p>
                    Período: <strong><?php echo ($fecha_desde !== '') ? date('d/m/Y', strtotime($fecha_desde)) : 'Inicio'; ?></strong> al <strong><?php echo ($fecha_hasta !== '') ? date('d/m/Y', strtotime($fecha_hasta)) : 'Actual'; ?></strong>
                    &nbsp;|&nbsp; Status: <strong><?php echo htmlspecialchars(ucfirst($status_fil)); ?></strong>
                    &nbsp;|&nbsp; Generado: <?php echo date('d/m/Y H:i'); ?>
                </p>
            </div>

            <div class="page-banner">
                <h1 class="page-title">Módulo de Reportes y Consultas</h1>
                <p class="page-sub">Filtre, consulte, imprima o exporte los registros del sistema según sus criterios.</p>
            </div>

            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="tipo_reporte">Tipo de Reporte</label>
                    <select name="tipo_reporte" id="tipo_reporte">
                        <option value="oc" <?php echo ($tipo_reporte === 'oc') ? 'selected' : ''; ?>>Órdenes de Compra</option>
                        <option value="op" <?php echo ($tipo_reporte === 'op') ? 'selected' : ''; ?>>Órdenes de Pago</option>
                        <option value="os" <?php echo ($tipo_reporte === 'os') ? 'selected' : ''; ?>>Órdenes de Servicio</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="fecha_desde">Fecha Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>

                <div class="filter-group">
                    <label for="fecha_hasta">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>

                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="todas" <?php echo ($status_fil === 'todas') ? 'selected' : ''; ?>>Todas</option>
                        <option value="pendiente" <?php echo ($status_fil === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="pagado" <?php echo ($status_fil === 'pagado' || $status_fil === 'aprobada') ? 'selected' : ''; ?>>Pagado</option>
                    </select>
                </div>

                <button type="submit" name="buscar" value="1" class="btn-filter">Generar Reporte</button>
            </form>

            <?php if (!$has_searched): ?>
                <div class="sin-datos">
                    Por favor configure los filtros y haga clic en <strong>"Generar Reporte"</strong> para visualizar los resultados.
                </div>
            <?php else: ?>
                <?php
                $num_rows = $resultados ? mysqli_num_rows($resultados) : 0;
                ?>
                <div class="actions-bar">
                    <div class="left-info">
                        Registros encontrados: <strong><?php echo $num_rows; ?></strong>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn-export-pdf" onclick="window.print();">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Imprimir PDF
                        </button>
                        <a href="?tipo_reporte=<?php echo urlencode($tipo_reporte); ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>&status=<?php echo urlencode($status_fil); ?>&buscar=1&export=excel" class="btn-export-excel">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            Exportar Excel (CSV)
                        </a>
                    </div>
                </div>

                <?php if ($num_rows === 0): ?>
                    <div class="sin-datos">No se encontraron registros con los filtros especificados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <?php if ($tipo_reporte === 'oc'): ?>
                            <table>
                               <thead>
                                   <tr>
                                       <th style="width:10%;">N° Orden</th>
                                       <th style="width:11%;">Fecha</th>
                                       <th style="width:9%;">Tipo</th>
                                       <th style="width:30%;">Proveedor</th>
                                       <th style="width:13%;">Base Imponible</th>
                                       <th style="width:11%;">IVA</th>
                                       <th style="width:13%;">Total General</th>
                                       <th style="width:7%;">Status</th>
                                   </tr>
                               </thead>
                               <tbody>
                                   <?php while ($row = mysqli_fetch_assoc($resultados)): ?>
                                       <?php
                                       $st = isset($row['status']) ? $row['status'] : 'pendiente';
                                       $st_clean = in_array(strtolower($st), array('pagado', 'pagada', 'aprobada')) ? 'PAGADO' : 'PENDIENTE';
                                       $badge = ($st_clean === 'PAGADO') ? 'badge-pagado' : 'badge-pendiente';

                                       $t_oc = isset($row['tipo']) ? $row['tipo'] : 'normal';
                                       $f_fmt = '';
                                       if (isset($row['fecha']) && $row['fecha'] != '') {
                                           $ts = strtotime($row['fecha']);
                                           $f_fmt = $ts ? date('d/m/Y', $ts) : htmlspecialchars($row['fecha']);
                                       }
                                       $monto_sum_general += isset($row['total_general']) ? (float)$row['total_general'] : 0;
                                       ?>
                                       <tr>
                                           <td style="text-align:center; font-weight:bold;"><?php echo htmlspecialchars(isset($row['numero_orden']) ? $row['numero_orden'] : $row['id']); ?></td>
                                           <td style="text-align:center;"><?php echo $f_fmt; ?></td>
                                           <td style="text-align:center;">
                                               <?php if ($t_oc === 'farmacia'): ?>
                                                   <span class="badge badge-farmacia">🏥 Farmacia</span>
                                               <?php else: ?>
                                                   <span class="badge badge-normal">Normal</span>
                                               <?php endif; ?>
                                           </td>
                                           <td><?php echo htmlspecialchars(isset($row['proveedor']) ? $row['proveedor'] : ''); ?></td>
                                           <td style="text-align:right;">Bs. <?php echo number_format(isset($row['base_imponible']) ? (float)$row['base_imponible'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:right;">Bs. <?php echo number_format(isset($row['iva_monto']) ? (float)$row['iva_monto'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:right; font-weight:bold;">Bs. <?php echo number_format(isset($row['total_general']) ? (float)$row['total_general'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:center;"><span class="badge <?php echo $badge; ?>"><?php echo $st_clean; ?></span></td>
                                       </tr>
                                   <?php endwhile; ?>
                               </tbody>
                               <tfoot>
                                   <tr>
                                       <td colspan="6" style="text-align:right; font-weight:bold; background:#e2e8f0;">MONTO TOTAL GENERAL:</td>
                                       <td style="text-align:right; font-weight:bold; background:#e2e8f0;">Bs. <?php echo number_format($monto_sum_general, 2, '.', ','); ?></td>
                                       <td style="background:#e2e8f0;"></td>
                                   </tr>
                               </tfoot>
                            </table>
                        <?php elseif ($tipo_reporte === 'op'): ?>
                            <table>
                               <thead>
                                   <tr>
                                       <th style="width:12%;">N° Pago</th>
                                       <th style="width:12%;">Fecha</th>
                                       <th style="width:34%;">Beneficiario</th>
                                       <th style="width:14%;">Monto Bruto</th>
                                       <th style="width:12%;">Retención</th>
                                       <th style="width:16%;">Monto Neto Pagado</th>
                                       <th style="width:10%;">Status</th>
                                   </tr>
                               </thead>
                               <tbody>
                                   <?php while ($row = mysqli_fetch_assoc($resultados)): ?>
                                       <?php
                                       $st = isset($row['status']) ? $row['status'] : 'pendiente';
                                       $st_clean = in_array(strtolower($row['status']), array('pagado', 'pagada', 'aprobada')) ? 'PAGADO' : 'PENDIENTE';
                                       $badge = ($st_clean === 'PAGADO') ? 'badge-pagado' : 'badge-pendiente';

                                       $f_fmt = '';
                                       if (isset($row['fecha']) && $row['fecha'] != '') {
                                           $ts = strtotime($row['fecha']);
                                           $f_fmt = $ts ? date('d/m/Y', $ts) : htmlspecialchars($row['fecha']);
                                       }
                                       $monto_sum_general += isset($row['monto_neto_pagar']) ? (float)$row['monto_neto_pagar'] : 0;
                                       ?>
                                       <tr>
                                           <td style="text-align:center; font-weight:bold;"><?php echo htmlspecialchars(isset($row['numero']) && $row['numero'] != '' ? $row['numero'] : $row['id']); ?></td>
                                           <td style="text-align:center;"><?php echo $f_fmt; ?></td>
                                           <td><?php echo htmlspecialchars(isset($row['beneficiario']) ? $row['beneficiario'] : ''); ?></td>
                                           <td style="text-align:right;">Bs. <?php echo number_format(isset($row['monto_bruto']) ? (float)$row['monto_bruto'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:right;">Bs. <?php echo number_format(isset($row['monto_retencion']) ? (float)$row['monto_retencion'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:right; font-weight:bold;">Bs. <?php echo number_format(isset($row['monto_neto_pagar']) ? (float)$row['monto_neto_pagar'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:center;"><span class="badge <?php echo $badge; ?>"><?php echo $st_clean; ?></span></td>
                                       </tr>
                                   <?php endwhile; ?>
                               </tbody>
                               <tfoot>
                                   <tr>
                                       <td colspan="5" style="text-align:right; font-weight:bold; background:#e2e8f0;">MONTO TOTAL NETO PAGADO:</td>
                                       <td style="text-align:right; font-weight:bold; background:#e2e8f0;">Bs. <?php echo number_format($monto_sum_general, 2, '.', ','); ?></td>
                                       <td style="background:#e2e8f0;"></td>
                                   </tr>
                               </tfoot>
                            </table>
                        <?php elseif ($tipo_reporte === 'os'): ?>
                            <table>
                               <thead>
                                   <tr>
                                       <th style="width:11%;">N° OS</th>
                                       <th style="width:12%;">Fecha</th>
                                       <th style="width:33%;">Proveedor</th>
                                       <th style="width:13%;">Base Imponible</th>
                                       <th style="width:11%;">IVA</th>
                                       <th style="width:13%;">Monto Total</th>
                                       <th style="width:10%;">Status</th>
                                   </tr>
                               </thead>
                               <tbody>
                                   <?php while ($row = mysqli_fetch_assoc($resultados)): ?>
                                       <?php
                                       $st = isset($row['status']) ? $row['status'] : 'pendiente';
                                       $st_clean = in_array(strtolower($row['status']), array('pagado', 'pagada', 'aprobada')) ? 'PAGADO' : 'PENDIENTE';
                                       $badge = ($st_clean === 'PAGADO') ? 'badge-pagado' : 'badge-pendiente';

                                       $f_fmt = '';
                                       if (isset($row['fecha']) && $row['fecha'] != '') {
                                           $ts = strtotime($row['fecha']);
                                           $f_fmt = $ts ? date('d/m/Y', $ts) : htmlspecialchars($row['fecha']);
                                       }
                                       $monto_sum_general += isset($row['monto_total']) ? (float)$row['monto_total'] : 0;
                                       ?>
                                       <tr>
                                           <td style="text-align:center; font-weight:bold;"><?php echo htmlspecialchars(isset($row['numero_os']) ? $row['numero_os'] : $row['id']); ?></td>
                                           <td style="text-align:center;"><?php echo $f_fmt; ?></td>
                                           <td><?php echo htmlspecialchars(isset($row['proveedor']) ? $row['proveedor'] : ''); ?></td>
                                           <td style="text-align:right;">Bs. <?php echo number_format(isset($row['base_imponible']) ? (float)$row['base_imponible'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:right;">Bs. <?php echo number_format(isset($row['iva_monto']) ? (float)$row['iva_monto'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:right; font-weight:bold;">Bs. <?php echo number_format(isset($row['monto_total']) ? (float)$row['monto_total'] : 0, 2, '.', ','); ?></td>
                                           <td style="text-align:center;"><span class="badge <?php echo $badge; ?>"><?php echo $st_clean; ?></span></td>
                                       </tr>
                                   <?php endwhile; ?>
                               </tbody>
                               <tfoot>
                                   <tr>
                                       <td colspan="5" style="text-align:right; font-weight:bold; background:#e2e8f0;">MONTO TOTAL GENERAL:</td>
                                       <td style="text-align:right; font-weight:bold; background:#e2e8f0;">Bs. <?php echo number_format($monto_sum_general, 2, '.', ','); ?></td>
                                       <td style="background:#e2e8f0;"></td>
                                   </tr>
                               </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>
</div><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>
</html>