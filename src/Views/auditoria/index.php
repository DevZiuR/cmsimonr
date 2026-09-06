<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: /sistema/public/login.php');
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    header('Location: /sistema/public/index.php');
    exit;
}
$es_admin = true;

require_once '../../../config/conexion.php';

/* ── Filtros opcionales ── */
$filtro_module = isset($_GET['module']) ? trim($_GET['module']) : '';
$filtro_q      = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = array();
if ($filtro_module !== '') {
    $where[] = "module = '" . mysqli_real_escape_string($conn, $filtro_module) . "'";
}
if ($filtro_q !== '') {
    $q_safe = '%' . mysqli_real_escape_string($conn, $filtro_q) . '%';
    $where[] = "(usuario_nombre LIKE '$q_safe' OR action LIKE '$q_safe' OR module LIKE '$q_safe' OR detail LIKE '$q_safe')";
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT id, user_id, usuario_nombre, action, module, detail, ip_address, created_at
        FROM audit_log $where_sql
        ORDER BY created_at DESC, id DESC
        LIMIT 500";
$result = mysqli_query($conn, $sql);

$sql_mods = "SELECT DISTINCT module FROM audit_log ORDER BY module ASC";
$res_mods = mysqli_query($conn, $sql_mods);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Registro de auditoría del sistema - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Registro de Auditoría – Contraloría MSR</title>
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

        .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .badge-accion {
            display: inline-block;
            padding: 3px 10px;
            font-size: 10.5px;
            font-weight: 700;
            border-radius: 999px;
            background: #e0e7ff;
            color: #3730a3;
        }

        .fecha-cell {
            white-space: nowrap;
            color: #475569;
        }

        .filters {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-input,
        .select-input {
            padding: 8px 12px;
            font-size: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            outline: none;
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
        }

        .search-input:focus,
        .select-input:focus {
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

        .sin-datos {
            text-align: center;
            color: #94a3b8;
            padding: 48px 20px;
            font-size: 12px;
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
            .sidebar, .action-bar, .filters, .site-footer, .no-print {
                display: none !important;
            }
            body { background: #fff !important; }
            .layout, .main-content, .panel, .panel-body { display: block !important; margin: 0 !important; padding: 0 !important; }
            table th { background: #e2e8f0 !important; color: #000 !important; }
        }
    </style>
</head>

<body>

    <div class="layout">

        <?php $active = 'auditoria';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">

            <div class="action-bar no-print">
                <div class="page-title-ic">
                    <span class="title-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 1 1-9-9" />
                            <path d="M12 3a9 9 0 0 1 9 9" />
                            <path d="M12 7v5l3 3" />
                        </svg>
                    </span>
                    <h1 class="page-title">Registro de Auditoría</h1>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <span class="ph-title">
                        <span class="ph-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></svg>
                        </span>
                        Actividad del Sistema (últimos 500 eventos)
                    </span>
                    <form method="GET" class="filters">
                        <select name="module" class="select-input" onchange="this.form.submit()">
                            <option value="">Todos los módulos</option>
                            <?php if ($res_mods): ?>
                                <?php while ($m = mysqli_fetch_assoc($res_mods)): ?>
                                    <option value="<?php echo htmlspecialchars($m['module']); ?>" <?php echo ($m['module'] === $filtro_module) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['module']); ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <input type="text" name="q" class="search-input" placeholder="Buscar usuario, acción o detalle..."
                            value="<?php echo htmlspecialchars($filtro_q); ?>">
                        <button type="submit" class="btn-link btn-primary">Filtrar</button>
                        <?php if ($filtro_module !== '' || $filtro_q !== ''): ?>
                            <a href="index.php" class="btn-link" style="background:#e2e8f0;color:#334155;">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="panel-body">
                    <?php if (!$result || mysqli_num_rows($result) === 0): ?>
                        <div class="sin-datos">No hay eventos de auditoría registrados.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 5%; text-align: center;">ID</th>
                                    <th style="width: 13%;">Usuario</th>
                                    <th style="width: 16%;">Acción</th>
                                    <th style="width: 12%;">Módulo</th>
                                    <th style="width: 30%;">Detalle</th>
                                    <th style="width: 10%;">IP</th>
                                    <th style="width: 14%;">Fecha / Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td style="text-align: center;" class="mono"><?php echo (int) $row['id']; ?></td>
                                        <td><?php echo htmlspecialchars($row['usuario_nombre'] !== null && $row['usuario_nombre'] !== '' ? $row['usuario_nombre'] : ('ID ' . (int) $row['user_id'])); ?></td>
                                        <td><span class="badge-accion"><?php echo htmlspecialchars($row['action']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['module']); ?></td>
                                        <td><?php echo $row['detail'] !== null && $row['detail'] !== '' ? htmlspecialchars($row['detail']) : '—'; ?></td>
                                        <td class="mono"><?php echo htmlspecialchars($row['ip_address'] !== null ? $row['ip_address'] : '—'); ?></td>
                                        <td class="fecha-cell"><?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?></td>
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