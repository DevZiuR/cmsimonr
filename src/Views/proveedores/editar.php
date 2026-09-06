<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once __DIR__ . '/../../../config/conexion.php';

// Obtener el ID del proveedor desde GET
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /sistema/src/Views/proveedores/index.php');
    exit;
}

// Buscar el proveedor en la base de datos
$id_esc = mysqli_real_escape_string($conn, $id);
$res = mysqli_query($conn, "SELECT * FROM proveedores WHERE id = '$id_esc' LIMIT 1");

if (!$res || mysqli_num_rows($res) === 0) {
    header('Location: /sistema/src/Views/proveedores/index.php?error=no_encontrado');
    exit;
}

$prov = mysqli_fetch_assoc($res);

$rif          = htmlspecialchars(isset($prov['rif'])          ? $prov['rif']          : '');
$razon_social = htmlspecialchars(isset($prov['razon_social']) ? $prov['razon_social'] : '');
$direccion    = htmlspecialchars(isset($prov['direccion'])    ? $prov['direccion']    : '');
$telefono     = htmlspecialchars(isset($prov['telefono'])     ? $prov['telefono']     : '');
$email        = htmlspecialchars(isset($prov['email'])        ? $prov['email']        : '');

// Mensajes de feedback desde GET
$error = isset($_GET['error']) ? $_GET['error'] : '';
$ok    = isset($_GET['ok'])    ? $_GET['ok']    : '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Editar proveedor - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Editar Proveedor – Contraloría MSR</title>
    <style>
    /* Premium typography: IBM Plex Sans headings, Inter body */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

        /* ── Reset & base ─────────────────────────────────────────────── */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            background: #f0f2f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ───────────────────────────────────────────────────── */
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

        .site-header .header-right .hdr-date { font-size: 13px; font-weight: 700; color: #ffffff; }
        .site-header .header-right .hdr-sub  { font-size: 10.5px; color: #94a3b8; }

        /* ── Layout ───────────────────────────────────────────────────── */
        .layout { display: flex; flex: 1; }

        /* Sidebar CSS managed centrally in includes/sidebar.php */

        /* ── Main content ─────────────────────────────────────────────── */
        .main-content {
            flex: 1;
            padding: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ── Form card ────────────────────────────────────────────────── */
        .form-card {
            background: #ffffff;
            border: 1px solid #dde3ec;
            border-radius: 8px;
            padding: 28px 32px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .form-card h2 {
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .form-card .form-subtitle {
            font-size: 11.5px;
            color: #64748b;
            margin-bottom: 22px;
        }

        .form-group { margin-bottom: 16px; }

        .form-group label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-family: 'Inter', sans-serif;
            font-size: 12.5px;
            color: #1e293b;
            background: #f9fafb;
            outline: none;
            transition: border-color 0.15s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #1565c0;
            background: #fff;
        }

        .form-group input.readonly-field {
            background: #eff2f7;
            color: #6b7280;
            cursor: not-allowed;
        }

        .rif-group {
            display: flex;
            gap: 6px;
        }

        .rif-group select {
            width: 65px;
            flex-shrink: 0;
        }

        .rif-group input {
            flex: 1;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 22px;
        }

        .btn-save {
            background: #1565c0;
            color: #fff;
            border: none;
            padding: 9px 22px;
            border-radius: 5px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }

        .btn-save:hover { background: #0d47a1; }

        .btn-cancel {
            background: #e2e8f0;
            color: #374151;
            border: none;
            padding: 9px 22px;
            border-radius: 5px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.15s;
        }

        .btn-cancel:hover { background: #cbd5e1; }

        /* ── Alerts ───────────────────────────────────────────────────── */
        .alert {
            padding: 10px 14px;
            border-radius: 5px;
            margin-bottom: 18px;
            font-size: 12px;
        }

        .alert-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }

        /* ── Footer ───────────────────────────────────────────────────── */
        .site-footer {
            background: #080616;
            color: #90a4ae;
            text-align: center;
            padding: 10px 20px;
            font-size: 10px;
            border-top: 3px solid #1565c0;
            margin-top: auto;
        }
    </style>
</head>

<body>

    <!-- HEADER --><!-- LAYOUT: SIDEBAR + MAIN -->
    <div class="layout">

        <!-- SIDEBAR -->
        <?php $active = 'prov-lista';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <div class="form-card">
                <h2>Editar Proveedor</h2>
                <p class="form-subtitle">Modifique los datos del proveedor con RIF <strong><?php echo $rif; ?></strong></p>

                <?php if ($ok): ?>
                    <div class="alert alert-success">&#10003; Proveedor actualizado correctamente.</div>
                <?php endif; ?>

                <?php if ($error === 'campos_requeridos'): ?>
                    <div class="alert alert-error">RIF y Razón Social son campos obligatorios.</div>
                <?php elseif ($error === 'error_actualizacion'): ?>
                    <div class="alert alert-error">Error al actualizar en la base de datos. Intente nuevamente.</div>
                <?php endif; ?>

                <form method="POST" action="/sistema/src/Controllers/proveedores/actualizar.php">
                    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

                    <div class="form-group">
                        <label for="rif">RIF *</label>
                        <div class="rif-group">
                            <select id="rif-tipo" required>
                                <option value="J" <?php echo (strpos($rif, 'J-') === 0) ? 'selected' : ''; ?>>J</option>
                                <option value="V" <?php echo (strpos($rif, 'V-') === 0) ? 'selected' : ''; ?>>V</option>
                                <option value="G" <?php echo (strpos($rif, 'G-') === 0) ? 'selected' : ''; ?>>G</option>
                                <option value="E" <?php echo (strpos($rif, 'E-') === 0) ? 'selected' : ''; ?>>E</option>
                                <option value="P" <?php echo (strpos($rif, 'P-') === 0) ? 'selected' : ''; ?>>P</option>
                            </select>
                            <input type="text" id="rif-numero" required maxlength="20"
                                value="<?php echo preg_replace('/^[JVGEP]-/', '', $rif); ?>"
                                oninput="formatRifNumero(this)">
                        </div>
                        <input type="hidden" id="rif" name="rif" value="<?php echo $rif; ?>">
                    </div>

                    <div class="form-group">
                        <label for="razon_social">Razón Social *</label>
                        <input type="text" id="razon_social" name="razon_social" value="<?php echo $razon_social; ?>" maxlength="200" required>
                    </div>

                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" value="<?php echo $direccion; ?>" maxlength="300">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" value="<?php echo $telefono; ?>" maxlength="30">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" maxlength="150">
                    </div>

                    <div class="form-actions">
                        <a href="/sistema/src/Views/proveedores/index.php" class="btn-cancel">Cancelar</a>
                        <button type="submit" class="btn-save">Guardar Cambios</button>
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
                    document.getElementById('rif').value = tipo + '-' + num;
                }
                document.getElementById('rif-tipo').addEventListener('change', syncRifHidden);
                document.querySelector('form').addEventListener('submit', function() {
                    syncRifHidden();
                });
                </script>
            </div>

        </main>
    </div>

    <!-- FOOTER --><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>
</html>