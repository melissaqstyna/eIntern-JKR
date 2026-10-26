<?php

// alumni.php

session_start();

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'db.php';

// Status dikira daripada enam tanda sebenar, bukan fail upload lama.
function eintern_document_checklist($row) {
    $labels = [
        'surat_iringan' => 'Surat iringan mohon LI daripada penyelaras',
        'resume' => 'Resume',
        'borang_jawapan' => 'Borang jawapan industri',
        'lampiran_butiran' => 'Lampiran butiran permohonan Pelajar',
        'objektif_skop' => 'Objektif skop pekerjaan',
        'lain_lain' => 'Lain-lain Borang',
    ];
    $stored = json_decode($row['dokumen_checklist'] ?? '{}', true);
    if (!is_array($stored)) { $stored = []; }
    $received = [];
    foreach ($labels as $key => $label) {
        $received[$key] = in_array($stored[$key] ?? false, [true, 1, '1'], true);
    }
    $count = count(array_filter($received));
    return ['labels' => $labels, 'received' => $received, 'count' => $count];
}



/* =========================================================

   EXPORT CSV

========================================================= */

if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');

    header(

        'Content-Disposition: attachment; filename=Alumni_Pelajar_Tamat_eIntern_' .

        date('Ymd_His') . '.csv'

    );

    $output = fopen('php://output', 'w');

    fputcsv($output, [

        'ID',

        'Nama Pelajar',

        'No. IC',

        'No. Matrik',

        'No. Telefon',

        'IPT',

        'Kursus',

        'Bahagian JKR',

        'Tarikh Mula',

        'Tarikh Tamat',

        'Dokumen Diterima',
        'Surat iringan mohon LI daripada penyelaras',
        'Resume',
        'Borang jawapan industri',
        'Lampiran butiran permohonan Pelajar',
        'Objektif skop pekerjaan',
        'Lain-lain Borang',

        'Catatan'

    ]);

    $export_query = "

        SELECT *

        FROM students

        WHERE status_tindakan = 'Tamat Latihan'

        ORDER BY tarikh_tamat DESC

    ";

    $export_res = $conn->query($export_query);

    while ($row = $export_res->fetch_assoc()) {

        fputcsv($output, [

            $row['id'],

            $row['nama'],

            $row['ic'],

            $row['matric'],

            $row['telefon'],

            $row['ipt'] ?? '',

            $row['kursus'] ?? '',

            $row['bahagian'],

            $row['tarikh_mula'],

            $row['tarikh_tamat'],

            (eintern_document_checklist($row)['count'] . '/6'),
            (eintern_document_checklist($row)['received']['surat_iringan'] ? 'Diterima' : 'Belum diterima'),
            (eintern_document_checklist($row)['received']['resume'] ? 'Diterima' : 'Belum diterima'),
            (eintern_document_checklist($row)['received']['borang_jawapan'] ? 'Diterima' : 'Belum diterima'),
            (eintern_document_checklist($row)['received']['lampiran_butiran'] ? 'Diterima' : 'Belum diterima'),
            (eintern_document_checklist($row)['received']['objektif_skop'] ? 'Diterima' : 'Belum diterima'),
            (eintern_document_checklist($row)['received']['lain_lain'] ? 'Diterima' : 'Belum diterima'),

            $row['catatan']

        ]);

    }

    fclose($output);

    exit();

}

/* =========================================================

   SEARCH & FILTER

========================================================= */

$search = $_GET['search'] ?? '';

$filter_bahagian = $_GET['bahagian'] ?? '';

$query = "

    SELECT *

    FROM students

    WHERE status_tindakan = 'Tamat Latihan'

";

$params = [];

$types = "";

if (!empty($search)) {

    $query .= "

        AND (

            nama LIKE ?

            OR ic LIKE ?

            OR matric LIKE ?

            OR ipt LIKE ?

            OR kursus LIKE ?

        )

    ";

    $search_term = "%$search%";

    $params[] = $search_term;

    $params[] = $search_term;

    $params[] = $search_term;

    $params[] = $search_term;

    $params[] = $search_term;

    $types .= "sssss";

}

if (!empty($filter_bahagian)) {

    $query .= " AND bahagian = ?";

    $params[] = $filter_bahagian;

    $types .= "s";

}

$query .= " ORDER BY tarikh_tamat DESC";

$stmt = $conn->prepare($query);

if (!empty($params)) {

    $stmt->bind_param($types, ...$params);

}

$stmt->execute();

$result = $stmt->get_result();

// Load issued certificates once so the print action only appears for saved copies.

