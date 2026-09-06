<?php
session_start();

if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

$es_admin      = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
$es_supervisor = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'supervisor');

require_once '../../../config/conexion.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Obtener datos de la orden
$query = "SELECT oc.*, p.rif, p.razon_social, p.direccion AS prov_direccion, p.telefono AS prov_telefono,
                 uc.nombre AS creado_por_nombre, uu.nombre AS actualizado_por_nombre
          FROM ordenes_compra oc
          LEFT JOIN proveedores p ON p.id = oc.proveedor_id
          LEFT JOIN usuarios uc ON uc.nombre = oc.created_by OR uc.usuario = oc.created_by
          LEFT JOIN usuarios uu ON uu.nombre = oc.updated_by OR uu.usuario = oc.updated_by
          WHERE oc.id = $id";
$result = mysqli_query($conn, $query);
$oc = mysqli_fetch_assoc($result);

if (!$oc) {
    die("Orden de compra no encontrada.");
}

// Obtener renglones
$renglones_res = mysqli_query($conn, "SELECT * FROM oc_renglones WHERE oc_id = $id ORDER BY id ASC");
$renglones = array();
while ($row = mysqli_fetch_assoc($renglones_res)) {
    $renglones[] = $row;
}

// Obtener partidas
$partidas_res = mysqli_query($conn, "SELECT * FROM oc_partidas WHERE oc_id = $id ORDER BY id ASC");
$partidas = array();
while ($row = mysqli_fetch_assoc($partidas_res)) {
    $partidas[] = $row;
}

$tipo = isset($oc['tipo']) ? $oc['tipo'] : 'normal';
$status = isset($oc['status']) ? $oc['status'] : 'pendiente';
$fecha_fmt = date('d/m/Y', strtotime($oc['fecha']));

