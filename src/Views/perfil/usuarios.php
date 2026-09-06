<?php
session_start();

if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

if (!$es_admin) {
    header('Location: /sistema/public/index.php');
    exit;
}

require_once '../../../config/conexion.php';

// Modelo de auditoría
$audit_path = __DIR__ . '/../../Models/audit_log.php';
if (file_exists($audit_path)) {
    require_once $audit_path;
}

$current_user_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

/* ════════════════════════════════════════════════════════════════
   PROCESAMIENTO DE ACCIONES ADMINISTRATIVAS (POST)
   ════════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

    /* ── 1. CREAR NUEVO USUARIO ── */
    if ($accion === 'crear') {
        $nombre   = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $usuario  = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $rol      = isset($_POST['rol']) ? trim($_POST['rol']) : 'visualizador';

        $roles_validos = array('admin', 'supervisor', 'visualizador');
        if (!in_array($rol, $roles_validos)) {
            $rol = 'visualizador';
        }

        if ($nombre === '' || $usuario === '' || $password === '') {
            $_SESSION['flash_msg']  = 'Todos los campos son obligatorios para crear un usuario.';
            $_SESSION['flash_type'] = 'error';
        } elseif (strlen($password) < 4) {
            $_SESSION['flash_msg']  = 'La contraseña debe tener al menos 4 caracteres.';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Verificar si el nombre de usuario ya existe
            $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?) LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $usuario);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $existe = (mysqli_stmt_num_rows($stmt) > 0);
            mysqli_stmt_close($stmt);

            if ($existe) {
                $_SESSION['flash_msg']  = "El nombre de usuario '{$usuario}' ya está registrado. Por favor elija otro.";
                $_SESSION['flash_type'] = 'error';
            } else {
                $hash = md5($password);
                $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (nombre, usuario, password, rol) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssss', $nombre, $usuario, $hash, $rol);
                if (mysqli_stmt_execute($stmt)) {
                    $nuevo_id = mysqli_insert_id($conn);
                    if (function_exists('log_activity')) {
                        log_activity($current_user_id, 'CREAR_USUARIO', 'usuarios', "Creó al usuario '{$usuario}' (ID: {$nuevo_id}) con rol '{$rol}'");
                    }
                    $_SESSION['flash_msg']  = "Usuario '{$usuario}' creado exitosamente con rol " . strtoupper($rol) . ".";
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_msg']  = 'Error al crear el usuario en la base de datos: ' . mysqli_error($conn);
                    $_SESSION['flash_type'] = 'error';
                }
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: /sistema/src/Views/perfil/usuarios.php');
        exit;
    }

    /* ── 2. EDITAR DATOS Y ROL DE USUARIO ── */
    if ($accion === 'editar') {
        $id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre  = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
        $rol     = isset($_POST['rol']) ? trim($_POST['rol']) : 'visualizador';

        $roles_validos = array('admin', 'supervisor', 'visualizador');
        if (!in_array($rol, $roles_validos)) {
            $rol = 'visualizador';
        }

        if ($id <= 0 || $nombre === '' || $usuario === '') {
            $_SESSION['flash_msg']  = 'ID, nombre y nombre de usuario son obligatorios.';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Verificar si el nuevo username está tomado por otro usuario
            $stmt = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'si', $usuario, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $duplicado = (mysqli_stmt_num_rows($stmt) > 0);
            mysqli_stmt_close($stmt);

            if ($duplicado) {
                $_SESSION['flash_msg']  = "El nombre de usuario '{$usuario}' ya pertenece a otra persona.";
                $_SESSION['flash_type'] = 'error';
            } else {
                // Protección: no quitarse el rol de admin si es el único admin en el sistema
                $puede_cambiar_rol = true;
                if ($rol !== 'admin') {
                    $res_admin = mysqli_query($conn, "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'admin' AND id != $id");
                    $fila_admin = $res_admin ? mysqli_fetch_assoc($res_admin) : null;
                    if ($fila_admin && (int)$fila_admin['total'] === 0) {
                        $puede_cambiar_rol = false;
                        $_SESSION['flash_msg']  = 'No es posible quitar el rol de Administrador al único administrador registrado en el sistema.';
                        $_SESSION['flash_type'] = 'error';
                    }
                }

                if ($puede_cambiar_rol) {
                    $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, usuario = ?, rol = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'sssi', $nombre, $usuario, $rol, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        if (function_exists('log_activity')) {
                            log_activity($current_user_id, 'EDITAR_USUARIO', 'usuarios', "Modificó datos del usuario '{$usuario}' (ID: {$id}), nuevo rol: '{$rol}'");
                        }
                        // Si se editó a sí mismo, actualizar sesión activa
                        if ($id === $current_user_id) {
                            $_SESSION['usuario_nombre'] = $nombre;
                            $_SESSION['nombre']         = $nombre;
                            $_SESSION['usuario']        = $usuario;
                            $_SESSION['rol']            = $rol;
                        }
                        $_SESSION['flash_msg']  = "Usuario '{$usuario}' actualizado correctamente.";
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_msg']  = 'Error al actualizar usuario: ' . mysqli_error($conn);
                        $_SESSION['flash_type'] = 'error';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        header('Location: /sistema/src/Views/perfil/usuarios.php');
        exit;
    }

    /* ── 3. ASIGNAR NUEVA CONTRASEÑA ESPECÍFICA ── */
    if ($accion === 'cambiar_password') {
        $id              = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nueva_pass      = isset($_POST['nueva_password']) ? trim($_POST['nueva_password']) : '';
        $confirmar_pass  = isset($_POST['confirmar_password']) ? trim($_POST['confirmar_password']) : '';

        if ($id <= 0 || $nueva_pass === '') {
            $_SESSION['flash_msg']  = 'La contraseña no puede estar vacía.';
            $_SESSION['flash_type'] = 'error';
        } elseif (strlen($nueva_pass) < 4) {
            $_SESSION['flash_msg']  = 'La contraseña debe contener al menos 4 caracteres.';
            $_SESSION['flash_type'] = 'error';
        } elseif ($nueva_pass !== $confirmar_pass) {
            $_SESSION['flash_msg']  = 'Las contraseñas ingresadas no coinciden.';
            $_SESSION['flash_type'] = 'error';
        } else {
            $hash = md5($nueva_pass);
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
            if (mysqli_stmt_execute($stmt)) {
                if (function_exists('log_activity')) {
                    log_activity($current_user_id, 'CAMBIO_PASSWORD_ADMIN', 'usuarios', "Cambió manualmente la contraseña del usuario ID: {$id}");
                }
                $_SESSION['flash_msg']  = "Contraseña actualizada exitosamente para el usuario seleccionado.";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_msg']  = 'Error al actualizar la contraseña: ' . mysqli_error($conn);
                $_SESSION['flash_type'] = 'error';
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: /sistema/src/Views/perfil/usuarios.php');
        exit;
    }

    /* ── 4. RESETEAR CON CONTRASEÑA ALEATORIA SEGURA ── */
    if ($accion === 'resetear') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $chars_lower   = 'abcdefghjkmnpqrstuvwxyz';
            $chars_upper   = 'ABCDEFGHJKMNPQRSTUVWXYZ';
            $chars_digits  = '23456789';
            $chars_special = '@#$%!';

            $password  = '';
            $password .= substr($chars_upper,   mt_rand(0, strlen($chars_upper)   - 1), 1);
            $password .= substr($chars_upper,   mt_rand(0, strlen($chars_upper)   - 1), 1);
            $password .= substr($chars_lower,   mt_rand(0, strlen($chars_lower)   - 1), 1);
            $password .= substr($chars_lower,   mt_rand(0, strlen($chars_lower)   - 1), 1);
            $password .= substr($chars_lower,   mt_rand(0, strlen($chars_lower)   - 1), 1);
            $password .= substr($chars_digits,  mt_rand(0, strlen($chars_digits)  - 1), 1);
            $password .= substr($chars_digits,  mt_rand(0, strlen($chars_digits)  - 1), 1);
            $password .= substr($chars_digits,  mt_rand(0, strlen($chars_digits)  - 1), 1);
            $password .= substr($chars_special, mt_rand(0, strlen($chars_special) - 1), 1);
            $password .= substr($chars_special, mt_rand(0, strlen($chars_special) - 1), 1);

            $chars_all = $chars_lower . $chars_upper . $chars_digits . $chars_special;
            $password .= substr($chars_all, mt_rand(0, strlen($chars_all) - 1), 1);
            $password .= substr($chars_all, mt_rand(0, strlen($chars_all) - 1), 1);
            $password  = str_shuffle($password);

            $nueva_pass_hash = md5($password);
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $nueva_pass_hash, $id);
            if (mysqli_stmt_execute($stmt)) {
                // Obtener nombre del usuario reseteado
                $res_nom = mysqli_query($conn, "SELECT nombre, usuario FROM usuarios WHERE id = $id LIMIT 1");
                $fila_nom = $res_nom ? mysqli_fetch_assoc($res_nom) : null;
                $nom_usr = $fila_nom ? $fila_nom['nombre'] : "ID $id";

                if (function_exists('log_activity')) {
                    log_activity($current_user_id, 'RESET_PASSWORD', 'usuarios', "Generó contraseña aleatoria para usuario ID: {$id}");
                }

                $_SESSION['reset_flash_pass'] = $password;
                $_SESSION['reset_user_nom']   = $nom_usr;
                $_SESSION['flash_msg']        = "Contraseña restablecida exitosamente para '{$nom_usr}'.";
                $_SESSION['flash_type']       = 'success';
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: /sistema/src/Views/perfil/usuarios.php');
        exit;
    }

    /* ── 5. ELIMINAR USUARIO ── */
    if ($accion === 'eliminar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $_SESSION['flash_msg']  = 'ID de usuario inválido.';
            $_SESSION['flash_type'] = 'error';
        } elseif ($id === $current_user_id) {
            $_SESSION['flash_msg']  = 'Acción denegada: No puedes eliminar tu propia cuenta de usuario en sesión activa.';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Verificar si el usuario a eliminar es un admin y si es el último
            $res_check = mysqli_query($conn, "SELECT usuario, nombre, rol FROM usuarios WHERE id = $id LIMIT 1");
            $usr_data  = $res_check ? mysqli_fetch_assoc($res_check) : null;

            if (!$usr_data) {
                $_SESSION['flash_msg']  = 'El usuario no existe.';
                $_SESSION['flash_type'] = 'error';
            } elseif ($usr_data['rol'] === 'admin') {
                $res_count_adm = mysqli_query($conn, "SELECT COUNT(*) as total FROM usuarios WHERE rol = 'admin' AND id != $id");
                $fila_count    = $res_count_adm ? mysqli_fetch_assoc($res_count_adm) : null;
                if ($fila_count && (int)$fila_count['total'] === 0) {
                    $_SESSION['flash_msg']  = 'No es posible eliminar al único administrador que queda en el sistema.';
                    $_SESSION['flash_type'] = 'error';
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE FROM usuarios WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    if (function_exists('log_activity')) {
                        log_activity($current_user_id, 'ELIMINAR_USUARIO', 'usuarios', "Eliminó al usuario '{$usr_data['usuario']}' ({$usr_data['nombre']})");
                    }
                    $_SESSION['flash_msg']  = "Usuario '{$usr_data['usuario']}' eliminado definitivamente del sistema.";
                    $_SESSION['flash_type'] = 'success';
                }
            } else {
                $stmt = mysqli_prepare($conn, "DELETE FROM usuarios WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if (function_exists('log_activity')) {
                    log_activity($current_user_id, 'ELIMINAR_USUARIO', 'usuarios', "Eliminó al usuario '{$usr_data['usuario']}' ({$usr_data['nombre']})");
                }
                $_SESSION['flash_msg']  = "Usuario '{$usr_data['usuario']}' eliminado definitivamente.";
                $_SESSION['flash_type'] = 'success';
            }
        }
        header('Location: /sistema/src/Views/perfil/usuarios.php');
        exit;
    }
}

/* ════════════════════════════════════════════════════════════════
   CONSULTA DE USUARIOS Y ESTADÍSTICAS
   ════════════════════════════════════════════════════════════════ */
$usuarios = array();
$total_admins = 0;
$total_supervisores = 0;
$total_visualizadores = 0;

$result = mysqli_query($conn, "SELECT id, nombre, usuario, rol, foto FROM usuarios ORDER BY nombre ASC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $usuarios[] = $row;
        if ($row['rol'] === 'admin') $total_admins++;
        elseif ($row['rol'] === 'supervisor') $total_supervisores++;
        else $total_visualizadores++;
    }
    mysqli_free_result($result);
}

$total_usuarios = count($usuarios);

// Mensajes flash
$flash_msg  = isset($_SESSION['flash_msg']) ? $_SESSION['flash_msg'] : '';
$flash_type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Contraseña generada en reseteo (se muestra 1 sola vez)
$reset_flash_pass = isset($_SESSION['reset_flash_pass']) ? $_SESSION['reset_flash_pass'] : '';
$reset_user_nom   = isset($_SESSION['reset_user_nom']) ? $_SESSION['reset_user_nom'] : '';
unset($_SESSION['reset_flash_pass'], $_SESSION['reset_user_nom']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestión de Usuarios y Roles - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Gestión de Usuarios – Contraloría MSR</title>
    <style>
        /* Tipografías institucionales */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'IBM Plex Sans', sans-serif !important;
        }

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
            color: #0f172a;
        }

        /* ── Layout ── */
        .layout {
            display: flex;
            flex: 1;
        }

        .main-content {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }

        .container {
            max-width: 1050px;
            margin: 0 auto;
        }

        /* ── Header de Página ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, #070707 0%, #1e293b 100%);
            color: white;
            padding: 20px 24px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
            border-bottom: 3px solid #2563eb;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: rgba(37, 99, 235, 0.2);
            border: 1px solid rgba(37, 99, 235, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #60a5fa;
        }

        .page-header h1 {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }

        .page-header .page-sub {
            font-size: 12px;
            color: #94a3b8;
        }

        .btn-crear-usuario {
            background: #2563eb;
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            font-size: 12px;
            font-weight: 700;
            font-family: inherit;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
            transition: all 0.15s ease;
        }

        .btn-crear-usuario:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.45);
        }

        /* ── Tarjetas de Estadísticas ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
        }

        .stat-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }

        .stat-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon-total  { background: #eff6ff; color: #2563eb; }
        .stat-icon-admin  { background: #ede9fe; color: #7c3aed; }
        .stat-icon-super  { background: #fef3c7; color: #d97706; }
        .stat-icon-viewer { background: #f1f5f9; color: #475569; }

        /* ── Alertas y Notificaciones ── */
        .alert {
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .pass-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            background: #ffffff;
            border: 1.5px solid #10b981;
            border-radius: 8px;
            padding: 10px 14px;
            width: 100%;
        }

        .pass-box .pass-value {
            font-family: 'Courier New', Courier, monospace;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #065f46;
            flex: 1;
            word-break: break-all;
        }

        .btn-copy {
            background: #059669;
            color: #ffffff;
            border: none;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 700;
            font-family: inherit;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-copy:hover {
            background: #047857;
        }

        .btn-copy.copied {
            background: #475569;
        }

        /* ── Barra de Búsqueda y Filtros ── */
        .filter-bar {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 12px;
            flex: 1;
            min-width: 240px;
        }

        .search-wrap svg {
            color: #94a3b8;
            flex-shrink: 0;
        }

        .search-input {
            border: none;
            background: transparent;
            font-size: 12px;
            font-family: inherit;
            width: 100%;
            outline: none;
            color: #0f172a;
        }

        .filter-role-select {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 7px 12px;
            font-size: 11.5px;
            font-family: inherit;
            color: #0f172a;
            outline: none;
            cursor: pointer;
        }

        /* ── Tabla Principal ── */
        .table-wrapper {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(0,0,0,0.04);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        thead {
            background: #1e293b;
            color: #f8fafc;
        }

        thead th {
            padding: 13px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 2px solid #334155;
            background: #1e293b;
            color: #f8fafc;
        }

        tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.12s ease;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover td {
            background: #f1f5f9;
        }

        tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            color: #1e293b;
        }

        /* Chip de usuario */
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
        }

        .user-avatar-admin {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }

        .user-avatar-super {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }

        .user-avatar-viewer {
            background: linear-gradient(135deg, #475569 0%, #64748b 100%);
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .user-name-txt {
            font-weight: 700;
            color: #0f172a;
            font-size: 12px;
        }

        .user-handle {
            font-family: monospace;
            color: #64748b;
            font-size: 11px;
        }

        .self-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 9.5px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 4px;
            margin-left: 6px;
            text-transform: uppercase;
        }

        /* Badges de Roles */
        .rol-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .rol-admin {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .rol-supervisor {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .rol-visualizador {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        /* Botones de acción */
        .actions-cell {
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-act {
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .btn-act-edit {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .btn-act-edit:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-act-pass {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        .btn-act-pass:hover {
            background: #bae6fd;
            color: #0369a1;
        }

        .btn-act-reset {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .btn-act-reset:hover {
            background: #fde68a;
            color: #92400e;
        }

        .btn-act-del {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-act-del:hover {
            background: #fecaca;
            color: #b91c1c;
        }

        .btn-act:disabled,
        .btn-act[disabled] {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* ── MODALES ── */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #ffffff;
            border-radius: 14px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalIn {
            from { transform: translateY(12px) scale(0.97); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }

        .modal-header {
            background: #0f172a;
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
        }

        .modal-close {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .modal-close:hover {
            color: #ffffff;
            background: rgba(255,255,255,0.1);
        }

        .modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 11.5px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .form-input {
            width: 100%;
            border: 1.5px solid #cbd5e1;
            background: #ffffff;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 12.5px;
            font-family: inherit;
            color: #0f172a;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .pass-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .pass-input-wrap .form-input {
            padding-right: 40px;
        }

        .btn-toggle-eye {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-toggle-eye:hover {
            color: #0f172a;
        }

        .btn-gen-pass {
            background: #f1f5f9;
            border: 1px dashed #94a3b8;
            color: #475569;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 4px;
            align-self: flex-start;
            transition: all 0.15s;
        }

        .btn-gen-pass:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .modal-footer {
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
        }

        .btn-cancel:hover {
            background: #f1f5f9;
        }

        .btn-save {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
            box-shadow: 0 2px 6px rgba(37,99,235,0.3);
        }

        .btn-save:hover {
            background: #1d4ed8;
        }

        .btn-save-danger {
            background: #dc2626;
            box-shadow: 0 2px 6px rgba(220,38,38,0.3);
        }

        .btn-save-danger:hover {
            background: #b91c1c;
        }
    </style>
</head>

<body>

    <!-- LAYOUT -->
    <div class="layout">

        <?php $active = 'usuarios';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container">

                <!-- 1. ENCABEZADO DE PÁGINA -->
                <div class="page-header">
                    <div class="page-header-left">
                        <div class="header-icon-box">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <div>
                            <h1>Gestión de Usuarios</h1>
                            <div class="page-sub">Administración completa de cuentas, roles institucionales y credenciales</div>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn-crear-usuario" onclick="abrirModalCrear()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Nuevo Usuario
                        </button>
                    </div>
                </div>

                <!-- 2. ALERTAS Y FLASH MESSAGES -->
                <?php if ($flash_msg !== ''): ?>
                    <div class="alert alert-<?php echo ($flash_type === 'success') ? 'success' : 'error'; ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <?php if ($flash_type === 'success'): ?>
                                <polyline points="20 6 9 17 4 12"/>
                            <?php else: ?>
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                            <?php endif; ?>
                        </svg>
                        <div><?php echo htmlspecialchars($flash_msg); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($reset_flash_pass !== ''): ?>
                    <div class="alert alert-success" style="flex-direction:column; align-items:flex-start;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Contraseña temporal generada para <strong><?php echo htmlspecialchars($reset_user_nom); ?></strong>:</span>
                        </div>
                        <div class="pass-box">
                            <span class="pass-value" id="pass-plain"><?php echo htmlspecialchars($reset_flash_pass); ?></span>
                            <button type="button" class="btn-copy" id="btn-copy-pass" onclick="copiarClaveTemporal()">Copiar Clave</button>
                        </div>
                        <div style="margin-top:6px; font-size:11px; color:#065f46; font-style:italic;">
                            ⚠ Copie esta contraseña y suminístresela al usuario. Por motivos de seguridad no se volverá a mostrar.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 3. TARJETAS DE ESTADÍSTICAS -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Total Usuarios</span>
                            <span class="stat-value"><?php echo $total_usuarios; ?></span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-total">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Administradores</span>
                            <span class="stat-value"><?php echo $total_admins; ?></span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-admin">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Supervisores</span>
                            <span class="stat-value"><?php echo $total_supervisores; ?></span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-super">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-info">
                            <span class="stat-label">Visualizadores</span>
                            <span class="stat-value"><?php echo $total_visualizadores; ?></span>
                        </div>
                        <div class="stat-icon-wrap stat-icon-viewer">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- 4. BARRA DE FILTRO Y BÚSQUEDA -->
                <div class="filter-bar">
                    <div class="search-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="filtro-usuarios" class="search-input" placeholder="Buscar por nombre o usuario..." oninput="filtrarUsuarios()">
                    </div>
                    <div>
                        <select id="filtro-rol" class="filter-role-select" onchange="filtrarUsuarios()">
                            <option value="">Todos los roles</option>
                            <option value="admin">Administrador</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="visualizador">Visualizador</option>
                        </select>
                    </div>
                </div>

                <!-- 5. TABLA DE USUARIOS -->
                <div class="table-wrapper">
                    <table id="tabla-usuarios">
                        <thead>
                            <tr>
                                <th style="width:32%">Usuario</th>
                                <th style="width:28%">Nombre Completo</th>
                                <th style="width:16%">Rol Asignado</th>
                                <th style="width:24%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) === 0): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:#94a3b8; padding:30px;">
                                    No hay usuarios registrados en el sistema.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                            <?php 
                                $es_propio = ($current_user_id === (int)$u['id']);
                                $inicial = strtoupper(substr($u['nombre'] !== '' ? $u['nombre'] : $u['usuario'], 0, 1));
                                $rol_lower = strtolower($u['rol']);
                                $avatar_cls = 'user-avatar-viewer';
                                if ($rol_lower === 'admin') $avatar_cls = 'user-avatar-admin';
                                elseif ($rol_lower === 'supervisor') $avatar_cls = 'user-avatar-super';
                            ?>
                            <tr class="row-usuario" data-rol="<?php echo htmlspecialchars($rol_lower); ?>" data-search="<?php echo htmlspecialchars(strtolower($u['nombre'] . ' ' . $u['usuario'])); ?>">
                                <td>
                                    <div class="user-chip">
                                        <div class="user-avatar <?php echo $avatar_cls; ?>">
                                            <?php echo htmlspecialchars($inicial); ?>
                                        </div>
                                        <div class="user-details">
                                            <span class="user-name-txt">
                                                <?php echo htmlspecialchars($u['usuario']); ?>
                                                <?php if ($es_propio): ?>
                                                    <span class="self-badge">Tú</span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="user-handle">ID #<?php echo (int)$u['id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color:#1e293b;"><?php echo htmlspecialchars($u['nombre']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($rol_lower === 'admin'): ?>
                                        <span class="rol-badge rol-admin">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            Administrador
                                        </span>
                                    <?php elseif ($rol_lower === 'supervisor'): ?>
                                        <span class="rol-badge rol-supervisor">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                            Supervisor
                                        </span>
                                    <?php else: ?>
                                        <span class="rol-badge rol-visualizador">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Visualizador
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <!-- Editar Datos / Rol -->
                                        <button type="button" class="btn-act btn-act-edit" title="Editar Nombre, Usuario y Rol"
                                                onclick="abrirModalEditar(<?php echo (int)$u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['nombre'])); ?>', '<?php echo addslashes(htmlspecialchars($u['usuario'])); ?>', '<?php echo htmlspecialchars($rol_lower); ?>')">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            Editar
                                        </button>

                                        <!-- Asignar Contraseña -->
                                        <button type="button" class="btn-act btn-act-pass" title="Asignar nueva contraseña"
                                                onclick="abrirModalPassword(<?php echo (int)$u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['usuario'])); ?>')">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                            Clave
                                        </button>

                                        <!-- Resetear Clave Aleatoria -->
                                        <form method="POST" action="usuarios.php" style="display:inline;" onsubmit="return confirm('¿Restablecer y generar una nueva contraseña temporal para <?php echo addslashes(htmlspecialchars($u['nombre'])); ?>?');">
                                            <input type="hidden" name="accion" value="resetear">
                                            <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                            <button type="submit" class="btn-act btn-act-reset" title="Generar clave aleatoria segura">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                                Reset
                                            </button>
                                        </form>

                                        <!-- Eliminar Usuario -->
                                        <?php if ($es_propio): ?>
                                            <button type="button" class="btn-act btn-act-del" disabled title="No puedes eliminar tu propia cuenta de administrador">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="usuarios.php" style="display:inline;" onsubmit="return confirm('¿Está seguro de que desea ELIMINAR permanentemente al usuario <?php echo addslashes(htmlspecialchars($u['usuario'])); ?> (<?php echo addslashes(htmlspecialchars($u['nombre'])); ?>)? Esta acción no se puede deshacer.');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                                <button type="submit" class="btn-act btn-act-del" title="Eliminar usuario del sistema">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>

    </div><!-- /.layout -->

    <!-- ════════════════════════════════════════════════════════════════
         MODAL 1: CREAR NUEVO USUARIO
         ════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="modal-crear">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Crear Nuevo Usuario</h3>
                <button type="button" class="modal-close" onclick="cerrarModal('modal-crear')">&times;</button>
            </div>
            <form method="POST" action="usuarios.php">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="crear-nombre">Nombre Completo</label>
                        <input type="text" id="crear-nombre" name="nombre" class="form-input" placeholder="Ej: María Rodríguez" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="crear-usuario">Nombre de Usuario (Login)</label>
                        <input type="text" id="crear-usuario" name="usuario" class="form-input" placeholder="Ej: mrodriguez" required autocomplete="off" style="text-transform:lowercase;" oninput="this.value=this.value.toLowerCase().replace(/\s+/g,'')">
                    </div>

                    <div class="form-group">
                        <label for="crear-rol">Rol en el Sistema</label>
                        <select id="crear-rol" name="rol" class="form-input" required>
                            <option value="visualizador">Visualizador (Solo consulta e impresión)</option>
                            <option value="supervisor">Supervisor (Emisión de órdenes y estatus)</option>
                            <option value="admin">Administrador (Acceso total al sistema)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="crear-pass">Contraseña Inicial</label>
                        <div class="pass-input-wrap">
                            <input type="password" id="crear-pass" name="password" class="form-input" placeholder="Mínimo 4 caracteres" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-eye" onclick="togglePassVisibility('crear-pass')" title="Mostrar / Ocultar">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button type="button" class="btn-gen-pass" onclick="generarSugerenciaPass('crear-pass')">⚡ Generar clave segura automática</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-crear')">Cancelar</button>
                    <button type="submit" class="btn-save">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         MODAL 2: EDITAR DATOS Y ROL
         ════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="modal-editar">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Editar Usuario</h3>
                <button type="button" class="modal-close" onclick="cerrarModal('modal-editar')">&times;</button>
            </div>
            <form method="POST" action="usuarios.php">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="id" id="edit-id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-nombre">Nombre Completo</label>
                        <input type="text" id="edit-nombre" name="nombre" class="form-input" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="edit-usuario">Nombre de Usuario</label>
                        <input type="text" id="edit-usuario" name="usuario" class="form-input" required autocomplete="off" style="text-transform:lowercase;" oninput="this.value=this.value.toLowerCase().replace(/\s+/g,'')">
                    </div>

                    <div class="form-group">
                        <label for="edit-rol">Rol en el Sistema</label>
                        <select id="edit-rol" name="rol" class="form-input" required>
                            <option value="visualizador">Visualizador (Solo consulta e impresión)</option>
                            <option value="supervisor">Supervisor (Emisión de órdenes y estatus)</option>
                            <option value="admin">Administrador (Acceso total al sistema)</option>
                        </select>
                    </div>

                    <div style="font-size:11px; color:#64748b; background:#f1f5f9; padding:10px; border-radius:6px; line-height:1.4;">
                        ℹ <strong>Nota:</strong> Para cambiar la contraseña de este usuario utilice el botón <strong>"Clave"</strong> o la opción <strong>"Reset"</strong> en la tabla.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-editar')">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════
         MODAL 3: CAMBIAR CONTRASEÑA ESPECÍFICA
         ════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="modal-password">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Cambiar Contraseña</h3>
                <button type="button" class="modal-close" onclick="cerrarModal('modal-password')">&times;</button>
            </div>
            <form method="POST" action="usuarios.php" onsubmit="return validarPasswordMatch()">
                <input type="hidden" name="accion" value="cambiar_password">
                <input type="hidden" name="id" id="pass-id" value="">
                <div class="modal-body">
                    <div style="font-size:12px; color:#334155; margin-bottom:4px;">
                        Asignando nueva contraseña para el usuario: <strong id="pass-user-label" style="color:#0f172a;"></strong>
                    </div>

                    <div class="form-group">
                        <label for="nueva-password">Nueva Contraseña</label>
                        <div class="pass-input-wrap">
                            <input type="password" id="nueva-password" name="nueva_password" class="form-input" placeholder="Mínimo 4 caracteres" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-eye" onclick="togglePassVisibility('nueva-password')" title="Mostrar / Ocultar">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <button type="button" class="btn-gen-pass" onclick="generarSugerenciaPass('nueva-password')">⚡ Generar clave segura</button>
                    </div>

                    <div class="form-group">
                        <label for="confirmar-password">Confirmar Nueva Contraseña</label>
                        <div class="pass-input-wrap">
                            <input type="password" id="confirmar-password" name="confirmar_password" class="form-input" placeholder="Repita la nueva contraseña" required autocomplete="new-password">
                            <button type="button" class="btn-toggle-eye" onclick="togglePassVisibility('confirmar-password')" title="Mostrar / Ocultar">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modal-password')">Cancelar</button>
                    <button type="submit" class="btn-save">Actualizar Contraseña</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPTS DE INTERACCIÓN -->
    <script>
    function abrirModalCrear() {
        document.getElementById('crear-nombre').value = '';
        document.getElementById('crear-usuario').value = '';
        document.getElementById('crear-pass').value = '';
        document.getElementById('crear-rol').value = 'visualizador';
        document.getElementById('modal-crear').classList.add('active');
        document.getElementById('crear-nombre').focus();
    }

    function abrirModalEditar(id, nombre, usuario, rol) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-nombre').value = nombre;
        document.getElementById('edit-usuario').value = usuario;
        document.getElementById('edit-rol').value = rol;
        document.getElementById('modal-editar').classList.add('active');
        document.getElementById('edit-nombre').focus();
    }

    function abrirModalPassword(id, usuario) {
        document.getElementById('pass-id').value = id;
        document.getElementById('pass-user-label').textContent = '@' + usuario;
        document.getElementById('nueva-password').value = '';
        document.getElementById('confirmar-password').value = '';
        document.getElementById('modal-password').classList.add('active');
        document.getElementById('nueva-password').focus();
    }

    function cerrarModal(modalId) {
        var el = document.getElementById(modalId);
        if (el) el.classList.remove('active');
    }

    // Cerrar con Escape o haciendo clic fuera del modal
    document.querySelectorAll('.modal-overlay').forEach(function(m) {
        m.addEventListener('click', function(e) {
            if (e.target === m) {
                m.classList.remove('active');
            }
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(function(m) {
                m.classList.remove('active');
            });
        }
    });

    function togglePassVisibility(inputId) {
        var inp = document.getElementById(inputId);
        if (!inp) return;
        inp.type = (inp.type === 'password') ? 'text' : 'password';
    }

    function generarSugerenciaPass(targetId) {
        var lower = 'abcdefghjkmnpqrstuvwxyz';
        var upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        var digits = '23456789';
        var specials = '@#$%!';
        var all = lower + upper + digits + specials;

        var p = '';
        p += upper.charAt(Math.floor(Math.random() * upper.length));
        p += upper.charAt(Math.floor(Math.random() * upper.length));
        p += lower.charAt(Math.floor(Math.random() * lower.length));
        p += lower.charAt(Math.floor(Math.random() * lower.length));
        p += digits.charAt(Math.floor(Math.random() * digits.length));
        p += digits.charAt(Math.floor(Math.random() * digits.length));
        p += specials.charAt(Math.floor(Math.random() * specials.length));
        for (var i = 0; i < 3; i++) {
            p += all.charAt(Math.floor(Math.random() * all.length));
        }
        p = p.split('').sort(function(){return 0.5 - Math.random()}).join('');

        var inp = document.getElementById(targetId);
        if (inp) {
            inp.value = p;
            inp.type = 'text'; // Mostrar para que el admin la vea
        }
        var confirmInp = document.getElementById('confirmar-password');
        if (targetId === 'nueva-password' && confirmInp) {
            confirmInp.value = p;
            confirmInp.type = 'text';
        }
    }

    function validarPasswordMatch() {
        var p1 = document.getElementById('nueva-password').value;
        var p2 = document.getElementById('confirmar-password').value;
        if (p1 !== p2) {
            alert('Las contraseñas no coinciden. Por favor verifíquelas.');
            return false;
        }
        if (p1.length < 4) {
            alert('La contraseña debe tener al menos 4 caracteres.');
            return false;
        }
        return true;
    }

    function copiarClaveTemporal() {
        var val = document.getElementById('pass-plain').innerText;
        var btn = document.getElementById('btn-copy-pass');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(val).then(function() {
                btn.textContent = '¡Copiada!';
                btn.classList.add('copied');
                setTimeout(function() {
                    btn.textContent = 'Copiar Clave';
                    btn.classList.remove('copied');
                }, 2000);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = val;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            btn.textContent = '¡Copiada!';
            btn.classList.add('copied');
            setTimeout(function() {
                btn.textContent = 'Copiar Clave';
                btn.classList.remove('copied');
            }, 2000);
        }
    }

    // Filtrado interactivo en tiempo real
    function filtrarUsuarios() {
        var txt = (document.getElementById('filtro-usuarios').value || '').toLowerCase().trim();
        var rol = (document.getElementById('filtro-rol').value || '').toLowerCase().trim();
        var rows = document.querySelectorAll('.row-usuario');

        rows.forEach(function(r) {
            var searchData = r.getAttribute('data-search') || '';
            var rolData = r.getAttribute('data-rol') || '';

            var matchTxt = (txt === '' || searchData.indexOf(txt) !== -1);
            var matchRol = (rol === '' || rolData === rol);

            if (matchTxt && matchRol) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
    }
    </script>

    <!-- FOOTER -->
    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?>
</body>
</html>