$issuedCertificateIds = [];

try {

    $certificateResult = $conn->query('SELECT student_id FROM internship_certificates');

    if ($certificateResult) {

        while ($certificateRow = $certificateResult->fetch_assoc()) {

            $issuedCertificateIds[(int) $certificateRow['student_id']] = true;

        }

    }

} catch (Throwable $e) {

    // The settings page provides setup guidance if the module is not installed yet.

}

/* =========================================================

   STATISTICS

========================================================= */

$total_tamat = $conn->query("

    SELECT COUNT(*) AS total

    FROM students

    WHERE status_tindakan = 'Tamat Latihan'

")->fetch_assoc()['total'] ?? 0;

$total_dokumen = 0;
$document_stats = $conn->query("SELECT dokumen_checklist FROM students WHERE status_tindakan = 'Tamat Latihan'");
if ($document_stats) {
    while ($document_row = $document_stats->fetch_assoc()) {
        if (eintern_document_checklist($document_row)['count'] > 0) { $total_dokumen++; }
    }
}

$total_bahagian = $conn->query("

    SELECT COUNT(DISTINCT bahagian) AS total

    FROM students

    WHERE status_tindakan = 'Tamat Latihan'

    AND bahagian IS NOT NULL

    AND bahagian != ''

")->fetch_assoc()['total'] ?? 0;

/* =========================================================

   HELPER

========================================================= */

function getInitials($name)

{

    $words = preg_split('/\s+/', trim($name));

    if (count($words) >= 2) {

        return strtoupper(

            substr($words[0], 0, 1) .

            substr($words[1], 0, 1)

        );

    }

    return strtoupper(substr($name, 0, 2));

}

?>

<!DOCTYPE html>

<html lang="ms">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"

          content="width=device-width, initial-scale=1.0">

    <title>Alumni Intern | e-Intern JKR Kuala Kangsar</title>

    <!-- Tailwind -->

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->

    <link

        rel="stylesheet"

        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"

    >

    <!-- Inter -->

    <link

        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"

        rel="stylesheet"

    >

    <style>

        * {

            box-sizing: border-box;

        }

        body {

            font-family: 'Segoe UI', Arial, sans-serif;

        }

        /* Smooth scrollbar */

        ::-webkit-scrollbar {

            width: 7px;

            height: 7px;

        }

        ::-webkit-scrollbar-track {

            background: #f1f5f9;

        }

        ::-webkit-scrollbar-thumb {

            background: #cbd5e1;

            border-radius: 10px;

        }

        ::-webkit-scrollbar-thumb:hover {

            background: #94a3b8;

        }

        /* Table */

        .alumni-table tbody tr {

            transition: all 0.2s ease;

        }

        .alumni-table tbody tr:hover {

            background: #fffdf5;

        }

        /* Avatar */

        .avatar {

            background: #fef3c7;

            border: 1px solid #fcd34d;

        }

        /* Print */

        @media print {

            @page {

                size: A4 landscape;

                margin: 10mm;

            }

            .no-print {

                display: none !important;

            }

            body {

                background: #ffffff !important;

                color: #000000 !important;

                font-size: 10px !important;

            }

            main {

                max-width: 100% !important;

                padding: 0 !important;

                margin: 0 !important;

            }

            .print-header {

                display: block !important;

            }

            .table-container {

                border: none !important;

                box-shadow: none !important;

                border-radius: 0 !important;

            }

            table {

                width: 100% !important;

                border-collapse: collapse !important;

                margin-top: 12px !important;

            }

            thead tr {

                background-color: #111827 !important;

                color: #ffffff !important;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }

            th {

                border: 1px solid #111827 !important;

                padding: 6px !important;

                font-size: 9px !important;

            }

            td {

                border: 1px solid #cbd5e1 !important;

                padding: 6px !important;

                font-size: 9px !important;

                vertical-align: top !important;

            }

            tbody tr:nth-child(even) {

                background: #f8fafc !important;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }

            .print-footer {

                display: flex !important;

                justify-content: space-between;

                align-items: flex-end;

                margin-top: 25px;

            }

        }

    
        /* Match the latest dashboard screen typography and spacing. */
        @media screen {
            body { font-size: 14px; line-height: 1.5; background: #f5f6f8; color: #263244; }
            main.max-w-7xl { max-width: 1600px; padding: 28px 20px; }
            .text-xs, .text-sm { font-size: 14px; line-height: 1.5; }
            [class*="text-[8px]"], [class*="text-[9px]"], [class*="text-[10px]"], [class*="text-[11px]"] { font-size: 12px !important; line-height: 1.5; }
            .text-slate-400, .text-gray-400 { color: #596273 !important; }
            .text-slate-500, .text-gray-500 { color: #4b5563 !important; }
            header nav { font-size: 14px; }
            header h1 { font-size: 22px; }
            header a > div:last-child { max-width: 280px; }
            main h2 { font-size: clamp(28px, 3vw, 34px); line-height: 1.25; font-weight: 700; }
            main h3 { font-size: 18px; font-weight: 700; }
            main label { font-size: 12px !important; font-weight: 600; text-transform: none; letter-spacing: 0; }
            main input, main select { font-size: 14px !important; min-height: 40px; border-color: #d6dce4; }
            button, header nav a { min-height: 38px; }
            .table-container { border-radius: 14px; border: 1px solid #dfe3e8; box-shadow: 0 3px 14px rgba(15,23,42,.035); }
            .alumni-table { width: 100%; min-width: 1200px; table-layout: fixed; }
            .alumni-table th { background: #f0f2f5; color: #475569; font-size: 12px !important; font-weight: 600; text-transform: none; letter-spacing: 0; padding: 14px; border-bottom: 1px solid #dfe3e8; }
            .alumni-table td { padding: 18px 14px; font-size: 14px; line-height: 1.6; vertical-align: top; white-space: normal; overflow-wrap: break-word; }
            .alumni-table th:nth-child(1) { width: 24%; }
            .alumni-table th:nth-child(2) { width: 20%; }
            .alumni-table th:nth-child(3) { width: 12%; }
            .alumni-table th:nth-child(4) { width: 13%; }
            .alumni-table th:nth-child(5) { width: 8%; }
            .alumni-table th:nth-child(6) { width: 12%; }
            .alumni-table th:nth-child(7) { width: 11%; }
            .alumni-table td [class*="text-["] { font-size: 13px !important; }
            .alumni-table td .font-black, .alumni-table td .font-extrabold { font-weight: 600; }
            .alumni-table td .font-bold { font-weight: 500; }
            .alumni-table td .uppercase { text-transform: none; }
            .alumni-table td p { margin-top: 5px; }
            .alumni-table td p:first-child { margin-top: 0; }
            .alumni-table td p.max-w-\[180px\] { max-width: none; }
            .alumni-table tbody tr:nth-child(even) { background: #fcfcfd; }
            .alumni-table tbody tr:hover { background: #fffdf3; }
            .alumni-table td:nth-child(5) a, .alumni-table td:nth-child(5) a span,
            .alumni-table td:nth-child(7) a { white-space: nowrap; overflow-wrap: normal; word-break: normal; }
            .alumni-table td:nth-child(5) a, .alumni-table td:nth-child(7) a { display: inline-flex; align-items: center; gap: 6px; padding: 6px 8px; flex-shrink: 0; }
            .alumni-table a i { flex-shrink: 0; }
            a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible { outline: 3px solid #715700; outline-offset: 3px; }
        }
        @media screen and (min-width: 1536px) { main.max-w-7xl { padding-left: 28px; padding-right: 28px; } }
        @media screen and (max-width: 639px) {
            main.max-w-7xl { padding-left: 12px; padding-right: 12px; }
            header a > div:last-child { max-width: 175px; }
        }


        @media screen {
            body, input, select, textarea, button { font-family: 'Segoe UI', Arial, sans-serif; }
            header nav a { font-weight: 600; }
        }

        /* Compact page width while retaining the current readable typography. */
        @media screen {
            main.max-w-7xl { max-width: 1280px; padding-left: 20px; padding-right: 20px; }
        }
        @media screen and (max-width: 639px) {
            main.max-w-7xl { padding-left: 12px; padding-right: 12px; }
        }


        @media screen {
            header .department-name { font-size: 10px !important; line-height: 1.4; font-weight: 500; letter-spacing: 0; }
        }
</style>

</head>

<body class="bg-slate-100 text-slate-900 min-h-screen">

<!-- =====================================================

     GOVERNMENT TOP BAR

===================================================== -->

<div class="no-print bg-slate-950 text-white">

    <div class="max-w-7xl mx-auto px-4 sm:px-6">

        <div class="h-9 flex items-center justify-between">

            <div class="flex items-center gap-2">

                <span class="w-2 h-2 rounded-full bg-amber-400"></span>

                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-slate-300">

                    Portal Rasmi e-Intern — JKR Kuala Kangsar

                </span>

            </div>

            <div

                id="live-clock"

                class="text-[10px] font-mono font-bold text-amber-400"

            >

                --:--:--

            </div>

        </div>

    </div>

</div>

<!-- =====================================================

     NAVBAR

===================================================== -->

<!-- NAVBAR -->

<header class="no-print bg-white border-b border-black/10 sticky top-0 z-50">

    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">

        <div class="min-h-[74px] flex items-center justify-between gap-4 py-3">

            <a href="index.php" class="flex items-center gap-3 min-w-0">

                <div class="flex items-center gap-2 shrink-0">

                    <img src="logo-perak.png" alt="Logo Perak" class="h-9 w-auto object-contain" onerror="this.style.display='none';">

                    <div class="h-7 w-px bg-black/10"></div>

                    <img src="logo-jkr.png" alt="Logo JKR" class="h-9 w-auto object-contain" onerror="this.style.display='none';">

                </div>

                <div class="border-l border-black/10 pl-3 min-w-0">

                    <h1 class="text-xl font-extrabold leading-none text-[#111111]">e-Intern</h1>

                    <p class="text-[10px] font-bold text-gray-500 tracking-wide mt-1 department-name">JABATAN KERJA RAYA DAERAH KUALA KANGSAR</p>

                </div>

            </a>

            <nav aria-label="Navigasi utama" class="hidden lg:flex items-center gap-1 text-xs font-bold shrink-0">

                

                <a href="dashboard.php" class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"><i class="fa-solid fa-table-list text-gray-400"></i>Dashboard</a>

                <a href="alumni.php" aria-current="page" class="px-4 py-2.5 rounded-lg bg-[#F4C400] text-[#111111] flex items-center gap-2"><i class="fa-solid fa-user-graduate"></i>Alumni</a>

                <a href="tambah.php" class="ml-2 px-4 py-2.5 rounded-lg bg-[#111111] text-white hover:bg-[#292929] transition flex items-center gap-2"><i class="fa-solid fa-plus text-[#F4C400]"></i>Permohonan Baru</a>

            <a href="logout.php" class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"><i class="fa-solid fa-right-from-bracket text-gray-400" aria-hidden="true"></i>Log Keluar</a>
</nav>

            <button id="alumni-menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="alumni-mobile-menu" class="lg:hidden w-10 h-10 shrink-0 rounded-lg border border-black/10 bg-white flex items-center justify-center"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>

        </div>

    </div>

    <nav id="alumni-mobile-menu" hidden aria-label="Navigasi mudah alih" class="lg:hidden border-t border-black/10 bg-white px-5 py-3 space-y-1 text-xs font-bold">

        

        <a href="dashboard.php" class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-table-list"></i>Dashboard</a>

        <a href="alumni.php" aria-current="page" class="flex items-center gap-2 px-4 py-3 rounded-lg bg-[#F4C400] text-[#111111]"><i class="fa-solid fa-user-graduate"></i>Alumni</a>

        <a href="tambah.php" class="flex items-center gap-2 px-4 py-3 rounded-lg bg-[#111111] text-white"><i class="fa-solid fa-plus text-[#F4C400]"></i>Permohonan Baru</a>

    <a href="logout.php" class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Log Keluar</a>
</nav>

</header>

<!-- =====================================================

     MAIN

===================================================== -->

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-7 w-full">

    <!-- =================================================

         PRINT HEADER

    ================================================= -->

    <div class="hidden print-header border-b-2 border-slate-900 pb-4 mb-4">

        <div class="flex justify-between items-start">

            <div class="flex items-center gap-4">

                <img

                    src="logo-jkr.png"

                    alt="Logo JKR"

                    class="h-14 w-auto"

                    onerror="this.style.display='none'"

                >

                <div>

                    <h1 class="text-lg font-black uppercase">

                        Jabatan Kerja Raya (JKR)

                        Daerah Kuala Kangsar

                    </h1>

                    <p class="text-[10px]">

                        Jalan Stesen, 33000 Kuala Kangsar,

                        Perak Darul Ridzuan

                    </p>

                    <p class="text-[9px] text-slate-500">

                        Tel: 05-776 1090 |

                        Laman Web: jkr.perak.gov.my

                    </p>

                </div>

            </div>

            <div class="text-right">

                <span class="inline-block bg-slate-900 text-white px-3 py-1 text-[9px] font-bold uppercase">

                    Laporan Alumni

                </span>

                <p class="text-[9px] mt-2">

                    Tarikh Cetakan:

                    <b><?= date('d/m/Y h:i A') ?></b>

                </p>

            </div>

        </div>

        <div class="mt-4 pt-3 border-t border-slate-300 flex justify-between">

            <h2 class="text-sm font-black uppercase">

                Senarai Rekod Pelajar Tamat Latihan Industri

            </h2>

            <p class="text-[10px]">

                Jumlah:

                <b><?= $result->num_rows ?> Orang</b>

            </p>

        </div>

    </div>

    <!-- =================================================

         PAGE HEADER

    ================================================= -->

    <section class="no-print mb-6">

        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">

            <div>

                <div class="flex items-center gap-2 mb-2">

                    <span class="w-2 h-2 bg-amber-400 rounded-full"></span>

                    <span class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-600">

                        Rekod & Arkib

                    </span>

                </div>

                <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-950">

                    Alumni Intern

                </h2>

                <p class="text-sm text-slate-500 mt-2 max-w-xl">

                    Direktori rasmi pelajar yang telah selesai menjalani

                    latihan industri di JKR Daerah Kuala Kangsar.

                </p>

            </div>

            <!-- ACTION BUTTONS -->

            <div class="flex gap-2">

                <a href="tetapan_sijil.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:bg-amber-50"><i class="fa-solid fa-certificate"></i> Tetapan Sijil</a>

<a

                    href="alumni.php?export=csv"

                    class="px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:border-amber-400 hover:bg-amber-50 transition flex items-center gap-2 shadow-sm"

                >

                    <i class="fa-solid fa-file-csv text-emerald-600"></i>

                    Export CSV

                </a>

                <button

                    onclick="window.print()"

                    class="px-4 py-2.5 bg-slate-950 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition flex items-center gap-2 shadow-sm"

                >

                    <i class="fa-solid fa-print text-amber-400"></i>

                    Cetak / PDF

                </button>

            </div>

        </div>

    </section>

    <!-- =================================================

         STATISTICS

    ================================================= -->

    <section class="no-print grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

        <!-- TOTAL ALUMNI -->

        <div class="bg-slate-950 rounded-2xl p-5 relative overflow-hidden">

            <div class="absolute -right-5 -bottom-7 opacity-10">

                <i class="fa-solid fa-graduation-cap text-8xl text-white"></i>

            </div>

            <div class="relative">

                <div class="flex items-center justify-between mb-4">

                    <div class="w-10 h-10 rounded-xl bg-amber-400 flex items-center justify-center text-slate-950">

                        <i class="fa-solid fa-user-graduate"></i>

                    </div>

                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">

                        Total

                    </span>

                </div>

                <p class="text-xs font-bold text-slate-400">

                    Alumni Tamat

                </p>

                <div class="flex items-end gap-2 mt-1">

                    <h3 class="text-3xl font-black text-white">

                        <?= number_format($total_tamat) ?>

                    </h3>

                    <span class="text-xs text-slate-400 mb-1">

                        orang

                    </span>

                </div>

            </div>

        </div>

        <!-- DOCUMENT -->

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">

            <div class="flex items-center justify-between mb-4">

                <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600">

                    <i class="fa-solid fa-folder-open"></i>

                </div>

                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">

                    Rekod

                </span>

            </div>

            <p class="text-xs font-bold text-slate-500">

                Rekod Dokumen Diterima

            </p>

            <div class="flex items-end gap-2 mt-1">

                <h3 class="text-3xl font-black text-slate-950">

                    <?= number_format($total_dokumen) ?>

                </h3>

                <span class="text-xs text-slate-400 mb-1">

                    rekod

                </span>

            </div>

        </div>

        <!-- DEPARTMENT -->

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">

            <div class="flex items-center justify-between mb-4">

                <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">

                    <i class="fa-solid fa-building"></i>

                </div>

                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">

                    JKR

                </span>

            </div>

            <p class="text-xs font-bold text-slate-500">

                Bahagian Terlibat

            </p>

            <div class="flex items-end gap-2 mt-1">

                <h3 class="text-3xl font-black text-slate-950">

                    <?= number_format($total_bahagian) ?>

                </h3>

                <span class="text-xs text-slate-400 mb-1">

                    bahagian

                </span>

            </div>

        </div>

    </section>

    <!-- =================================================

         SEARCH / FILTER

    ================================================= -->

    <section class="no-print bg-white border border-slate-200 rounded-2xl shadow-sm mb-6">

        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">

            <div class="w-9 h-9 rounded-lg bg-slate-950 text-amber-400 flex items-center justify-center">

                <i class="fa-solid fa-sliders text-xs"></i>

            </div>

            <div>

                <h3 class="text-sm font-black text-slate-950">

                    Carian & Penapisan

                </h3>

                <p class="text-[10px] text-slate-400">

                    Cari rekod alumni berdasarkan maklumat pelajar

                </p>

            </div>

        </div>

        <form

            method="GET"

            action="alumni.php"

            class="p-5"

        >

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_260px_auto] gap-3">

                <!-- SEARCH -->

                <div>

                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">

                        Carian Alumni

                    </label>

                    <div class="relative">

                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>

                        <input

                            type="text"

                            name="search"

                            value="<?= htmlspecialchars($search) ?>"

                            placeholder="Nama, IC, matrik, IPT atau kursus..."

                            class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-300 focus:border-amber-400 focus:bg-white focus:outline-none transition"

                        >

                    </div>

                </div>

                <!-- BAHAGIAN -->

                <div>

                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">

                        Bahagian JKR

                    </label>

                    <select

                        name="bahagian"

                        class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-300 focus:border-amber-400 focus:bg-white focus:outline-none transition"

                    >

                        <option value="">

                            Semua Bahagian

                        </option>

                        <?php

                        $bahagian_list = [

                            "Bahagian Jalan",

                            "Bahagian Bangunan",

                            "Bahagian Elektrik",

                            "Bahagian Air / Bekalan Air",

                            "Bahagian Mekanikal",

                            "Bahagian Pentadbiran & Kewangan",

                            "Bahagian Ukur Bahan"

                        ];

                        foreach ($bahagian_list as $b):

                            $selected =

                                ($filter_bahagian === $b)

                                ? 'selected'

                                : '';

                        ?>

                            <option

                                value="<?= htmlspecialchars($b) ?>"

                                <?= $selected ?>

                            >

                                <?= htmlspecialchars($b) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- BUTTON -->

                <div class="flex items-end gap-2">

                    <button

                        type="submit"

                        class="flex-1 lg:flex-none px-5 py-2.5 bg-slate-950 hover:bg-slate-800 text-amber-400 rounded-xl text-xs font-black transition flex items-center justify-center gap-2"

                    >

                        <i class="fa-solid fa-magnifying-glass"></i>

                        Cari

                    </button>

                    <a

                        href="alumni.php"

                        class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold border border-slate-200 transition"

                        title="Reset carian"

                    >

                        <i class="fa-solid fa-rotate-left"></i>

                    </a>

                </div>

            </div>

        </form>

    </section>

    <!-- =================================================

         TABLE HEADER

    ================================================= -->

    <section class="mb-3 flex items-center justify-between">

        <div>

            <h3 class="text-sm font-black text-slate-950">

                Senarai Alumni

            </h3>

            <p class="text-[10px] text-slate-400 mt-0.5">

                <?= number_format($result->num_rows) ?>

                rekod dipaparkan

            </p>

        </div>

        <?php if (!empty($search) || !empty($filter_bahagian)): ?>

            <div class="no-print flex items-center gap-1.5">

                <span class="text-[9px] font-bold text-slate-400 uppercase">

                    Filter aktif

                </span>

                <span class="w-2 h-2 rounded-full bg-amber-400"></span>

            </div>

        <?php endif; ?>

    </section>

    <!-- =================================================

         TABLE

    ================================================= -->

    <div class="table-container bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">

        <div class="overflow-x-auto">

            <table class="alumni-table w-full text-left">

                <thead>

                    <tr class="bg-slate-950 text-white">

                        <th class="px-5 py-4 text-[10px] font-black uppercase tracking-wider w-[24%]">

                            Maklumat Alumni

                        </th>

                        <th class="px-5 py-4 text-[10px] font-black uppercase tracking-wider w-[18%]">

                            IPT & Kursus

                        </th>

                        <th class="px-5 py-4 text-[10px] font-black uppercase tracking-wider w-[14%]">

                            Bahagian

                        </th>

                        <th class="px-5 py-4 text-[10px] font-black uppercase tracking-wider w-[14%]">

                            Tempoh Latihan

                        </th>

                        <th class="px-5 py-4 text-[10px] font-black uppercase tracking-wider w-[10%]">

                            Dokumen

                        </th>

                        <th class="px-5 py-4 text-[10px] font-black uppercase tracking-wider">

                            Catatan

                        </th>

                    <th class="no-print px-3 py-4 text-[10px] font-black uppercase tracking-wider">Sijil</th>

</tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                <?php if ($result->num_rows > 0): ?>

                    <?php

                    $bil = 1;

                    while ($row = $result->fetch_assoc()):

                        $initials = getInitials($row['nama']);

                    ?>

                    <tr>

                        <!-- =================================

                             ALUMNI

                        ================================= -->

                        <td class="px-5 py-5">

                            <div class="flex items-start gap-3">

                                <div

                                    class="avatar w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-xs font-black text-amber-700"

                                >

                                    <?= htmlspecialchars($initials) ?>

                                </div>

                                <div class="min-w-0">

                                    <div class="flex items-center gap-2">

                                        <span class="text-sm font-black text-slate-950">

                                            <?= htmlspecialchars($row['nama']) ?>

                                        </span>

                                        <span class="no-print text-[8px] font-bold text-slate-300">

                                            #<?= $bil++ ?>

                                        </span>

                                    </div>

                                    <div class="mt-1 space-y-0.5">

                                        <div class="text-[10px] text-slate-500">

                                            <span class="font-bold text-slate-600">

                                                IC:

                                            </span>

                                            <?= htmlspecialchars($row['ic']) ?>

                                        </div>

                                        <div class="text-[10px] text-slate-500">

                                            <span class="font-bold text-slate-600">

                                                Matrik:

                                            </span>

                                            <?= htmlspecialchars($row['matric']) ?>

                                        </div>

                                        <div class="text-[10px] text-slate-500 flex items-center gap-1">

                                            <i class="fa-solid fa-phone text-[8px] text-amber-500"></i>

                                            <?= htmlspecialchars($row['telefon']) ?>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </td>

                        <!-- =================================

                             IPT

                        ================================= -->

                        <td class="px-5 py-5">

                            <div class="text-xs font-black text-slate-900 leading-snug">

                                <?= htmlspecialchars($row['ipt'] ?? 'N/A') ?>

                            </div>

                            <div class="text-[10px] text-slate-500 mt-1 leading-relaxed">

                                <?= htmlspecialchars($row['kursus'] ?? 'N/A') ?>

                            </div>

                        </td>

                        <!-- =================================

                             BAHAGIAN

                        ================================= -->

                        <td class="px-5 py-5">

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[10px] font-bold text-slate-700">

                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>

                                <?= htmlspecialchars($row['bahagian'] ?: 'N/A') ?>

                            </span>

                        </td>

                        <!-- =================================

                             TEMPOH

                        ================================= -->

                        <td class="px-5 py-5">

                            <div class="space-y-2">

                                <div>

                                    <p class="text-[8px] uppercase font-black tracking-wider text-slate-400">

                                        Mula

                                    </p>

                                    <p class="text-[10px] font-bold text-slate-700 mt-0.5">

                                        <?= htmlspecialchars($row['tarikh_mula']) ?>

                                    </p>

                                </div>

                                <div>

                                    <p class="text-[8px] uppercase font-black tracking-wider text-slate-400">

                                        Tamat

                                    </p>

                                    <p class="text-[10px] font-black text-emerald-600 mt-0.5">

                                        <?= htmlspecialchars($row['tarikh_tamat']) ?>

                                    </p>

                                </div>

                            </div>

                        </td>

                        <!-- =================================

                             DOCUMENT

                        ================================= -->

                        <td class="px-5 py-5">

                            
<?php $document_info = eintern_document_checklist($row); ?>
<span class="inline-flex px-2 py-1 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 text-xs font-semibold">
    <?= $document_info['count'] ?>/6 diterima
</span>


                        </td>

                        <!-- =================================

                             CATATAN

                        ================================= -->

                        <td class="px-5 py-5">

                            <?php if (!empty($row['catatan'])): ?>

                                <p class="text-[10px] text-slate-500 leading-relaxed max-w-[180px]">

                                    <i class="fa-solid fa-quote-left text-[8px] text-amber-400 mr-1"></i>

                                    <?= htmlspecialchars($row['catatan']) ?>

                                </p>

                            <?php else: ?>

                                <span class="text-[10px] text-slate-300">

                                    Tiada catatan

                                </span>

                            <?php endif; ?>

                        </td>

                    <td class="no-print px-3 py-5">

                        <div class="flex flex-col items-start gap-2">

                            <a href="sijil.php?id=<?= (int) $row['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-[11px] font-bold text-slate-800 hover:bg-amber-100"><i class="fa-solid fa-certificate"></i> <?= isset($issuedCertificateIds[(int) $row['id']]) ? 'Lihat Sijil' : 'Jana Sijil' ?></a>

                            <?php if (isset($issuedCertificateIds[(int) $row['id']])): ?>

                                <a href="sijil.php?id=<?= (int) $row['id'] ?>&amp;print=1" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-950 text-white text-[11px] font-bold hover:bg-slate-700"><i class="fa-solid fa-print"></i> Cetak</a>

                            <?php endif; ?>

                        </div>

                    </td>

</tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <!-- EMPTY STATE -->

                    <tr>

                        <td

                            colspan="7"

                            class="py-20 text-center"

                        >

                            <div class="flex flex-col items-center">

                                <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-300 mb-4">

                                    <i class="fa-solid fa-user-graduate text-2xl"></i>

                                </div>

                                <h4 class="text-sm font-black text-slate-700">

                                    Tiada rekod dijumpai

                                </h4>

                                <p class="text-[10px] text-slate-400 mt-1">

                                    Cuba ubah kata carian atau pilihan bahagian.

                                </p>

                                <a

                                    href="alumni.php"

                                    class="no-print mt-4 px-4 py-2 bg-slate-950 text-amber-400 rounded-lg text-[10px] font-bold hover:bg-slate-800 transition"

                                >

                                    Reset Carian

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- TABLE FOOTER -->

        <?php if ($result->num_rows > 0): ?>

            <div class="no-print px-5 py-3 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2">

                <p class="text-[9px] text-slate-400 font-medium">

                    <i class="fa-solid fa-circle-info mr-1"></i>

                    Rekod hanya memaparkan pelajar berstatus

                    <span class="font-bold text-slate-600">

                        “Tamat Latihan”

                    </span>.

                </p>

                <p class="text-[9px] font-bold text-slate-500">

                    <?= number_format($result->num_rows) ?>

                    rekod

                </p>

            </div>

        <?php endif; ?>

    </div>

    <!-- =================================================

         PRINT FOOTER

    ================================================= -->

    <div class="hidden print-footer">

        <div>

            <p class="text-[9px] text-slate-500 font-mono">

                Dokumen ini dijana secara automatik menerusi Portal e-Intern JKR Kuala Kangsar.

            </p>

            <p class="text-[9px] text-slate-500 font-mono">

                Cetak Semula: ID-ALUMNI-<?= date('Ymd-His') ?>

            </p>

        </div>

        <div class="text-center w-52 border-t border-slate-900 pt-2">

            <p class="text-[10px] font-bold uppercase">

                Tandatangan & Cop Pegawai

            </p>

            <p class="text-[9px] text-slate-500">

                Jabatan Kerja Raya Daerah Kuala Kangsar

            </p>

        </div>

    </div>

</main>

<!-- =====================================================

     FOOTER

===================================================== -->

<footer class="no-print bg-slate-950 text-slate-400 mt-8">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-5">

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">

            <div class="flex items-center gap-2">

                <div class="w-6 h-6 rounded-md bg-amber-400 flex items-center justify-center text-slate-950 text-[9px] font-black">

                    JKR

                </div>

                <p class="text-[10px]">

                    © <?= date('Y') ?> Jabatan Kerja Raya Kuala Kangsar.

                    Hak Cipta Terpelihara.

                </p>

            </div>

            <div class="flex items-center gap-3">

                <span class="text-[9px] text-slate-500">

                    e-Intern System

                </span>

                <span class="w-1 h-1 bg-amber-400 rounded-full"></span>

                <span class="text-[9px] font-bold text-slate-300">

                    v2.0

                </span>

            </div>

        </div>

    </div>

</footer>

<!-- =====================================================

     CLOCK

===================================================== -->

<script>

function updateClock() {

    const now = new Date();

    let hours = now.getHours();

    const minutes =

        String(now.getMinutes()).padStart(2, '0');

    const seconds =

        String(now.getSeconds()).padStart(2, '0');

    const ampm =

        hours >= 12 ? 'PM' : 'AM';

    hours =

        hours % 12 || 12;

    const clockEl =

        document.getElementById('live-clock');

    if (clockEl) {

        clockEl.textContent =

            `${String(hours).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;

    }

}

setInterval(updateClock, 1000);

updateClock();

</script>

<script>

(function () {

    const button = document.getElementById('alumni-menu-toggle');

    const menu = document.getElementById('alumni-mobile-menu');

    if (!button || !menu) return;

    function setOpen(open) {

        menu.hidden = !open;

        button.setAttribute('aria-expanded', String(open));

        button.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');

        button.querySelector('i').className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';

    }

    button.addEventListener('click', function () { setOpen(menu.hidden); });

    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setOpen(false); });

    window.addEventListener('resize', function () { if (window.innerWidth >= 1024) setOpen(false); });

})();

</script>

</body>

</html>