$status_val = in_array(strtolower($status), array('pagado', 'pagada', 'aprobada')) ? 'pagado' : 'pendiente';
$status_badge = ($status_val === 'pagado') ? 'pagado' : 'pendiente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Ver Orden de Compra N° <?= htmlspecialchars($oc['numero_orden']) ?>">
    <title>Orden de Compra N° <?= htmlspecialchars($oc['numero_orden']) ?> — Contraloría MSR</title>
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
        body { font-family: 'Inter', sans-serif; font-size: 11px; background: #f0f2f5; color: #000; display: flex; flex-direction: column; min-height: 100vh; }
        
        .site-header { background: #070707; color: white; display: flex; align-items: center; gap: 14px; padding: 8px 20px; border-bottom: 3px solid #0d47a1; position: sticky; top: 0; z-index: 100; }
        .site-header img { height: 52px; width: auto; display: block; }
        .site-header .brand { display: flex; flex-direction: column; gap: 2px; }
        .site-header .brand-title { font-size: 18px; font-weight: bold; letter-spacing: 0.7px; text-transform: uppercase; }
        .site-header .brand-sub { font-size: 12.5px; color: #bbdefb; }
        .site-header .header-right { margin-left: auto; font-size: 11px; color: #e3f2fd; text-align: right; }

        .layout { display: flex; flex: 1; }
        .main-content { flex: 1; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .container { background: white; padding: 20px; border: 1px solid #ccc; width: 100%; max-width: 900px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .oc-document { border: 2px solid #000; padding: 12px; }
        
        .header-grid { display: grid; grid-template-columns: 100px 1fr 100px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 10px; }
        .header-grid img { width: 85px; height: auto; display: block; margin: 0 auto; }
        .header-text { text-align: center; font-size: 11px; font-weight: bold; line-height: 1.3; }

        .title-row { display: grid; grid-template-columns: 1fr 220px; border: 1px solid #000; margin-bottom: 10px; }
        .title-cell { background: <?= ($tipo === 'farmacia') ? '#2e7d32' : '#1565c0' ?>; color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 6px; }
        .title-cell h2 { font-size: 16px; letter-spacing: 1px; text-transform: uppercase; }
        .badge-farmacia { display: inline-block; background: #ffffff; color: #1b5e20; font-size: 9px; font-weight: bold; padding: 2px 8px; border-radius: 2px; margin-top: 3px; text-transform: uppercase; }

        .info-cell { display: grid; grid-template-columns: 1fr 1fr; border-left: 1px solid #000; text-align: center; }
        .info-cell div { padding: 4px; border-bottom: 1px solid #000; font-size: 10px; font-weight: bold; }
        .info-cell div:nth-child(3), .info-cell div:nth-child(4) { border-bottom: none; font-size: 12px; }

        .prov-block { border: 1px solid #000; display: grid; grid-template-columns: 1fr 200px; margin-bottom: 10px; }
        .prov-left { padding: 8px; border-right: 1px solid #000; }
        .prov-row { margin-bottom: 4px; font-size: 11px; }
        .prov-row label { font-weight: bold; width: 100px; display: inline-block; }
        .prov-right { padding: 8px; text-align: center; font-weight: bold; font-size: 10px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; }

        .section-title { background: <?= ($tipo === 'farmacia') ? '#2e7d32' : '#1565c0' ?>; color: white; font-weight: bold; font-size: 11px; text-align: center; padding: 4px; text-transform: uppercase; margin-bottom: 0; border: 1px solid #000; border-bottom: none; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #000; }
        table th { background: #e0e0e0; border: 1px solid #000; padding: 4px; font-size: 10px; text-align: center; }
        table td { border: 1px solid #000; padding: 4px 6px; font-size: 11px; vertical-align: middle; }
        .right { text-align: right; }
        .center { text-align: center; }

        .totales-wrap { display: flex; justify-content: flex-end; margin-bottom: 10px; }
        .totales-box { width: 320px; border: 1px solid #000; }
        .tot-row { display: flex; justify-content: space-between; padding: 4px 8px; border-bottom: 1px solid #ccc; font-size: 11px; }
        .tot-row:last-child { border-bottom: none; }
        .tot-row.total { font-weight: bold; font-size: 12px; background: #e3f2fd; border-top: 2px solid #000; }
        .tot-row.total.farmacia { background: #e8f5e9; }
        .tot-row.exento { background: #e8f5e9; color: #1b5e20; font-weight: bold; }

        .monto-letras { border: 1px solid #000; padding: 6px; font-size: 11px; margin-bottom: 10px; background: #fff8e1; }

        .firmas { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 30px; margin-bottom: 10px; }
        .firma-box { border-top: 1px solid #000; text-align: center; padding-top: 4px; font-size: 10px; font-weight: bold; }

        .condicion { font-size: 9px; text-align: center; border-top: 1px solid #ccc; padding-top: 6px; margin-top: 10px; font-style: italic; color: #555; }

        .badge { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: bold; border-radius: 4px; text-transform: uppercase; }
        .badge-pendiente { background: #fff8e1; color: #b45309; border: 1px solid #fcd34d; }
        .badge-aprobada, .badge-pagada, .badge-pagado { background: #d1fae5; color: #047857; border: 1px solid #6ee7b7; }
        .badge-rechazado, .badge-anulada { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; }

        .btns { display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
        .btns a, .btns button { padding: 9px 18px; font-size: 13px; font-weight: 700; text-decoration: none; border-radius: 6px; border: none; cursor: pointer; color: white; font-family: 'Geist', sans-serif; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.15); }
        .btns a:hover, .btns button:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.25); }
        .btn-volver { background: #475569 !important; }
        .btn-imprimir { background: #1565c0 !important; }
        .btn-editar { background: #d97706 !important; }
        .btn-anular { background: #dc2626 !important; }

        @media print {
            .site-header, .sidebar, .btns, .no-print, .site-footer { display: none !important; }
            body { background: white; font-size: 10pt; }
            .container { border: none; box-shadow: none; padding: 0; width: 100%; max-width: none; }
            .main-content { padding: 0; }
            .oc-document { border: 2px solid #000; }
        }
    </style>
</head>
<body><div class="layout">
    <?php $active = 'oc-lista'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            
            <div class="oc-document">

                <div class="header-grid">
                    <img src="/sistema/assets/img/logo.png" alt="Logo MSR">
                    <div class="header-text">
                        REPÚBLICA BOLIVARIANA DE VENEZUELA<br>
                        ESTADO ANZOÁTEGUI<br>
                        CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ
                    </div>
                    <img src="/sistema/assets/img/sncf.png" alt="SN CF">
                </div>

                <div class="title-row">
                    <div class="title-cell">
                        <h2>ORDEN DE COMPRAS</h2>
                        <?php if ($tipo === 'farmacia'): ?>
                            <span class="badge-farmacia">🏥 Productos Farmacéuticos – Exento</span>
                        <?php endif; ?>
                    </div>
                    <div class="info-cell">
                        <div>N° Orden</div>
                        <div>Fecha</div>
                        <div><?= htmlspecialchars($oc['numero_orden']) ?></div>
                        <div><?= $fecha_fmt ?></div>
                    </div>
                </div>

                <div class="prov-block">
                    <div class="prov-left">
                        <div class="prov-row"><label>Proveedor:</label> <?= htmlspecialchars($oc['rif']) ?> — <?= htmlspecialchars($oc['razon_social']) ?></div>
                        <div class="prov-row"><label>Dirección:</label> <?= htmlspecialchars(strtoupper($oc['prov_direccion'])) ?></div>
                        <div class="prov-row"><label>Teléfono:</label> <?= htmlspecialchars($oc['prov_telefono']) ?></div>
                        <div class="prov-row"><label>Lugar Entrega:</label> <?= htmlspecialchars(strtoupper(isset($oc['lugar_entrega']) ? $oc['lugar_entrega'] : '')) ?></div>
                    </div>
                    <div class="prov-right">
                        DIRECCIÓN DE ADMINISTRACIÓN PLANIFICACIÓN Y PRESUPUESTO
                    </div>
                </div>

                <div class="section-title">RENGLONES DE LA ORDEN DE COMPRAS</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:40%">DESCRIPCIÓN</th>
                            <th style="width:18%">IMPUT. PPTARIA.</th>
                            <th style="width:8%">UNIDAD</th>
                            <th style="width:8%">CANT.</th>
                            <th style="width:13%">P. UNITARIO</th>
                            <th style="width:13%">TOTAL Bs.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $hay_exento = false; ?>
                        <?php foreach ($renglones as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['descripcion']) ?><?php if (isset($r['exento']) && $r['exento']): $hay_exento = true; ?> <span style="display:inline-block;background:#e8f5e9;color:#1b5e20;font-size:8px;font-weight:bold;padding:1px 5px;border-radius:2px;vertical-align:middle;border:1px solid #a5d6a7;">EXENTO IVA</span><?php endif; ?></td>
                            <td class="center"><?= htmlspecialchars($r['imput_presupuestaria']) ?></td>
                            <td class="center"><?= htmlspecialchars($r['unidad']) ?></td>
                            <td class="center"><?= htmlspecialchars($r['cantidad']) ?></td>
                            <td class="right"><?= number_format($r['precio_unitario'], 2, '.', ',') ?></td>
                            <td class="right"><?= number_format($r['total'], 2, '.', ',') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="totales-wrap">
                    <div class="totales-box">
                        <div class="tot-row"><span>Base Imponible:</span><span>Bs. <?= number_format($oc['base_imponible'], 2, '.', ',') ?></span></div>
                        <div class="tot-row"><span>SAT 0,1%:</span><span>Bs. <?= number_format($oc['sat_monto'], 2, '.', ',') ?></span></div>
                        <div class="tot-row"><span>Sub-Total:</span><span>Bs. <?= number_format($oc['base_imponible'] + $oc['sat_monto'], 2, '.', ',') ?></span></div>
                        <?php if ($tipo === 'farmacia'): ?>
                            <div class="tot-row exento"><span>IVA:</span><span>EXENTO</span></div>
                        <?php else: ?>
                            <div class="tot-row"><span>IVA 16%:</span><span>Bs. <?= number_format($oc['iva_monto'], 2, '.', ',') ?></span></div>
                        <?php endif; ?>
                        <div class="tot-row total <?= ($tipo === 'farmacia') ? 'farmacia' : '' ?>"><span>Total General:</span><span>Bs. <?= number_format($oc['total_general'], 2, '.', ',') ?></span></div>
                        <?php if ($tipo !== 'farmacia' && $hay_exento): ?>
                            <div class="tot-row" style="color:#1b5e20;font-weight:bold;font-size:9px;"><span colspan="2">Los renglones marcados EXENTO IVA no generan IVA.</span></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="monto-letras">
                    Monto total en letra: <strong><?= htmlspecialchars($oc['monto_letras']) ?></strong>
                </div>

                <div class="section-title" style="margin-top:10px;">Por Compras y Servicios</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:50%">Partida</th>
                            <th style="width:50%">Monto</th>
                        </tr>            
                    </thead>
                    <tbody>
                        <?php foreach ($partidas as $p): ?>
                        <tr>
                            <td class="center"><?= htmlspecialchars($p['partida']) ?></td>
                            <td class="right">Bs. <?= number_format($p['monto'], 2, '.', ',') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

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

                <!-- Audit trail section for supervisors -->
                <div style="margin-top: 15px; padding: 8px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 9.5px; color: #475569; display: flex; justify-content: space-between; align-items: center;" class="no-print">
                    <div>
                        <strong>👤 Creado por:</strong> <?= htmlspecialchars(!empty($oc['creado_por_nombre']) ? $oc['creado_por_nombre'] : (!empty($oc['created_by']) ? $oc['created_by'] : 'Sistema')) ?>
                        <?php if (!empty($oc['created_at'])): ?>
                            <span style="color:#64748b;">(<?= date('d/m/Y h:i A', strtotime($oc['created_at'])) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($oc['updated_by'])): ?>
                    <div>
                        <strong>✏️ Última modificación:</strong> <?= htmlspecialchars(!empty($oc['actualizado_por_nombre']) ? $oc['actualizado_por_nombre'] : (!empty($oc['updated_by']) ? $oc['updated_by'] : 'Sistema')) ?>
                        <?php if (!empty($oc['updated_at'])): ?>
                            <span style="color:#64748b;">(<?= date('d/m/Y h:i A', strtotime($oc['updated_at'])) ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- /.oc-document -->


            <!-- Panel de cambio de status (Admin y Supervisor) -->
            <?php if ($es_admin || $es_supervisor): ?>
            <div class="status-change-panel no-print" style="margin-top: 18px; padding: 12px 18px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-weight: 700; font-size: 11px; text-transform: uppercase; color: #475569;">Estado Actual:</span>
                    <span class="badge badge-<?= $status_badge ?>" id="current-status-badge" style="font-size: 11px; padding: 4px 10px;"><?= strtoupper($status_val) ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 11px; font-weight: 600; color: #64748b;">Cambiar estado:</span>
                    <select id="select-status-detail" onchange="cambiarStatusDetalle('ordenes_compra', <?= $oc['id'] ?>, this.value)" style="padding: 6px 12px; font-size: 11.5px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-weight: 600; background: #f8fafc; cursor: pointer; outline: none;">
                        <option value="pendiente" <?= ($status_val === 'pendiente') ? 'selected' : '' ?>>🟡 Pendiente</option>
                        <option value="pagado" <?= ($status_val === 'pagado') ? 'selected' : '' ?>>💳 Pagado</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="btns no-print">
                <a href="index.php" class="btn-volver">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Volver
                </a>
                <button type="button" class="btn-imprimir" onclick="window.print()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Imprimir / PDF
                </button>
                <?php if ($status === 'pendiente'): ?>
                    <a href="nueva_oc.php?id=<?= $oc['id'] ?>" class="btn-editar">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Editar Orden
                    </a>
                    <a href="../../controllers/ordenes_compra/anular_oc.php?id=<?= $oc['id'] ?>" class="btn-anular" onclick="return confirm('¿Desea anular esta orden?')">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                        </svg>
                        Anular
                    </a>
                <?php endif; ?>
            </div>

            <script>
            function cambiarStatusDetalle(tabla, id, nuevoStatus) {
                if (!confirm('¿Desea cambiar el estado de la orden a "' + nuevoStatus.toUpperCase() + '"?')) {
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
                        alert('¡Estado actualizado correctamente!');
                        location.reload();
                    } else {
                        alert('Error al actualizar estado: ' + (data.error || 'Desconocido'));
                    }
                })
                .catch(err => {
                    alert('Error en la comunicación con el servidor');
                    console.error(err);
                });
            }
            </script>

        </div>
    </main>
</div><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>
</html>