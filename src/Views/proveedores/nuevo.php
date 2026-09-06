<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = ($_SESSION['rol'] === 'admin');
if (!$es_admin) {
    header('Location: index.php');
    exit;
}

require_once '../../../config/conexion.php';

// Obtener datos devueltos por GET para la persistencia del formulario en caso de error
$rif          = isset($_GET['rif']) ? htmlspecialchars($_GET['rif']) : '';
$razon_social = isset($_GET['razon_social']) ? htmlspecialchars($_GET['razon_social']) : '';
$direccion    = isset($_GET['direccion']) ? htmlspecialchars($_GET['direccion']) : '';
$telefono     = isset($_GET['telefono']) ? htmlspecialchars($_GET['telefono']) : '';
$email        = isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '';

/* ── Últimos 5 proveedores agregados ── */
$sql_ultimos = "SELECT id, rif, razon_social, telefono, email, created_at FROM proveedores ORDER BY id DESC LIMIT 5";
$res_ultimos = mysqli_query($conn, $sql_ultimos);
$ultimos_proveedores = array();
if ($res_ultimos) {
    while ($row = mysqli_fetch_assoc($res_ultimos)) {
        $ultimos_proveedores[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registrar nuevo proveedor - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Nuevo Proveedor – Contraloría MSR</title>
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

        .site-header .header-right .hdr-date {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }

        .site-header .header-right .hdr-sub {
            font-size: 10.5px;
            color: #94a3b8;
        }

        /* ── Layout: sidebar + main ────────────────────────────────────── */
        .layout {
            display: flex;
            flex: 1;
        }

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

        .inst-header img {
            width: 75px;
            height: auto;
            display: block;
        }

        .inst-details {
            text-align: center;
            flex-grow: 1;
            padding: 0 15px;
        }

        .inst-details p {
            font-size: 10.5px;
            line-height: 1.5;
            color: #475569;
        }

        .inst-details h2 {
            font-size: 15px;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0f172a;
            font-weight: 800;
        }

        /* ── Form components ───────────────────────────────────────────── */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 14px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 14px;
        }

        .field label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            letter-spacing: 0.3px;
        }

        .field input,
        .field select {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 9px 12px;
            font-size: 12.5px;
            color: #1e293b;
            width: 100%;
            font-family: 'Inter', sans-serif;
            border-radius: 8px;
            outline: none;
            transition: all 0.15s ease;
        }

        .field input:focus,
        .field select:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .rif-group {
            display: flex;
            gap: 8px;
        }

        .rif-group select {
            width: 68px;
            flex-shrink: 0;
            background: #f1f5f9;
            font-weight: 700;
            color: #1e40af;
            border-color: #cbd5e1;
        }

        .rif-group input {
            flex: 1;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
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

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

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

        /* ── Recent Providers Card ─────────────────────────────────────── */
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

        .recent-table td.col-rif {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #1e40af;
            white-space: nowrap;
        }

        .recent-table td.col-razon {
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

        .site-footer strong {
            color: #e3f2fd;
        }
    </style>
</head>

<body>

    <!-- HEADER --><!-- LAYOUT: SIDEBAR + MAIN -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php $active = 'prov-nueva'; require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <div class="form-container">
                <!-- Institutional Header inside form -->
                <div class="inst-header">
                    <div style="flex-shrink:0;">
                        <img src="/sistema/assets/img/logo.png" alt="Logo">
                    </div>
                    <div class="inst-details">
                        <p><strong>REPÚBLICA BOLIVARIANA DE VENEZUELA</strong><br>
                        ESTADO ANZOÁTEGUI<br>
                        <strong>CONTRALORÍA DEL MUNICIPIO SIMÓN RODRÍGUEZ</strong></p>
                        <h2>Registro de Proveedores</h2>
                    </div>
                    <div style="flex-shrink:0;">
                        <img src="/sistema/assets/img/sncf.png" alt="NCF" style="width:75px; height:auto;">
                    </div>
                </div>

                <!-- Alert Error -->
                <?php if (isset($_GET['error']) && $_GET['error'] === 'rif_duplicado'): ?>
                    <div class="alert alert-danger">
                        El RIF ingresado ya se encuentra registrado por otro proveedor.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'campos_requeridos'): ?>
                    <div class="alert alert-danger">
                        Por favor, complete todos los campos obligatorios (*).
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] === 'error_insercion'): ?>
                    <div class="alert alert-danger">
                        Ocurrió un error al guardar el proveedor. Por favor, inténtelo de nuevo.
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="../../controllers/proveedores/crear.php" id="form-nuevo-prov">
                    <div class="meta-grid">
                        <div class="field">
                            <label>RIF <span style="color: red;">*</span></label>
                            <div class="rif-group">
                                <select id="rif-tipo" required>
                                    <option value="J" <?php echo (strpos($rif, 'J-') === 0) ? 'selected' : ''; ?>>J</option>
                                    <option value="V" <?php echo (strpos($rif, 'V-') === 0) ? 'selected' : ''; ?>>V</option>
                                    <option value="G" <?php echo (strpos($rif, 'G-') === 0) ? 'selected' : ''; ?>>G</option>
                                    <option value="E" <?php echo (strpos($rif, 'E-') === 0) ? 'selected' : ''; ?>>E</option>
                                    <option value="P" <?php echo (strpos($rif, 'P-') === 0) ? 'selected' : ''; ?>>P</option>
                                </select>
                                <input type="text" id="rif-numero" required placeholder="12345678-9"
                                    value="<?php echo preg_replace('/^[JVGEP]-/', '', $rif); ?>"
                                    oninput="formatRifNumero(this)">
                            </div>
                            <input type="hidden" name="rif" id="rif-hidden" value="<?php echo $rif; ?>">
                        </div>
                        <div class="field">
                            <label>Razón Social <span style="color: red;">*</span></label>
                            <input type="text" name="razon_social" value="<?php echo $razon_social; ?>" required oninput="this.value = this.value.toUpperCase()" placeholder="EMPRESA, C.A.">
                        </div>
                    </div>

                    <div class="meta-grid">
                        <div class="field">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?php echo $telefono; ?>" placeholder="0283-0000000">
                        </div>
                        <div class="field">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo $email; ?>" placeholder="proveedor@correo.com">
                        </div>
                    </div>

                    <div class="field">
                        <label>Dirección</label>
                        <input type="text" name="direccion" value="<?php echo $direccion; ?>" placeholder="Av. Principal, Edificio X, Oficina Y...">
                    </div>

                    <div class="btns">
                        <button type="submit" class="btn-guardar">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            Guardar proveedor
                        </button>
                        <a href="index.php" class="btn-cancelar">Cancelar</a>
                    </div>
                </form>

                <script>
                function formatRifNumero(input) {
                    var digits = input.value.replace(/\D/g, '');
                    if (digits.length > 9) digits = digits.slice(0, 9);
                    if (digits.length === 9) {
                        input.value = digits.slice(0, 8) + '-' + digits.slice(8);
                    } else {
                        input.value = digits;
                    }
                    syncRifHidden();
                }
                function syncRifHidden() {
                    var tipo = document.getElementById('rif-tipo').value;
                    var num  = document.getElementById('rif-numero').value.trim();
                    document.getElementById('rif-hidden').value = tipo + '-' + num;
                }
                document.getElementById('rif-tipo').addEventListener('change', syncRifHidden);
                document.getElementById('form-nuevo-prov').addEventListener('submit', function() {
                    syncRifHidden();
                });
                </script>
            </div>

            <!-- ══ ÚLTIMOS 5 PROVEEDORES AGREGADOS ══════════════════════════════ -->
            <div class="recent-card">
                <div class="recent-card-head">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Últimos 5 Proveedores Agregados
                    </h3>
                    <span class="count-badge"><?php echo count($ultimos_proveedores); ?> recientemente</span>
                </div>
                <?php if (!empty($ultimos_proveedores)): ?>
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>RIF</th>
                                <th>Razón Social</th>
                                <th>Teléfono</th>
                                <th>Email</th>
                                <th style="text-align:right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ultimos_proveedores as $p): ?>
                                <tr>
                                    <td class="col-rif"><?php echo htmlspecialchars($p['rif']); ?></td>
                                    <td class="col-razon"><?php echo htmlspecialchars($p['razon_social']); ?></td>
                                    <td><?php echo !empty($p['telefono']) ? htmlspecialchars($p['telefono']) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                                    <td><?php echo !empty($p['email']) ? htmlspecialchars($p['email']) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                                    <td style="text-align:right;">
                                        <a href="editar.php?id=<?php echo $p['id']; ?>" class="btn-edit-recent" title="Editar Proveedor">
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align:center; padding: 24px; color:#94a3b8; font-size:12px;">
                        No hay proveedores registrados aún.
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- FOOTER --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>

</html>