<?php
// =========================================================
// TAMBAH.PHP
// Sistem e-Intern JKR Daerah Kuala Kangsar
// =========================================================
session_start();
require_once 'db.php';

// =========================================================
// SEMAK LOGIN
// =========================================================
if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// =========================================================
// HELPER
// =========================================================
function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// =========================================================
// INITIAL SETUP
// =========================================================
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$student = null;
$is_edit = false;

// =========================================================
// LOAD EXISTING STUDENT FOR EDIT
// =========================================================
if ($id && $id > 0) {
    $stmt = $conn->prepare("
        SELECT *
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        $is_edit = true;
    }

    $stmt->close();

    if (!$student) {
        header("Location: dashboard.php");
        exit();
    }
}

// =========================================================
// DOCUMENT LABELS
// =========================================================
$document_labels = [
    'surat_iringan' => 'Surat Permohonan Latihan Industri daripada IPTA',
    'resume' => 'Resume/Biodata Pelajar',
    'borang_jawapan' => 'Borang Maklum Persetujuan Latihan Industri',
    'lampiran_butiran' => 'Surat Permohonan Pelajar',
    'objektif_skop' => 'Lampiran Skop Latihan Industri',
    'lain_lain' => 'Lain-lain Borang'
];

$document_values = json_decode(
    $student['dokumen_checklist'] ?? '{}',
    true
);

if (!is_array($document_values)) {
    $document_values = [];
}

$bahagian_list = [
    "Bahagian Jalan",
    "Bahagian Bangunan",
    "Bahagian Elektrik",
    "Bahagian Air / Bekalan Air",
    "Bahagian Mekanikal",
    "Bahagian Pentadbiran & Kewangan",
    "Bahagian Ukur Bahan"
];

$status_list = [
    "Dalam Proses Permohonan",
    "Aktif (Sedang Latihan)",
    "Tamat Latihan",
    "Ditolak / Batal"
];

