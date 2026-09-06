<?php
session_start();

if (!isset($_SESSION['usuario']) && !isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/login.php');
    exit;
}

$es_admin = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');

require_once '../../../config/conexion.php';

$nombre_valor = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : (isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : '');
$usuario_valor = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '';
$rol_valor = isset($_SESSION['rol']) ? $_SESSION['rol'] : 'visualizador';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="description" content="Mi Perfil - Contraloría del Municipio Simón Rodríguez">
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    <title>Mi Perfil – Contraloría MSR</title>
    <style>
        /* Premium typography: IBM Plex Sans headings, Inter body */
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
            font-size: 12px;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ── */
        .site-header {
            background: #070707;
            color: white;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 20px;
            border-bottom: 3px solid #2563eb;
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

        /* ── Layout ── */
        .layout {
            display: flex;
            flex: 1;
        }

        /* ── Main Content ── */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
        }

        .container {
            background: white;
            padding: 24px;
            border: 1px solid #cbd5e1;
            max-width: 600px;
            margin: 0 auto;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        /* ── Profile Banner ── */
        .profile-banner {
            background-image: url(/sistema/assets/img/banner.png);
            background-size: cover;
            background-position: center;
            color: black;
            padding: 20px 24px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.2);
            opacity: 1.2;
        }

        .profile-avatar-lg {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #000000ff;
            border: 2px solid #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            flex-shrink: 0;
        }

        .profile-info-title {
            font-size: 18px;
            font-weight: 800;
            color: #f3f2f2ff;
            margin-bottom: 2px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .profile-info-role {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.15);
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        /* ── Form styling ── */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
            font-size: 11.5px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            font-family: inherit;
            font-size: 12px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #1e293b;
            transition: all 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn-submit {
            background: #101A36;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .btn-submit:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        /* ── Alerts ── */
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

        /* ── Footer ── */
        .site-footer {
            background: #000000ff;
            color: #90a4ae;
            text-align: center;
            padding: 12px 20px;
            font-size: 10.5px;
            border-top: 3px solid #2563eb;
            line-height: 1.8;
            margin-top: auto;
        }

        .site-footer strong {
            color: #e3f2fd;
        }
    </style>
</head>

<body>

    <!-- LAYOUT -->
    <div class="layout">

        <?php $active = 'perfil';
        require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container">

                <div class="profile-banner">
                    <?php
                    $init_char = strtoupper(substr($nombre_valor !== '' ? $nombre_valor : $usuario_valor, 0, 1));
                    $foto_valor = isset($_SESSION['foto']) ? $_SESSION['foto'] : (isset($_SESSION['usuario_foto']) ? $_SESSION['usuario_foto'] : '');
                    if (empty($foto_valor) && isset($conn) && $conn && isset($_SESSION['usuario_id'])) {
                        $uid = (int)$_SESSION['usuario_id'];
                        $rq = @mysqli_query($conn, "SELECT foto FROM usuarios WHERE id = $uid LIMIT 1");
                        if ($rq && $rw = @mysqli_fetch_assoc($rq)) {
                            $foto_valor = isset($rw['foto']) ? $rw['foto'] : '';
                            $_SESSION['foto'] = $foto_valor;
                            $_SESSION['usuario_foto'] = $foto_valor;
                        }
                    }
                    ?>
                    <div class="profile-avatar-lg" style="overflow: hidden; padding: 0;">
                        <?php if (!empty($foto_valor) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/sistema/assets/img/avatars/' . $foto_valor)): ?>
                            <img src="/sistema/assets/img/avatars/<?php echo htmlspecialchars($foto_valor); ?>" alt="PFP" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        <?php else: ?>
                            <?php echo htmlspecialchars($init_char); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="profile-info-title">
                            <?php echo htmlspecialchars($nombre_valor !== '' ? $nombre_valor : $usuario_valor); ?>
                        </div>
                        <div class="profile-info-role"><?php echo htmlspecialchars(ucfirst($rol_valor)); ?></div>
                    </div>
                </div>

                <?php if (isset($_GET['ok']) && $_GET['ok'] == '1'): ?>
                    <div class="alert alert-success">
                        Perfil actualizado exitosamente.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <?php
                        $err = $_GET['error'];
                        if ($err === 'password_mismatch') {
                            echo 'Error: Las contraseñas no coinciden.';
                        } elseif ($err === 'datos_vacios') {
                            echo 'Error: Por favor complete los campos obligatorios.';
                        } else {
                            echo 'Error: Ocurrió un problema al actualizar el perfil.';
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <form action="/sistema/controllers/auth/actualizar_perfil.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo:</label>
                        <input type="text" name="nombre" id="nombre" class="form-control"
                            value="<?php echo htmlspecialchars($nombre_valor); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="usuario">Usuario:</label>
                        <input type="text" name="usuario" id="usuario" class="form-control"
                            value="<?php echo htmlspecialchars($usuario_valor); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="foto">Foto de Perfil (PFP):</label>
                        <input type="file" name="foto" id="foto" class="form-control" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">
                        <small style="color: #64748b; display: block; margin-top: 4px;">Sube una nueva imagen para actualizar tu foto al instante.</small>
                        <?php if (!empty($foto_valor) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/sistema/assets/img/avatars/' . $foto_valor)): ?>
                            <div style="margin-top: 8px;">
                                <a href="/sistema/controllers/auth/eliminar_foto.php" onclick="return confirm('¿Estás seguro de eliminar tu foto de perfil?');" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; color: #dc2626; text-decoration: none;">
                                    &times; Eliminar foto actual
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="rol">Rol de Usuario:</label>
                        <input type="text" id="rol" class="form-control"
                            value="<?php echo htmlspecialchars(ucfirst($rol_valor)); ?>" readonly
                            style="background:#e9ecef; cursor:not-allowed;">
                    </div>

                    <hr style="margin: 18px 0; border: none; border-top: 1px solid #ccc;">

                    <div class="form-group">
                        <label for="password_nueva">Nueva Contraseña (dejar en blanco para mantener la actual):</label>
                        <input type="password" name="password_nueva" id="password_nueva" class="form-control" placeholder="***">
                    </div>

                    <div class="form-group">
                        <label for="password_confirmar">Confirmar Nueva Contraseña:</label>
                        <input type="password" name="password_confirmar" id="password_confirmar" class="form-control" placeholder="***">
                    </div>

                    <hr style="margin: 18px 0; border: none; border-top: 1px solid #cbd5e1;">

                    <h4 style="font-size: 14px; margin-bottom: 12px; color: #1e293b; font-weight: 600;">Preferencias de
                        Sesión e Inactividad</h4>

                    <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                        <input type="checkbox" id="autologout_enabled"
                            style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="autologout_enabled"
                            style="cursor: pointer; margin-bottom: 0; font-weight: 500; color: #334155;">
                            Activar cierre automático de sesión por inactividad
                        </label>
                    </div>

                    <div class="form-group" id="autologout_minutes_group">
                        <label for="autologout_minutes">Tiempo de inactividad (en minutos):</label>
                        <input type="number" id="autologout_minutes" class="form-control" min="1" max="120" value="5"
                            placeholder="Ej. 5">
                        <small style="color: #64748b; display: block; margin-top: 4px;">El sistema mostrará un aviso
                            emergente con cuenta regresiva de 10 segundos antes del cierre de sesión.</small>
                    </div>

                    <div style="margin-top: 18px; text-align: right;">
                        <button type="submit" class="btn-submit">Guardar Cambios</button>
                    </div>
                </form>

            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const enabledInput = document.getElementById('autologout_enabled');
            const minutesInput = document.getElementById('autologout_minutes');
            const form = document.querySelector('form[action*="actualizar_perfil.php"]');

            // Load existing user settings from localStorage or defaults
            let settings = { enabled: true, timeoutMinutes: 5 };
            try {
                const saved = localStorage.getItem('user_session_settings');
                if (saved) {
                    settings = Object.assign(settings, JSON.parse(saved));
                }
            } catch (e) { }

            if (enabledInput) enabledInput.checked = settings.enabled;
            if (minutesInput) minutesInput.value = settings.timeoutMinutes || 5;

            // Auto-submit form when PFP file is selected
            const fotoInput = document.getElementById('foto');
            if (fotoInput) {
                fotoInput.addEventListener('change', () => {
                    if (fotoInput.files && fotoInput.files.length > 0) {
                        if (form) form.submit();
                    }
                });
            }

            // Save settings on profile form submit
            if (form) {
                form.addEventListener('submit', () => {
                    const isEnabled = enabledInput ? enabledInput.checked : true;
                    const mins = minutesInput ? parseInt(minutesInput.value, 10) || 5 : 5;

                    if (window.sessionAutoLogoutInstance) {
                        window.sessionAutoLogoutInstance.saveSettings(isEnabled, mins);
                    } else {
                        try {
                            localStorage.setItem('user_session_settings', JSON.stringify({
                                enabled: isEnabled,
                                timeoutMinutes: mins
                            }));
                        } catch (e) { }
                    }
                });
            }
        });
    </script><?php require_once $_SERVER['DOCUMENT_ROOT'] . '/sistema/includes/footer.php'; ?>
</body>

</html>