<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
$es_admin = ($_SESSION['rol'] === 'admin');

require_once '../../../config/conexion.php';

/* ── Cargar partida en edición ── */
$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$edit_row = null;
if ($edit_id > 0) {
    $res_edit = mysqli_query($conn, "SELECT * FROM partidas WHERE id = $edit_id LIMIT 1");
    if ($res_edit) {
        $edit_row = mysqli_fetch_assoc($res_edit);
    }
}

$ver_inactivas = isset($_GET['ver']) && $_GET['ver'] === 'inactivas';
$where_estado = $ver_inactivas ? "activa = 0" : "activa = 1";

$query = "SELECT id, codigo, denominacion, grupo, activa, created_at FROM partidas WHERE $where_estado ORDER BY codigo ASC";
$result = mysqli_query($conn, $query);

$grupos = array('GASTOS DE PERSONAL', 'MATERIALES Y SUMINISTROS', 'SERVICIOS NO PERSONALES', 'ACTIVOS REALES', 'TRANSFERENCIAS Y DONACIONES', 'OTRAS PARTIDAS');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catálogo de partidas presupuestarias - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Catálogo de Partidas – Contraloría MSR</title>
    <style>
        h1, h2, h3, h4, h5, h6, .page-title, .section-title, .card-title, .panel-title, .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
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
            background-color: rgba(0, 0, 0, 0.10);
            background-blend-mode: multiply;
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
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .panel-head {
            background: #080d1cff;
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
            color: #2e81e6ff;
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

        .codigo-cell {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: #1e40af;
            white-space: nowrap;
        }

        .badge-estado {
            display: inline-block;
            padding: 3px 10px;
            font-size: 10.5px;
            font-weight: 700;
            border-radius: 999px;
        }

        .badge-activa {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-inactiva {
            background: #fee2e2;
            color: #b91c1c;
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
        }

        .btn-delete {
            background: #ef4444;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-restore {
            background: #059669;
        }

        .btn-restore:hover {
            background: #047857;
        }

        .sin-datos {
            text-align: center;
            color: #94a3b8;
            padding: 48px 20px;
            font-size: 12px;
        }

        .search-bar-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 12px;
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
        }

        .search-input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: 8px;
            border: 1px solid transparent;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: #1e40af;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1e3a8a;
        }

        .btn-secondary {
            background: #ffffff;
            color: #1e293b;
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #f1f5f9;
        }

        .btn-ghost {
            background: #e2e8f0;
            color: #334155;
        }

        .btn-ghost:hover {
            background: #cbd5e1;
        }

        .form-panel {
            padding: 20px 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 220px 1fr 240px;
            gap: 12px;
            align-items: end;
        }

        .form-grid label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 5px;
        }

        .form-grid input,
        .form-grid select {
            width: 100%;
            padding: 8px 10px;
            font-size: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
        }

        .form-grid input:focus,
        .form-grid select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .site-footer {
            background: #070707;
            color: #90a4ae;
            text-align: center;
            padding: 12px 20px;
            font-size: 10.5px;
            border-top: 3px solid #2563eb;
            line-height: 1.8;
            margin-top: auto;
        }

        @media print {
            .sidebar, .action-bar, .search-input, .form-panel, .btn-action, .alert, .badge-estado, .site-footer, .no-print {
                display: none !important;
            }
            body { background: #fff !important; }
            .layout, .main-content, .panel, .panel-body { display: block !important; margin: 0 !important; padding: 0 !important; }
            table th { background: #e2e8f0 !important; color: #000 !important; }
        }
    </style>
    <script>
        function confirmarAccion(url, msg) {
            if (confirm(msg || '¿Está seguro de que desea continuar?')) {
                window.location.href = url;
            }
        }

        function filtrarTabla(valor) {
            var term = (valor || '').toLowerCase().trim();
            var table = document.getElementById('tabla-partidas');
            if (!table) return;
            var rows = table.querySelectorAll('tbody tr');
            for (var i = 0; i < rows.length; i++) {
                var rowText = (rows[i].textContent || rows[i].innerText || '').toLowerCase();
                rows[i].style.display = (rowText.indexOf(term) !== -1) ? '' : 'none';
            }
        }
    </script>
</head>

<body>

    <div class="layout">

        <?php $active = 'partidas';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">

            <div class="action-bar no-print">
                <div class="page-title-ic">
                    <span class="title-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5A2.5 2.5 0 0 1 1.5 17V7a2.5 2.5 0 0 1 2.5-2.5h16A2.5 2.5 0 0 1 22.5 7v10a2.5 2.5 0 0 1-2.5 2.5z" />
                            <path d="M1.5 9h21" />
                            <path d="M7 13h10" />
                        </svg>
                    </span>
                    <h1 class="page-title">Catálogo de Partidas</h1>
                </div>
            </div>

            <?php if (isset($_GET['ok'])): ?>
                <div class="alert alert-success">
                    <?php
                    $msgs = array('1' => 'Partida registrada exitosamente.', '2' => 'Partida actualizada exitosamente.', '3' => 'Partida reactivada.', '4' => 'Partida desactivada.');
                    echo isset($msgs[$_GET['ok']]) ? $msgs[$_GET['ok']] : 'Operación exitosa.';
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php
                    $errs = array('campos_requeridos' => 'El código y la denominación son obligatorios.', 'duplicado' => 'Ya existe una partida con ese código.', 'db_error' => 'Ocurrió un error al guardar en la base de datos.');
                    echo isset($errs[$_GET['error']]) ? $errs[$_GET['error']] : 'Ocurrió un error.';
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($es_admin): ?>
                <div class="panel form-panel no-print" style="margin-bottom:24px;">
                    <form method="POST" action="/sistema/src/Controllers/partidas/guardar.php">
                        <?php if ($edit_row): ?>
                            <input type="hidden" name="id" value="<?php echo (int) $edit_row['id']; ?>">
                        <?php endif; ?>
                        <div class="form-grid">
                            <div>
                                <label for="codigo">Código presupuestario</label>
                                <input type="text" id="codigo" name="codigo" required
                                    value="<?php echo htmlspecialchars($edit_row ? $edit_row['codigo'] : (isset($_GET['codigo']) ? $_GET['codigo'] : '')); ?>"
                                    placeholder="01-08-00-00-51-403-99-01-00">
                            </div>
                            <div>
                                <label for="denominacion">Denominación</label>
                                <input type="text" id="denominacion" name="denominacion" required
                                    value="<?php echo htmlspecialchars($edit_row ? $edit_row['denominacion'] : (isset($_GET['denominacion']) ? $_GET['denominacion'] : '')); ?>"
                                    placeholder="Descripción de la partida">
                            </div>
                            <div>
                                <label for="grupo">Grupo</label>
                                <select id="grupo" name="grupo">
                                    <?php
                                    $grupo_sel = $edit_row ? $edit_row['grupo'] : (isset($_GET['grupo']) ? $_GET['grupo'] : 'OTRAS PARTIDAS');
                                    foreach ($grupos as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo ($g === $grupo_sel) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn-link btn-primary">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    <?php echo $edit_row ? 'Actualizar partida' : 'Registrar partida'; ?>
                                </button>
                                <?php if ($edit_row): ?>
                                    <a href="index.php" class="btn-link btn-ghost">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-head">
                    <span class="ph-title">
                        <span class="ph-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                        </span>
                        <?php echo $ver_inactivas ? 'Partidas Desactivadas' : 'Partidas Activas (' . mysqli_num_rows($result) . ')'; ?>
                    </span>
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <input type="text" id="busqueda-tabla" class="search-input" placeholder="Buscar por código, denominación o grupo..."
                            onkeyup="filtrarTabla(this.value)">
                        <?php if ($ver_inactivas): ?>
                            <a href="index.php" class="btn-link btn-secondary">Ver activas</a>
                        <?php else: ?>
                            <a href="index.php?ver=inactivas" class="btn-link btn-secondary">Ver desactivadas</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <?php if (!$result || mysqli_num_rows($result) === 0): ?>
                        <div class="sin-datos">
                            <?php echo $ver_inactivas ? 'No hay partidas desactivadas.' : 'No hay partidas registradas.'; ?>
                        </div>
                    <?php else: ?>
                        <table id="tabla-partidas">
                            <thead>
                                <tr>
                                    <th style="width: 4%; text-align: center;">ID</th>
                                    <th style="width: 30%;">Código</th>
                                    <th style="width: 38%;">Denominación</th>
                                    <th style="width: 16%;">Grupo</th>
                                    <th style="width: 6%; text-align: center;">Estado</th>
                                    <th style="width: 14%; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php $pid = (int) $row['id']; ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $pid; ?></td>
                                        <td class="codigo-cell"><?php echo htmlspecialchars($row['codigo']); ?></td>
                                        <td><?php echo htmlspecialchars($row['denominacion']); ?></td>
                                        <td><?php echo htmlspecialchars($row['grupo']); ?></td>
                                        <td style="text-align: center;">
                                            <?php if ((int) $row['activa'] === 1): ?>
                                                <span class="badge-estado badge-activa">Activa</span>
                                            <?php else: ?>
                                                <span class="badge-estado badge-inactiva">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <?php if ($es_admin): ?>
                                            <a href="index.php?edit=<?php echo $pid; ?>" class="btn-action btn-edit">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Editar
                                            </a>
                                            <?php if ((int) $row['activa'] === 1): ?>
                                                <a href="javascript:void(0);" class="btn-action btn-delete"
                                                    onclick="confirmarAccion('/sistema/src/Controllers/partidas/eliminar.php?id=<?php echo $pid; ?>', '¿Desactivar esta partida? Los documentos que ya la usan no se verán afectados.')">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    Desactivar
                                                </a>
                                            <?php else: ?>
                                                <a href="javascript:void(0);" class="btn-action btn-restore"
                                                    onclick="confirmarAccion('/sistema/src/Controllers/partidas/eliminar.php?id=<?php echo $pid; ?>&action=reactivar', '¿Reactivar esta partida?')">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                                    Reactivar
                                                </a>
                                            <?php endif; ?>
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
    </div><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?></body>

</html>