// =========================================================
// PROCESS FORM
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = strtoupper(trim($_POST['nama'] ?? ''));
    $jantina = trim($_POST['jantina'] ?? '');
    $no_rujukan_fail = trim($_POST['no_rujukan_fail'] ?? '');
    $ic = trim($_POST['ic'] ?? '');
    $matric = trim($_POST['matric'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $ipt = trim($_POST['ipt'] ?? '');
    $kursus = trim($_POST['kursus'] ?? '');
    $bahagian = trim($_POST['bahagian'] ?? '');
    $tarikh_mula = $_POST['tarikh_mula'] ?? '';
    $tarikh_tamat = $_POST['tarikh_tamat'] ?? '';
    $status_tindakan = $_POST['status_tindakan'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');

    // =====================================================
    // DOCUMENT MANAGEMENT
    // =====================================================
    $document_values = [];
    $submitted_documents = $_POST['dokumen'] ?? [];

    if (!is_array($submitted_documents)) {
        $submitted_documents = [];
    }

    foreach ($document_labels as $key => $label) {
        $document_values[$key] =
            isset($submitted_documents[$key])
            && $submitted_documents[$key] === '1';
    }

    $dokumen_checklist = json_encode($document_values);

    // Keserasian database lama.
    $status_dokumen = $student['status_dokumen']
        ?? 'Menunggu Dokumen';

    // Kekalkan rujukan fail muat naik lama.
    $dokumen_fail = $student['dokumen_fail'] ?? null;

    // =====================================================
    // UPDATE EXISTING RECORD
    // =====================================================
    if ($is_edit) {
        $student_id = (int) $student['id'];

        $stmt = $conn->prepare("
            UPDATE students SET
                nama=?,
                jantina=?,
                no_rujukan_fail=?,
                ic=?,
                matric=?,
                telefon=?,
                ipt=?,
                kursus=?,
                bahagian=?,
                tarikh_mula=?,
                tarikh_tamat=?,
                status_dokumen=?,
                status_tindakan=?,
                catatan=?,
                dokumen_fail=?,
                dokumen_checklist=?
            WHERE id=?
        ");

        $stmt->bind_param(
            "ssssssssssssssssi",
            $nama,
            $jantina,
            $no_rujukan_fail,
            $ic,
            $matric,
            $telefon,
            $ipt,
            $kursus,
            $bahagian,
            $tarikh_mula,
            $tarikh_tamat,
            $status_dokumen,
            $status_tindakan,
            $catatan,
            $dokumen_fail,
            $dokumen_checklist,
            $student_id
        );

        $stmt->execute();
        $stmt->close();
    } else {
        // =================================================
        // INSERT NEW RECORD
        // =================================================
        $stmt = $conn->prepare("
            INSERT INTO students (
                nama,
                jantina,
                no_rujukan_fail,
                ic,
                matric,
                telefon,
                ipt,
                kursus,
                bahagian,
                tarikh_mula,
                tarikh_tamat,
                status_dokumen,
                status_tindakan,
                catatan,
                dokumen_fail,
                dokumen_checklist
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssssssssssss",
            $nama,
            $jantina,
            $no_rujukan_fail,
            $ic,
            $matric,
            $telefon,
            $ipt,
            $kursus,
            $bahagian,
            $tarikh_mula,
            $tarikh_tamat,
            $status_dokumen,
            $status_tindakan,
            $catatan,
            $dokumen_fail,
            $dokumen_checklist
        );

        $stmt->execute();
        $student_id = $conn->insert_id;
        $stmt->close();

        // =================================================
        // GENERATE AUTOMATIC NO. RUJUKAN
        // =================================================
        $no_rujukan = 'EIN-' . date('Y') . '-'
            . str_pad($student_id, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("
            UPDATE students
            SET no_rujukan = ?
            WHERE id = ?
        ");

        $stmt->bind_param("si", $no_rujukan, $student_id);
        $stmt->execute();
        $stmt->close();
    }

    // =====================================================
    // REDIRECT TO DASHBOARD
    // =====================================================
    header("Location: dashboard.php?success=saved");
    exit();
}

$received_count = count(
    array_filter(
        array_intersect_key($document_values, $document_labels)
    )
);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $is_edit ? 'Kemaskini Pelajar' : 'Permohonan Baru' ?>
        | e-Intern JKR
    </title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        * {
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f5f6f8;
            color: #111111;
            font-size: 14px;
            line-height: 1.5;
        }

        .form-card {
            background: #ffffff;
            border: 1px solid #dfe3e8;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 3px 14px rgba(15, 23, 42, .035);
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 13px;
            pointer-events: none;
        }

        .form-input,
        .form-select,
        .date-input,
        .textarea {
            width: 100%;
            background: #ffffff;
            border: 1px solid #d6dce4;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.5;
            min-height: 44px;
            outline: none;
            transition: all .2s ease;
        }

        .form-input,
        .form-select {
            padding: 12px 14px 12px 40px;
        }

        .date-input,
        .textarea {
            padding: 12px 14px;
        }

        .textarea {
            resize: vertical;
        }

        .form-input:focus,
        .form-select:focus,
        .date-input:focus,
        .textarea:focus {
            border-color: #F4C400;
            box-shadow: 0 0 0 3px rgba(244, 196, 0, .15);
        }

        .form-input::placeholder,
        .textarea::placeholder {
            color: #64748b;
            opacity: 1;
        }

        .form-card label {
            font-size: 13px;
            font-weight: 600;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-number {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: #111111;
            color: #F4C400;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .required {
            color: #dc2626;
        }

        .text-xs,
        .text-sm {
            font-size: 14px;
            line-height: 1.5;
        }

        [class*="text-[9px]"],
        [class*="text-[10px]"],
        [class*="text-[11px]"] {
            font-size: 12px !important;
            line-height: 1.5;
        }

        .text-gray-400 {
            color: #596273 !important;
        }

        .text-gray-500 {
            color: #4b5563 !important;
        }

        header h1 {
            font-size: 22px;
        }

        header nav a {
            font-size: 14px;
            font-weight: 600;
            min-height: 38px;
        }

        header a > div:last-child {
            max-width: 280px;
        }

        header .department-name {
            font-size: 10px !important;
            line-height: 1.4;
            font-weight: 500;
            letter-spacing: 0;
        }

        main h2 {
            font-size: clamp(28px, 3vw, 34px);
            line-height: 1.25;
            font-weight: 700;
        }

        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #715700;
            outline-offset: 3px;
        }

        #tambah-mobile-menu[hidden] {
            display: none !important;
        }

        ::-webkit-scrollbar {
            width: 7px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #999999;
        }

        @media screen and (max-width: 639px) {
            header a > div:last-child {
                max-width: 175px;
            }
        }
        
        /* Selaraskan saiz navbar */
header h1 {
    font-size: 20px;
}

header nav a {
    font-size: 12px;
    line-height: 1.5;
    font-weight: 700;
    min-height: auto;
}

header .department-name {
    font-size: 10px !important;
    line-height: 1.4;
}
    </style>
</head>

<body class="min-h-screen flex flex-col">

<!-- =====================================================
     TOP BAR
====================================================== -->
<div class="no-print bg-slate-950 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="h-9 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>

                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-300">
                    Portal Rasmi e-Intern — JKR Kuala Kangsar
                </span>
            </div>

            <div
                id="live-clock"
                class="text-[10px] font-mono font-bold text-amber-400 whitespace-nowrap"
            >
                --:--:--
            </div>
        </div>
    </div>
</div>

<!-- =====================================================
     NAVBAR
====================================================== -->
<header class="no-print bg-white border-b border-black/10 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="min-h-[74px] flex items-center justify-between gap-4 py-3">
            <a href="index.php" class="flex items-center gap-3 min-w-0">
                <div class="flex items-center gap-2 shrink-0">
                    <img
                        src="logo-perak.png"
                        alt="Logo Perak"
                        class="h-9 w-auto object-contain"
                        onerror="this.style.display='none';"
                    >

                    <div class="h-7 w-px bg-black/10"></div>

                    <img
                        src="logo-jkr.png"
                        alt="Logo JKR"
                        class="h-9 w-auto object-contain"
                        onerror="this.style.display='none';"
                    >
                </div>

                <div class="border-l border-black/10 pl-3 min-w-0">
                    <h1 class="text-xl font-extrabold leading-none text-[#111111]">
                        e-Intern
                    </h1>

                    <p class="text-[10px] font-bold text-gray-500 tracking-wide mt-1 department-name">
                        JABATAN KERJA RAYA DAERAH KUALA KANGSAR
                    </p>
                </div>
            </a>

            <nav
                aria-label="Navigasi utama"
                class="hidden lg:flex items-center gap-1 text-xs font-bold shrink-0"
            >
                <a
                    href="dashboard.php"
                    class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"
                >
                    <i class="fa-solid fa-table-list" aria-hidden="true"></i>
                    Dashboard
                </a>

                <a
                    href="alumni.php"
                    class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"
                >
                    <i class="fa-solid fa-user-graduate" aria-hidden="true"></i>
                    Alumni
                </a>

                <a
                    href="tambah.php"
                    aria-current="page"
                    class="ml-2 px-4 py-2.5 rounded-lg bg-[#111111] text-white hover:bg-[#292929] transition flex items-center gap-2"
                >
                    <i class="fa-solid fa-plus text-[#F4C400]" aria-hidden="true"></i>
                    Permohonan Baru
                </a>

                <a
                    href="logout.php"
                    class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"
                >
                    <i class="fa-solid fa-right-from-bracket text-gray-400" aria-hidden="true"></i>
                    Log Keluar
                </a>
            </nav>

            <button
                id="tambah-menu-toggle"
                type="button"
                aria-label="Buka menu navigasi"
                aria-expanded="false"
                aria-controls="tambah-mobile-menu"
                class="lg:hidden w-10 h-10 shrink-0 rounded-lg border border-black/10 bg-white flex items-center justify-center"
            >
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <nav
        id="tambah-mobile-menu"
        hidden
        aria-label="Navigasi mudah alih"
        class="lg:hidden border-t border-black/10 bg-white px-5 py-3 space-y-1 text-xs font-bold"
    >
        <a
            href="dashboard.php"
            class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition"
        >
            <i class="fa-solid fa-table-list" aria-hidden="true"></i>
            Dashboard
        </a>

        <a
            href="alumni.php"
            class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition"
        >
            <i class="fa-solid fa-user-graduate" aria-hidden="true"></i>
            Alumni
        </a>

        <a
            href="tambah.php"
            aria-current="page"
            class="flex items-center gap-2 px-4 py-3 rounded-lg bg-[#111111] text-white"
        >
            <i class="fa-solid fa-plus text-[#F4C400]" aria-hidden="true"></i>
            Permohonan Baru
        </a>

        <a
            href="logout.php"
            class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100"
        >
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            Log Keluar
        </a>
    </nav>
</header>

<!-- =====================================================
     MAIN CONTENT
====================================================== -->
<main class="max-w-5xl mx-auto w-full px-4 sm:px-6 py-8 flex-grow">

    <!-- BREADCRUMB -->
    <div class="flex items-center gap-2 text-[11px] text-gray-400 font-semibold mb-5">
        <a href="dashboard.php" class="hover:text-black transition">
            Dashboard
        </a>

        <i class="fa-solid fa-chevron-right text-[8px]" aria-hidden="true"></i>

        <span class="text-gray-700">
            <?= $is_edit ? 'Kemaskini Pelajar' : 'Permohonan Baru' ?>
        </span>
    </div>

    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5 mb-7">
        <div>
            <?php if ($is_edit && !empty($student['no_rujukan'])): ?>
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#FFF7CC] border border-[#F4C400]/40 text-[#7a5d00] text-[10px] font-extrabold">
                        <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                        <?= escape($student['no_rujukan']) ?>
                    </span>
                </div>
            <?php endif; ?>

            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                <?= $is_edit
                    ? 'Kemaskini Maklumat Pelajar'
                    : 'Pendaftaran Pelajar' ?>
            </h2>

            <p class="text-xs sm:text-sm text-gray-500 mt-2 max-w-xl">
                <?= $is_edit
                    ? 'Kemaskini maklumat pelajar dan pastikan semua data adalah tepat.'
                    : 'Daftarkan maklumat pelajar baharu untuk rekod latihan industri JKR.' ?>
            </p>
        </div>

        <a
            href="dashboard.php"
            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-gray-200 text-xs font-bold hover:bg-gray-50 transition"
        >
            <i class="fa-solid fa-arrow-left text-gray-400" aria-hidden="true"></i>
            Kembali ke Rekod
        </a>
    </div>

    <!-- =================================================
         FORM
    ================================================== -->
    <form
        action="tambah.php<?= $is_edit ? '?id=' . (int) $student['id'] : '' ?>"
        method="POST"
        class="space-y-5"
    >
        <input
            type="hidden"
            name="id"
            value="<?= escape($student['id'] ?? '') ?>"
        >

        <!-- =================================================
             SECTION 01 — MAKLUMAT PELAJAR
        ================================================== -->
        <div class="form-card">
            <div class="px-5 sm:px-7 py-5 border-b border-gray-100">
                <div class="section-title">
                    <div class="section-number">01</div>

                    <div>
                        <h3 class="text-sm font-extrabold">
                            Maklumat Pelajar
                        </h3>

                        <p class="text-[10px] text-gray-500 mt-0.5">
                            Maklumat peribadi dan pengajian
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-7 space-y-5">

                <!-- NAMA -->
                <div>
                    <label
                        for="nama"
                        class="block text-xs font-bold text-gray-700 mb-2"
                    >
                        Nama Penuh Pelajar
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-user input-icon" aria-hidden="true"></i>

                        <input
                            type="text"
                            id="nama"
                            name="nama"
                            required
                            value="<?= escape(strtoupper($student['nama'] ?? '')) ?>"
                            class="form-input"
                            placeholder="Contoh: AHMAD BIN ABDULLAH"
                            oninput="
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(start, end);
    "
>
                    </div>
                </div>

                <!-- JANTINA + NO. RUJUKAN FAIL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <!-- JANTINA — PILIHAN -->
                    <div>
                        <label
                            for="jantina"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            Jantina
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-venus-mars input-icon" aria-hidden="true"></i>

                            <select
                                id="jantina"
                                name="jantina"
                                class="form-select"
                            >
                                <option value="">-- Pilih Jantina --</option>

                                <option
                                    value="Lelaki"
                                    <?= ($student['jantina'] ?? '') === 'Lelaki'
                                        ? 'selected' : '' ?>
                                >
                                    Lelaki
                                </option>

                                <option
                                    value="Perempuan"
                                    <?= ($student['jantina'] ?? '') === 'Perempuan'
                                        ? 'selected' : '' ?>
                                >
                                    Perempuan
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- NO. RUJUKAN FAIL — PILIHAN -->
                    <div>
                        <label
                            for="no_rujukan_fail"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            No. Rujukan Fail
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-folder-open input-icon" aria-hidden="true"></i>

                            <input
                                type="text"
                                id="no_rujukan_fail"
                                name="no_rujukan_fail"
                                maxlength="100"
                                value="<?= escape($student['no_rujukan_fail'] ?? '') ?>"
                                class="form-input"
                                placeholder="Masukkan nombor rujukan fail jika ada"
                            >
                        </div>
                    </div>
                </div>

                <!-- IC + MATRIC -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label
                            for="ic"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            No. Kad Pengenalan
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-id-card input-icon" aria-hidden="true"></i>

                            <input
                                type="text"
                                id="ic"
                                name="ic"
                                required
                                value="<?= escape($student['ic'] ?? '') ?>"
                                class="form-input"
                                placeholder="01020308XXXX"
                            >
                        </div>
                    </div>

                    <div>
                        <label
                            for="matric"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            No. Matrik
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-graduation-cap input-icon" aria-hidden="true"></i>

                            <input
                                type="text"
                                id="matric"
                                name="matric"
                                required
                                value="<?= escape($student['matric'] ?? '') ?>"
                                class="form-input"
                                placeholder="2023XXXXXX"
                            >
                        </div>
                    </div>
                </div>

                <!-- TELEFON -->
                <div>
                    <label
                        for="telefon"
                        class="block text-xs font-bold text-gray-700 mb-2"
                    >
                        No. Telefon
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-phone input-icon" aria-hidden="true"></i>

                        <input
                            type="tel"
                            id="telefon"
                            name="telefon"
                            required
                            value="<?= escape($student['telefon'] ?? '') ?>"
                            class="form-input"
                            placeholder="011-12345678"
                        >
                    </div>
                </div>

                <!-- IPT + KURSUS -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label
                            for="ipt"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            Institusi Pengajian Tinggi (IPT)
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns input-icon" aria-hidden="true"></i>

                            <input
                                type="text"
                                id="ipt"
                                name="ipt"
                                required
                                value="<?= escape($student['ipt'] ?? '') ?>"
                                class="form-input"
                                placeholder="Contoh: UiTM Seri Iskandar"
                            >
                        </div>
                    </div>

                    <div>
                        <label
                            for="kursus"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            Kursus / Bidang Pengajian
                            <span class="required">*</span>
                        </label>

                        <div class="input-wrapper">
                            <i class="fa-solid fa-book-open input-icon" aria-hidden="true"></i>

                            <input
                                type="text"
                                id="kursus"
                                name="kursus"
                                required
                                value="<?= escape($student['kursus'] ?? '') ?>"
                                class="form-input"
                                placeholder="Contoh: Diploma Kejuruteraan Awam"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================
             SECTION 02 — PENEMPATAN & TEMPOH
        ================================================== -->
        <div class="form-card">
            <div class="px-5 sm:px-7 py-5 border-b border-gray-100">
                <div class="section-title">
                    <div class="section-number">02</div>

                    <div>
                        <h3 class="text-sm font-extrabold">
                            Penempatan & Tempoh
                        </h3>

                        <p class="text-[10px] text-gray-500 mt-0.5">
                            Maklumat penempatan latihan industri
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-7 space-y-5">

                <!-- BAHAGIAN JKR — PILIHAN -->
                <div>
                    <label
                        for="bahagian"
                        class="block text-xs font-bold text-gray-700 mb-2"
                    >
                        Bahagian JKR
                    </label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-sitemap input-icon" aria-hidden="true"></i>

                        <select
                            id="bahagian"
                            name="bahagian"
                            class="form-select"
                        >
                            <option value="">
                                -- Pilih Bahagian JKR --
                            </option>

                            <?php foreach ($bahagian_list as $b): ?>
                                <option
                                    value="<?= escape($b) ?>"
                                    <?= ($student['bahagian'] ?? '') === $b
                                        ? 'selected' : '' ?>
                                >
                                    <?= escape($b) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- DATES -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label
                            for="tarikh_mula"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            Tarikh Mula Latihan
                            <span class="required">*</span>
                        </label>

                        <input
                            type="date"
                            id="tarikh_mula"
                            name="tarikh_mula"
                            required
                            value="<?= escape($student['tarikh_mula'] ?? '') ?>"
                            class="date-input"
                        >
                    </div>

                    <div>
                        <label
                            for="tarikh_tamat"
                            class="block text-xs font-bold text-gray-700 mb-2"
                        >
                            Tarikh Tamat Latihan
                            <span class="required">*</span>
                        </label>

                        <input
                            type="date"
                            id="tarikh_tamat"
                            name="tarikh_tamat"
                            required
                            value="<?= escape($student['tarikh_tamat'] ?? '') ?>"
                            class="date-input"
                        >
                    </div>
                </div>

                <div class="flex items-start gap-3 bg-[#FFFBEA] border border-[#F4C400]/30 rounded-xl p-4">
                    <div class="w-8 h-8 rounded-lg bg-[#F4C400] flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-calendar text-black text-xs" aria-hidden="true"></i>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-gray-800">
                            Tempoh latihan
                        </p>

                        <p class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                            Pastikan tarikh mula dan tamat latihan adalah berdasarkan surat rasmi daripada institusi pengajian.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================
             SECTION 03 — DOKUMEN & STATUS
        ================================================== -->
        <div class="form-card">
            <div class="px-5 sm:px-7 py-5 border-b border-gray-100">
                <div class="section-title">
                    <div class="section-number">03</div>

                    <div>
                        <h3 class="text-sm font-extrabold">
                            Dokumen & Status
                        </h3>

                        <p class="text-[10px] text-gray-500 mt-0.5">
                            Dokumen sokongan dan status tindakan
                        </p>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-7 space-y-5">

                <!-- SENARAI SEMAK DOKUMEN -->
                <fieldset class="border border-gray-200 rounded-xl p-4 sm:p-5">
                    <legend class="px-2 text-sm font-bold text-gray-800">
                        Senarai Semak Dokumen
                    </legend>

                    <p class="text-sm text-gray-500 mb-4">
                        Tandakan dokumen yang telah diterima.
                    </p>

                    <div class="space-y-3">
                        <?php foreach ($document_labels as $key => $label): ?>
                            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-yellow-50">
                                <input
                                    type="checkbox"
                                    name="dokumen[<?= escape($key) ?>]"
                                    value="1"
                                    data-document-checkbox
                                    class="mt-0.5 w-5 h-5 shrink-0 accent-[#b28f00]"
                                    <?= !empty($document_values[$key])
                                        ? 'checked' : '' ?>
                                >

                                <span class="text-sm leading-6 text-gray-800">
                                    <?= escape($label) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <p
                        id="document-summary"
                        aria-live="polite"
                        class="text-sm font-bold mt-4"
                    >
                        <?= $received_count ?>/<?= count($document_labels) ?> diterima
                    </p>

                    <p class="text-xs text-gray-500 mt-2">
                        Tandakan mengikut dokumen yang diterima. Checklist disimpan apabila butang simpan ditekan.
                    </p>
                </fieldset>

                <!-- STATUS -->
                <div>
                    <label
                        for="status_tindakan"
                        class="block text-xs font-bold text-gray-700 mb-2"
                    >
                        Status Tindakan
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrapper">
                        <i class="fa-solid fa-list-check input-icon" aria-hidden="true"></i>

                        <select
                            id="status_tindakan"
                            name="status_tindakan"
                            required
                            class="form-select"
                        >
                            <?php foreach ($status_list as $status): ?>
                                <option
                                    value="<?= escape($status) ?>"
                                    <?= ($student['status_tindakan'] ?? 'Dalam Proses Permohonan') === $status
                                        ? 'selected' : '' ?>
                                >
                                    <?= escape($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- CATATAN -->
                <div>
                    <label
                        for="catatan"
                        class="block text-xs font-bold text-gray-700 mb-2"
                    >
                        Catatan / Ulasan Tambahan
                    </label>

                    <textarea
                        id="catatan"
                        name="catatan"
                        rows="4"
                        class="textarea"
                        placeholder="Masukkan sebarang catatan atau ulasan berkaitan pelajar..."
                    ><?= escape($student['catatan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- =================================================
             ACTION BAR
        ================================================== -->
        <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row gap-4 items-center justify-between">
            <div class="flex items-center gap-2 text-[10px] text-gray-400">
                <i class="fa-solid fa-shield-halved text-[#F4C400]" aria-hidden="true"></i>

                <span>
                    Pastikan semua maklumat telah disemak sebelum disimpan.
                </span>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <a
                    href="dashboard.php"
                    class="w-full sm:w-auto px-5 py-3 rounded-xl border border-gray-200 bg-gray-50 hover:bg-gray-100 text-xs font-bold text-gray-600 text-center transition"
                >
                    Batal
                </a>

                <button
                    type="submit"
                    class="w-full sm:w-auto px-6 py-3 rounded-xl bg-[#F4C400] hover:bg-yellow-300 text-black text-xs font-extrabold border border-black transition flex items-center justify-center gap-2"
                >
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>

                    <?= $is_edit
                        ? 'Simpan Kemaskini'
                        : 'Daftar Pelajar' ?>
                </button>
            </div>
        </div>
    </form>
</main>

<!-- =====================================================
     FOOTER
====================================================== -->
<footer class="bg-[#111111] text-white mt-8">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10 py-6">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-[#F4C400] text-black flex items-center justify-center">
                    <i class="fa-solid fa-road text-xs" aria-hidden="true"></i>
                </div>

                <div>
                    <p class="text-xs font-bold">e-Intern</p>

                    <p class="text-[10px] text-gray-400">
                        Jabatan Kerja Raya Daerah Kuala Kangsar
                    </p>
                </div>
            </div>

            <p class="text-[10px] text-gray-500 text-center">
                Sistem Pengurusan Pelajar Latihan Industri
                © <?= date('Y') ?>
            </p>
        </div>
    </div>
</footer>

<!-- =====================================================
     JAVASCRIPT
====================================================== -->
<script>
// LIVE CLOCK
function updateClock() {
    const now = new Date();
    let hours = now.getHours();

    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12 || 12;

    const clockEl = document.getElementById('live-clock');

    if (clockEl) {
        clockEl.textContent =
            `${String(hours).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
    }
}

updateClock();
setInterval(updateClock, 1000);

// MOBILE MENU
(function () {
    const button = document.getElementById('tambah-menu-toggle');
    const menu = document.getElementById('tambah-mobile-menu');

    if (!button || !menu) {
        return;
    }

    function setOpen(open) {
        menu.hidden = !open;

        button.setAttribute('aria-expanded', String(open));

        button.setAttribute(
            'aria-label',
            open ? 'Tutup menu navigasi' : 'Buka menu navigasi'
        );

        button.querySelector('i').className = open
            ? 'fa-solid fa-xmark'
            : 'fa-solid fa-bars';
    }

    button.addEventListener('click', function () {
        setOpen(menu.hidden);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            setOpen(false);
        }
    });
})();

// DOCUMENT CHECKLIST
(function () {
    const checkboxes = Array.from(
        document.querySelectorAll('[data-document-checkbox]')
    );

    const summary = document.getElementById('document-summary');

    if (!summary) {
        return;
    }

    function refreshDocuments() {
        const received = checkboxes.filter(
            checkbox => checkbox.checked
        ).length;

        const total = checkboxes.length;

        summary.textContent = `${received}/${total} diterima`;

        summary.classList.toggle(
            'text-emerald-700',
            received === total
        );

        summary.classList.toggle(
            'text-amber-700',
            received !== total
        );
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshDocuments);
    });

    refreshDocuments();
})();
</script>
</body>
</html>