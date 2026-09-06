<?php
/**
 * login.php — Página de inicio de sesión
 * Compatible con PHP 5.4+
 */
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: /sistema/public/index.php');
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    $error = 'Usuario o contraseña incorrectos.';
}
$usuario = '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inicio de sesión al Sistema de Contraloría — Municipio Simón Rodríguez">
    <title>Iniciar Sesión — Sistema Contraloría</title>
    <link rel="icon" type="image/png" href="/sistema/assets/img/logo.png">


    <link href="/sistema/assets/fonts/fonts.css" rel="stylesheet">

    <style>
        /* Premium typography: Geist headings & body */
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
            font-family: 'Geist', sans-serif !important;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --navy: #0B1F3A;
            --white: #FFFFFF;
            --off-white: #F4F6FA;
            --text-primary: #0F1C2E;
            --text-secondary: #5A6880;
            --text-muted: #9BA8BA;
            --danger: #E05C5C;
            --danger-bg: #FEF0F0;
            --border: #E2E8F0;
            --border-focus: #3B6FD4;
            --shadow-input: 0 0 0 3px rgba(59, 111, 212, .14);
            --radius-sm: 10px;
            --transition: .22s cubic-bezier(.4, 0, .2, 1);
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: 'Geist', sans-serif;
            background: var(--off-white);
            display: flex;
            min-height: 100vh;
            color: var(--text-primary);
        }

        @keyframes pageFadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ─── LAYOUT ──────────────────────────────────────────────────── */
        .login-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            animation: pageFadeIn 1.1s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }




        /* ─── LEFT PANEL ──────────────────────────────────────────────── */
        .panel-left {
            flex: 0 0 50%;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;

            /* BACKGROUND LOgin */
            background-image: url('/sistema/assets/img/bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        /* Dark overlay*/
        .panel-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg,
                    rgba(6, 14, 45, 0.68) 0%,
                    rgba(8, 20, 70, 0.40) 45%,
                    rgba(0, 0, 0, 0.18) 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── Logo top-left ── */
        .left-header {
            position: relative;
            z-index: 2;
            padding: clamp(24px, 3.5vh, 40px) clamp(28px, 3.5vw, 48px);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .left-header img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, .35));
        }

        .left-header .org-name {
            color: rgba(255, 255, 255, .9);
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .03em;
            line-height: 1.3;
        }

        .left-header .org-name span {
            display: block;
            color: rgba(255, 255, 255, .55);
            font-size: .68rem;
            font-weight: 400;
            letter-spacing: .04em;
        }

        /* ── Bottom content ── */
        .left-body {
            position: relative;
            z-index: 2;
            margin-top: auto;
            padding: clamp(28px, 4vh, 52px) clamp(28px, 3.5vw, 52px);
        }

        .left-body h2 {
            color: #FFFFFF;
            font-size: clamp(1.7rem, 2.8vw, 2.6rem);
            font-weight: 800;
            line-height: 1.18;
            letter-spacing: -.025em;
            margin-bottom: clamp(12px, 1.8vh, 20px);
        }

        .left-body h2 em {
            font-style: italic;
            color: rgba(255, 210, 100, .95);
        }

        .left-body p {
            color: rgba(255, 255, 255, .65);
            font-size: clamp(.82rem, 1vw, .95rem);
            font-weight: 400;
            line-height: 1.7;
            max-width: 380px;
        }

        .left-copy {
            position: relative;
            z-index: 2;
            padding: clamp(12px, 1.5vh, 20px) clamp(28px, 3.5vw, 52px);
            color: rgba(255, 255, 255, .28);
            font-size: .65rem;
            letter-spacing: .05em;
        }

        /* ─── RIGHT PANEL — Dark Theme ─────────────────────────────────── */
        .panel-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            background: #09090b;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
        }

        /* Card header — centered */
        .card-header {
            margin-bottom: 32px;
            text-align: center;
        }

        .card-header h2 {
            font-size: 1.9rem;
            font-weight: 800;
            color: #f4f4f5;
            line-height: 1.15;
            letter-spacing: -.03em;
            margin-bottom: 7px;
        }

        .card-header .card-sub {
            font-size: .875rem;
            color: #71717a;
            font-weight: 400;
            line-height: 1.5;
        }

        /* Divider */
        .form-divider {
            height: 1px;
            background: #27272a;
            margin-bottom: 28px;
        }

        /* Error */
        .alert-error {
            background: rgba(220, 38, 38, .12);
            border: 1px solid rgba(220, 38, 38, .30);
            border-left: 4px solid #dc2626;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            font-size: .83rem;
            color: #fca5a5;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: #a1a1aa;
            margin-bottom: 8px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #52525b;
            pointer-events: none;
            transition: color var(--transition);
            display: flex;
            align-items: center;
        }

        .input-wrap input {
            width: 100%;
            padding: 13px 44px 13px 44px;
            border: 1.5px solid #27272a;
            border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif;
            font-size: .88rem;
            color: #f4f4f5;
            background: #18181b;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
        }

        .input-wrap input::placeholder {
            color: #52525b;
            font-weight: 400;
        }

        .input-wrap input:not(:placeholder-shown) {
            background: #1c1c1f;
            border-color: #3f3f46;
        }

        .input-wrap input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .18), 0 0 12px rgba(99, 102, 241, .10);
            background: #1c1c1f;
        }

        .input-wrap:focus-within .input-icon {
            color: #818cf8;
        }

        .toggle-pw {
            position: absolute;
            right: 13px;
            background: none;
            border: none;
            cursor: pointer;
            color: #52525b;
            padding: 4px;
            display: flex;
            align-items: center;
            border-radius: 6px;
            transition: color var(--transition), background var(--transition);
        }

        .toggle-pw:hover {
            color: #a1a1aa;
            background: #27272a;
        }

        .forgot-wrap {
            margin-top: 7px;
            text-align: right;
        }

        .forgot-link {
            font-size: .76rem;
            color: #818cf8;
            text-decoration: none;
            font-weight: 500;
            transition: opacity var(--transition);
        }

        .forgot-link:hover {
            opacity: .7;
        }

        /* Submit */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #6366f1;
            color: #ffffff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'Inter', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: .02em;
            overflow: hidden;
            transition: transform var(--transition), box-shadow var(--transition), background var(--transition);
            margin-top: 8px;
        }

        .btn-login:hover {
            background: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, .35);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-inner {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-arrow {
            display: inline-flex;
            transition: transform var(--transition);
        }

        .btn-login:hover .btn-arrow {
            transform: translateX(4px);
        }

        /* Footer */
        .login-footer {
            margin-top: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: .72rem;
            color: #3f3f46;
        }

        /* ─── RESPONSIVE ──────────────────────────────────────────────── */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }

            .panel-left {
                flex: none;
                width: 100%;
                min-height: 140px;
            }

            .left-body,
            .left-copy {
                display: none;
            }

            .panel-right {
                padding: 32px 20px 44px;
                background: #09090b;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ─── Login premium entrance animations ───────────────────────────── */
        @keyframes lg-card-in {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes lg-text-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes lg-orb-a {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(24px, -20px) scale(1.1);
            }
        }

        @keyframes lg-orb-b {

            0%,
            100% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(-18px, 16px) scale(1.07);
            }
        }

        @keyframes lg-shimmer {
            from {
                left: -60%;
            }

            to {
                left: 130%;
            }
        }

        /* Card entrance */
        .login-card {
            animation: lg-card-in 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
        }

        /* Stagger for header h2 and sub */
        .card-header h2 {
            animation: lg-text-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.28s both;
        }

        .card-header .card-sub {
            animation: lg-text-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.4s both;
        }

        /* Floating orbs on the dark right panel */
        .panel-right {
            position: relative;
            overflow: hidden;
        }

        .panel-right::before,
        .panel-right::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(70px);
            opacity: 0.13;
        }

        .panel-right::before {
            width: 340px;
            height: 340px;
            background: #818cf8;
            top: -80px;
            right: -40px;
            animation: lg-orb-a 11s ease-in-out infinite;
        }

        .panel-right::after {
            width: 220px;
            height: 220px;
            background: #67e8f9;
            bottom: -60px;
            left: -20px;
            animation: lg-orb-b 14s ease-in-out infinite;
        }

        /* Left panel text stagger */
        .left-body p {
            animation: lg-text-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.55s both;
        }

        /* Button shimmer sweep on hover */
        .btn-login {
            position: relative;
            overflow: hidden;
        }

        .btn-login::after {
            content: '';
            position: absolute;
            top: 0;
            left: -60%;
            width: 40%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.12), transparent);
            transform: skewX(-20deg);
            pointer-events: none;
        }

        .btn-login:hover::after {
            animation: lg-shimmer 0.55s ease forwards;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">

        <!-- ── LEFT PANEL ── -->
        <aside class="panel-left">

            <!-- Logo top-left -->
            <div class="left-header">
                <img src="/sistema/assets/img/logo.png" alt="Logo Contraloría">
                <div class="org-name">
                    Contraloría Municipal
                    <span>Simón Rodríguez</span>
                </div>
            </div>

            <!-- Bottom text -->
            <div class="left-body">
                <!-- <h2>Gestión con<br><em>transparencia.</em></h2> -->
                <p>
                    Plataforma institucional de la <strong>Contraloría del Municipio Simón Rodríguez</strong> para el
                    control y
                    seguimiento de órdenes de compra, órdenes de pago y gestión de proveedores. Acceso restringido a
                    personal autorizado.
                </p>
            </div>

            <div class="left-copy">
                &copy; <?php echo date('Y'); ?> Contraloría del Municipio Simón Rodríguez
            </div>

        </aside>

        <!-- ── RIGHT PANEL ── -->
        <main class="panel-right">
            <div class="login-card">

                <div class="card-header">
                    <h2>Iniciar Sesión</h2>
                    <p class="card-sub">Acceso exclusivo para personal autorizado.</p>
                </div>

                <div class="form-divider"></div>

                <?php if ($error !== ''): ?>
                    <div class="alert-error" id="alert-error" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="../controllers/auth/login.php" id="login-form">

                    <div class="form-group">
                        <label for="usuario">Usuario</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </span>
                            <input type="text" id="usuario" name="usuario"
                                value="<?php echo htmlspecialchars($usuario); ?>" placeholder="Nombre de usuario"
                                autocomplete="username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                            </span>
                            <input type="password" id="password" name="password" placeholder="Contraseña"
                                autocomplete="current-password" required>
                            <button type="button" class="toggle-pw" id="toggle-pw" title="Mostrar contraseña"
                                aria-label="Mostrar contraseña">
                                <svg class="eye-show" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="eye-hide" style="display:none;" xmlns="http://www.w3.org/2000/svg"
                                    width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path
                                        d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" y1="2" x2="22" y2="22" />
                                </svg>
                            </button>
                        </div>
                        <div class="forgot-wrap">
                            <a href="recuperar.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="btn-submit">
                        <span class="btn-inner">
                            Iniciar Sesión
                            <span class="btn-arrow">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M5 12h14" />
                                    <path d="m12 5 7 7-7 7" />
                                </svg>
                            </span>
                        </span>
                    </button>

                </form>

                <div class="login-footer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    Acceso restringido — solo personal autorizado
                </div>

            </div>
        </main>

    </div>

    <script>
        (function () {
            var btn = document.getElementById('toggle-pw');
            var input = document.getElementById('password');
            if (!btn || !input) return;
            var eyeShow = btn.querySelector('.eye-show');
            var eyeHide = btn.querySelector('.eye-hide');
            btn.addEventListener('click', function () {
                if (input.type === 'password') {
                    input.type = 'text';
                    eyeShow.style.display = 'none';
                    eyeHide.style.display = 'block';
                    btn.setAttribute('aria-label', 'Ocultar contraseña');
                    btn.setAttribute('title', 'Ocultar contraseña');
                } else {
                    input.type = 'password';
                    eyeShow.style.display = 'block';
                    eyeHide.style.display = 'none';
                    btn.setAttribute('aria-label', 'Mostrar contraseña');
                    btn.setAttribute('title', 'Mostrar contraseña');
                }
            });
        }());

        (function () {
            var form = document.getElementById('login-form');
            var btn = document.getElementById('btn-submit');
            if (!form || !btn) return;
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.querySelector('.btn-inner').innerHTML =
                    '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:spin .8s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Verificando\u2026';
                btn.style.opacity = '0.8';
            });
        }());
    </script>
</body>

</html>