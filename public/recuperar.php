<?php
/**
 * recuperar.php — Recuperación de contraseña
 * Compatible con PHP 5.4+
 */
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recuperación de contraseña — Sistema Contraloría">
    <title>Recuperar Contraseña — Sistema Contraloría</title>
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">
    
    
    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">

    <style>
    /* Premium typography: Geist headings & body */
        h1, h2, h3, h4, h5, h6,
        .page-title, .section-title, .card-title, .panel-title,
        .hdr-title, .brand-title, .brand-sub,
        .title-cell, .title-cell h2,
        .sb-section-label, .sb-parent-label, .sb-user-name, .sb-user-badge {
            font-family: 'Geist', sans-serif !important;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --navy:     #0B1F3A;
            --off-white:#F4F6FA;
            --white:    #FFFFFF;
            --border:   #E2E8F0;
            --text-primary:   #0F1C2E;
            --text-secondary: #5A6880;
            --text-muted:     #9BA8BA;
            --transition: .22s cubic-bezier(.4,0,.2,1);
        }

        body {
            font-family: 'Geist', sans-serif;
            background: var(--off-white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--text-primary);
        }

        /* ─── Card ─────────────────────────────────────────────────────── */
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border);
            box-shadow: 0 24px 80px rgba(11, 31, 58, .10);
            padding: 48px 44px;
            text-align: center;
        }

        /* Icon container */
        .icon-circle {
            width: 68px;
            height: 68px;
            background: #EEF2FF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: #3B6FD4;
        }

        /* Heading */
        .card h1 {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.2;
            color: var(--text-primary);
            margin-bottom: 14px;
        }

        /* Body text */
        .card p {
            font-size: .9rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 0;
        }

        .card p strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: var(--border);
            margin: 28px 0;
        }

        /* Info box */
        .info-box {
            background: #F0F4FF;
            border: 1px solid #C7D7F8;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
            margin-bottom: 28px;
        }

        .info-box svg {
            flex-shrink: 0;
            color: #3B6FD4;
            margin-top: 1px;
        }

        .info-box p {
            font-size: .82rem;
            color: #334E88;
            line-height: 1.55;
            margin-bottom: 0;
        }

        /* Back button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            width: 100%;
            padding: 13px;
            background: var(--navy);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: .88rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            letter-spacing: .02em;
            transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
        }

        .btn-back:hover {
            background: #132f5c;
            transform: translateY(-1px);
            box-shadow: 0 8px 28px rgba(11, 31, 58, .24);
        }

        .btn-back:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-back svg {
            transition: transform var(--transition);
        }

        .btn-back:hover svg {
            transform: translateX(-3px);
        }

        /* Footer */
        .card-footer {
            margin-top: 20px;
            font-size: .72rem;
            color: var(--text-muted);
        }
    </style>
</head>

<body>
    <div class="card">

        <!-- Lock icon -->
        <div class="icon-circle">
            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <h1>¿Olvidaste tu contraseña?</h1>

        <p>
            Por seguridad, el restablecimiento de contraseñas es gestionado directamente por el administrador del sistema.
        </p>

        <div class="divider"></div>

        <div class="info-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <p>
                Por favor <strong>contacta al administrador del sistema</strong> para que te asigne una nueva contraseña. Una vez recibida, podrás actualizarla desde tu perfil.
            </p>
        </div>

        <a href="login.php" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
            </svg>
            Volver al inicio de sesión
        </a>

        <p class="card-footer">
            &copy; <?php echo date('Y'); ?> Contraloría del Municipio Simón Rodríguez
        </p>

    </div>
</body>
</html>
