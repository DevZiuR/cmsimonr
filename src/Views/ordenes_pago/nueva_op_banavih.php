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

$meses_es = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$mes_num_actual = (int) date('n');
$anio_actual = date('Y');
$fecha_hoy = date('d/m/Y');

$op_edit = null;
$retenciones_edit = [];
$contabilidad_edit = [];
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$pagado_banco_val = '';
$pagado_cuenta_val = '';
$pagado_transf_val = '';

if ($edit_id > 0) {
    $res_edit = mysqli_query($conn, "SELECT op.*, uc.nombre AS creado_por_nombre, uu.nombre AS actualizado_por_nombre
                                     FROM ordenes_pago op
                                     LEFT JOIN usuarios uc ON uc.nombre = op.created_by OR uc.usuario = op.created_by
                                     LEFT JOIN usuarios uu ON uu.nombre = op.updated_by OR uu.usuario = op.updated_by
                                     WHERE op.id = $edit_id AND op.deleted_at IS NULL");
    if ($res_edit && mysqli_num_rows($res_edit) > 0) {
        $op_edit = mysqli_fetch_assoc($res_edit);

        // Decode pagado details
        if (!empty($op_edit['recibe_cedula'])) {
            $dec = json_decode($op_edit['recibe_cedula'], true);
            if (is_array($dec)) {
                $pagado_banco_val = isset($dec['banco']) ? $dec['banco'] : '';
                $pagado_cuenta_val = isset($dec['cuenta']) ? $dec['cuenta'] : '';
                $pagado_transf_val = isset($dec['transf']) ? $dec['transf'] : '';
            } else {
                $pagado_transf_val = $op_edit['recibe_cedula'];
            }
        }

        // Load retenciones
        $res_ret = mysqli_query($conn, "SELECT * FROM op_retenciones WHERE op_id = $edit_id ORDER BY id ASC");
        while ($r = mysqli_fetch_assoc($res_ret)) {
            $retenciones_edit[] = $r;
        }

        // Load contabilidad
        $cont_json_str = isset($op_edit['cont_json']) ? $op_edit['cont_json'] : '[]';
        $contabilidad_edit = json_decode($cont_json_str, true);
        if (!is_array($contabilidad_edit)) {
            $contabilidad_edit = json_decode(stripslashes($cont_json_str), true);
        }
        if (!is_array($contabilidad_edit)) {
            $contabilidad_edit = [];
        }
    }
}
?><!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Nueva Orden de Pago BANAVIH — Contraloría del Municipio Simón Rodríguez">
    <title>Nueva OP BANAVIH — Contraloría MSR</title>
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
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
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

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

        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            background: white;
            padding: 15px;
            border: 1px solid #ccc;
            width: 100%;
            max-width: 980px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
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

        /* Panel helper */
        .panel-banavih {
            border: 1px solid #1565c0;
            background: #f0f6ff;
            padding: 12px 16px;
            margin-bottom: 14px;
            border-radius: 4px;
        }

        .panel-banavih .pb-title {
            font-size: 11px;
            font-weight: bold;
            color: #1565c0;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .panel-banavih .pb-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
        }

        .panel-banavih label {
            font-size: 10px;
            font-weight: bold;
            display: block;
            margin-bottom: 3px;
            color: #333;
        }

        .panel-banavih select,
        .panel-banavih input {
            border: 1px solid #1565c0;
            padding: 4px 8px;
            font-size: 11px;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: #fff;
            text-transform: uppercase;
        }

        /* Document */
        .op-document {
            border: 2px solid #000;
            background: #fff;
            font-family: 'Inter', sans-serif;
            color: #000;
            width: 100%;
            box-sizing: border-box;
        }

        .op-header-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            border-bottom: 1.5px solid #000;
        }

        .op-logo-left img,
        .op-logo-right img {
            height: 70px;
            width: auto;
            display: block;
        }

        .op-header-center {
            text-align: center;
            line-height: 1.3;
        }

        .op-header-center .op-rep {
            font-weight: bold;
            font-size: 11.5px;
            text-transform: uppercase;
        }

        .op-header-center .op-est {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .op-header-center .op-ent {
            font-weight: 800;
            font-size: 12px;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .op-row-title {
            display: flex;
            border-bottom: 1.5px solid #000;
        }

        .op-title-cell {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1.5px solid #000;
            padding: 6px;
        }

        .op-title-cell h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin: 0;
        }

        .op-num-fecha-cell {
            width: 270px;
            display: flex;
            flex-direction: column;
        }

        .op-nf-row {
            display: flex;
            border-bottom: 1px solid #000;
            height: 24px;
            align-items: center;
        }

        .op-nf-row:last-child {
            border-bottom: none;
        }

        .op-nf-row label {
            width: 85px;
            font-weight: bold;
            font-size: 11px;
            padding-left: 8px;
            border-right: 1.5px solid #000;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .op-nf-row input {
            flex: 1;
            border: none;
            padding: 2px 6px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        /* Beneficiarios */
        .op-row-benef-lbl {
            display: flex;
            border-bottom: 1px solid #000;
            background: #d2d7f4 !important;
            font-weight: bold;
            font-size: 10px;
        }

        .op-cell-benef-lbl {
            flex: 1;
            padding: 3px 8px;
            border-right: 1px solid #000;
        }

        .op-cell-nit-lbl {
            width: 90px;
            padding: 3px 8px;
            border-right: 1px solid #000;
            text-align: center;
        }

        .op-cell-rif-lbl {
            width: 220px;
            padding: 3px 8px;
            text-align: left;
        }

        .op-row-benef-val {
            display: flex;
            border-bottom: 1px solid #000;
            min-height: 24px;
            align-items: center;
        }

        .op-cell-benef-val {
            flex: 1;
            border-right: 1px solid #000;
            padding: 2px 8px;
            font-weight: bold;
            font-size: 11.5px;
            text-transform: uppercase;
        }

        .op-cell-nit-val {
            width: 90px;
            border-right: 1px solid #000;
            min-height: 24px;
        }

        .op-cell-rif-val {
            width: 220px;
            padding: 2px 8px;
            font-weight: bold;
            font-size: 11.5px;
        }

        /* Letras / Concepto */
        .op-row-cant-lbl {
            border-bottom: 1px solid #000;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 10px;
            background: #d2d7f4 !important;
        }

        .op-row-cant-val {
            border-bottom: 1.5px solid #000;
            padding: 6px 8px;
            font-size: 11.5px;
            font-weight: bold;
            font-style: italic;
            min-height: 30px;
            display: flex;
            align-items: center;
        }

        .op-row-concepto-lbl {
            border-bottom: 1px solid #000;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
            background: #d2d7f4 !important;
        }

        .op-row-concepto {
            border-bottom: 1.5px solid #000;
            padding: 10px 12px;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .op-row-concepto textarea {
            width: 100%;
            border: none;
            outline: none;
            resize: none;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.5;
            text-transform: uppercase;
            text-align: center;
            background: transparent;
            box-sizing: border-box;
        }

        /* Banco */
        .op-row-banco {
            display: flex;
            border-bottom: 1.5px solid #000;
            align-items: center;
            min-height: 32px;
        }

        .op-cell-banco {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border-right: 1px solid #000;
            font-size: 11px;
            font-weight: bold;
        }

        .op-cell-banco label {
            margin-right: 6px;
            white-space: nowrap;
        }

        .op-cell-banco select {
            flex: 1;
            border: none;
            font-size: 11px;
            font-weight: bold;
            font-family: 'Inter', sans-serif;
            text-transform: uppercase;
            outline: none;
            background: transparent;
            cursor: pointer;
        }

        .op-cell-cuenta {
            flex: 1.2;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border-right: 1px solid #000;
            font-size: 11px;
            font-weight: bold;
        }

        .op-cell-cuenta label {
            margin-right: 6px;
            white-space: nowrap;
        }

        .op-cell-cuenta input {
            flex: 1;
            border: none;
            font-size: 11px;
            font-weight: bold;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        .op-cell-monto {
            width: 270px;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: bold;
        }

        .op-cell-monto label {
            margin-right: 8px;
            white-space: nowrap;
        }

        .monto-bs-box {
            flex: 1;
            border: 2px solid #000;
            padding: 2px 6px;
            background: #fff;
        }

        .monto-bs-box input {
            width: 100%;
            border: none;
            text-align: right;
            font-size: 12px;
            font-weight: 800;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        /* Retenciones */
        .op-table-ret {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .op-table-ret th {
            border-bottom: 1.5px solid #000;
            border-right: 1px solid #000;
            padding: 4px 6px;
            font-weight: bold;
            text-align: left;
            font-size: 10.5px;
            background: #fff;
        }

        .op-table-ret th:last-child {
            border-right: none;
        }

        .op-table-ret td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 3px 6px;
        }

        .op-table-ret td:last-child {
            border-right: none;
        }

        .op-table-ret input {
            width: 100%;
            border: none;
            font-size: 11px;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        .op-ret-totals {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid #000;
            padding: 4px 8px;
        }

        .op-ret-totals-right {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
            margin-left: auto;
        }

        .op-ret-tot-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: bold;
        }

        .op-ret-tot-row .val-box {
            border: 1.5px solid #000;
            padding: 2px 8px;
            min-width: 130px;
            text-align: right;
            font-weight: bold;
        }

        .op-ret-tot-row .val-box-bold {
            border: 2px solid #000;
            padding: 2px 8px;
            min-width: 130px;
            text-align: right;
            font-weight: 800;
            font-size: 12px;
        }

        /* Contabilidad */
        .op-cont-header-bar {
            border-bottom: 1.5px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.5px;
            background: #d2d7f4 !important;
        }

        .op-cont-total-box {
            border: 2px solid #000;
            padding: 2px 8px;
            min-width: 110px;
            text-align: right;
            font-weight: 800;
            font-size: 12px;
            font-style: italic;
            background: #fff;
            color: #000;
        }

        .op-table-cont {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .op-table-cont th {
            border-bottom: 1.5px solid #000;
            border-right: 1px solid #000;
            padding: 3px 2px;
            text-align: center;
            font-weight: bold;
            font-size: 9.5px;
            line-height: 1.1;
        }

        .op-table-cont th:last-child {
            border-right: none;
        }

        .op-table-cont td {
            border-bottom: 1px solid #000;
            border-right: 1px solid #000;
            padding: 2px;
            text-align: center;
        }

        .op-table-cont td:last-child {
            border-right: none;
        }

        .op-table-cont input {
            width: 100%;
            border: none;
            text-align: center;
            font-size: 10px;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        /* Unified Firmas & Pagado Grid */
        .op-unified-bottom-grid {
            display: flex;
            flex-direction: column;
            border-top: 1.5px solid #000;
        }

        .op-grid-row {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr 1.5fr 1.2fr;
            border-bottom: 1px solid #000;
        }

        .op-grid-row:last-child {
            border-bottom: none;
        }

        .op-grid-row>div {
            border-right: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        .op-grid-row>div:last-child {
            border-right: none;
        }

        /* Row 1: Headers (Purple) */
        .op-row-hdr-purple {
            background: #d2d7f4 !important;
            font-weight: bold;
            font-size: 9.5px;
            text-align: center;
            line-height: 1.2;
            text-transform: uppercase;
            padding: 5px 4px;
        }

        /* Row 2: Signature Space */
        .op-row-sig-body {
            min-height: 48px;
            background: #fff;
        }

        .op-row-sig-body>div {
            flex-direction: column;
            justify-content: flex-end;
            padding-bottom: 3px;
            font-size: 9px;
            font-weight: bold;
            color: #222;
            text-transform: uppercase;
        }

        /* Row 3: Names (Purple) */
        .op-row-names-purple {
            background: #d2d7f4 !important;
            font-weight: bold;
            font-size: 10px;
            text-align: center;
            text-transform: uppercase;
            padding: 4px;
        }

        /* Row 4: Pagado Header (Purple) */
        .op-row-pagado-purple {
            background: #d2d7f4 !important;
            font-weight: bold;
            font-size: 9.5px;
            text-align: center;
            text-transform: uppercase;
            padding: 4px;
        }

        /* Row 5: Pagado Data Row */
        .op-row-pagado-data {
            min-height: 36px;
            background: #fff;
        }

        .op-row-pagado-data>div {
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
        }

        .op-row-pagado-data input,
        .op-row-pagado-data select {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            font-family: 'Inter', sans-serif;
            text-transform: uppercase;
        }

        parent;
        }

        /* Buttons */
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

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        @media print {
            @page {
                size: letter portrait;
                margin: 8mm;
            }

            body {
                background: white !important;
                color: #000 !important;
                min-height: 100vh !important;
            }

            .no-print,
            .site-header,
            .site-footer,
            .sidebar,
            .btns,
            .btn-add {
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

            .op-document {
                border: 2px solid #000 !important;
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
                appearance: none !important;
                color: #000 !important;
                font-family: 'Inter', sans-serif !important;
            }
        }
    </style>
</head>

<body>
    <div class="layout">
        <?php $active = 'op-banavih';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="container">

                <!-- Panel BANAVIH helper -->
                <div class="panel-banavih no-print">
                    <div class="pb-title">&#127970; BANAVIH &mdash; Configurar datos del pago</div>
                    <div class="pb-grid">
                        <div>
                            <label>MES</label>
                            <select id="arr-mes" onchange="buildConcepto()" style="width:120px;">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $meses_es[$m]; ?>" <?php echo $m == $mes_num_actual ? ' selected' : ''; ?>><?php echo $meses_es[$m]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label>A&Ntilde;O</label>
                            <input type="number" id="arr-anio" value="<?php echo $anio_actual; ?>" min="2020" max="2099"
                                onchange="buildConcepto()" style="width:70px;">
                        </div>
                        <div>
                            <label>N&deg; MEMORANDUM</label>
                            <input type="text" id="arr-memo-num" value="" oninput="buildConcepto()" style="width:130px;"
                                placeholder="CM-000-2026">
                        </div>
                        <div>
                            <label>FECHA MEMORANDUM</label>
                            <input type="text" id="arr-memo-fecha" value="<?php echo $fecha_hoy; ?>"
                                oninput="buildConcepto()" placeholder="DD/MM/AAAA" style="width:100px;">
                        </div>
                        <div>
                            <label>CUENTA DESTINO BANAVIH</label>
                            <input type="text" id="arr-cuenta-banavih" value="0102-0509-31-0000072075"
                                oninput="buildConcepto()" style="width:175px;" placeholder="0000-0000-00-0000000000">
                        </div>
                    </div>
                </div>

                <form method="POST" action="../../controllers/ordenes_pago/guardar_op_banavih.php" id="form-op">
                    <?php if ($op_edit): ?>
                        <input type="hidden" name="op_id" value="<?php echo (int) $op_edit['id']; ?>">
                    <?php endif; ?>
                    <div class="op-document">

                        <!-- ENCABEZADO -->
                        <div class="op-header-grid">
                            <div class="op-logo-left"><img src="/sistema/assets/img/logo.png" alt="Logo"></div>
                            <div class="op-header-center">
                                <p class="op-rep">REP&Uacute;BLICA BOLIVARIANA DE VENEZUELA</p>
                                <p class="op-est">ESTADO ANZOA&Atilde;TEGUI</p>
                                <p class="op-ent">CONTRALOR&Iacute;A DEL MUNICIPIO SIM&Oacute;N RODR&Iacute;GUEZ</p>
                            </div>
                            <div class="op-logo-right"><img src="/sistema/assets/img/sncf.png" alt="SN CF"></div>
                        </div>

                        <!-- TÍTULO + NÚMERO/FECHA -->
                        <div class="op-row-title">
                            <div class="op-title-cell">
                                <h2>ORDEN DE PAGO</h2>
                            </div>
                            <div class="op-num-fecha-cell">
                                <div class="op-nf-row">
                                    <label>NUMERO:</label>
                                    <input type="text" name="numero_op" id="numero_op" required placeholder="000"
                                        value="<?php echo htmlspecialchars($op_edit ? $op_edit['numero'] : ''); ?>">
                                </div>
                                <div class="op-nf-row">
                                    <label>FECHA:</label>
                                    <input type="date" name="fecha" id="fecha_op"
                                        value="<?php echo htmlspecialchars($op_edit ? $op_edit['fecha'] : date('Y-m-d')); ?>"
                                        required>
                                </div>
                            </div>
                        </div>

                        <!-- BENEFICIARIOS -->
                        <div class="op-row-benef-lbl">
                            <div class="op-cell-benef-lbl">BENEFICIARIO DE LA ORDEN</div>
                            <div class="op-cell-nit-lbl">NIT</div>
                            <div class="op-cell-rif-lbl">RIF.No.</div>
                        </div>
                        <div class="op-row-benef-val">
                            <div class="op-cell-benef-val">CONTRALOR&Iacute;A MUNICIPAL</div>
                            <div class="op-cell-nit-val"></div>
                            <div class="op-cell-rif-val">G-20005555-3</div>
                        </div>
                        <div class="op-row-benef-val" style="border-bottom:1.5px solid #000;">
                            <div class="op-cell-benef-val">BANAVIH</div>
                            <div class="op-cell-nit-val"></div>
                            <div class="op-cell-rif-val">G-20000085-6</div>
                        </div>

                        <!-- CANTIDAD EN LETRAS -->
                        <div class="op-row-cant-lbl">POR LA CANTIDAD DE :</div>
                        <div class="op-row-cant-val"><span id="monto-letras-txt">&mdash;</span></div>

                        <!-- CONCEPTO -->
                        <div class="op-row-concepto-lbl">CONCEPTO</div>
                        <div class="op-row-concepto">
                            <textarea name="concepto" id="concepto" class="auto-expand" rows="3"
                                style="text-transform:uppercase;"
                                oninput="this.value=this.value.toUpperCase();"><?php echo htmlspecialchars($op_edit ? $op_edit['concepto'] : ''); ?></textarea>
                        </div>

                        <!-- BANCO / CUENTA / MONTO -->
                        <div class="op-row-banco">
                            <div class="op-cell-banco">
                                <label>BANCO:</label>
                                <select name="banco" id="banco">
                                    <?php
                                    $bancos = ['BANCO DE VENEZUELA', 'BANESCO', 'MERCANTIL', 'BANCO BBVA PROVINCIAL', 'BANCO NACIONAL DE CRÉDITO', 'BANCAMIGA', 'BANCO DEL TESORO', 'BANCO BICENTENARIO', 'BANCO FONDO COMÚN', 'VENEZOLANO DE CRÉDITO', 'BANFANB'];
                                    $selected_banco = $op_edit ? $op_edit['banco'] : 'BANCO DE VENEZUELA';
                                    foreach ($bancos as $b):
                                        $sel = ($selected_banco === $b) ? 'selected' : '';
                                        ?>
                                        <option value="<?php echo $b; ?>" <?php echo $sel; ?>><?php echo $b; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="op-cell-cuenta">
                                <label>N&deg; DE CUENTA CTE</label>
                                <input type="text" name="numero_cuenta" id="numero_cuenta"
                                    placeholder="0102-0509-31-0000072355"
                                    value="<?php echo htmlspecialchars($op_edit ? $op_edit['numero_cuenta'] : ''); ?>"
                                    oninput="fmtCuenta(this)">
                            </div>
                            <div class="op-cell-monto">
                                <label>MONTO BS.</label>
                                <div class="monto-bs-box"><input type="text" id="monto-bs" placeholder="0.00"
                                        value="<?php echo htmlspecialchars($op_edit ? number_format($op_edit['monto_neto_pagar'], 2, '.', ',') : '0.00'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- RETENCIONES BANAVIH (monto directo) -->
                        <table class="op-table-ret" id="tabla-ret">
                            <thead>
                                <tr>
                                    <th style="width:42%">DESCRIPCI&Oacute;N</th>
                                    <th style="width:26%">C&Oacute;DIGO PRESUPUESTARIO</th>
                                    <th style="width:12%; text-align:right; padding-right:8px;">MONTO RETENCI&Oacute;N
                                    </th>
                                    <th style="width:30px" class="no-print"></th>
                                </tr>
                            </thead>
                            <tbody id="ret-body"></tbody>
                        </table>
                        <div class="op-ret-totals">
                            <div><button type="button" class="btn-add no-print" onclick="addRetRow()">+ Agregar</button>
                            </div>
                            <div class="op-ret-totals-right">
                                <div class="op-ret-tot-row">
                                    <span>TOTAL RETENCIONES Bs.</span>
                                    <div class="val-box"><span
                                            id="total-retenciones"><?php echo htmlspecialchars($op_edit ? number_format($op_edit['monto_retencion'], 2, '.', ',') : '0.00'); ?></span>
                                    </div>
                                </div>
                                <div class="op-ret-tot-row">
                                    <span>MONTO NETO A PAGAR Bs.</span>
                                    <div class="val-box-bold"><span
                                            id="monto-neto"><?php echo htmlspecialchars($op_edit ? number_format($op_edit['monto_neto_pagar'], 2, '.', ',') : '0.00'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CONTABILIDAD PRESUPUESTARIA -->
                        <div class="op-cont-header-bar">CONTABILIDAD PRESUPUESTARIA</div>
                        <table class="op-table-cont" id="tabla-cont">
                            <thead>
                                <tr>
                                    <th>A&Ntilde;O</th>
                                    <th>$ECTOR</th>
                                    <th>PROG.</th>
                                    <th>SUB-PROG.</th>
                                    <th>PROY.</th>
                                    <th>ACTI.</th>
                                    <th>OBRA</th>
                                    <th>PARTID.</th>
                                    <th>GEN</th>
                                    <th>ESPEC.</th>
                                    <th>SUB ESP.</th>
                                    <th style="width:24%;padding:4px 6px;">
                                        <div
                                            style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                                            <span style="font-weight:bold;font-size:11px;white-space:nowrap;">TOTAL
                                                Bs.</span>
                                            <div class="op-cont-total-box"><span
                                                    id="cont-total-header"><?php echo htmlspecialchars($op_edit ? number_format($op_edit['monto_bruto'], 2, '.', ',') : '0.00'); ?></span>
                                            </div>
                                        </div>
                                    </th>
                                    <th style="width:30px" class="no-print"></th>
                                </tr>
                            </thead>
                            <tbody id="cont-body"></tbody>
                        </table>
                        <div style="padding:4px 8px;border-bottom:1.5px solid #000;" class="no-print">
                            <button type="button" class="btn-add" onclick="addContRow()">+ Agregar fila</button>
                        </div>

                        <!-- UNIFIED FIRMAS & PAGADO SECTION (EXACT DESIGN MATCH) -->
                        <div class="op-unified-bottom-grid">
                            <!-- Row 1: Headers (Purple) -->
                            <div class="op-grid-row op-row-hdr-purple">
                                <div>DISPONIBILIDAD FINANCIERA</div>
                                <div>IMPUTACI&Oacute;N PRESUPUESTARIA</div>
                                <div>DIRECCI&Oacute;N DE ADMINISTRACI&Oacute;N PLANIFICACI&Oacute;N Y PRESUPUESTO</div>
                                <div>CONTRALORA MUNICIPAL PROV.</div>
                            </div>

                            <!-- Row 2: Signature Space -->
                            <div class="op-grid-row op-row-sig-body">
                                <div></div>
                                <div></div>
                                <div>CONFORMADO POR</div>
                                <div>APROBADO POR</div>
                            </div>

                            <!-- Row 3: Names (Purple) -->
                            <div class="op-grid-row op-row-names-purple">
                                <div>DANIELA MAR&Iacute;N</div>
                                <div>DANIELA MAR&Iacute;N</div>
                                <div>GLIBER P&Eacute;REZ</div>
                                <div>SILVIA MU&Ntilde;OZ</div>
                            </div>

                            <!-- Row 4: Pagado Header (Purple) -->
                            <div class="op-grid-row op-row-pagado-purple">
                                <div>PAGADO</div>
                                <div>BANCO</div>
                                <div>CUENTA CORRIENTE Nro.</div>
                                <div>TRANSFERENCIA N&ordf;</div>
                            </div>

                            <!-- Row 5: Pagado Data Row -->
                            <div class="op-grid-row op-row-pagado-data">
                                <div style="font-weight:800; font-size:10.5px;">COMPROBANTE</div>
                                <div>
                                    <select name="comprobante_banco" id="comprobante_banco">
                                        <option value="BANCO DE VENEZUELA" selected>BANCO DE VENEZUELA</option>
                                        <option value="BANESCO">BANESCO</option>
                                        <option value="MERCANTIL">MERCANTIL</option>
                                        <option value="PROVINCIAL">PROVINCIAL</option>
                                        <option value="BNC">BNC</option>
                                        <option value="BANFANB">BANFANB</option>
                                        <option value="BANCO DEL TESORO">BANCO DEL TESORO</option>
                                    </select>
                                </div>
                                <div>
                                    <input type="text" name="comprobante_cuenta" id="comprobante_cuenta"
                                        value="0102-0509-31-0000072355">
                                </div>
                                <div>
                                    <input type="text" name="comprobante_transf"
                                        value="<?php echo htmlspecialchars($op_edit ? $op_edit['recibe_firma'] : 'ND'); ?>">
                                </div>
                            </div>
                        </div>

                    </div><!-- /.op-document -->

                    <!-- Campos ocultos -->
                    <input type="hidden" name="monto_bs" id="h-monto-bs">
                    <input type="hidden" name="monto_letras" id="h-letras">
                    <input type="hidden" name="total_retenciones" id="h-ret">
                    <input type="hidden" name="monto_neto" id="h-neto">
                    <input type="hidden" name="cont_json" id="h-cont">
                    <input type="hidden" name="doc_tipo" value="BANAVIH">

                    <?php if ($op_edit): ?>
                        <!-- Audit trail section for supervisors -->
                        <div style="margin-top: 15px; padding: 8px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 9.5px; color: #475569; display: flex; justify-content: space-between; align-items: center;"
                            class="no-print">
                            <div>
                                <strong>👤 Creado por:</strong>
                                <?= htmlspecialchars(!empty($op_edit['creado_por_nombre']) ? $op_edit['creado_por_nombre'] : (!empty($op_edit['created_by']) ? $op_edit['created_by'] : 'Sistema')) ?>
                                <?php if (!empty($op_edit['created_at'])): ?>
                                    <span
                                        style="color:#64748b;">(<?= date('d/m/Y h:i A', strtotime($op_edit['created_at'])) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($op_edit['updated_by'])): ?>
                                <div>
                                    <strong>✏️ Última modificación:</strong>
                                    <?= htmlspecialchars(!empty($op_edit['actualizado_por_nombre']) ? $op_edit['actualizado_por_nombre'] : (!empty($op_edit['updated_by']) ? $op_edit['updated_by'] : 'Sistema')) ?>
                                    <?php if (!empty($op_edit['updated_at'])): ?>
                                        <span
                                            style="color:#64748b;">(<?= date('d/m/Y h:i A', strtotime($op_edit['updated_at'])) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="btns no-print">
                        <button type="submit" class="btn-guardar" onclick="prepararEnvio()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Guardar Orden de Pago
                        </button>
                        <button type="button" class="btn-imprimir" onclick="window.print()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2">
                                </path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                            Imprimir / PDF
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?>
    <script>
        var ONES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'diecis\u00e9is', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte'];
        var TENS = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        var HUNDS = ['', 'cien', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        function inWords(n) { n = Math.floor(n); if (n === 0) return 'cero'; if (n <= 20) return ONES[n]; if (n < 100) { var d = Math.floor(n / 10), u = n % 10; if (d === 2 && u > 0) return 'veinti' + ONES[u]; return TENS[d] + (u ? ' y ' + ONES[u] : ''); } if (n < 1000) { var c = Math.floor(n / 100), r = n % 100; if (n === 100) return 'cien'; return HUNDS[c] + (r ? ' ' + inWords(r) : ''); } if (n < 1000000) { var m = Math.floor(n / 1000), r2 = n % 1000; return (m === 1 ? 'mil' : inWords(m) + ' mil') + (r2 ? ' ' + inWords(r2) : ''); } return n.toString(); }
        function montoLetras(val) { var e = Math.floor(val), c = Math.round((val - e) * 100), cs = c < 10 ? '0' + c : '' + c; return inWords(e).toUpperCase() + ' BOLIVARES CON ' + cs + '/100'; }
        function parseMonto(val) {
            if (typeof val === 'number') return val;
            if (!val) return 0;
            var str = val.toString().trim();
            if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
                var lastDot = str.lastIndexOf('.');
                var lastComma = str.lastIndexOf(',');
                if (lastComma > lastDot) {
                    str = str.replace(/\./g, '').replace(',', '.');
                } else {
                    str = str.replace(/,/g, '');
                }
            } else if (str.indexOf(',') !== -1) {
                var parts = str.split(',');
                if (parts.length === 2 && parts[1].length === 3) {
                    str = str.replace(',', '');
                } else {
                    str = str.replace(',', '.');
                }
            }
            return parseFloat(str) || 0;
        }

        function fmt(n) {
            var v = parseFloat(n);
            if (isNaN(v)) return '0.00';
            return v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        var montoBs = 0, retIdx = 0, contIdx = 0;

        function buildConcepto() {
            var mes = document.getElementById('arr-mes').value;
            var anio = document.getElementById('arr-anio').value;
            var memo = document.getElementById('arr-memo-num').value.trim().toUpperCase();
            var fecha = document.getElementById('arr-memo-fecha').value.trim();
            var cta = document.getElementById('arr-cuenta-banavih').value.trim();
            var txt = 'TRANSFERENCIA PARA LA CUENTA N\u00b0 ' + cta + ' (FONDOS DE TERCEROS): PARA CANCELAR LAS RETENCIONES Y APORTES PATRONALES DE LEY DE R\u00c9GIMEN PRESTACIONAL DE VIVIENDA Y H\u00c1BITAT DEL MES DE ' + mes + ' ' + anio + ', AL PERSONAL; OBREROS, EMPLEADOS, CONTRALORA, DIRECTORES Y JEFES DE LA CONTRALOR\u00cdA MUNICIPAL SIM\u00d3N RODR\u00cdGUEZ.' + (memo ? ' SEG\u00daN CONSTA EN MEMORANDUM N\u00b0 ' + memo + ' DE FECHA ' + fecha + '.' : '');
            var el = document.getElementById('concepto');
            if (el) { el.value = txt; autoExpand(el); }
        }

        function syncCuenta(val) {
            var helper = document.getElementById('arr-cuenta-banavih');
            if (helper) { helper.value = val; }
            var comp = document.getElementById('comprobante_cuenta');
            if (comp) { comp.value = val; }
            buildConcepto();
        }

        /* ── Account number auto-formatter (XXXX-XXXX-XX-XXXXXXXXXX) ────────── */
        function fmtCuenta(el) {
            var raw = el.value.replace(/[^0-9]/g, '').substring(0, 20);
            var out = '';
            if (raw.length > 0) out = raw.substring(0, 4);
            if (raw.length > 4) out += '-' + raw.substring(4, 8);
            if (raw.length > 8) out += '-' + raw.substring(8, 10);
            if (raw.length > 10) out += '-' + raw.substring(10, 20);
            el.value = out;
            syncCuenta(out);
        }

        /* ── BANAVIH predefined retenciones ──────────────────────────────────── */
        var BANAVIH_RETS = [
            { desc: 'LEY DE REG. PREST. DE VIVIENDA Y HABITAT EMPLEADOS', cod: '4.01.01.01.00' },
            { desc: 'LEY DE REG. PREST. DE VIVIENDA Y HABITAT OBREROS', cod: '4.01.01.10.00' },
            { desc: 'LEY DE REG. PREST. DE VIVIENDA Y HABITAT CONTRALORA', cod: '4.01.01.35.00' },
            { desc: 'LEY DE REG. PREST. DE VIVIENDA Y HABITAT DIRECTORES Y JEFES', cod: '4.01.01.36.00' }
        ];

        function pickRetBanavih(selEl) {
            var val = selEl.value;
            if (!val) return;
            for (var k = 0; k < BANAVIH_RETS.length; k++) {
                if (BANAVIH_RETS[k].desc === val) {
                    var tr = selEl.closest('tr');
                    var descEl = tr.querySelector('.ret-desc');
                    var codEl = tr.querySelector('.ret-codigo');
                    if (descEl) { descEl.value = BANAVIH_RETS[k].desc; }
                    if (codEl) { codEl.value = BANAVIH_RETS[k].cod; }
                    break;
                }
            }
            selEl.value = '';
        }

        function addRetRow(desc, codigo, monto) {
            var i = retIdx++;
            var d = desc !== undefined ? desc : '';
            var c = codigo !== undefined ? codigo : '';
            var m = monto !== undefined ? monto : '';
            var opts = BANAVIH_RETS.map(function (r) { return '<option value="' + r.desc + '">'; }).join('');
            var tr = document.createElement('tr'); tr.id = 'ret' + i;
            tr.innerHTML =
                '<td style="position:relative;">' +
                '<input list="banavih-list-' + i + '" type="text" name="ret_desc[]" class="ret-desc" value="' + d + '" placeholder="Descripci\u00f3n" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">' +
                '<datalist id="banavih-list-' + i + '">' + opts + '</datalist>' +
                '</td>' +
                '<td><input type="text" name="ret_codigo[]" class="ret-codigo" value="' + c + '" placeholder="4.01.01.01.00"></td>' +
                '<td style="text-align:right;"><input type="text" name="ret_comision[]" class="ret-comision" value="' + m + '" placeholder="0.00" oninput="recalcRetenciones()" style="text-align:right;"></td>' +
                '<td style="text-align:center;" class="no-print"><button type="button" onclick="delRetRow(' + i + ')" style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button></td>';
            document.getElementById('ret-body').appendChild(tr);
            /* Auto-fill code when user picks from datalist */
            var descInp = tr.querySelector('.ret-desc');
            var codInp = tr.querySelector('.ret-codigo');
            descInp.addEventListener('change', function () {
                var v = this.value.toUpperCase();
                for (var k = 0; k < BANAVIH_RETS.length; k++) {
                    if (BANAVIH_RETS[k].desc === v) { codInp.value = BANAVIH_RETS[k].cod; break; }
                }
            });
            recalcRetenciones();
        }
        function delRetRow(i) { var tr = document.getElementById('ret' + i); if (tr) { tr.parentNode.removeChild(tr); } recalcRetenciones(); }

        function recalcRetenciones() {
            var filas = document.querySelectorAll('#ret-body tr'), totalRet = 0;
            for (var i = 0; i < filas.length; i++) {
                var el = filas[i].querySelector('.ret-comision');
                var raw = el ? el.value : '0';
                totalRet += parseMonto(raw);
            }
            var neto = montoBs + totalRet;
            document.getElementById('total-retenciones').textContent = fmt(totalRet);
            document.getElementById('monto-neto').textContent = fmt(neto);
            var ev = document.getElementById('monto-bs'); if (ev) ev.value = fmt(neto);
            var letras = neto > 0 ? montoLetras(neto) : '\u2014';
            document.getElementById('monto-letras-txt').textContent = letras;
            document.getElementById('h-letras').value = letras;
            document.getElementById('h-ret').value = totalRet.toFixed(2);
            document.getElementById('h-neto').value = neto.toFixed(2);
        }

        function recalcContabilidad() {
            var filas = document.querySelectorAll('#cont-body tr'), sum = 0;
            for (var i = 0; i < filas.length; i++) {
                var el = filas[i].querySelector('input[name="cont_total[]"]');
                var v = el ? el.value : '0';
                sum += parseMonto(v);
            }
            montoBs = sum;
            var h = document.getElementById('cont-total-header'); if (h) h.textContent = fmt(montoBs);
            var hb = document.getElementById('h-monto-bs'); if (hb) hb.value = montoBs.toFixed(2);
            recalcRetenciones();
        }

        document.addEventListener('blur', function (e) {
            if (e.target && (e.target.classList.contains('ret-comision') || e.target.name === 'cont_total[]')) {
                var num = parseMonto(e.target.value);
                if (e.target.value.trim() !== '') {
                    e.target.value = fmt(num);
                    recalcRetenciones();
                }
            }
        }, true);

        function addContRowFromData(anio, sector, prog, subprog, proy, acti, obra, partid, gen, espec, sub, monto) {
            var i = contIdx++; var tr = document.createElement('tr'); tr.id = 'cont' + i;
            tr.innerHTML = '<td><input type="text" name="cont_anio[]" value="' + (anio || '2026') + '"></td>' +
                '<td><input type="text" name="cont_sector[]" value="' + (sector || '01') + '"></td>' +
                '<td><input type="text" name="cont_prog[]" value="' + (prog || '08') + '"></td>' +
                '<td><input type="text" name="cont_subprog[]" value="' + (subprog || '00') + '"></td>' +
                '<td><input type="text" name="cont_proy[]" value="' + (proy || '00') + '"></td>' +
                '<td><input type="text" name="cont_acti[]" value="' + (acti || '51') + '"></td>' +
                '<td><input type="text" name="cont_obra[]" value="' + (obra || '') + '"></td>' +
                '<td><input type="text" name="cont_partid[]" value="' + (partid || '') + '"></td>' +
                '<td><input type="text" name="cont_gen[]" value="' + (gen || '') + '"></td>' +
                '<td><input type="text" name="cont_espec[]" value="' + (espec || '') + '"></td>' +
                '<td><input type="text" name="cont_sub[]" value="' + (sub || '') + '"></td>' +
                '<td style="text-align:right;"><input type="text" name="cont_total[]" value="' + fmt(parseFloat(monto) || 0) + '" style="text-align:right;" oninput="recalcContabilidad()"></td>' +
                '<td style="text-align:center;" class="no-print"><button type="button" onclick="delContRow(' + i + ')" style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button></td>';
            document.getElementById('cont-body').appendChild(tr);
            recalcContabilidad();
        }
        function addContRow() { addContRowFromData('', '', '', '', '', '', '', '', '', '', '', 0); }
        function delContRow(i) { var tr = document.getElementById('cont' + i); if (tr) { tr.parentNode.removeChild(tr); } recalcContabilidad(); }

        function prepararEnvio() {
            var rows = [], filas = document.querySelectorAll('#cont-body tr');
            for (var i = 0; i < filas.length; i++) {
                var tr = filas[i];
                var g = function (n) { var el = tr.querySelector('input[name="' + n + '"]'); return el ? el.value : ''; };
                rows.push({
                    anio: g('cont_anio[]'),
                    sector: g('cont_sector[]'),
                    prog: g('cont_prog[]'),
                    subprog: g('cont_subprog[]'),
                    proy: g('cont_proy[]'),
                    acti: g('cont_acti[]'),
                    obra: g('cont_obra[]'),
                    partid: g('cont_partid[]'),
                    gen: g('cont_gen[]'),
                    espec: g('cont_espec[]'),
                    sub: g('cont_sub[]'),
                    total: g('cont_total[]')
                });
            }
            document.getElementById('h-cont').value = JSON.stringify(rows);
        }

        function autoExpand(el) { if (!el) return; el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; }
        document.addEventListener('input', function (e) { if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.classList.contains('auto-expand'))) { autoExpand(e.target); } });

        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($edit_id > 0): ?>
                // Load database retenciones
                <?php foreach ($retenciones_edit as $r): ?>
                    addRetRow(
                        "<?php echo htmlspecialchars($r['descripcion']); ?>",
                        "<?php echo htmlspecialchars($r['codigo_presupuestario']); ?>",
                        "<?php echo number_format((float) $r['monto_comision'], 2, '.', ','); ?>"
                    );
                <?php endforeach; ?>

                // Load database contabilidad
                var contEditData = <?php echo json_encode($contabilidad_edit); ?>;
                if (Array.isArray(contEditData) && contEditData.length > 0) {
                    contEditData.forEach(function(c) {
                        var m = c.total !== undefined ? parseMonto(c.total) : 0;
                        addContRowFromData(
                            c.anio || '2026',
                            c.sector || '01',
                            c.prog || '08',
                            c.subprog || '00',
                            c.proy || '00',
                            c.acti || '51',
                            c.obra || '',
                            c.partid || '',
                            c.gen || '',
                            c.espec || '',
                            c.sub || '',
                            m
                        );
                    });
                }
        <?php else: ?>
            // Default rows for this new O.P
            addRetRow('LEY DE REG. PREST. DE VIVIENDA Y HABITAT EMPLEADOS', '4.01.01.01.00', '');
            addRetRow('LEY DE REG. PREST. DE VIVIENDA Y HABITAT OBREROS', '4.01.01.10.00', '');
            addRetRow('LEY DE REG. PREST. DE VIVIENDA Y HABITAT CONTRALORA', '4.01.01.35.00', '');
            addRetRow('LEY DE REG. PREST. DE VIVIENDA Y HABITAT DIRECTORES Y JEFES', '4.01.01.36.00', '');

            addContRowFromData('2026', '01', '08', '00', '00', '51', '401', '06', '05', '', '', 0);
            addContRowFromData('2026', '01', '08', '00', '00', '51', '401', '06', '13', '', '', 0);
            addContRowFromData('2026', '01', '08', '00', '00', '51', '401', '06', '34', '', '', 0);
            addContRowFromData('2026', '01', '08', '00', '00', '51', '401', '06', '42', '', '', 0);

            buildConcepto();
        <?php endif; ?>

        document.querySelectorAll('textarea').forEach(autoExpand);
        var dirty = false, submitting = false;
        var f = document.getElementById('form-op');
        if (f) { f.addEventListener('input', function () { dirty = true; }); f.addEventListener('change', function () { dirty = true; }); f.addEventListener('submit', function () { submitting = true; }); }
        var cb = document.getElementById('cont-body');
        if (cb) { cb.addEventListener('input', function (e) { if (e.target && e.target.name === 'cont_total[]') recalcContabilidad(); }); }
        window.addEventListener('beforeunload', function (e) { if (dirty && !submitting) { var m = 'Datos no guardados. Saldra sin guardar.'; (e || window.event).returnValue = m; return m; } });
});
    </script>
</body>

</html>