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

/* ── Últimos 5 productos agregados ── */
$sql_ultimos = "SELECT id, descripcion, imput_presupuestaria FROM productos ORDER BY id DESC LIMIT 5";
$res_ultimos = mysqli_query($conn, $sql_ultimos);
$ultimos_productos = array();
if ($res_ultimos) {
    while ($row = mysqli_fetch_assoc($res_ultimos)) {
        $ultimos_productos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nuevo producto - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Nuevo Producto – Contraloría MSR</title>
    <style>
        /* Premium typography */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        /* ── Reset & base ──────────────────────────────────────────────── */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ────────────────────────────────────────────────────── */
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

        .site-header img { height: 52px; width: auto; display: block; }

        .site-header .brand { display: flex; flex-direction: column; gap: 2px; }

        .site-header .brand-title {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .site-header .brand-sub { font-size: 12.5px; color: #bbdefb; letter-spacing: 0.2px; }

        .site-header .header-right {
            margin-left: auto;
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .layout { display: flex; flex: 1; }

        /* ── Main content ──────────────────────────────────────────────── */
        .main-content {
            flex: 1;
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── Form Container ────────────────────────────────────────────── */
        .form-container {
            width: 100%;
            max-width: 840px;
            background: #ffffff;
            padding: 32px 36px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
            margin-top: 10px;
            margin-bottom: 28px;
        }

        /* ── Institutional Header inside form ──────────────────────────── */
        .inst-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 16px;
        }

        .inst-header img { width: 75px; height: auto; display: block; }

        .inst-details { text-align: center; flex-grow: 1; padding: 0 15px; }

        .inst-details p { font-size: 10.5px; line-height: 1.5; color: #475569; }

        .inst-details h2 {
            font-size: 15px;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0f172a;
            font-weight: 800;
        }

        /* ── Form components ───────────────────────────────────────────── */
        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 16px;
        }

        .field label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.3px;
        }

        .field input,
        .field textarea {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 10px 14px;
            font-size: 12.5px;
            color: #1e293b;
            width: 100%;
            font-family: 'Inter', sans-serif;
            border-radius: 8px;
            outline: none;
            transition: all 0.15s ease;
        }

        .field input:focus,
        .field textarea:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        #campo-imput {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: #1e40af;
        }

        /* ── Alerts ────────────────────────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }

        /* ── Buttons ───────────────────────────────────────────────────── */
        .btns {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-guardar {
            background: #2563eb;
            color: white;
            border: 1px solid #2563eb;
            padding: 10px 22px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 9px;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-guardar:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-cancelar {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            padding: 10px 20px;
            font-size: 12.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            border-radius: 9px;
            transition: all 0.15s ease;
        }

        .btn-cancelar:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* ── Recent Products Card ──────────────────────────────────────── */
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

        .recent-table td.col-desc {
            font-weight: 600;
            color: #0f172a;
        }

        .recent-table td.col-imput {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #1e40af;
            white-space: nowrap;
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

        /* ── Footer ────────────────────────────────────────────────────── */
        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            line-height: 1.8;
            margin-top: auto;
            width: 100%;
        }

        .site-footer strong { color: #e3f2fd; }
    </style>
</head>

<body>

    <!-- HEADER --><!-- LAYOUT: SIDEBAR + MAIN -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php $active = 'prod-nuevo'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <div class="form-container">

                <!-- Institutional Header -->
                <div class="inst-header">
                    <div style="flex-shrink:0;">
                        <img src="/sistema/assets/img/logo.png" alt="Logo">
                    </div>
                    <div class="inst-details">
                        <p><strong>REPÚBLICA BOLIVARIANA DE VENEZUELA</strong><br>
                        ESTADO ANZOÁTEGUI<br>
                        <strong>CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</strong></p>
                        <h2>Registro de Productos</h2>
                    </div>
                    <div style="flex-shrink:0;">
                        <img src="/sistema/assets/img/sncf.png" alt="NCF" style="width:75px; height:auto;">
                    </div>
                </div>

                <!-- Alerts -->
                <?php if (isset($_GET['error']) && $_GET['error'] === 'campos_requeridos'): ?>
                    <div class="alert alert-danger">Por favor, complete todos los campos obligatorios (*).</div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'error_insercion'): ?>
                    <div class="alert alert-danger">Ocurrió un error al guardar el producto. Por favor, inténtelo de nuevo.</div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="/sistema/src/Controllers/productos/crear.php" id="form-nuevo-producto">

                    <div class="field">
                        <label>Descripción <span style="color:red;">*</span></label>
                        <input type="text"
                               name="descripcion"
                               id="campo-descripcion"
                               value="<?php echo isset($_GET['descripcion']) ? htmlspecialchars($_GET['descripcion']) : ''; ?>"
                               required
                               placeholder="Ej: MATERIALES DE OFICINA, PAPELERÍA..."
                               oninput="this.value = this.value.toUpperCase()">
                    </div>

                    <div class="field">
                        <label>Imputación Presupuestaria <span style="color:red;">*</span></label>
                        <input type="text"
                               name="imput_presupuestaria"
                               id="campo-imput"
                               value="<?php echo isset($_GET['imput_presupuestaria']) ? htmlspecialchars($_GET['imput_presupuestaria']) : ''; ?>"
                               required
                               placeholder="Ej: 4.01.01.01.00">
                    </div>

                    <div class="btns">
                        <button type="submit" class="btn-guardar" id="btn-guardar-producto">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Guardar Producto
                        </button>
                        <a href="index.php" class="btn-cancelar" id="btn-cancelar-producto">Cancelar</a>
                    </div>
                </form>

            </div>

            <!-- ══ ÚLTIMOS 5 PRODUCTOS AGREGADOS ══════════════════════════════ -->
            <div class="recent-card">
                <div class="recent-card-head">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                        Últimos 5 Productos Agregados
                    </h3>
                    <span class="count-badge"><?php echo count($ultimos_productos); ?> recientemente</span>
                </div>
                <?php if (!empty($ultimos_productos)): ?>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Imputación Presupuestaria</th>
                                <th style="text-align:right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_productos as $prod): ?>
                                <tr>
                                    <td class="col-desc"><?php echo htmlspecialchars($prod['descripcion']); ?></td>
                                    <td class="col-imput"><?php echo htmlspecialchars($prod['imput_presupuestaria']); ?></td>
                                    <td style="text-align:right;">
                                        <a href="editar.php?id=<?php echo $prod['id']; ?>" class="btn-edit-recent" title="Editar Producto">
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding: 24px; color:#94a3b8; font-size:12px;">
                        No hay productos registrados aún.
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- FOOTER --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>

</html>