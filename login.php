<?php
// login.php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
session_start();

// Jika pengguna sudah log masuk, terus ke Dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/db.php';

$error = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("
            SELECT id, username, password, nama_penuh, peranan
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (
                password_verify($password, $user['password']) ||
                $password === $user['password']
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_penuh'] = $user['nama_penuh'];
                $_SESSION['peranan'] = $user['peranan'];

                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Kata laluan yang dimasukkan tidak tepat.';
            }
        } else {
            $error = 'Nama pengguna atau kata laluan tidak sah.';
        }

        $stmt->close();
    } else {
        $error = 'Sila lengkapkan nama pengguna dan kata laluan.';
    }
}
?>

<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Log Masuk | e-Intern JKR Kuala Kangsar</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            color: #19232c;
            background: #f7f8f6;
        }

        a {
            color: inherit;
        }

        button,
        input {
            font: inherit;
        }

        .login-shell {
            height: 100vh;
            height: 100dvh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: minmax(0, 1fr);
        }

        /* PANEL KIRI */
        .visual-panel {
            position: relative;
            overflow: hidden;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 24px clamp(30px, 5vw, 76px);
            background: #f0f0e9;
            border-right: 1px solid #e2e5de;
        }

        .visual-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(
                    ellipse at 25% 25%,
                    #fff9d5 0,
                    transparent 58%
                ),
                linear-gradient(140deg, #ffffff70, transparent);
            pointer-events: none;
        }

        .panel-top,
        .panel-copy,
        .panel-footer {
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .panel-top {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 11px;
            letter-spacing: 1.3px;
            font-weight: 700;
            color: #6b706c;
            text-transform: uppercase;
        }

        .panel-top::before {
            content: '';
            width: 24px;
            height: 3px;
            background: #e3b600;
            flex-shrink: 0;
        }

        .panel-copy {
            margin-top: clamp(24px, 6vh, 60px);
            max-width: 440px;
        }

        /* TAJUK UTAMA */
        .panel-copy h1 {
            font-size: clamp(28px, 3.1vw, 42px);
            line-height: 1.25;
            letter-spacing: -0.8px;
            margin: 0 0 16px;
            font-weight: 700;
            color: #19232c;
        }

        /* SLOGAN */
        .panel-tagline {
            font-size: 16px;
            line-height: 1.6;
            font-weight: 600;
            color: #927200;
            margin-bottom: 14px;
        }

        .panel-copy p {
            font-size: 14px;
            line-height: 1.8;
            color: #65706c;
            max-width: 340px;
            margin: 0;
        }

        .abstract-art {
            position: relative;
            isolation: isolate;
            flex: 1;
            min-height: 0;
            margin: 16px -25px 12px;
            overflow: hidden;
            background:
                radial-gradient(
                    circle,
                    #bbc3b733 1px,
                    transparent 1px
                ) 0 0 / 18px 18px;
        }

        .abstract-art span {
            position: absolute;
            display: block;
        }

        .geometry-outline {
            height: 220px;
            width: 220px;
            border: 1px solid #b7bfb4;
            border-radius: 50%;
            left: 5%;
            top: 15%;
            opacity: 0.6;
        }

        .geometry-yellow {
            width: 235px;
            height: 155px;
            left: 22%;
            top: 25%;
            border-radius: 90px;
            background: linear-gradient(135deg, #f8de6b, #ecc124);
            transform: rotate(-34deg);
            box-shadow: 0 15px 35px #9b800013;
        }

        .geometry-dark {
            width: 190px;
            height: 105px;
            left: 48%;
            top: 54%;
            border-radius: 70px;
            background: linear-gradient(135deg, #475349, #25352c);
            transform: rotate(-34deg);
        }

        .geometry-line {
            width: 190px;
            height: 105px;
            left: 40%;
            top: 40%;
            border: 1px solid #fbfaf0;
            border-radius: 70px;
            transform: rotate(-34deg);
            z-index: 2;
        }

        .panel-footer {
            padding-top: 14px;
            font-size: 11px;
            color: #767e77;
            line-height: 1.6;
            border-top: 1px solid #d9ddd4;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .panel-footer::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #c49b00;
            flex-shrink: 0;
        }

        /* PANEL BORANG */
        .form-panel {
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding: 24px clamp(28px, 5vw, 72px);
            background: #fff;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .logos {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .logos img {
            height: 40px;
            width: auto;
            max-width: 65px;
            object-fit: contain;
        }

        .logo-divider {
            height: 30px;
            width: 1px;
            background: #e2e5e5;
        }

        .brand-copy {
            border-left: 1px solid #e2e5e5;
            padding-left: 14px;
        }

        .brand-copy strong {
            font-size: 20px;
            letter-spacing: -0.6px;
            display: block;
            line-height: 1.2;
        }

        .brand-copy small {
            font-size: 10px;
            letter-spacing: 0.3px;
            line-height: 1.6;
            color: #6c757d;
            display: block;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .form-content {
            width: 100%;
            max-width: 370px;
            margin: auto;
            padding: 16px 0;
            flex-shrink: 0;
        }

        .form-kicker {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 11px;
            color: #886b00;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .form-kicker::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #dfb300;
        }

        .form-content h2 {
            font-size: 26px;
            letter-spacing: -0.7px;
            line-height: 1.25;
            margin: 0 0 10px;
        }

        .intro {
            color: #77808a;
            font-size: 13px;
            line-height: 1.7;
            margin: 0 0 20px;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            margin-bottom: 8px;
            color: #35414c;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap input {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1px solid #dce0e3;
            background: #fafbfb;
            border-radius: 8px;
            color: #19232c;
            font-size: 14px;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .input-wrap input::placeholder {
            color: #9aa2a9;
            font-size: 13px;
        }

        .input-wrap input:focus {
            outline: none;
            border-color: #be9900;
            background: #fff;
            box-shadow: 0 0 0 3px #f4c40022;
        }

        #password {
            padding-right: 52px;
        }

        .password-toggle {
            position: absolute;
            right: 5px;
            top: 5px;
            height: 36px;
            width: 38px;
            display: grid;
            place-items: center;
            background: transparent;
            border: 0;
            border-radius: 6px;
            color: #727b83;
            cursor: pointer;
        }

        .password-toggle:hover {
            background: #ecefee;
        }

        .password-toggle svg {
            width: 19px;
            height: 19px;
        }

        .password-toggle:focus-visible,
        .submit:focus-visible {
            outline: 2px solid #a48100;
            outline-offset: 3px;
        }

        .submit {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            height: 46px;
            border: 1px solid #dfb600;
            background: #f4c400;
            color: #202116;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            padding: 0 16px;
            margin-top: 20px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .submit:hover {
            background: #e9ba00;
        }

        .submit svg {
            width: 18px;
            height: 18px;
        }

        .security-note {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            font-size: 11px;
            color: #869098;
            line-height: 1.7;
            margin: 14px 0 0;
        }

        .security-note svg {
            width: 14px;
            height: 14px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .notice {
            padding: 12px 14px;
            border: 1px solid #edc9c4;
            border-radius: 8px;
            background: #fff4f2;
            color: #a34034;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 22px;
        }

        .notice.success {
            background: #f0f8f2;
            border-color: #cae3d0;
            color: #2c6841;
        }

        .form-footer {
            font-size: 10px;
            line-height: 1.7;
            color: #8a9299;
            text-align: center;
            padding-top: 12px;
            border-top: 1px solid #eef0f1;
            flex-shrink: 0;
        }

        /* RESPONSIVE */
        @media (min-width: 1400px) {
            .login-shell {
                max-width: 1600px;
                margin: auto;
            }

            .visual-panel {
                border-left: 1px solid #e2e5de;
            }

            .form-panel {
                border-right: 1px solid #e2e5de;
            }
        }

        @media (max-width: 900px) {
            .visual-panel,
            .form-panel {
                padding-left: 30px;
                padding-right: 30px;
            }

            .brand {
                gap: 10px;
            }

            .brand-copy {
                padding-left: 10px;
            }

            .logos img {
                height: 33px;
            }

            .brand-copy small {
                font-size: 9px;
            }

            .panel-copy h1 {
                font-size: 32px;
            }

            .panel-tagline {
                font-size: 14px;
            }
        }

        @media (max-height: 650px) {
            .visual-panel,
            .form-panel {
                padding-top: 18px;
                padding-bottom: 18px;
            }

            .panel-copy {
                margin-top: 24px;
            }

            .panel-copy h1 {
                font-size: 30px;
                margin-bottom: 12px;
            }

            .panel-tagline {
                font-size: 14px;
                margin-bottom: 10px;
            }

            .panel-copy p {
                font-size: 13px;
            }

            .panel-footer {
                padding-top: 10px;
            }

            .form-content {
                padding: 12px 0;
            }

            .form-content h2 {
                font-size: 23px;
                margin-bottom: 6px;
            }

            .form-kicker {
                margin-bottom: 8px;
            }

            .intro {
                margin-bottom: 14px;
            }

            .field {
                margin-bottom: 12px;
            }

            .input-wrap input {
                height: 42px;
            }

            .password-toggle {
                height: 32px;
            }

            .submit {
                height: 42px;
                margin-top: 16px;
            }

            .security-note {
                margin-top: 10px;
            }

            .notice {
                margin-bottom: 12px;
                padding: 8px 12px;
            }

            .logos img {
                height: 32px;
            }

            .brand-copy strong {
                font-size: 18px;
            }
        }

        @media (max-width: 700px) {
            .login-shell {
                grid-template-columns: 1fr;
            }

            .visual-panel {
                display: none;
            }

            .form-panel {
                min-height: 0;
                padding: 20px 24px;
            }

            .brand {
                justify-content: center;
            }

            .form-content {
                padding: 16px 0;
                max-width: 390px;
            }

            .form-content h2 {
                font-size: 25px;
            }
        }

        /* Benarkan scroll untuk skrin yang terlalu pendek */
        @media (max-height: 480px) {
            .login-shell {
                height: auto;
                min-height: 100vh;
                min-height: 100dvh;
                grid-template-columns: 1fr;
            }

            .visual-panel {
                display: none;
            }

            .form-panel {
                min-height: 100vh;
                min-height: 100dvh;
                padding: 14px 24px;
            }

            .brand {
                justify-content: center;
            }

            .form-content {
                padding: 20px 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="login-shell">

        <!-- PANEL KIRI -->
        <aside class="visual-panel" aria-label="Pengenalan e-Intern">
            <div class="panel-top">
                JKR Daerah Kuala Kangsar
            </div>

            <div class="panel-copy">
                <h1>Sistem Pengurusan Pelajar Latihan Industri</h1>

                <div class="panel-tagline">
                    Pengurusan tersusun. Urusan lebih mudah.
                </div>

                <p>
                    Urus rekod pelajar, maklumat latihan industri
                    dan sijil penghargaan dalam satu sistem.
                </p>
            </div>

            <div class="abstract-art" aria-hidden="true">
                <span class="geometry-outline"></span>
                <span class="geometry-yellow"></span>
                <span class="geometry-dark"></span>
                <span class="geometry-line"></span>
            </div>

            <div class="panel-footer">
                Jabatan Kerja Raya Daerah Kuala Kangsar, Perak
            </div>
        </aside>

        <!-- PANEL LOG MASUK -->
        <main class="form-panel">
            <div class="brand">
                <div class="logos">
                    <img
                        src="logo-perak.png"
                        alt="Logo Perak"
                        onerror="this.style.display='none'"
                    >

                    <span class="logo-divider" aria-hidden="true"></span>

                    <img
                        src="logo-jkr.png"
                        alt="Logo JKR"
                        onerror="this.style.display='none'"
                    >
                </div>

                <div class="brand-copy">
                    <strong>e-Intern</strong>
                    <small>
                        Jabatan Kerja Raya<br>
                        Daerah Kuala Kangsar
                    </small>
                </div>
            </div>

            <div class="form-content">
                <div class="form-kicker">Portal kakitangan</div>

                <h2>Selamat datang kembali</h2>

                <p class="intro">
                    Log masuk untuk mengakses sistem e-Intern.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="notice" role="alert">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_msg !== ''): ?>
                    <div class="notice success" role="status">
                        <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <div class="field">
                        <label for="username">Nama pengguna</label>

                        <div class="input-wrap">
                            <input
                                type="text"
                                id="username"
                                name="username"
                                autocomplete="username"
                                placeholder="Masukkan nama pengguna"
                                value="<?= htmlspecialchars(
                                    is_string($_POST['username'] ?? null)
                                        ? $_POST['username']
                                        : '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Kata laluan</label>

                        <div class="input-wrap">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Masukkan kata laluan"
                                required
                            >

                            <button
                                class="password-toggle"
                                type="button"
                                id="password-toggle"
                                aria-label="Tunjukkan kata laluan"
                                aria-pressed="false"
                                aria-controls="password"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    aria-hidden="true"
                                >
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                    <path
                                        id="eye-slash"
                                        d="m3 3 18 18"
                                        style="display:none"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button class="submit" type="submit">
                        <span>Log Masuk</span>

                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            aria-hidden="true"
                        >
                            <path d="M5 12h14m-5-5 5 5-5 5"/>
                        </svg>
                    </button>
                </form>

                <p class="security-note">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                        aria-hidden="true"
                    >
                        <path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4Z"/>
                        <path d="m8 12 3 3 5-6"/>
                    </svg>

                    <span>
                        Akses untuk pengguna berdaftar.
                        Sila jaga kerahsiaan maklumat akaun anda.
                    </span>
                </p>
            </div>

            <footer class="form-footer">
                &copy; <?= date('Y') ?>
                JKR Daerah Kuala Kangsar · e-Intern
            </footer>
        </main>
    </div>

    <script>
        const toggle = document.getElementById('password-toggle');
        const password = document.getElementById('password');
        const eyeSlash = document.getElementById('eye-slash');

        toggle.addEventListener('click', function () {
            const show = password.type === 'password';

            password.type = show ? 'text' : 'password';

            toggle.setAttribute('aria-pressed', String(show));
            toggle.setAttribute(
                'aria-label',
                show ? 'Sembunyikan kata laluan' : 'Tunjukkan kata laluan'
            );

            eyeSlash.style.display = show ? '' : 'none';
        });
    </script>
</body>
</html>