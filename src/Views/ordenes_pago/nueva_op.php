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

/* ── Órdenes de compra para el select ── */
$ordenes_compra = mysqli_query($conn,
    "SELECT oc.id, oc.numero_orden, oc.fecha, p.razon_social
     FROM ordenes_compra oc
     JOIN proveedores p ON p.id = oc.proveedor_id
     ORDER BY oc.fecha DESC, oc.id DESC"
);

/* ── Órdenes de servicio para el select ── */
$ordenes_servicio = mysqli_query($conn,
    "SELECT os.id, os.numero_os, os.fecha, p.razon_social
     FROM ordenes_servicio os
     JOIN proveedores p ON p.id = os.proveedor_id
     ORDER BY os.fecha DESC, os.id DESC"
);

/* ── Fetch existing OP (when editing/viewing via ?id=X) ── */
$op_edit = null;
$retenciones_edit = [];
$contabilidad_edit = [];
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($edit_id > 0) {
    $res_op_edit = mysqli_query($conn, "SELECT op.*,
                                               uc.nombre AS creado_por_nombre, uu.nombre AS actualizado_por_nombre
                                        FROM ordenes_pago op
                                        LEFT JOIN usuarios uc ON uc.nombre = op.created_by OR uc.usuario = op.created_by
                                        LEFT JOIN usuarios uu ON uu.nombre = op.updated_by OR uu.usuario = op.updated_by
                                        WHERE op.id = $edit_id AND op.deleted_at IS NULL");
    if ($res_op_edit && mysqli_num_rows($res_op_edit) > 0) {
        $op_edit = mysqli_fetch_assoc($res_op_edit);

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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Nueva Orden de Pago — Contraloría del Municipio Simón Rodríguez">
    <title>Nueva Orden de Pago — Contraloría MSR</title>
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <style>
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        /* ── Reset y Estilos Generales ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; font-size: 11px; background: #f0f2f5; display: flex; flex-direction: column; min-height: 100vh; color: #000; }

        /* ── Header ── */
        .site-header { background: #070707; color: white; display: flex; align-items: center; gap: 14px; padding: 8px 20px; border-bottom: 3px solid #0d47a1; position: sticky; top: 0; z-index: 100; }
        .site-header img { height: 52px; width: auto; display: block; }
        .site-header .brand { display: flex; flex-direction: column; gap: 2px; }
        .site-header .brand-title { font-size: 18px; font-weight: bold; letter-spacing: 0.7px; text-transform: uppercase; }
        .site-header .brand-sub { font-size: 12.5px; color: #bbdefb; }
        .site-header .header-right { margin-left: auto; font-size: 11px; color: #e3f2fd; text-align: right; }

        /* ── Layout ── */
        .layout { display: flex; flex: 1; }

        /* ── Sidebar CSS is now managed centrally in includes/sidebar.php ── */

        /* ── Main Content ── */
        .main-content { flex: 1; padding: 20px; overflow-x: auto; display: flex; flex-direction: column; align-items: center; }

        /* ── Container ── */
        .container { background: white; padding: 15px; border: 1px solid #ccc; width: 100%; max-width: 980px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        /* ── Footer ── */
        .site-footer { background: #080616; color: #90a4ae; text-align: center; padding: 10px 20px; font-size: 10px; border-top: 3px solid #1565c0; line-height: 1.8; margin-top: auto; }
        .site-footer strong { color: #e3f2fd; }

        /* ============================================================
           DOCUMENTO ORDEN DE PAGO (DISEÑO EXACTO SEGÚN IMAGEN)
           ============================================================ */
        .op-document {
            border: 2px solid #000;
            background: #fff;
            font-family: 'Inter', sans-serif;
            color: #000;
            width: 100%;
            box-sizing: border-box;
        }

        /* ── Header Institucional con Logos ── */
        .op-header-grid {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            border-bottom: 1.5px solid #000;
        }

        .op-logo-left img, .op-logo-right img {
            height: 70px;
            width: auto;
            display: block;
        }

        .op-header-center {
            text-align: center;
            line-height: 1.3;
        }

        .op-header-center .op-rep { font-weight: bold; font-size: 11.5px; text-transform: uppercase; }
        .op-header-center .op-est { font-weight: bold; font-size: 11px; text-transform: uppercase; }
        .op-header-center .op-ent { font-weight: 800; font-size: 12px; margin-top: 2px; text-transform: uppercase; }

        /* ── Fila Título ORDEN DE PAGO + NÚMERO / FECHA ── */
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

        /* ── Fila Beneficiario / NIT / RIF ── */
        .op-row-benef-lbl {
            display: flex;
            border-bottom: 1px solid #000;
            background: #fff;
            font-weight: bold;
            font-size: 10px;
        }

        .op-cell-benef-lbl { flex: 1; padding: 3px 8px; border-right: 1px solid #000; }
        .op-cell-nit-lbl { width: 90px; padding: 3px 8px; border-right: 1px solid #000; text-align: center; }
        .op-cell-rif-lbl { width: 220px; padding: 3px 8px; text-align: left; }

        .op-row-benef-val {
            display: flex;
            border-bottom: 1.5px solid #000;
            min-height: 28px;
            align-items: center;
        }

        .op-cell-benef-val { flex: 1; border-right: 1px solid #000; padding: 2px 6px; }
        .op-cell-benef-val select { width: 100%; border: none; font-size: 12px; font-weight: bold; font-family: 'Inter', sans-serif; background: transparent; outline: none; cursor: pointer; }
        .op-cell-nit-val { width: 90px; border-right: 1px solid #000; min-height: 28px; }
        .op-cell-rif-val { width: 220px; padding: 2px 6px; }
        .op-cell-rif-val input { width: 100%; border: none; font-size: 12px; font-weight: bold; font-family: 'Inter', sans-serif; background: transparent; outline: none; }

        /* ── Fila Por la Cantidad de ── */
        .op-row-cant-lbl {
            border-bottom: 1px solid #000;
            padding: 3px 8px;
            font-weight: bold;
            font-size: 10px;
            background: #fff;
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

        /* ── Fila Concepto / Descripción ── */
        .op-row-concepto {
            border-bottom: 1.5px solid #000;
            padding: 10px 12px;
            min-height: 70px;
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

        /* ── Fila Banco / Cuenta / Monto Bs ── */
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

        .op-cell-banco label { margin-right: 6px; white-space: nowrap; }
        .op-cell-banco input { flex: 1; border: none; font-size: 11px; font-weight: bold; font-family: 'Inter', sans-serif; text-transform: uppercase; outline: none; background: transparent; }

        .op-cell-cuenta {
            flex: 1.2;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            border-right: 1px solid #000;
            font-size: 11px;
            font-weight: bold;
        }

        .op-cell-cuenta label { margin-right: 6px; white-space: nowrap; }
        .op-cell-cuenta input { flex: 1; border: none; font-size: 11px; font-weight: bold; font-family: 'Inter', sans-serif; outline: none; background: transparent; }

        .op-cell-monto {
            width: 270px;
            display: flex;
            align-items: center;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: bold;
        }

        .op-cell-monto label { margin-right: 8px; white-space: nowrap; }
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

        /* ── Tabla Retenciones ── */
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

        /* ── Contabilidad Presupuestaria ── */
        .op-cont-header-bar {
            border-bottom: 1.5px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.5px;
            position: relative;
            background: #fff;
        }

        .op-cont-total-top {
            position: absolute;
            right: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: bold;
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

        /* ── Fila No. O/C y FECHA ── */
        .op-row-oc {
            display: flex;
            border-top: 1.5px solid #000;
            border-bottom: 1.5px solid #000;
            align-items: center;
            padding: 6px 12px;
            gap: 40px;
            font-size: 11px;
            font-weight: bold;
        }

        .op-oc-field {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .op-oc-field select {
            border: 1px solid #999;
            padding: 2px 6px;
            font-size: 11px;
            font-family: 'Inter', sans-serif;
            background: #fff;
            outline: none;
        }

        .op-oc-fecha-field {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .op-oc-fecha-field input {
            border: none;
            font-weight: bold;
            font-size: 11px;
            font-family: 'Inter', sans-serif;
            width: 110px;
            outline: none;
            background: transparent;
        }

        /* ── Firmas / Aprobaciones ── */
        .op-firmas-container {
            display: flex;
            flex-direction: column;
        }

        .op-firmas-header-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1.3fr 1.3fr;
            border-bottom: 1px solid #000;
            background: #fff;
        }

        .op-firmas-header-row > div {
            border-right: 1px solid #000;
            padding: 4px 2px;
            text-align: center;
            font-weight: bold;
            font-size: 9.5px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .op-firmas-header-row > div:last-child {
            border-right: none;
        }

        .op-firmas-body-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1.3fr 1.3fr;
            border-bottom: 1.5px solid #000;
            min-height: 75px;
        }

        .op-firma-col {
            border-right: 1px solid #000;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 6px 4px;
            text-align: center;
        }

        .op-firma-col:last-child {
            border-right: none;
        }

        .op-firma-space {
            flex: 1;
        }

        .op-firma-label-sub {
            font-size: 9px;
            color: #222;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .op-firma-name {
            font-weight: bold;
            font-size: 10.5px;
            text-transform: uppercase;
        }

        /* ── Recibe Conforme ── */
        .op-recibe-header-row {
            display: grid;
            grid-template-columns: 1.5fr 1.5fr 1fr 1fr;
            border-bottom: 1px solid #000;
            background: #fff;
        }

        .op-recibe-header-row > div {
            border-right: 1px solid #000;
            padding: 3px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 9.5px;
            text-transform: uppercase;
        }

        .op-recibe-header-row > div:last-child {
            border-right: none;
        }

        .op-recibe-body-row {
            display: grid;
            grid-template-columns: 1.5fr 1.5fr 1fr 1fr;
            min-height: 36px;
            align-items: center;
        }

        .op-recibe-body-row > div {
            border-right: 1px solid #000;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .op-recibe-body-row > div:last-child {
            border-right: none;
        }

        .op-recibe-body-row input {
            width: 100%;
            border: none;
            text-align: center;
            font-size: 10.5px;
            font-weight: bold;
            font-family: 'Inter', sans-serif;
            outline: none;
            background: transparent;
        }

        /* ── Botón agregar fila ── */
        .btn-add { font-size: 11px; color: #1565c0; background: none; border: none; cursor: pointer; padding: 4px 6px; font-weight: bold; }
        .btn-add:hover { text-decoration: underline; }

        /* ── Botones principales ── */
        .btns { margin-top: 15px; display: flex; gap: 10px; justify-content: center; width: 100%; }
        .btn-guardar  { background: #1565c0; color: white; border: none; padding: 10px 24px; font-size: 13px; font-weight: bold; cursor: pointer; font-family: 'Geist', sans-serif; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 6px; box-shadow: 0 2px 5px rgba(21, 101, 192, 0.3); }
        .btn-imprimir { background: #c62828; color: white; border: none; padding: 10px 20px; font-size: 13px; font-weight: bold; cursor: pointer; font-family: 'Geist', sans-serif; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 6px; box-shadow: 0 2px 5px rgba(198, 40, 40, 0.3); }
        .btn-guardar:hover  { background: #0d47a1; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(13, 71, 161, 0.4); }
        .btn-imprimir:hover { background: #b71c1c; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(183, 28, 28, 0.4); }

        /* Quita flechas en inputs number */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

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
            .no-print, .site-header, .site-footer, .sidebar, .btns, .btn-add {
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
                box-shadow: none !important;
                page-break-inside: avoid;
                zoom: 0.94;
                margin: 0 auto !important;
            }
            input, select, textarea {
                border: none !important;
                background: transparent !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                appearance: none !important;
                box-shadow: none !important;
                color: #000 !important;
                font-family: 'Inter', sans-serif !important;
            }
            select::-ms-expand { display: none; }
        }
    </style>
</head>
<body><div class="layout">
<?php $active = 'op-nueva'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
<main class="main-content">
<div class="container">

    <form method="POST" action="../../controllers/ordenes_pago/guardar_op.php" id="form-op">

        <!-- Formulario rápido de nuevo proveedor (oculto por defecto, no se imprime) -->
        <div id="form-nuevo-proveedor" class="no-print" style="display:none; border:1px solid #1565c0; padding:12px; margin-bottom:15px; background:#f4f8ff;">
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
                        <input type="text" id="np-rif-num" style="flex:1; border:1px solid #ccc; padding:4px;" placeholder="12345678-9">
                    </div>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:bold;">Razón Social</label>
                    <input type="text" id="np-nombre" style="width:100%; border:1px solid #ccc; padding:4px;" placeholder="EMPRESA, C.A."
                        oninput="this.value=this.value.toUpperCase()">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:bold;">Teléfono</label>
                    <input type="text" id="np-telefono" style="width:100%; border:1px solid #ccc; padding:4px;" placeholder="0283-0000000"
                        oninput="formatTelefono(this)">
                </div>
            </div>
            <div style="margin-top:6px;">
                <label style="font-size:10px; font-weight:bold;">Dirección</label>
                <input type="text" id="np-direccion" style="width:100%; border:1px solid #ccc; padding:4px;" placeholder="Av. Principal..."
                    oninput="this.value=this.value.toUpperCase()">
            </div>
            <div style="margin-top:10px; display:flex; gap:8px;">
                <button type="button" onclick="guardarNuevoProveedor()" style="background:#1565c0; color:white; border:none; padding:6px 14px; font-size:11px; cursor:pointer; font-weight:bold;">
                    Guardar proveedor
                </button>
                <button type="button" onclick="toggleFormProveedor()" style="background:#666; color:white; border:none; padding:6px 14px; font-size:11px; cursor:pointer;">
                    Cancelar
                </button>
            </div>
        </div>

        <!-- ESTRUCTURA EXACTA DEL DOCUMENTO ORDEN DE PAGO -->
        <div class="op-document">

            <!-- 1. ENCABEZADO DE LOGOS Y TEXTO INSTITUCIONAL -->
            <div class="op-header-grid">
                <div class="op-logo-left">
                    <img src="/sistema/assets/img/logo.png" alt="Logo Contraloría MSR">
                </div>
                <div class="op-header-center">
                    <p class="op-rep">REPÚBLICA BOLIVARIANA DE VENEZUELA</p>
                    <p class="op-est">ESTADO ANZOÁTEGUI</p>
                    <p class="op-ent">CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</p>
                </div>
                <div class="op-logo-right">
                    <img src="/sistema/assets/img/sncf.png" alt="SN CF">
                </div>
            </div>

            <!-- 2. TITULO ORDEN DE PAGO + NÚMERO Y FECHA -->
            <div class="op-row-title">
                <div class="op-title-cell">
                    <h2>ORDEN DE PAGO</h2>
                </div>
                <div class="op-num-fecha-cell">
                    <div class="op-nf-row">
                        <label>NUMERO:</label>
                        <input type="text" name="numero_op" id="numero_op" value="<?php echo htmlspecialchars($op_edit ? $op_edit['numero'] : ''); ?>" required placeholder="000">
                    </div>
                    <div class="op-nf-row">
                        <label>FECHA:</label>
                        <div style="position: relative; flex: 1; height: 100%; display: flex; align-items: center;">
                            <input type="hidden" name="fecha" id="fecha_op" value="<?php echo htmlspecialchars($op_edit && $op_edit['fecha'] ? $op_edit['fecha'] : date('Y-m-d')); ?>" required>
                            <input type="text" id="fecha_op_display" readonly
                                value="<?php echo htmlspecialchars($op_edit && $op_edit['fecha'] ? date('d/m/Y', strtotime($op_edit['fecha'])) : date('d/m/Y')); ?>"
                                placeholder="DD/MM/YYYY"
                                onclick="openDatePicker(this, document.getElementById('fecha_op'))"
                                style="cursor: pointer; width: 100%; height: 100%; border: none; padding: 2px 6px; font-size: 12px; font-weight: bold; text-align: center; font-family: 'Inter', sans-serif; outline: none; background: transparent;"
                                title="Seleccionar Fecha">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. BENEFICIARIO DE LA ORDEN / NIT / RIF -->
            <div class="op-row-benef-lbl">
                <div class="op-cell-benef-lbl">BENEFICIARIO DE LA ORDEN</div>
                <div class="op-cell-nit-lbl">NIT</div>
                <div class="op-cell-rif-lbl">RIF.No.</div>
            </div>
            <div class="op-row-benef-val">
                <div class="op-cell-benef-val">
                    <select name="proveedor_id" id="sel-proveedor" required onchange="cargarProveedor(this.value)">
                        <option value="">-- Seleccionar Proveedor --</option>
                        <?php while ($p = mysqli_fetch_assoc($proveedores)): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($op_edit && (int)$op_edit['proveedor_id'] === (int)$p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['rif']); ?> - <?php echo htmlspecialchars($p['razon_social']); ?></option>
                        <?php endwhile; ?>
                        <option value="nuevo" style="font-weight:bold; color:#1565c0;">+ Agregar Nuevo Proveedor...</option>
                    </select>
                </div>
                <div class="op-cell-nit-val"></div>
                <div class="op-cell-rif-val">
                    <input type="text" name="rif_beneficiario" id="rif-beneficiario" value="<?php echo htmlspecialchars($op_edit ? $op_edit['rif_beneficiario'] : ''); ?>" placeholder="J-40184563-0">
                </div>
            </div>

            <!-- 4. POR LA CANTIDAD DE -->
            <div class="op-row-cant-lbl">POR LA CANTIDAD DE :</div>
            <div class="op-row-cant-val">
                <span id="monto-letras-txt"><?php echo htmlspecialchars($op_edit && !empty($op_edit['monto_letras']) ? $op_edit['monto_letras'] : '—'); ?></span>
            </div>

            <!-- 5. DESCRIPCIÓN / CONCEPTO -->
            <div class="op-row-concepto">
                <textarea name="concepto" id="concepto" class="auto-expand" rows="1" placeholder="DESCRIPCIÓN DE PAGO" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();"><?php echo htmlspecialchars($op_edit ? $op_edit['concepto'] : ''); ?></textarea>
            </div>

            <!-- 5b. HELPER ARRENDAMIENTO (visible solo cuando aplica, no se imprime) -->
            <div id="panel-arrendamiento" class="no-print" style="display:none; border:1px solid #1565c0; background:#f4f8ff; padding:10px 14px; border-top:none;">
                <div style="font-size:10px; font-weight:bold; color:#1565c0; margin-bottom:8px; text-transform:uppercase;">📋 Completar concepto de arrendamiento</div>
                <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
                    <div>
                        <label style="font-size:10px; font-weight:bold; display:block; margin-bottom:2px;">MES</label>
                        <select id="arr-mes" onchange="buildConcepto()" style="border:1px solid #1565c0; padding:4px 8px; font-size:11px; font-family:'Inter',sans-serif; background:#fff; outline:none; cursor:pointer;">
                            <option value="ENERO"<?php echo date('n')==1?' selected':''; ?>>ENERO</option>
                            <option value="FEBRERO"<?php echo date('n')==2?' selected':''; ?>>FEBRERO</option>
                            <option value="MARZO"<?php echo date('n')==3?' selected':''; ?>>MARZO</option>
                            <option value="ABRIL"<?php echo date('n')==4?' selected':''; ?>>ABRIL</option>
                            <option value="MAYO"<?php echo date('n')==5?' selected':''; ?>>MAYO</option>
                            <option value="JUNIO"<?php echo date('n')==6?' selected':''; ?>>JUNIO</option>
                            <option value="JULIO"<?php echo date('n')==7?' selected':''; ?>>JULIO</option>
                            <option value="AGOSTO"<?php echo date('n')==8?' selected':''; ?>>AGOSTO</option>
                            <option value="SEPTIEMBRE"<?php echo date('n')==9?' selected':''; ?>>SEPTIEMBRE</option>
                            <option value="OCTUBRE"<?php echo date('n')==10?' selected':''; ?>>OCTUBRE</option>
                            <option value="NOVIEMBRE"<?php echo date('n')==11?' selected':''; ?>>NOVIEMBRE</option>
                            <option value="DICIEMBRE"<?php echo date('n')==12?' selected':''; ?>>DICIEMBRE</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:bold; display:block; margin-bottom:2px;">AÑO</label>
                        <input type="number" id="arr-anio" value="<?php echo date('Y'); ?>" min="2020" max="2099" onchange="buildConcepto()" style="width:70px; border:1px solid #1565c0; padding:4px 6px; font-size:11px; font-family:'Inter',sans-serif; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:bold; display:block; margin-bottom:2px;">N° MEMORANDUM</label>
                        <input type="text" id="arr-memo-num" value="" oninput="buildConcepto()" style="width:130px; border:1px solid #1565c0; padding:4px 6px; font-size:11px; font-family:'Inter',sans-serif; outline:none; text-transform:uppercase;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:bold; display:block; margin-bottom:2px;">FECHA MEMORANDUM</label>
                        <div style="position: relative; width: 100px;">
                            <input type="text" id="arr-memo-fecha" value="<?php echo date('d/m/Y'); ?>" oninput="buildConcepto()"
                                onclick="openDatePicker(this, null, function() { buildConcepto(); })"
                                placeholder="DD/MM/AAAA" style="cursor: pointer; width:100px; border:1px solid #1565c0; padding:4px 6px; font-size:11px; font-family:'Inter',sans-serif; outline:none;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. BANCO / CUENTA CTE / MONTO BS -->
            <div class="op-row-banco">
                <div class="op-cell-banco">
                    <label>BANCO:</label>
                    <select name="banco" id="banco" style="flex:1; border:none; font-size:11px; font-weight:bold; font-family:'Inter', sans-serif; text-transform:uppercase; outline:none; background:transparent; cursor:pointer;">
                        <option value="BANCO DE VENEZUELA" selected>BANCO DE VENEZUELA</option>
                        <option value="BANESCO">BANESCO</option>
                        <option value="MERCANTIL">MERCANTIL</option>
                        <option value="BANCO BBVA PROVINCIAL">BANCO BBVA PROVINCIAL</option>
                        <option value="BANCO NACIONAL DE CRÉDITO">BANCO NACIONAL DE CRÉDITO</option>
                        <option value="BANCAMIGA">BANCAMIGA</option>
                        <option value="BANCO DEL TESORO">BANCO DEL TESORO</option>
                        <option value="BANCO BICENTENARIO">BANCO BICENTENARIO</option>
                        <option value="BANCO EXTERIOR">BANCO EXTERIOR</option>
                        <option value="BANCO FONDO COMÚN">BANCO FONDO COMÚN</option>
                        <option value="VENEZOLANO DE CRÉDITO">VENEZOLANO DE CRÉDITO</option>
                        <option value="100% BANCO">100% BANCO</option>
                        <option value="BANFANB">BANFANB</option>
                        <option value="BANCO ACTIVO">BANCO ACTIVO</option>
                        <option value="BANCO CARONÍ">BANCO CARONÍ</option>
                        <option value="BANPLUS">BANPLUS</option>
                        <option value="BANCO PLAZA">BANCO PLAZA</option>
                        <option value="DEL SUR BANCO UNIVERSAL">DEL SUR BANCO UNIVERSAL</option>
                        <option value="MI BANCO">MI BANCO</option>
                        <option value="BANGENTE">BANGENTE</option>
                    </select>
                </div>
                <div class="op-cell-cuenta">
                    <label>N° DE CUENTA CTE</label>
                    <input type="text" name="numero_cuenta" id="numero_cuenta" value="<?php echo htmlspecialchars($op_edit ? $op_edit['numero_cuenta'] : ''); ?>" placeholder="0102-1234-56-1234567890">
                </div>
                <div class="op-cell-monto">
                    <label>MONTO BS.</label>
                    <div class="monto-bs-box">
                        <input type="text" id="monto-bs" value="<?php echo htmlspecialchars($op_edit ? number_format((float)$op_edit['monto_bruto'], 2, '.', ',') : '0.00'); ?>" placeholder="0.00">
                    </div>
                </div>
            </div>

            <!-- 7. SECCIÓN RETENCIONES -->
            <table class="op-table-ret" id="tabla-ret">
                <thead>
                    <tr>
                        <th style="width:38%">DESCRIPCIÓN</th>
                        <th style="width:30%">CÓDIGO PRESUPUESTARIO:</th>
                        <th style="width:12%">Tasa %</th>
                        <th style="width:20%">MONTO COMISION</th>
                        <th style="width:30px" class="no-print"></th>
                    </tr>
                </thead>
                <tbody id="ret-body">
                    <!-- Filas generadas por JS -->
                </tbody>
            </table>

            <div class="op-ret-totals">
                <div>
                    <button type="button" class="btn-add no-print" onclick="addRetRow()">+ Agregar retención</button>
                </div>
                <div class="op-ret-totals-right">
                    <div class="op-ret-tot-row">
                        <span>MONTO A RETENER Bs.</span>
                        <div class="val-box"><span id="total-retenciones">0.00</span></div>
                    </div>
                    <div class="op-ret-tot-row">
                        <span>MONTO NETO A PAGAR Bs.</span>
                        <div class="val-box-bold"><span id="monto-neto">0.00</span></div>
                    </div>
                </div>
            </div>

            <!-- 8. SECCIÓN CONTABILIDAD PRESUPUESTARIA -->
            <div class="op-cont-header-bar">
                <span>CONTABILIDAD PRESUPUESTARIA</span>
            </div>

            <table class="op-table-cont" id="tabla-cont">
                <thead>
                    <tr>
                        <th>AÑO</th>
                        <th>$ECTOR</th>
                        <th>PROG.<br><small style="font-weight:normal;">PROG.</small></th>
                        <th>SUB-PROG.</th>
                        <th>PROY.</th>
                        <th>ACTI.</th>
                        <th>OBRA<br><small style="font-weight:normal;">COD.</small></th>
                        <th>PARTID.</th>
                        <th>GEN</th>
                        <th>ESPEC.</th>
                        <th>SUB<br><small style="font-weight:normal;">ESP.</small></th>
                        <th style="width:24%; padding:4px 6px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                                <span style="font-weight:bold; font-size:11px; white-space:nowrap;">TOTAL Bs.</span>
                                <div class="op-cont-total-box"><span id="cont-total-header"><?php echo htmlspecialchars($op_edit ? number_format((float)$op_edit['monto_bruto'], 2, '.', ',') : '0.00'); ?></span></div>
                            </div>
                        </th>
                        <th style="width:30px" class="no-print"></th>
                    </tr>
                </thead>
                <tbody id="cont-body">
                    <!-- Filas generadas por JS -->
                </tbody>
            </table>
            <div style="padding:4px 8px; border-bottom:1.5px solid #000;" class="no-print">
                <button type="button" class="btn-add" onclick="addContRow()">+ Agregar fila manual</button>
            </div>

            <!-- 9. FILA NO. O/C - O/S Y FECHA -->
            <div class="op-row-oc">
                <div class="op-oc-field">
                    <label id="oc_label">No. O/C - O/S</label>
                    <select id="sel-orden" onchange="cargarOrden(this.value)">
                        <option value="">-- Sin O/C u O/S vinculada --</option>
                        <?php if ($ordenes_compra && mysqli_num_rows($ordenes_compra) > 0): ?>
                        <optgroup label="Órdenes de Compra (O.C.)">
                            <?php while ($oc = mysqli_fetch_assoc($ordenes_compra)): ?>
                            <option value="OC-<?php echo $oc['id']; ?>" <?php echo ($op_edit && (int)$op_edit['oc_id'] === (int)$oc['id']) ? 'selected' : ''; ?>>
                                O.C. N° <?php echo htmlspecialchars($oc['numero_orden']); ?> &mdash; <?php echo htmlspecialchars($oc['razon_social']); ?> (<?php echo htmlspecialchars($oc['fecha']); ?>)
                            </option>
                            <?php endwhile; ?>
                        </optgroup>
                        <?php endif; ?>
                        <?php if ($ordenes_servicio && mysqli_num_rows($ordenes_servicio) > 0): ?>
                        <optgroup label="Órdenes de Servicio (O.S.)">
                            <?php while ($os = mysqli_fetch_assoc($ordenes_servicio)): ?>
                            <option value="OS-<?php echo $os['id']; ?>" <?php echo ($op_edit && (int)$op_edit['os_id'] === (int)$os['id']) ? 'selected' : ''; ?>>
                                O.S. N° <?php echo htmlspecialchars($os['numero_os']); ?> &mdash; <?php echo htmlspecialchars($os['razon_social']); ?> (<?php echo htmlspecialchars($os['fecha']); ?>)
                            </option>
                            <?php endwhile; ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="op-oc-fecha-field">
                    <label>FECHA</label>
                    <input type="text" id="oc_fecha_display" placeholder="DD/MM/AAAA">
                </div>
            </div>

            <!-- 10. FIRMAS / APROBACIONES -->
            <div class="op-firmas-container">
                <div class="op-firmas-header-row">
                    <div>DISPONIBILIDAD FINANCIERA</div>
                    <div>IMPUTACIÓN PRESUPUESTARIA</div>
                    <div>DIRECTOR DE ADMINISTRACIÓN P Y P</div>
                    <div>CONTRALORA MUNICIPAL PROV.</div>
                </div>
                <div class="op-firmas-body-row">
                    <div class="op-firma-col">
                        <div class="op-firma-space"></div>
                        <div class="op-firma-name">DANIELA MARÍN</div>
                    </div>
                    <div class="op-firma-col">
                        <div class="op-firma-space"></div>
                        <div class="op-firma-name">DANIELA MARÍN</div>
                    </div>
                    <div class="op-firma-col">
                        <div class="op-firma-space"></div>
                        <div class="op-firma-label-sub">CONFORMADO POR</div>
                        <div class="op-firma-name">GLIBER PÉREZ</div>
                    </div>
                    <div class="op-firma-col">
                        <div class="op-firma-space"></div>
                        <div class="op-firma-label-sub">APROBADO POR</div>
                        <div class="op-firma-name">SILVIA MUÑOZ</div>
                    </div>
                </div>
                <div class="op-recibe-header-row">
                    <div>RECIBE CONFORME</div>
                    <div>FIRMA</div>
                    <div>CEDULA</div>
                    <div>FECHA</div>
                </div>
                <div class="op-recibe-body-row">
                    <div>
                        <input type="text" name="recibe_firma" id="recibe-firma" value="<?php echo htmlspecialchars($op_edit ? $op_edit['recibe_firma'] : ''); ?>" placeholder="Nombre completo">
                    </div>
                    <div>
                        <div style="border-bottom:1px solid #000; width:80%; height:1px; margin: 15px auto 0;"></div>
                    </div>
                    <div>
                        <input type="text" name="recibe_cedula" id="recibe-cedula" value="<?php echo htmlspecialchars($op_edit ? $op_edit['recibe_cedula'] : ''); ?>" placeholder="V-00.000.000">
                    </div>
                    <div>
                        <div style="position: relative; width: 100%; display: flex; align-items: center;">
                            <input type="hidden" name="recibe_fecha" id="recibe-fecha" value="<?php echo htmlspecialchars($op_edit && $op_edit['recibe_fecha'] ? $op_edit['recibe_fecha'] : ''); ?>">
                            <input type="text" id="recibe_fecha_display" readonly
                                value="<?php echo htmlspecialchars($op_edit && $op_edit['recibe_fecha'] ? date('d/m/Y', strtotime($op_edit['recibe_fecha'])) : ''); ?>"
                                placeholder="DD/MM/AAAA"
                                onclick="openDatePicker(this, document.getElementById('recibe-fecha'))"
                                style="cursor: pointer; width: 100%; border: none; text-align: center; font-size: 10.5px; font-weight: bold; font-family: 'Inter', sans-serif; outline: none; background: transparent;"
                                title="Seleccionar Fecha">
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.op-document -->

        <!-- Campos ocultos requeridos por backend -->
        <input type="hidden" name="op_id"             id="h-op-id" value="<?php echo $edit_id; ?>">
        <input type="hidden" name="oc_id"             id="h-oc-id" value="<?php echo htmlspecialchars($op_edit && $op_edit['oc_id'] ? $op_edit['oc_id'] : '0'); ?>">
        <input type="hidden" name="os_id"             id="h-os-id" value="<?php echo htmlspecialchars($op_edit && $op_edit['os_id'] ? $op_edit['os_id'] : '0'); ?>">
        <input type="hidden" name="doc_tipo"          id="h-doc-tipo" value="<?php echo htmlspecialchars($op_edit && $op_edit['doc_tipo'] ? $op_edit['doc_tipo'] : ''); ?>">
        <input type="hidden" name="monto_bs"          id="h-monto-bs">
        <input type="hidden" name="monto_letras"      id="h-letras">
        <input type="hidden" name="total_retenciones" id="h-ret">
        <input type="hidden" name="monto_neto"        id="h-neto">
        <input type="hidden" name="cont_json"         id="h-cont">

        <!-- BOTONES DE ACCIÓN -->

        <?php if ($op_edit): ?>
        <!-- Audit trail section for supervisors -->
        <div style="margin-top: 15px; padding: 8px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 9.5px; color: #475569; display: flex; justify-content: space-between; align-items: center;" class="no-print">
            <div>
                <strong>👤 Creado por:</strong> <?= htmlspecialchars(!empty($op_edit['creado_por_nombre']) ? $op_edit['creado_por_nombre'] : (!empty($op_edit['created_by']) ? $op_edit['created_by'] : 'Sistema')) ?>
                <?php if (!empty($op_edit['created_at'])): ?>
                    <span style="color:#64748b;">(<?= date('d/m/Y h:i A', strtotime($op_edit['created_at'])) ?>)</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($op_edit['updated_by'])): ?>
            <div>
                <strong>✏️ Última modificación:</strong> <?= htmlspecialchars(!empty($op_edit['actualizado_por_nombre']) ? $op_edit['actualizado_por_nombre'] : (!empty($op_edit['updated_by']) ? $op_edit['updated_by'] : 'Sistema')) ?>
                <?php if (!empty($op_edit['updated_at'])): ?>
                    <span style="color:#64748b;">(<?= date('d/m/Y h:i A', strtotime($op_edit['updated_at'])) ?>)</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="btns no-print">
            <button type="submit" class="btn-guardar" onclick="return validarYPrepararEnvio(event)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Guardar Orden de Pago
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
/* ============================================================
   CONVERSIÓN NÚMERO A LETRAS
   ============================================================ */
var ONES  = ['','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve',
             'diez','once','doce','trece','catorce','quince','dieciséis','diecisiete',
             'dieciocho','diecinueve','veinte'];
var TENS  = ['','','veinte','treinta','cuarenta','cincuenta','sesenta','setenta','ochenta','noventa'];
var HUNDS = ['','cien','doscientos','trescientos','cuatrocientos','quinientos',
             'seiscientos','setecientos','ochocientos','novecientos'];

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
    var cents  = Math.round((val - entero) * 100);
    var centsStr = cents < 10 ? '0' + cents : '' + cents;
    return inWords(entero).toUpperCase() + ' BOLIVARES CON ' + centsStr + '/100';
}

function parseMonto(val) {
    if (typeof val === 'number') return val;
    if (!val) return 0;
    var str = val.toString().trim();
    if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
        var lastDot   = str.lastIndexOf('.');
        var lastComma = str.lastIndexOf(',');
        if (lastComma > lastDot) {
            /* 75.548,14  → Spanish format → swap */
            str = str.replace(/\./g, '').replace(',', '.');
        } else {
            /* 75,548.14  → English format → strip commas */
            str = str.replace(/,/g, '');
        }
    } else if (str.indexOf(',') !== -1) {
        var parts2 = str.split(',');
        if (parts2.length === 2 && parts2[1].length === 3) {
            /* 75,548  → thousands comma, remove it */
            str = str.replace(',', '');
        } else {
            /* 75,14  → decimal comma → convert */
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

/* ============================================================
   ESTADO GLOBAL
   ============================================================ */
var montoBs  = 0;
var montoIva = 0;
var montoSat = 0;
var montoBase = 0;
var retIdx   = 0;
var contIdx  = 0;

/* ============================================================
   FUNCIONES AUXILIARES DE FORMULARIO
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
    var rifTipo   = document.getElementById('np-rif-tipo').value;
    var rifNum    = document.getElementById('np-rif-num').value.trim();
    var rif       = rifTipo + '-' + rifNum;
    var nombre    = document.getElementById('np-nombre').value.trim();
    var telefono  = document.getElementById('np-telefono').value.trim();
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
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.error) { alert('Error: ' + data.error); return; }
        alert('Proveedor creado exitosamente.');
        var select  = document.getElementById('sel-proveedor');
        var lastOpt = select.querySelector('option[value="nuevo"]');
        var option  = document.createElement('option');
        option.value       = data.id;
        option.textContent = rif + ' - ' + nombre;
        if (lastOpt) { select.insertBefore(option, lastOpt); }
        else { select.appendChild(option); }
        select.value = data.id;
        document.getElementById('rif-beneficiario').value = rif;
        document.getElementById('np-rif-tipo').value = 'J';
        document.getElementById('np-rif-num').value  = '';
        document.getElementById('np-nombre').value   = '';
        document.getElementById('np-telefono').value = '';
        document.getElementById('np-direccion').value = '';
        toggleFormProveedor();
    });
}

function cargarProveedor(id) {
    if (!id) {
        document.getElementById('rif-beneficiario').value = '';
        return;
    }
    if (id === 'nuevo') {
        toggleFormProveedor();
        document.getElementById('sel-proveedor').value = '';
        return;
    }
    fetch('../../controllers/proveedores/buscar.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(p) {
            document.getElementById('rif-beneficiario').value = (p && p.rif) ? p.rif : '';
        });
}

/* ============================================================
   ETIQUETA + NÚMERO DE LA ORDEN SELECCIONADA
   El dropdown muestra SOLO el número (p. ej. "025") tras elegir.
   La etiqueta cambia a "No. O/C" o "No. O/S" según el tipo.
   ============================================================ */
var ordenInfoCache = {};

function actualizarNumeroOrden() {
    var sel = document.getElementById('sel-orden');
    var lbl = document.getElementById('oc_label');
    if (!sel) return;

    var tipo = '';
    var numero = '';

    if (sel.selectedIndex > -1) {
        var opc = sel.options[sel.selectedIndex];
        var val = opc.value || '';

        /* Recuperar o construir la info de la orden usando el texto original */
        var info = ordenInfoCache[val];
        if (!info && val !== '') {
            var txt = opc.text || '';
            var mt = (val.indexOf('OC-') === 0) ? 'O.C.' : (val.indexOf('OS-') === 0) ? 'O.S.' : '';
            var mn = txt.match(/N\s*[°º]\s*(.*?)\s*(?:—|–|\(|$)/);
            info = { tipo: mt, numero: mn ? mn[1].trim() : '' };
            ordenInfoCache[val] = info;
        }
        if (info) {
            tipo = info.tipo;
            numero = info.numero;
        }

        /* El dropdown muestra únicamente el número de la orden */
        if (opc && numero !== '') {
            opc.text = numero;
        }
    }

    if (lbl) {
        if (tipo === 'O.C.') {
            lbl.textContent = 'No. O/C';
        } else if (tipo === 'O.S.') {
            lbl.textContent = 'No. O/S';
        } else {
            lbl.textContent = 'No. O/C - O/S';
        }
    }
}

/* ============================================================
   CARGAR ORDEN VINCULADA (O.C. u O.S.)
   ============================================================ */
function cargarOrden(val) {
    montoBsUserEdited = false;
    document.getElementById('cont-body').innerHTML = '';
    document.getElementById('ret-body').innerHTML = '';

    /* Mostrar tipo + número de la orden en el campo de texto */
    actualizarNumeroOrden();

    var hOc   = document.getElementById('h-oc-id');
    var hOs   = document.getElementById('h-os-id');
    var hTipo = document.getElementById('h-doc-tipo');

    if (!val) {
        if (hOc) hOc.value = '0';
        if (hOs) hOs.value = '0';
        if (hTipo) hTipo.value = '';
        montoBs  = 0;
        montoIva = 0;
        montoSat = 0;
        montoBase = 0;
        document.getElementById('monto-bs').value = '0.00';
        var headerTotal = document.getElementById('cont-total-header');
        if (headerTotal) headerTotal.textContent = '0.00';
        document.getElementById('oc_fecha_display').value = '';
        var panelArrReset = document.getElementById('panel-arrendamiento');
        if (panelArrReset) panelArrReset.style.display = 'none';
        var conceptoEl = document.getElementById('concepto');
        if (conceptoEl) {
            conceptoEl.value = '';
            autoExpand(conceptoEl);
        }
        addRetRow();
        recalcContabilidad();
        return;
    }

    var partes = val.split('-');
    var tipo   = partes[0].toLowerCase(); /* 'oc' or 'os' */
    var id     = partes[1];

    if (tipo === 'oc') {
        if (hOc) hOc.value = id;
        if (hOs) hOs.value = '0';
        if (hTipo) hTipo.value = 'OC';
    } else if (tipo === 'os') {
        if (hOc) hOc.value = '0';
        if (hOs) hOs.value = id;
        if (hTipo) hTipo.value = 'OS';
    }

    fetch('../../controllers/ordenes_compra/buscar_op.php?tipo=' + tipo + '&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            montoBs   = (data && data.total_general)  ? parseFloat(data.total_general)  : 0;
            montoBase = (data && data.base_imponible) ? parseFloat(data.base_imponible) : montoBs;
            montoIva  = (data && data.iva_monto)      ? parseFloat(data.iva_monto)      : 0;
            montoSat  = (data && data.sat_monto)      ? parseFloat(data.sat_monto)      : 0;
            document.getElementById('monto-bs').value = fmt(montoBs);

            var headerTotal = document.getElementById('cont-total-header');
            if (headerTotal) headerTotal.textContent = fmt(montoBs);

            if (data && data.fecha) {
                var parts = data.fecha.split('-');
                if (parts.length === 3) {
                    document.getElementById('oc_fecha_display').value = parts[2] + '/' + parts[1] + '/' + parts[0];
                } else {
                    document.getElementById('oc_fecha_display').value = data.fecha;
                }
            }

            if (data && data.proveedor_id) {
                var selProv = document.getElementById('sel-proveedor');
                if (selProv) {
                    selProv.value = data.proveedor_id;
                    cargarProveedor(data.proveedor_id);
                }
            }

            if (data.partidas && data.partidas.length > 0) {
                for (var i = 0; i < data.partidas.length; i++) {
                    var part = data.partidas[i];
                    var pCode = (part.partida ? part.partida.trim() : '');
                    var pMonto = (part.monto !== undefined ? parseFloat(part.monto) : 0);
                    // Skip 0-amount IVA rows if iva_monto is 0
                    if (pMonto <= 0 && (pCode === '4.03.18.01.00' || pCode === '403-18-01-00')) continue;
                    addContRowFromData(pCode, pMonto);
                }
            } else {
                addContRow();
            }

            document.getElementById('ret-body').innerHTML = '';
            var hasRet = false;
            if (montoIva > 0) {
                addRetRow('RETENCION IVA 75%', '01-08-00-00-51-403-18-01-00', 75);
                hasRet = true;
            }
            if (montoSat > 0) {
                addRetRow('RETENCION SAT 0,1%', '01-08-00-00-51-403-18-99-00', 0.1);
                hasRet = true;
            }
            if (!hasRet) {
                addRetRow();
            }

            recalcContabilidad();

            /* ── Detección arrendamiento ── */
            var descOs = (data.descripcion_os || '').toUpperCase();
            var panelArr = document.getElementById('panel-arrendamiento');
            var conceptoEl = document.getElementById('concepto');
            if (descOs.indexOf('ARRENDAMIENTO') !== -1) {
                /* Intentar extraer mes del concepto de la OS */
                var meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO',
                             'JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
                var selMes = document.getElementById('arr-mes');
                for (var mi = 0; mi < meses.length; mi++) {
                    if (descOs.indexOf(meses[mi]) !== -1) {
                        selMes.value = meses[mi];
                        break;
                    }
                }
                if (panelArr) panelArr.style.display = 'block';
                buildConcepto();
            } else {
                if (panelArr) panelArr.style.display = 'none';
                if (conceptoEl && conceptoEl.value.indexOf('CANCELACIÓN DEL CANON DE ARRENDAMIENTO') !== -1) {
                    conceptoEl.value = '';
                }
            }

            if (conceptoEl) autoExpand(conceptoEl);
        });
}

/* =====================================\=======================
   HELPER ARRENDAMIENTO: CONSTRUIR CONCEPTO
   ============================================================ */
function buildConcepto() {
    var mes   = (document.getElementById('arr-mes')        ? document.getElementById('arr-mes').value        : 'JULIO').toUpperCase();
    var anio  = (document.getElementById('arr-anio')       ? document.getElementById('arr-anio').value       : '2026');
    var memo  = 'CM-203-2026';
    var fecha = (document.getElementById('arr-memo-fecha') ? document.getElementById('arr-memo-fecha').value : '09/07/2026');

    var elMemoInp = document.getElementById('arr-memo-num');
    if (elMemoInp) { elMemoInp.value = 'CM-203-2026'; }

    var texto = 'CANCELACIÓN DEL CANON DE ARRENDAMIENTO DE LOS LOCALES UBICADOS EN EL CENTRO' +
        ' COMERCIAL SILVANA, POR EL FUNCIONAMIENTO DE TODAS LAS OFICINAS DE ESTE ORGANISMO' +
        ' CONTRALOR. CORRESPONDIENTE AL MES DE ' + mes + ' DEL AÑO ' + anio +
        '. SEGÚN CONSTA EN MEMORANDUM N° ' + memo + ' DE FECHA ' + fecha + '.';

    var el = document.getElementById('concepto');
    if (el) {
        el.value = texto;
        autoExpand(el);
    }
}

/* ============================================================
   RECALCULAR CONTABILIDAD PRESUPUESTARIA
   ============================================================ */
function recalcContabilidad() {
    var filasCont = document.querySelectorAll('#cont-body tr');
    var sumIva = 0;
    var sumSat = 0;
    var sumBase = 0;
    var sumTotal = 0;

    for (var i = 0; i < filasCont.length; i++) {
        var tr = filasCont[i];
        
        var elObra = tr.querySelector('input[name="cont_obra[]"]');
        var elPart = tr.querySelector('input[name="cont_partid[]"]');
        var elGen  = tr.querySelector('input[name="cont_gen[]"]');
        var obra   = elObra ? elObra.value.trim() : '';
        var partid = elPart ? elPart.value.trim() : '';
        var gen    = elGen ? elGen.value.trim() : '';
        
        var elTotal = tr.querySelector('input[name="cont_total[]"]');
        var valStr = elTotal ? elTotal.value : '0';
        var monto = parseMonto(valStr);
        
        sumTotal += monto;
        
        var fullCode = obra + '-' + partid + '-' + gen;
        if (fullCode.indexOf('403-18-01') !== -1) {
            sumIva += monto;
        } else if (fullCode.indexOf('403-18-99') !== -1) {
            sumSat += monto;
        } else {
            sumBase += monto;
        }
    }

    montoBs   = sumTotal;
    montoIva  = sumIva;
    if (sumSat > 0 || montoSat === 0) {
        montoSat = sumSat;
    }
    if (sumBase > 0 || montoBase === 0) {
        montoBase = sumBase;
    }

    var headerTotal = document.getElementById('cont-total-header');
    if (headerTotal) {
        headerTotal.textContent = fmt(montoBs);
    }
    
    var hMontoBs = document.getElementById('h-monto-bs');
    if (hMontoBs) {
        hMontoBs.value = montoBs.toFixed(2);
    }

    recalcRetenciones();
}



function onRetComisionInput(inputEl) {
    inputEl.dataset.userEdited = 'true';
    recalcRetenciones();
}

function onRetTasaInput(inputEl) {
    var tr = inputEl.closest('tr');
    if (tr) {
        var elCom = tr.querySelector('.ret-comision');
        if (elCom) { delete elCom.dataset.userEdited; }
    }
    recalcRetenciones();
}

/* ============================================================
   RECALCULAR RETENCIONES
   ============================================================ */
var montoBsUserEdited = false;

function recalcRetenciones() {
    var filas    = document.querySelectorAll('#ret-body tr');
    var totalRet = 0;

    for (var i = 0; i < filas.length; i++) {
        var tr     = filas[i];
        var elCod  = tr.querySelector('.ret-codigo');
        var elTasa = tr.querySelector('.ret-tasa');
        var elCom  = tr.querySelector('.ret-comision');
        var codigo = elCod ? elCod.value.trim() : '';
        var tasa   = elTasa ? (parseFloat(elTasa.value) || 0) : 0;

        var com = 0;
        if (elCom && elCom.dataset.userEdited === 'true') {
            com = parseMonto(elCom.value);
        } else {
            var baseCalculo = montoBs;
            if (codigo.indexOf('403-18-01') !== -1) {
                baseCalculo = montoIva;
            } else if (codigo.indexOf('403-18-99') !== -1 && tasa === 100) {
                baseCalculo = montoSat;
            } else {
                baseCalculo = montoBase;
            }
            com = baseCalculo * tasa / 100;
            if (elCom) { elCom.value = fmt(com); }
        }
        totalRet += com;
    }

    var montoNeto = montoBs - totalRet;

    document.getElementById('total-retenciones').textContent = fmt(totalRet);

    var elMontoBsVisible = document.getElementById('monto-bs');
    if (elMontoBsVisible) {
        if (!montoBsUserEdited) {
            elMontoBsVisible.value = fmt(montoNeto);
        } else {
            var userVal = parseMonto(elMontoBsVisible.value);
            if (userVal > 0) montoNeto = userVal;
        }
    }

    document.getElementById('monto-neto').textContent = fmt(montoNeto);

    var letras = montoNeto > 0 ? montoLetras(montoNeto) : '—';
    document.getElementById('monto-letras-txt').textContent = letras;

    document.getElementById('h-letras').value = letras;
    document.getElementById('h-ret').value    = totalRet.toFixed(2);
    document.getElementById('h-neto').value   = montoNeto.toFixed(2);
}

/* ============================================================
   AGREGAR FILA DE RETENCIÓN
   ============================================================ */
function addRetRow(desc, codigo, tasa, monto) {
    var i  = retIdx++;
    var defaultDesc   = desc !== undefined ? desc : '';
    var defaultCodigo = codigo !== undefined ? codigo : '01-08-00-00-51-403-18-01-00';
    var defaultTasa   = tasa !== undefined ? tasa : 75;
    var defaultCom    = monto !== undefined ? (typeof monto === 'number' ? fmt(monto) : monto) : '0.00';

    var tr = document.createElement('tr');
    tr.id  = 'ret' + i;
    tr.innerHTML =
        '<td><input type="text" name="ret_desc[]" class="ret-desc" value="' + defaultDesc + '" placeholder="Descripción"></td>' +
        '<td><input type="text" name="ret_codigo[]" class="ret-codigo" value="' + defaultCodigo + '" placeholder="01-08-00-00-51-403-18-01-00" oninput="recalcRetenciones()"></td>' +
        '<td style="text-align:center;">' +
            '<input type="number" name="ret_tasa[]" class="ret-tasa" value="' + defaultTasa + '" step="0.01" min="0" max="100"' +
            ' oninput="onRetTasaInput(this)" style="text-align:center;">' +
        '</td>' +
        '<td style="text-align:right;">' +
            '<input type="text" name="ret_comision[]" class="ret-comision" value="' + defaultCom + '"' +
            ' style="text-align:right;" oninput="onRetComisionInput(this)">' +
        '</td>' +
        '<td style="text-align:center;" class="no-print">' +
            '<button type="button" onclick="delRetRow(' + i + ')"' +
            ' style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button>' +
        '</td>';
    document.getElementById('ret-body').appendChild(tr);
    recalcRetenciones();
}

function delRetRow(i) {
    var tr = document.getElementById('ret' + i);
    if (tr) { tr.parentNode.removeChild(tr); }
    recalcRetenciones();
}

/* ============================================================
   FILAS DE CONTABILIDAD PRESUPUESTARIA
   ============================================================ */
function addContRowFromFull(anio, sector, prog, subprog, proy, acti, obra, partid, gen, espec, sub, monto) {
    var i   = contIdx++;
    var tr  = document.createElement('tr');
    tr.id   = 'cont' + i;
    tr.innerHTML =
        '<td><input type="text" name="cont_anio[]"    value="' + (anio || '2026') + '"></td>' +
        '<td><input type="text" name="cont_sector[]"  value="' + (sector || '01') + '"></td>' +
        '<td><input type="text" name="cont_prog[]"    value="' + (prog || '08') + '"></td>' +
        '<td><input type="text" name="cont_subprog[]" value="' + (subprog || '00') + '"></td>' +
        '<td><input type="text" name="cont_proy[]"    value="' + (proy || '00') + '"></td>' +
        '<td><input type="text" name="cont_acti[]"    value="' + (acti || '51') + '"></td>' +
        '<td><input type="text" name="cont_obra[]"    value="' + (obra || '') + '"></td>' +
        '<td><input type="text" name="cont_partid[]"  value="' + (partid || '') + '"></td>' +
        '<td><input type="text" name="cont_gen[]"     value="' + (gen || '') + '"></td>' +
        '<td><input type="text" name="cont_espec[]"   value="' + (espec || '') + '"></td>' +
        '<td><input type="text" name="cont_sub[]"     value="' + (sub || '') + '"></td>' +
        '<td style="text-align:right;">' +
            '<input type="text" name="cont_total[]" value="' + fmt(parseFloat(monto) || 0) + '"' +
            ' style="text-align:right;">' +
        '</td>' +
        '<td style="text-align:center;" class="no-print">' +
            '<button type="button" onclick="delContRow(' + i + ')"' +
            ' style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button>' +
        '</td>';
    document.getElementById('cont-body').appendChild(tr);
    recalcContabilidad();
}

function addContRowFromData(partida, monto) {
    /* Split partida like "4.02.05.01.00" → ['4','02','05','01','00'] */
    var parts = (partida && partida.length > 0) ? partida.split('.') : [];
    while (parts.length < 5) { parts.push(''); }

    /* OBRA COD = seg[0]+seg[1]  (e.g. '4'+'02' = '402') */
    var obra   = (parts[0] || '') + (parts[1] || '');
    var partid = parts[2] || '';
    var gen    = parts[3] || '';
    var espec  = parts[4] || '';

    addContRowFromFull('2026', '01', '08', '00', '00', '51', obra, partid, gen, espec, '', monto);
}

function addContRow() {
    addContRowFromFull('2026', '01', '08', '00', '00', '51', '', '', '', '', '', 0);
}

function delContRow(i) {
    var tr = document.getElementById('cont' + i);
    if (tr) { tr.parentNode.removeChild(tr); }
    recalcContabilidad();
}

/* ============================================================
   PREPARAR ENVÍO
   ============================================================ */
function prepararEnvio() {
    var rows  = [];
    var filas = document.querySelectorAll('#cont-body tr');
    for (var i = 0; i < filas.length; i++) {
        var tr = filas[i];
        var get = function(name) {
            var el = tr.querySelector('input[name="' + name + '"]');
            return el ? el.value : '';
        };
        rows.push({
            anio:    get('cont_anio[]'),
            sector:  get('cont_sector[]'),
            prog:    get('cont_prog[]'),
            subprog: get('cont_subprog[]'),
            proy:    get('cont_proy[]'),
            acti:    get('cont_acti[]'),
            obra:    get('cont_obra[]'),
            partid:  get('cont_partid[]'),
            gen:     get('cont_gen[]'),
            espec:   get('cont_espec[]'),
            sub:     get('cont_sub[]')
        });
    }
    document.getElementById('h-cont').value = JSON.stringify(rows);
}

function validarYPrepararEnvio(e) {
    recalcContabilidad();
    prepararEnvio();

    /* ── Regla 1: Partida 18-99-00 (SAT) en CONTABILIDAD PRESUPUESTARIA debe ser IDÉNTICA al monto de RETENCION SAT 0.1% ── */
    var montoRetSat = 0;
    var filasRet = document.querySelectorAll('#ret-body tr');
    for (var i = 0; i < filasRet.length; i++) {
        var trR = filasRet[i];
        var elCodR  = trR.querySelector('.ret-codigo');
        var elDescR = trR.querySelector('.ret-desc');
        var elComR  = trR.querySelector('.ret-comision');
        var codR  = elCodR ? elCodR.value.trim() : '';
        var descR = elDescR ? elDescR.value.trim().toUpperCase() : '';
        if (codR.indexOf('18-99') !== -1 || descR.indexOf('SAT') !== -1 || descR.indexOf('0.1') !== -1) {
            montoRetSat += parseMonto(elComR ? elComR.value : 0);
        }
    }

    var montoPartidaSat = 0;
    var filasCont = document.querySelectorAll('#cont-body tr');
    for (var j = 0; j < filasCont.length; j++) {
        var trC = filasCont[j];
        var elPart = trC.querySelector('input[name="cont_partid[]"]');
        var elGen  = trC.querySelector('input[name="cont_gen[]"]');
        var elTot  = trC.querySelector('input[name="cont_total[]"]');
        var part = elPart ? elPart.value.trim() : '';
        var gen  = elGen ? elGen.value.trim() : '';
        var tot  = parseMonto(elTot ? elTot.value : 0);

        if ((part === '18' && gen === '99') || (part === '403' && gen === '18-99')) {
            montoPartidaSat += tot;
        }
    }

    if (montoRetSat > 0 && Math.abs(montoRetSat - montoPartidaSat) > 0.01) {
        alert('⚠️ Error de Validación (Regla SAT):\n\nLa partida 18-99-00 en Contabilidad Presupuestaria (Bs. ' + fmt(montoPartidaSat) + ') debe ser IDÉNTICA al monto de RETENCIÓN SAT 0.1% (Bs. ' + fmt(montoRetSat) + ').\n\nPor favor corrija la cifra antes de continuar.');
        if (e && e.preventDefault) e.preventDefault();
        return false;
    }

    /* ── Regla 3: TOTAL Bs de Contabilidad debe ser EXACTAMENTE igual a MONTO NETO A PAGAR + MONTO A RETENER ── */
    var totalRetenciones = parseMonto(document.getElementById('total-retenciones') ? document.getElementById('total-retenciones').textContent : 0);
    var montoNeto = parseMonto(document.getElementById('monto-neto') ? document.getElementById('monto-neto').textContent : 0);
    var totalEsperado = Math.round((montoNeto + totalRetenciones) * 100) / 100;
    var totalContabilidad = Math.round(montoBs * 100) / 100;

    if (Math.abs(totalEsperado - totalContabilidad) > 0.01) {
        var diff = Math.abs(totalEsperado - totalContabilidad);
        alert('⚠️ Error de Cuadre al Centavo:\n\nEl TOTAL de Contabilidad Presupuestaria (Bs. ' + fmt(totalContabilidad) + ') debe ser EXACTAMENTE igual a MONTO NETO A PAGAR + RETENCIONES (Bs. ' + fmt(totalEsperado) + ').\n\nDiferencia detectada: Bs. ' + fmt(diff) + '.\n(Sugerencia: Verifique los centavos en la partida del SAT).');
        if (e && e.preventDefault) e.preventDefault();
        return false;
    }

    return true;
}

/* ============================================================
   INICIALIZACIÓN
   ============================================================ */

function initCuentaInput() {
    var cuentaInput = document.getElementById('numero_cuenta');
    if (!cuentaInput) return;
    cuentaInput.addEventListener('input', function() {
        var digits = cuentaInput.value.replace(/\D/g, '');
        if (digits.length > 20) digits = digits.slice(0, 20);
        if (digits.length > 10) {
            cuentaInput.value = digits.slice(0, 4) + '-' + digits.slice(4, 8) + '-' + digits.slice(8, 10) + '-' + digits.slice(10);
        } else if (digits.length > 8) {
            cuentaInput.value = digits.slice(0, 4) + '-' + digits.slice(4, 8) + '-' + digits.slice(8);
        } else if (digits.length > 4) {
            cuentaInput.value = digits.slice(0, 4) + '-' + digits.slice(4);
        } else {
            cuentaInput.value = digits;
        }
    });
}

function initRifInput() {
    var rifInput = document.getElementById('np-rif-num');
    if (!rifInput) return;
    rifInput.addEventListener('input', function() {
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

function autoExpand(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = (el.scrollHeight) + 'px';
}

document.addEventListener('input', function (e) {
    if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.classList.contains('auto-expand'))) {
        autoExpand(e.target);
    }
});

/* ── Detección de Cambios no Guardados ── */
var formDirty = false;
var isSubmitting = false;

document.addEventListener('DOMContentLoaded', function() {
    initRifInput();
    initCuentaInput();
    document.querySelectorAll('textarea').forEach(autoExpand);

    <?php if ($op_edit && !empty($op_edit['banco'])): ?>
    var selBanco = document.getElementById('banco');
    if (selBanco) { selBanco.value = <?php echo json_encode($op_edit['banco']); ?>; }
    <?php endif; ?>

    <?php if ($edit_id > 0): ?>
        // Load database retenciones
        <?php foreach ($retenciones_edit as $r): ?>
            addRetRow(
                <?php echo json_encode($r['descripcion']); ?>,
                <?php echo json_encode($r['codigo_presupuestario']); ?>,
                <?php echo (float)$r['tasa']; ?>,
                <?php echo json_encode(number_format((float)$r['monto_comision'], 2, '.', ',')); ?>
            );
        <?php endforeach; ?>
        <?php if (empty($retenciones_edit)): ?>
            addRetRow();
        <?php endif; ?>

        // Load database contabilidad
        var contEditData = <?php echo json_encode($contabilidad_edit); ?>;
        if (Array.isArray(contEditData) && contEditData.length > 0) {
            contEditData.forEach(function(c) {
                var m = c.total !== undefined ? parseMonto(c.total) : 0;
                addContRowFromFull(
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
        } else {
            addContRow();
        }
        recalcContabilidad();
    <?php else: ?>
        addRetRow();
        addContRow();
        recalcContabilidad();
    <?php endif; ?>

    var elMontoBsVisible = document.getElementById('monto-bs');
    if (elMontoBsVisible) {
        elMontoBsVisible.addEventListener('input', function() {
            montoBsUserEdited = true;
            var userVal = parseMonto(this.value);
            document.getElementById('monto-neto').textContent = fmt(userVal);
            var letras = userVal > 0 ? montoLetras(userVal) : '—';
            document.getElementById('monto-letras-txt').textContent = letras;
            document.getElementById('h-letras').value = letras;
            document.getElementById('h-neto').value = userVal.toFixed(2);
        });
    }

    var formOP = document.getElementById('form-op');
    if (formOP) {
        formOP.addEventListener('input', function () { formDirty = true; });
        formOP.addEventListener('change', function () { formDirty = true; });
        formOP.addEventListener('submit', function (e) {
            if (!validarYPrepararEnvio(e)) {
                return false;
            }
            isSubmitting = true;
        });
    }

    var contBody = document.getElementById('cont-body');
    if (contBody) {
        contBody.addEventListener('input', function(e) {
            if (e.target && (e.target.name === 'cont_total[]' || e.target.name === 'cont_obra[]' || e.target.name === 'cont_partid[]' || e.target.name === 'cont_gen[]')) {
                recalcContabilidad();
            }
        });
    }

    /* Pre-cargar OC u OS si se pasa por parametro URL ?oc_id=X o ?os_id=Y */
    var urlParams = new URLSearchParams(window.location.search);
    var idParam = urlParams.get('id');
    var ocParam = urlParams.get('oc_id');
    var osParam = urlParams.get('os_id');
    var selOrden = document.getElementById('sel-orden');
    if (!idParam) {
        if (ocParam && selOrden) {
            selOrden.value = 'OC-' + ocParam;
            cargarOrden('OC-' + ocParam);
        } else if (osParam && selOrden) {
            selOrden.value = 'OS-' + osParam;
            cargarOrden('OS-' + osParam);
        }
    }
});

window.addEventListener('beforeunload', function (e) {
    if (formDirty && !isSubmitting) {
        var msg = 'Tiene información no guardada. Si sale de la página, los datos ingresados se perderán.';
        (e || window.event).returnValue = msg;
        return msg;
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
        if (activePopup && !activePopup.contains(e.target) && !e.target.closest('#fecha_op_display, #recibe_fecha_display, #arr-memo-fecha')) {
            closePopup();
        }
    });

    window.openDatePicker = function(displayEl, hiddenEl, onChangeCallback) {
        if (activePopup) {
            var wasSame = (activePopup.dataset.targetId === displayEl.id);
            closePopup();
            if (wasSame) return;
        }

        var val = (hiddenEl && hiddenEl.value) ? hiddenEl.value : '';
        if (!val && displayEl.value) {
            var dp = displayEl.value.split('/');
            if (dp.length === 3) {
                val = dp[2] + '-' + dp[1] + '-' + dp[0];
            }
        }

        var parts = val.split('-');
        var today = new Date();
        var currYear = parts.length === 3 ? parseInt(parts[0], 10) : today.getFullYear();
        var currMonth = parts.length === 3 ? parseInt(parts[1], 10) - 1 : today.getMonth();

        var pop = document.createElement('div');
        pop.className = 'sp-picker-popover';
        pop.dataset.targetId = displayEl.id || '';

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

            var curVal = (hiddenEl && hiddenEl.value) ? hiddenEl.value : '';
            if (!curVal && displayEl.value) {
                var cdp = displayEl.value.split('/');
                if (cdp.length === 3) curVal = cdp[2] + '-' + cdp[1] + '-' + cdp[0];
            }
            var selParts = curVal.split('-');
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
                    if (hiddenEl) {
                        hiddenEl.value = currYear + '-' + mStr + '-' + dStr;
                    }
                    displayEl.value = dStr + '/' + mStr + '/' + currYear;
                    formDirty = true;
                    closePopup();
                    if (typeof onChangeCallback === 'function') {
                        onChangeCallback(displayEl.value);
                    }
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