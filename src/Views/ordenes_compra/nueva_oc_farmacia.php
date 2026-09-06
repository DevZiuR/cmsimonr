<?php
session_start();

if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
if (!$es_admin) {
    header('Location: index.php');
    exit;
}

require_once '../../../config/conexion.php';
$proveedores = mysqli_query($conn, "SELECT id, rif, razon_social FROM proveedores ORDER BY razon_social");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva OC – Farmacia / Productos Exentos</title>
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
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

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; font-size: 12px; background: #f0f2f5; display: flex; flex-direction: column; min-height: 100vh; }

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
        /* ── Main ── */
        .main-content { flex: 1; padding: 20px; overflow-x: auto; }
        /* ── Footer ── */
        .site-footer { background: #080616; color: #90a4ae; text-align: center; padding: 10px 20px; font-size: 10px; border-top: 3px solid #1565c0; line-height: 1.8; margin-top: auto; }
        .site-footer strong { color: #e3f2fd; }

        .container { background: white; padding: 20px; border: 1px solid #ccc; }
        .header { text-align: center; flex-grow: 1; padding: 0 15px; }
        .header p { font-size: 11px; line-height: 1.6; }
        .header h2 { font-size: 14px; margin-top: 8px; text-transform: uppercase; letter-spacing: 1px; }


        /* Badge farmacia en el encabezado del form */
        .badge-farmacia {
            display: inline-block;
            background: #1b5e20;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 10px;
            border-radius: 2px;
            letter-spacing: 0.5px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
        .meta-grid.three { grid-template-columns: 2fr 1fr 1fr; }
        .field { display: flex; flex-direction: column; gap: 3px; }
        .field label { font-size: 10px; font-weight: bold; }
        .field input, .field select, .field textarea { border: 1px solid #999; padding: 4px 6px; font-size: 12px; width: 100%; font-family: inherit; resize: none; overflow: hidden; box-sizing: border-box; line-height: 1.35; }
        .field input[readonly], .field textarea[readonly] { background: #f0f0f0; }

        .section-title {
            background: #c8e6c9; font-weight: bold; font-size: 11px;
            text-align: center; padding: 5px; border: 1px solid #81c784;
            text-transform: uppercase; margin: 12px 0 0;
            color: #1b5e20;
        }

        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        table th { background: #a5d6a7; border: 1px solid #81c784; padding: 5px 4px; text-align: center; font-size: 10px; color: #1b5e20; }
        table td { border: 1px solid #ccc; padding: 2px 3px; }
        table td input { width: 100%; border: none; font-size: 11px; padding: 2px; background: transparent; outline: none; }
        table td input:focus { background: #f1f8e9; }
        td.right input, td.right { text-align: right; }
        td.center input, td.center { text-align: center; }

        /* ── Autocomplete Sugerencias ── */
        table tr { position: relative; }
        table tr:focus-within, table tr.has-suggestions { position: relative; z-index: 1000 !important; }
        .desc-cell { position: relative; vertical-align: top; }
        .desc-cell:focus-within, .desc-cell.has-suggestions { position: relative; z-index: 1001 !important; }
        .desc-autocomplete-wrapper { position: relative; width: 100%; }
        .sug-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 2px);
            left: 0;
            width: 100%;
            min-width: 320px;
            max-height: 200px;
            overflow-y: auto;
            background: #ffffff !important;
            border: 1.5px solid #2e7d32 !important;
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
        .sug-item:last-child { border-bottom: none; }
        .sug-item:hover { background: #f1f8e9 !important; }
        .sug-item strong { color: #0f172a; font-size: 11px; text-align: left; }
        .sug-item .imput-pill {
            color: #1b5e20;
            background: #e8f5e9;
            font-weight: 700;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            white-space: nowrap;
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        .btn-add { font-size: 11px; color: #2e7d32; background: none; border: none; cursor: pointer; padding: 5px 0; margin-top: 4px; }
        .totales-wrap { display: flex; justify-content: flex-end; margin-top: 10px; }
        .totales-box { width: 280px; border: 1px solid #ccc; }
        .tot-row { display: flex; justify-content: space-between; padding: 3px 8px; border-bottom: 1px solid #eee; font-size: 12px; }
        .tot-row.total { font-weight: bold; background: #f1f8e9; border-top: 2px solid #2e7d32; border-bottom: none; }
        .tot-row.exento { background: #e8f5e9; color: #1b5e20; font-weight: bold; }

        .monto-letras { border: 1px solid #ccc; padding: 8px; font-size: 11px; margin-top: 8px; background: #fafafa; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 30px; }
        .firma-box { border-top: 1px solid #000; padding-top: 4px; text-align: center; font-size: 10px; }
        .condicion { font-size: 9px; border-top: 1px solid #ccc; text-transform: uppercase; margin-top: 15px; padding-top: 6px; text-align: center; color: #555; }
        .btns { margin-top: 15px; display: flex; gap: 8px; }
        .btn-guardar { background: #2e7d32; color: white; border: none; padding: 8px 20px; font-size: 12px; cursor: pointer; }
        .btn-imprimir { background: #FF0000; color: white; border: none; padding: 8px 16px; font-size: 12px; cursor: pointer; }

        /* Nota de exención */
        .nota-exenta {
            border: 1px solid #81c784;
            background: #e8f5e9;
            padding: 6px 10px;
            font-size: 10px;
            color: #1b5e20;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        @media print {
            @page { margin: 1cm; margin-top: 1cm; margin-bottom: 1cm;
                @top-center    { content: none; }
                @bottom-center { content: none; }
                @top-left      { content: none; }
                @top-right     { content: none; }
                @bottom-left   { content: none; }
                @bottom-right  { content: none; }
            }
            .no-print      { display: none !important; }
            .site-header   { display: none !important; }
            .site-footer   { display: none !important; }
            .sidebar       { display: none !important; }
            .btns          { display: none !important; }
            .btn-add       { display: none !important; }
            body { background: white; }
            .layout { display: block; }
            .main-content { padding: 0; }
            .container { border: none; margin: 0; padding: 4px; }
        }
    </style>
</head>
<body><div class="layout">
<?php $active = 'oc-nueva'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
<main class="main-content">
<div class="container">

    <!-- ENCABEZADO -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
        <div style="flex-shrink:0;">
            <img src="/sistema/assets/img/logo.png" alt="Logo" style="width:85px; height:auto; display:block;">
        </div>
        <div class="header">
            <p><strong>REPÚBLICA BOLIVARIANA DE VENEZUELA</strong><br>
            ESTADO ANZOÁTEGUI<br>
            <strong>CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</strong></p>
            <h2>Orden de Compras</h2>
            <span class="badge-farmacia">🏥 Productos Farmacéuticos – Exento de IVA</span>
        </div>
        <div style="flex-shrink:0;">
            <img src="/sistema/assets/img/sncf.png" alt="NCF" style="width:85px; height:auto; display:block;">
        </div>
    </div>

    <!-- Campo oculto que identifica esta OC como farmacia -->
    <form method="POST" action="../../controllers/ordenes_compra/guardar_oc.php" id="form-oc">
    <input type="hidden" name="tipo" value="farmacia">

        <div class="field">
            <label>Proveedor</label>
            <div style="display:flex; gap:6px; align-items:center;">
                <select name="proveedor_id" id="sel-proveedor" required onchange="cargarProveedor(this.value)"
                    style="width:auto; flex:1; max-width:300px;">
                    <option value="">-- Seleccionar --</option>
                    <?php while($p = mysqli_fetch_assoc($proveedores)): ?>
                    <option value="<?= htmlspecialchars($p['id']) ?>"><?= htmlspecialchars($p['rif']) ?> - <?= htmlspecialchars($p['razon_social']) ?></option>
                    <?php endwhile; ?>
                    <option value="nuevo" style="font-weight:bold; color:#1565c0;">+ Agregar Nuevo Proveedor...</option>
                </select>
                <button type="button" class="no-print" onclick="toggleFormProveedor()"
                    style="background:#2e7d32; color:white; border:none; padding:4px 10px; font-size:11px; cursor:pointer; white-space:nowrap; flex-shrink:0;">
                    + Nuevo
                </button>
            </div>

            <!-- Formulario rapido de nuevo proveedor -->
            <div id="form-nuevo-proveedor" style="display:none; border:1px solid #81c784; padding:10px; margin-bottom:10px; background:#f1f8e9;">
                <strong style="font-size:11px;">NUEVO PROVEEDOR</strong>
                <div class="meta-grid three" style="margin-top:8px;">
                    <div class="field">
                        <label>RIF</label>
                        <div style="display:flex; gap:4px;">
                            <select id="np-rif-tipo" style="width:55px; border:1px solid #ccc; padding:4px; font-size:12px;">
                                <option value="J">J</option>
                                <option value="V">V</option>
                                <option value="G">G</option>
                                <option value="E">E</option>
                                <option value="P">P</option>
                            </select>
                            <input type="text" id="np-rif-num" placeholder="12345678-9" style="flex:1;">
                        </div>
                    </div>
                    <div class="field">
                        <label>Razón Social</label>
                        <input type="text" id="np-nombre" placeholder="FARMACIA XYZ, C.A.">
                    </div>
                    <div class="field">
                        <label>Teléfono</label>
                        <input type="text" id="np-telefono" placeholder="0283-0000000">
                    </div>
                </div>
                <div class="field" style="margin-top:6px;">
                    <label>Dirección</label>
                    <input type="text" id="np-direccion" placeholder="Av. Principal...">
                </div>
                <div style="margin-top:8px; display:flex; gap:8px;">
                    <button type="button" onclick="guardarNuevoProveedor()"
                        style="background:#2e7d32; color:white; border:none; padding:5px 14px; font-size:11px; cursor:pointer;">
                        Guardar proveedor
                    </button>
                    <button type="button" onclick="toggleFormProveedor()"
                        style="background:#888; color:white; border:none; padding:5px 14px; font-size:11px; cursor:pointer;">
                        Cancelar
                    </button>
                </div>
            </div>

            <div class="meta-grid three" style="margin-top:8px;">
                <div class="field">
                    <label>N° Orden</label>
                    <input type="text" name="numero_orden" required placeholder="000">
                </div>
                <div class="field">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>

        <div class="meta-grid" style="margin-bottom:10px; margin-top:10px;">
            <div class="field">
                <label>Dirección del proveedor</label>
                <textarea name="direccion_proveedor" id="dir-prov" readonly class="auto-expand" rows="1" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()"></textarea>
            </div>
            <div class="field">
                <label>Lugar de entrega</label>
                <textarea name="lugar_entrega" class="auto-expand" rows="1" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()" placeholder="Av. Francisco de Miranda..."></textarea>
            </div>
        </div>

        <div class="field" style="margin-bottom:10px;">
            <label>Teléfono</label>
            <textarea name="telefono_proveedor" id="tel-prov" readonly class="auto-expand" rows="1" style="max-width:200px;"></textarea>
        </div>

        <!-- Nota de exención visible en pantalla y en impresión -->
        <div class="nota-exenta">
            ⚕ Productos farmacéuticos – EXENTO DE IVA conforme a la Ley del IVA vigente (Art. 18 y ss.)
        </div>

        <div style="text-align:right; font-size:11px; color:#555; margin-bottom:4px;">
            DIRECCIÓN DE ADMINISTRACIÓN PLANIFICACIÓN Y PRESUPUESTO
        </div>

        <!-- TABLA DE RENGLONES -->
        <div class="section-title">Renglones de la Orden de Compras – Productos Farmacéuticos</div>
        <table id="tabla-oc">
            <thead>
                <tr>
                    <th style="width:35%">DESCRIPCIÓN (MEDICAMENTO / INSUMO)</th>
                    <th style="width:19%">IMPUT. PPTARIA.</th>
                    <th style="width:8%">UNIDAD</th>
                    <th style="width:7%">CANT.</th>
                    <th style="width:13%">P. UNITARIO</th>
                    <th style="width:13%">TOTAL Bs.</th>
                    <th style="width:5%" class="no-print"></th>
                </tr>
            </thead>
            <tbody id="renglones">
                <!-- Filas generadas por addRow() en JavaScript -->
            </tbody>
        </table>

        <button type="button" class="btn-add no-print" onclick="addRow()">+ Agregar renglón</button>

        <!-- TOTALES — SIN IVA -->
        <div class="totales-wrap">
            <div class="totales-box">
                <div class="tot-row"><span>Base Imponible:</span><span id="t-base">0,00</span></div>
                <div class="tot-row"><span>SAT 0,1%:</span><span id="t-sat">0,00</span></div>
                <div class="tot-row"><span>Sub-Total:</span><span id="t-sub">0,00</span></div>
                <div class="tot-row exento"><span>IVA:</span><span>EXENTO</span></div>
                <div class="tot-row total"><span>Total General:</span><span id="t-total">0,00</span></div>
            </div>
        </div>

        <!-- Campos ocultos llenados por JS antes de enviar -->
        <input type="hidden" name="base_imponible" id="h-base">
        <input type="hidden" name="sat_monto"      id="h-sat">
        <input type="hidden" name="iva_monto"      id="h-iva" value="0">
        <input type="hidden" name="total_general"  id="h-total">
        <input type="hidden" name="monto_letras"   id="h-letras">
        <input type="hidden" name="renglones_json" id="h-renglones">

        <div class="monto-letras">
            Monto total en letra: <strong id="monto-letras-txt">—</strong>
        </div>

        <!-- PARTIDAS -->
        <div class="section-title" style="margin-top:10px;">Por Compras y Servicios</div>
        <table>
            <thead>
                <tr>
                    <th style="width:50%">Partida</th>
                    <th style="width:50%">Monto</th>
                </tr>
            </thead>
            <tbody id="partidas-body">
                <!-- Se genera automático -->
            </tbody>
        </table>

        <!-- FIRMAS -->
        <div class="firmas">
            <div class="firma-box">Jefa de Planificación y Presupuesto</div>
            <div class="firma-box">Firma y Cédula de Identidad</div>
            <div class="firma-box">Contralora Municipal Provisional</div>
        </div>
        <div style="margin-top:20px; border-top:1px solid #000; padding-top:6px; font-size:10px;">
            Director de Administración, Planificación y Presupuesto:
        </div>

        <p class="condicion">
            El organismo se reserva el derecho de anular unilateralmente la presente orden de compras sin indemnización de conformidad con lo dispuesto en la ley que rige la materia.
        </p>

        <div class="btns no-print">
            <button type="submit" class="btn-guardar">💾 Guardar</button>
            <button type="button" class="btn-imprimir" onclick="window.print()">Imprimir / PDF 🖨</button>
        </div>

    </form>
</div><!-- /.container -->
</main>
</div><!-- /.layout --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?><script>
let rowIdx = 0;

const ONES  = ['','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve',
                'diez','once','doce','trece','catorce','quince','dieciséis','diecisiete',
                'dieciocho','diecinueve','veinte'];
const TENS  = ['','','veinte','treinta','cuarenta','cincuenta','sesenta','setenta','ochenta','noventa'];
const HUNDS = ['','cien','doscientos','trescientos','cuatrocientos','quinientos',
               'seiscientos','setecientos','ochocientos','novecientos'];

function inWords(n) {
    n = Math.floor(n);
    if (n === 0) return 'cero';
    if (n <= 20) return ONES[n];
    if (n < 100) {
        const d = Math.floor(n/10), u = n%10;
        if (d === 2 && u > 0) return 'veinti' + ONES[u];
        return TENS[d] + (u ? ' y ' + ONES[u] : '');
    }
    if (n < 1000) {
        const c = Math.floor(n/100), r = n%100;
        if (n === 100) return 'cien';
        return HUNDS[c] + (r ? ' ' + inWords(r) : '');
    }
    if (n < 1000000) {
        const m = Math.floor(n/1000), r = n%1000;
        return (m === 1 ? 'mil' : inWords(m) + ' mil') + (r ? ' ' + inWords(r) : '');
    }
    return n.toString();
}

function montoLetras(val) {
    const entero = Math.floor(val);
    const cents  = Math.round((val - entero) * 100);
    return inWords(entero).toUpperCase() + ' BOLÍVARES CON ' + String(cents).padStart(2,'0') + '/100';
}

function fmt(n) {
    return n.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
}

function cargarProveedor(id) {
    if (!id) return;
    if (id === 'nuevo') {
        toggleFormProveedor();
        document.getElementById('sel-proveedor').value = '';
        return;
    }
    fetch('../../controllers/proveedores/buscar.php?id=' + id)
        .then(r => r.json())
        .then(p => {
            document.getElementById('dir-prov').value = p.direccion || '';
            document.getElementById('tel-prov').value = p.telefono || '';
            autoExpand(document.getElementById('dir-prov'));
            autoExpand(document.getElementById('tel-prov'));
        });
}

function buscarProducto(input, i) {
    input.value = input.value.toUpperCase();
    const q = input.value;
    const sug = document.getElementById('sug' + i);
    const tr = input.closest('tr');
    const td = input.closest('td');

    if (q.length < 2) {
        if (sug) sug.style.display = 'none';
        if (tr) tr.classList.remove('has-suggestions');
        if (td) td.classList.remove('has-suggestions');
        return;
    }

    fetch('../../controllers/productos/buscar.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            if (!data || data.length === 0) {
                if (sug) sug.style.display = 'none';
                if (tr) tr.classList.remove('has-suggestions');
                if (td) td.classList.remove('has-suggestions');
                return;
            }
            sug.innerHTML = data.map(p => {
                const descEsc = (p.descripcion || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const imputEsc = (p.imput_presupuestaria || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return `
                    <div class="sug-item" onclick="seleccionarProducto(${i}, '${descEsc}', '${imputEsc}')">
                        <strong>${p.descripcion}</strong>
                        <span class="imput-pill">${p.imput_presupuestaria || ''}</span>
                    </div>
                `;
            }).join('');
            sug.style.display = 'block';
            if (tr) tr.classList.add('has-suggestions');
            if (td) td.classList.add('has-suggestions');
        });
}

function seleccionarProducto(i, desc, imput) {
    const tr = document.getElementById('r' + i);
    if (!tr) return;
    const descEl = tr.querySelector('input[name="desc[]"]');
    const imputEl = tr.querySelector('input[name="imput[]"]');
    if (descEl) descEl.value = desc;
    if (imputEl) imputEl.value = imput;
    const sug = document.getElementById('sug' + i);
    if (sug) sug.style.display = 'none';
    tr.classList.remove('has-suggestions');
    const td = tr.querySelector('.desc-cell') || tr.querySelector('td');
    if (td) td.classList.remove('has-suggestions');
    recalc();
}

function addRow() {
    const i = rowIdx++;
    const tr = document.createElement('tr');
    tr.id = 'r' + i;
    tr.innerHTML = `
    <td class="desc-cell">
        <div class="desc-autocomplete-wrapper">
            <input type="text" name="desc[]" placeholder="MEDICAMENTO / INSUMO"
                style="text-transform:uppercase; width:100%"
                oninput="buscarProducto(this, ${i})"
                autocomplete="off">
            <div id="sug${i}" class="sug-dropdown"></div>
        </div>
    </td>
    <td style="position:relative">
        <input type="text" name="imput[]" placeholder="4.02.05.01.00"
            oninput="recalc()" id="imput${i}">
        <button type="button" onclick="guardarProducto(${i})"
            class="no-print"
            style="position:absolute; right:2px; top:2px; font-size:9px;
                   background:#2e7d32; color:white; border:none;
                   padding:1px 5px; cursor:pointer;">
            +
        </button>
    </td>
    <td class="center">
        <input type="text" name="unidad[]" value="1" readonly
            style="text-align:center; background:#f0f0f0; cursor:default;">
    </td>
    <td class="right">
        <input type="number" name="cant[]" value="1" min="1" step="1"
            oninput="calcRow(${i})" style="text-align:right">
    </td>
    <td class="right">
        <input type="text" name="pu[]" value="0"
            oninput="formatearPU(this, ${i})"
            style="text-align:right">
    </td>
    <td class="right" id="tot${i}">0,00</td>
    <td class="center no-print">
        <button type="button" onclick="delRow(${i})"
            style="background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;">X</button>
    </td>
    `;
    document.getElementById('renglones').appendChild(tr);
}

function toggleFormProveedor() {
    const f = document.getElementById('form-nuevo-proveedor');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
    if (f.style.display === 'block') {
        const inp = document.getElementById('np-rif-num');
        if (inp) {
            inp.value = '';
            inp.focus();
        }
        document.getElementById('np-rif-tipo').value = 'J';
    }
}

function guardarNuevoProveedor() {
    const rifTipo   = document.getElementById('np-rif-tipo').value;
    const rifNum    = document.getElementById('np-rif-num').value.trim();
    const rif       = rifTipo + '-' + rifNum;
    const nombre    = document.getElementById('np-nombre').value.trim();
    const telefono  = document.getElementById('np-telefono').value.trim();
    const direccion = document.getElementById('np-direccion').value.trim();
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
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert('Error: ' + data.error); return; }
        alert('Proveedor creado exitosamente.');
        const select = document.getElementById('sel-proveedor');
        const lastOpt = select.querySelector('option[value="nuevo"]');
        const option = document.createElement('option');
        option.value = data.id;
        option.textContent = rif + ' - ' + nombre;
        if (lastOpt) {
            select.insertBefore(option, lastOpt);
        } else {
            select.appendChild(option);
        }
        select.value = data.id;
        document.getElementById('dir-prov').value = direccion;
        document.getElementById('tel-prov').value = telefono;
        autoExpand(document.getElementById('dir-prov'));
        autoExpand(document.getElementById('tel-prov'));
        document.getElementById('np-rif-tipo').value = 'J';
        document.getElementById('np-rif-num').value  = '';
        document.getElementById('np-nombre').value    = '';
        document.getElementById('np-telefono').value  = '';
        document.getElementById('np-direccion').value = '';
        toggleFormProveedor();
    });
}

function delRow(i) {
    const r = document.getElementById('r' + i);
    if (r) r.remove();
    recalc();
}

function formatearPU(input, i) {
    let raw = input.value.replace(/[^0-9,]/g, '');
    let partes = raw.split(',');
    let entero = partes[0].replace(/\./g, '');
    let decimal = partes[1] !== undefined ? partes[1].substring(0, 2) : null;
    entero = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = decimal !== null ? entero + ',' + decimal : entero;
    calcRow(i);
}

function calcRow(i) {
    const tr = document.getElementById('r' + i);
    if (!tr) return;
    const cant = parseFloat(tr.querySelector('input[name="cant[]"]').value) || 0;
    const puRaw = tr.querySelector('input[name="pu[]"]').value
        .replace(/\./g, '').replace(',', '.');
    const pu = parseFloat(puRaw) || 0;
    document.getElementById('tot' + i).textContent = fmt(cant * pu);
    recalc();
}

/* ── Recalc SIN IVA (farmacia = exento) ── */
function recalc() {
    let base = 0;
    document.querySelectorAll('#renglones tr').forEach(tr => {
        const cant = parseFloat(tr.querySelector('input[name="cant[]"]')?.value) || 0;
        const puRaw = (tr.querySelector('input[name="pu[]"]')?.value || '0')
            .replace(/\./g, '').replace(',', '.');
        const pu = parseFloat(puRaw) || 0;
        base += cant * pu;
    });

    const sat   = base * 0.001;   /* SAT 0,1% */
    const sub   = base + sat;
    const iva   = 0;              /* EXENTO */
    const total = sub;            /* Total = Sub-Total (sin IVA) */

    document.getElementById('t-base').textContent  = fmt(base);
    document.getElementById('t-sat').textContent   = fmt(sat);
    document.getElementById('t-sub').textContent   = fmt(sub);
    document.getElementById('t-total').textContent = fmt(total);

    const letras = total > 0 ? montoLetras(total) : '—';
    document.getElementById('monto-letras-txt').textContent = letras;

    document.getElementById('h-base').value   = base.toFixed(2);
    document.getElementById('h-sat').value    = sat.toFixed(2);
    document.getElementById('h-iva').value    = '0.00';
    document.getElementById('h-total').value  = total.toFixed(2);
    document.getElementById('h-letras').value = letras;

    actualizarPartidas();
}

document.getElementById('form-oc').addEventListener('submit', function() {
    const rows = [];
    document.querySelectorAll('#renglones tr').forEach(tr => {
        rows.push({
            desc:   tr.querySelector('input[name="desc[]"]')?.value  || '',
            imput:  tr.querySelector('input[name="imput[]"]')?.value || '',
            unidad: '1',
            cant:   tr.querySelector('input[name="cant[]"]')?.value  || 0,
            pu:     (tr.querySelector('input[name="pu[]"]')?.value || '0')
                        .replace(/\./g, '').replace(',', '.'),
        });
    });
    document.getElementById('h-renglones').value = JSON.stringify(rows);
});

addRow();
addRow();
addRow();

function actualizarPartidas() {
    const grupos = {};
    document.querySelectorAll('#renglones tr').forEach(tr => {
        const imput = tr.querySelector('input[name="imput[]"]')?.value.trim();
        const cant  = parseFloat(tr.querySelector('input[name="cant[]"]')?.value) || 0;
        const puRaw = (tr.querySelector('input[name="pu[]"]')?.value || '0')
            .replace(/\./g, '').replace(',', '.');
        const pu    = parseFloat(puRaw) || 0;
        const total = cant * pu;
        if (!imput) return;
        grupos[imput] = (grupos[imput] || 0) + total;
    });

    const tbody = document.getElementById('partidas-body');
    tbody.innerHTML = '';
    Object.keys(grupos).forEach(imput => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="partida[]" value="${imput}" readonly style="background:#f0f0f0;"></td>
            <td class="right"><input type="text" name="monto_partida[]"
                value="${fmt(grupos[imput])}" readonly
                style="text-align:right; background:#f0f0f0;"></td>
        `;
        tbody.appendChild(tr);
    });
}

function guardarProducto(i) {
    const tr    = document.getElementById('r' + i);
    const desc  = tr.querySelector('input[name="desc[]"]').value.trim();
    const imput = tr.querySelector('input[name="imput[]"]').value.trim();
    if (!desc || !imput) { alert('Completa descripción e imputación primero.'); return; }
    fetch('../../controllers/productos/guardar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'descripcion=' + encodeURIComponent(desc) + '&imput=' + encodeURIComponent(imput)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok)     { alert('Producto guardado.'); }
        else if (data.existe) { alert('Ese producto ya existe en la BD.!!!'); }
        else             { alert('Error al guardar.'); }
    });
}

function initRifInput() {
    const rifInput = document.getElementById('np-rif-num');
    if (!rifInput) return;

    rifInput.addEventListener('input', function() {
        let digits = rifInput.value.replace(/\D/g, '');
        if (digits.length > 9) {
            digits = digits.slice(0, 9);
        }
        if (digits.length === 9) {
            rifInput.value = digits.slice(0, 8) + '-' + digits.slice(8);
        } else {
            rifInput.value = digits;
        }
    });
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
</script>
</body>
</html>