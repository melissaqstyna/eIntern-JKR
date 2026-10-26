<?php
// =========================================================
// DASHBOARD.PHP
// Sistem e-Intern JKR Daerah Kuala Kangsar
// =========================================================

session_start();

require_once 'db.php';

// Status dikira daripada enam tanda sebenar, bukan fail upload lama.
function eintern_document_checklist($row) {
    $labels = [
        'surat_iringan' => 'Surat Permohonan Latihan Industri daripada IPTA',
        'resume' => 'Resume/Biodata Pelajar',
        'borang_jawapan' => 'Borang Maklum Persetujuan Latihan Industri',
        'lampiran_butiran' => 'Surat Permohonan Pelajar',
        'objektif_skop' => 'Lampiran Skop Latihan Industri',
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



// =========================================================
// SEMAK STATUS LOG MASUK
// =========================================================

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// =========================================================
// EXCEL EXPORT — XLSX sebenar, tanpa Composer atau ZipArchive.
// Semua pengenalan disimpan sebagai teks, bukan formula/nombor.
// =========================================================
function eintern_excel_xml($value) {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

function eintern_excel_column($index) {
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

// ZIP tanpa mampatan untuk pakej Open XML.
function eintern_excel_zip($files) {
    $body = '';
    $directory = '';
    $count = 0;
    foreach ($files as $name => $data) {
        $offset = strlen($body);
        $size = strlen($data);
        $crc = crc32($data);
        $name_length = strlen($name);
        $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33,
            $crc, $size, $size, $name_length, 0) . $name . $data;
        $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 33,
            $crc, $size, $size, $name_length, 0, 0, 0, 0, 0, $offset) . $name;
        $count++;
    }
    return $body . $directory . pack('VvvvvVVv', 0x06054b50, 0, 0,
        $count, $count, strlen($directory), strlen($body), 0);
}

if (isset($_GET['export']) && in_array($_GET['export'], ['xlsx', 'csv'], true)) {
    // Pautan CSV lama turut diarahkan kepada eksport Excel.
    $headers = [
        'Bil.', 'Kod Rekod e-Intern (EIN)', 'No. Fail JKR', 'Nama Pelajar',
        'Jantina', 'No. IC', 'No. Matrik', 'No. Telefon', 'IPT', 'Kursus',
        'Bahagian JKR', 'Tarikh Mula', 'Tarikh Tamat', 'Dokumen Diterima',
        'Surat Permohonan Latihan Industri daripada IPTA',
        'Resume/Biodata Pelajar', 'Borang Maklum Persetujuan Latihan Industri',
        'Surat Permohonan Pelajar', 'Lampiran Skop Latihan Industri',
        'Lain-lain Borang', 'Status Tindakan', 'Catatan'
    ];
    $widths = [7, 25, 27, 38, 14, 20, 20, 20, 35, 38, 33, 17, 17, 19,
        28, 25, 28, 28, 28, 25, 30, 45];
    $last_column = eintern_excel_column(count($headers));
    $xml_start = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheet = $xml_start . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheet .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    $sheet .= '<sheetFormatPr defaultRowHeight="30"/><cols>';
    foreach ($widths as $i => $width) {
        $n = $i + 1;
        $sheet .= '<col min="'.$n.'" max="'.$n.'" width="'.$width.'" customWidth="1"/>';
    }
    $sheet .= '</cols><sheetData>';
    $export_time = new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur'));
    $title_rows = [
        'e-Intern — JKR Daerah Kuala Kangsar',
        'Rekod Pelajar Latihan Industri',
        'Dijana: ' . $export_time->format('d/m/Y H:i') . ' | Semua rekod'
    ];
    foreach ($title_rows as $i => $title) {
        $n = $i + 1;
        $style = $i === 0 ? 1 : 2;
        $sheet .= '<row r="'.$n.'" ht="26" customHeight="1"><c r="A'.$n.'" s="'.$style.'" t="inlineStr"><is><t>'.eintern_excel_xml($title).'</t></is></c></row>';
    }
    $sheet .= '<row r="4" ht="65" customHeight="1">';
    foreach ($headers as $i => $label) {
        $cell = eintern_excel_column($i + 1) . '4';
        $sheet .= '<c r="'.$cell.'" s="3" t="inlineStr"><is><t>'.eintern_excel_xml($label).'</t></is></c>';
    }
    $sheet .= '</row>';

    // Sama seperti eksport asal: semua rekod, termasuk alumni.
    $stmt = $conn->prepare('SELECT * FROM students ORDER BY id DESC');
    $stmt->execute();
    $result = $stmt->get_result();
    $number = 0;
    while ($row = $result->fetch_assoc()) {
        $number++;
        $checklist = eintern_document_checklist($row);
        $values = [
            $number, $row['no_rujukan'] ?? '', $row['no_rujukan_fail'] ?? '',
            $row['nama'] ?? '', $row['jantina'] ?? '', $row['ic'] ?? '',
            $row['matric'] ?? '', $row['telefon'] ?? '', $row['ipt'] ?? '',
            $row['kursus'] ?? '', $row['bahagian'] ?? '',
            $row['tarikh_mula'] ?? '', $row['tarikh_tamat'] ?? '',
            $checklist['count'] . '/6'
        ];
        foreach ($checklist['received'] as $received) {
            $values[] = $received ? 'Diterima' : 'Belum diterima';
        }
        $values[] = $row['status_tindakan'] ?? '';
        $values[] = $row['catatan'] ?? '';
        $n = $number + 4;
        $sheet .= '<row r="'.$n.'">';
        foreach ($values as $i => $value) {
            $cell = eintern_excel_column($i + 1) . $n;
            // Bil., jantina, tarikh, semakan dokumen dan status di tengah.
            $center = $i === 0 || $i === 4 || ($i >= 11 && $i <= 20);
            $style = ($number % 2 === 0 ? 6 : 4) + ($center ? 1 : 0);
            // Tarikh sah ialah nombor Excel dengan format dd/mm/yyyy.
            $date = null;
            if (($i === 11 || $i === 12) && is_string($value)) {
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
                if ($parsed && $parsed->format('Y-m-d') === $value && $value >= '1900-03-01') {
                    $date = (int) (new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC')))->diff($parsed)->format('%a');
                }
            }
            if ($date !== null) {
                $sheet .= '<c r="'.$cell.'" s="'.($number % 2 === 0 ? 9 : 8).'"><v>'.$date.'</v></c>';
            } elseif ($i === 0) {
                $sheet .= '<c r="'.$cell.'" s="'.$style.'"><v>'.$number.'</v></c>';
            } else {
                $sheet .= '<c r="'.$cell.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.eintern_excel_xml($value).'</t></is></c>';
            }
        }
        $sheet .= '</row>';
    }
    $stmt->close();
    $last_row = max(4, $number + 4);
    $sheet .= '</sheetData><autoFilter ref="A4:'.$last_column.$last_row.'"/>';
    $sheet .= '<mergeCells count="3"><mergeCell ref="A1:'.$last_column.'1"/><mergeCell ref="A2:'.$last_column.'2"/><mergeCell ref="A3:'.$last_column.'3"/></mergeCells>';
    $sheet .= '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape"/></worksheet>';

    $styles = $xml_start . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $styles .= '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/></numFmts>';
    $styles .= '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>';
    $styles .= '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4C400"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill></fills>';
    $styles .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>';
    foreach (['left','right','top','bottom'] as $edge) {
        $styles .= '<'.$edge.' style="thin"><color rgb="FFD1D5DB"/></'.$edge.'>';
    }
    $styles .= '<diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="10">';
    $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
    $styles .= '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>';
    $styles .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>';
    $styles .= '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
    foreach ([0, 3] as $fill) {
        foreach (['left', 'center'] as $align) {
            $styles .= '<xf numFmtId="49" fontId="0" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="'.$align.'" vertical="center" wrapText="1"/></xf>';
        }
    }
    foreach ([0, 3] as $fill) {
        $styles .= '<xf numFmtId="164" fontId="0" fillId="'.$fill.'" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
    }
    $styles .= '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $files = [
        '[Content_Types].xml' => $xml_start . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => $xml_start . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => $xml_start . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rekod Pelajar" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => $xml_start . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => $styles,
        'xl/worksheets/sheet1.xml' => $sheet
    ];
    $download = eintern_excel_zip($files);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Rekod_Pelajar_eIntern_'.$export_time->format('Ymd_His').'.xlsx"');
    header('Cache-Control: no-store');
    echo $download;
    exit();
}

// =========================================================
// DELETE STUDENT
// =========================================================

if (
    isset($_GET['delete']) &&
    $_GET['delete'] !== ''
) {

    $delete_id = (int) $_GET['delete'];

    // Get student information first

    $stmt = $conn->prepare("
        SELECT
            status_tindakan,
            dokumen_fail
        FROM students
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $delete_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $delete_student = $result->fetch_assoc();

    $stmt->close();

    // Student not found

    if (!$delete_student) {

        header(
            "Location: dashboard.php?error=not_found"
        );

        exit();
    }

    // Protect alumni records

    if (
        $delete_student['status_tindakan'] ===
        'Tamat Latihan'
    ) {

        header(
            "Location: dashboard.php?error=alumni_protected"
        );

        exit();
    }

    // Delete uploaded document

    if (
        !empty(
            $delete_student['dokumen_fail']
        )
    ) {

        $oldFile =
            './uploads/' .
            $delete_student['dokumen_fail'];

        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    // Delete student

    $stmt = $conn->prepare("
        DELETE FROM students
        WHERE id = ?
    ");

    $stmt->bind_param(
        "i",
        $delete_id
    );

    $stmt->execute();

    $stmt->close();

    header(
        "Location: dashboard.php?success=deleted"
    );

    exit();
}

// =========================================================
// STATISTICS
// =========================================================

$total_students = 0;
$active_students = 0;
$pending_students = 0;
$alumni_students = 0;

// Total

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

if ($result) {

    $row = $result->fetch_assoc();

    $total_students =
        (int) ($row['total'] ?? 0);
}

// Active

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
    WHERE status_tindakan = 'Aktif (Sedang Latihan)'
");

if ($result) {

    $row = $result->fetch_assoc();

    $active_students =
        (int) ($row['total'] ?? 0);
}

// Pending

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
    WHERE status_tindakan = 'Dalam Proses Permohonan'
");

if ($result) {

    $row = $result->fetch_assoc();

    $pending_students =
        (int) ($row['total'] ?? 0);
}

// Alumni

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
    WHERE status_tindakan = 'Tamat Latihan'
");

if ($result) {

    $row = $result->fetch_assoc();

    $alumni_students =
        (int) ($row['total'] ?? 0);
}

// Action overview uses all records, independently of table filters.
$dashboard_today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kuala_Lumpur')))->format('Y-m-d');
$dashboard_week = (new DateTimeImmutable($dashboard_today))->modify('+7 days')->format('Y-m-d');
$ending_soon = 0;
$overdue_training = 0;
$overview_stmt = $conn->prepare("SELECT SUM(CASE WHEN tarikh_tamat BETWEEN ? AND ? THEN 1 ELSE 0 END) AS soon, SUM(CASE WHEN tarikh_tamat >= '1000-01-01' AND tarikh_tamat < ? THEN 1 ELSE 0 END) AS overdue FROM students WHERE status_tindakan = 'Aktif (Sedang Latihan)'");
if ($overview_stmt) {
    $overview_stmt->bind_param('sss', $dashboard_today, $dashboard_week, $dashboard_today);
    $overview_stmt->execute();
    $overview_row = $overview_stmt->get_result()->fetch_assoc();
    $ending_soon = (int) ($overview_row['soon'] ?? 0);
    $overdue_training = (int) ($overview_row['overdue'] ?? 0);
    $overview_stmt->close();
}

// =========================================================
// SEARCH / FILTER / SORT
// =========================================================

$search =
    trim($_GET['search'] ?? '');

$filter_bahagian =
    trim($_GET['bahagian'] ?? '');

$filter_status =
    trim($_GET['status'] ?? '');

$sort_by =
    $_GET['sort'] ?? 'created_at';

$order =
    strtoupper($_GET['order'] ?? 'DESC');

// Allowed sort columns

$allowed_sort = [

    'no_rujukan',
    'nama',
    'tarikh_mula',
    'tarikh_tamat',
    'created_at'

];

if (!in_array($sort_by, $allowed_sort, true)) {
    $sort_by = 'created_at';
}

if (!in_array($order, ['ASC', 'DESC'], true)) {
    $order = 'DESC';
}

// =========================================================
// BUILD QUERY
// =========================================================

$sql = "
    SELECT *
    FROM students
    WHERE 1=1
";

$params = [];
$types = '';

// SEARCH

if ($search !== '') {

    $sql .= "
        AND (
            no_rujukan LIKE ?
            OR no_rujukan_fail LIKE ?
            OR nama LIKE ?
            OR ic LIKE ?
            OR matric LIKE ?
            OR ipt LIKE ?
            OR kursus LIKE ?
        )
    ";

    $search_value =
        '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $params[] = $search_value;
    $types .= 'sssssss';
}

// FILTER BAHAGIAN

if ($filter_bahagian !== '') {

    $sql .= "
        AND bahagian = ?
    ";

    $params[] =
        $filter_bahagian;

    $types .= 's';
}

// FILTER STATUS

if ($filter_status !== '') {

    $sql .= "
        AND status_tindakan = ?
    ";

    $params[] =
        $filter_status;

    $types .= 's';
}

// SORT

$sql .= "
    ORDER BY
    {$sort_by}
    {$order}
";

$stmt = $conn->prepare($sql);

// Bind dynamic parameters

if (!empty($params)) {

    $bind_names = [];

    $bind_names[] = $types;

    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bind_names
    );
}

$stmt->execute();

$students_result =
    $stmt->get_result();

// =========================================================
// FLASH MESSAGE
// =========================================================

$success =
    $_GET['success'] ?? '';

$error =
    $_GET['error'] ?? '';

?>

<!DOCTYPE html>

<html lang="ms">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Dashboard | e-Intern JKR Kuala Kangsar
    </title>

    <!-- =====================================================
         TAILWIND
    ====================================================== -->

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- =====================================================
         FONT AWESOME
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- =====================================================
         GOOGLE FONT
    ====================================================== -->

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <!-- =====================================================
         CUSTOM CSS
    ====================================================== -->

    <style>

        * {
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {

            background:
                #f7f7f5;

            color:
                #111111;
        }

        /* =====================================
           TOP BAR
        ===================================== */

        .top-bar {

            background:
                #111111;

            border-bottom:
                3px solid
                #F4C400;
        }

        /* =====================================
           NAVIGATION
        ===================================== */

        .nav-link {

            transition:
                all .2s ease;
        }

        .nav-link:hover {

            background:
                #f3f3f3;

            color:
                #111111;
        }

        .nav-active {

            background:
                #F4C400;

            color:
                #111111;
        }

        .nav-active:hover {

            background:
                #e5b900;
        }

        /* =====================================
           STAT CARD
        ===================================== */

        .stat-card {

            background:
                #ffffff;

            border:
                1px solid
                #e5e5e5;

            border-radius:
                16px;

            transition:
                all .2s ease;
        }

        .stat-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 8px 24px
                rgba(0,0,0,.05);
        }

        /* =====================================
           MAIN CARD
        ===================================== */

        .main-card {

            background:
                #ffffff;

            border:
                1px solid
                #e5e5e5;

            border-radius:
                18px;

            overflow:
                hidden;
        }

        /* =====================================
           SEARCH INPUT
        ===================================== */

        .search-input {

            width:
                100%;

            padding:
                11px
                14px
                11px
                38px;

            background:
                #f8f8f7;

            border:
                1px solid
                #dedede;

            border-radius:
                10px;

            font-size:
                11px;

            outline:
                none;

            transition:
                all .2s ease;
        }

        .search-input:focus {

            background:
                #ffffff;

            border-color:
                #F4C400;

            box-shadow:
                0 0 0 3px
                rgba(244,196,0,.15);
        }

        .search-wrapper {

            position:
                relative;
        }

        .search-icon {

            position:
                absolute;

            left:
                14px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #9ca3af;

            font-size:
                12px;

            pointer-events:
                none;
        }

        /* =====================================
           SELECT
        ===================================== */

        .filter-select {

            width:
                100%;

            padding:
                11px
                12px;

            background:
                #f8f8f7;

            border:
                1px solid
                #dedede;

            border-radius:
                10px;

            font-size:
                11px;

            font-weight:
                600;

            outline:
                none;

            cursor:
                pointer;
        }

        .filter-select:focus {

            background:
                #ffffff;

            border-color:
                #F4C400;

            box-shadow:
                0 0 0 3px
                rgba(244,196,0,.15);
        }

        /* =====================================
           TABLE
        ===================================== */

        .student-table {

            border-collapse:
                separate;

            border-spacing:
                0;
        }

        .student-table th {

            background:
                #f8f8f7;

            color:
                #6b7280;

            font-size:
                9px;

            font-weight:
                800;

            text-transform:
                uppercase;

            letter-spacing:
                .06em;

            padding:
                13px 14px;

            border-bottom:
                1px solid
                #e5e5e5;

            white-space:
                nowrap;
        }

        .student-table td {

            padding:
                15px 14px;

            border-bottom:
                1px solid
                #eeeeee;

            vertical-align:
                middle;
        }

        .student-table tbody tr {

            transition:
                background .15s ease;
        }

        .student-table tbody tr:hover {

            background:
                #fffdf2;
        }

        .student-table tbody tr:last-child td {

            border-bottom:
                none;
        }

        /* =====================================
           REFERENCE NUMBER
        ===================================== */

        .reference-number {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                5px 8px;

            border-radius:
                7px;

            background:
                #FFF7CC;

            border:
                1px solid
                rgba(244,196,0,.45);

            color:
                #715700;

            font-size:
                9px;

            font-weight:
                800;

            letter-spacing:
                .02em;
        }

        /* =====================================
           STATUS
        ===================================== */

        .status-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                5px;

            padding:
                5px 8px;

            border-radius:
                7px;

            font-size:
                8px;

            font-weight:
                800;
        }

        /* =====================================
           MOBILE MENU
        ===================================== */

        #mobileMenu {

            display:
                none;
        }

        #mobileMenu.show {

            display:
                block;
        }

        /* =====================================
           SCROLLBAR
        ===================================== */

        ::-webkit-scrollbar {

            width:
                7px;

            height:
                7px;
        }

        ::-webkit-scrollbar-track {

            background:
                #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {

            background:
                #c7c7c7;

            border-radius:
                10px;
        }

        ::-webkit-scrollbar-thumb:hover {

            background:
                #999999;
        }

        /* =====================================
           PRINT
        ===================================== */

        .print-only {

            display:
                none;
        }

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

            .main-card, .student-table-wrapper {

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

            /* Print only the six requested report columns. */
            .student-table thead th:nth-child(1),
            .student-table thead th:nth-child(7),
            .student-table tbody td:not([colspan]):nth-child(1),
            .student-table tbody td:not([colspan]):nth-child(7) {
                display: none !important;
            }
            .student-table-wrapper { overflow: visible !important; }
            .student-table { table-layout: auto !important; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            .print-only { display: block !important; }
        }

        /* =====================================
           MOBILE
        ===================================== */

        @media (max-width: 767px) {

            .desktop-nav {

                display:
                    none;
            }

        }

        /* Standard, readable dashboard typography for screen use. */
        @media screen {
            body { font-size: 16px; line-height: 1.5; }
            .text-gray-400 { color: #596273 !important; }
            .text-gray-500 { color: #4b5563 !important; }
            .search-input, .filter-select, select[name="sort"], select[name="order"] {
                font-size: 12px !important;
                min-height: 36px;
                line-height: 1.5;
            }
            .search-input { padding: 9px 12px 9px 36px; }
            .search-input::placeholder { color: #596273; opacity: 1; }
            .search-icon { font-size: 12px; }
            .student-table-wrapper { width: 100%; }
            .student-table { width: 100%; min-width: 0; table-layout: fixed; }
            .student-table th, .student-table td {
                white-space: normal;
                overflow-wrap: anywhere;
                word-break: normal;
            }
            .student-table th:nth-child(1) { width: 4%; }
            .student-table th:nth-child(2) { width: 11%; }
            .student-table th:nth-child(3) { width: 20%; }
            .student-table th:nth-child(4) { width: 15%; }
            .student-table th:nth-child(5) { width: 11%; }
            .student-table th:nth-child(6) { width: 12%; }
            .student-table th:nth-child(7) { width: 8%; }
            .student-table th:nth-child(8) { width: 12%; }
            .student-table th:nth-child(9) { width: 7%; }
            .student-table td > div { min-width: 0; }
            .student-table td .flex { flex-wrap: wrap; }
            .student-table td span, .student-table td a {
                max-width: 100%;
                white-space: normal;
                overflow-wrap: anywhere;
            }
            .student-table th {
                font-size: 11px;
                color: #374151;
                padding: 11px 6px;
                letter-spacing: .02em;
            }
            .student-table td { font-size: 12px; padding: 12px 6px; }
            .reference-number, .status-badge {
                font-size: 11px;
                line-height: 1.5;
                padding: 5px 8px;
            }
            .filter-select { padding: 9px 12px; }
            .reference-number { white-space: normal; overflow-wrap: anywhere; }
            .student-table .w-8.h-8 { width: 32px; height: 32px; flex-shrink: 0; }
            button, a[title="Reset"] { min-height: 34px; }
            button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible {
                outline: 3px solid #715700;
                outline-offset: 3px;
            }
        }

        #dashboard-mobile-menu[hidden]{display:none!important}
    
        /* Typography follows the supplied Alumni reference. */
        @media screen {
            body { font-family: 'Segoe UI', Arial, sans-serif; }
            .text-xs { font-size:12px; }
            .text-sm { font-size:14px; }
            main > div:first-child h2 { font-size:30px; line-height:1.2; font-weight:900; }
            main > div:first-child p { font-size:14px; line-height:1.5; }
            .student-table th { font-size:9px; }
            .student-table td { font-size:11px; }
            .reference-number,.status-badge { font-size:10px; }
            .search-input,.filter-select,select[name="sort"],select[name="order"] { font-size:11px!important; }
            @media(min-width:640px){main > div:first-child h2 {font-size:36px;}}
        }

        @media screen {
            .overview-total { background: #111827; border-color: #111827; color: #fff; }
            .overview-total p:first-child { color: #d1d5db !important; }
            .overview-total p.text-3xl { color: #f4c400; }
            .overview-total .w-9 { background: #ffffff16; }
            .overview-total i { color: #f4c400; }
            .overview-active { border-top: 3px solid #059669; }
            .overview-pending { border-top: 3px solid #eab308; }
            .overview-alumni { border-top: 3px solid #2563eb; }
            .overview-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
            .overview-panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 20px; }
            .overview-icon { width: 34px; height: 34px; display: grid; place-items: center; background: #fff8d6; color: #806300; border-radius: 10px; font-size: 14px; }
            .department-chart { display: grid; gap: 11px; }
            .department-track { height: 7px; border-radius: 10px; background: #f1f5f9; overflow: hidden; }
            .department-track > div { height: 100%; background: #e9b900; border-radius: inherit; }
            .action-summary { display: flex; align-items: center; gap: 12px; padding: 13px 0; border-top: 1px solid #f1f5f9; }
            .action-summary strong { display: block; font-size: 12px; font-weight: 700; }
            .action-summary small { display: block; margin-top: 3px; color: #64748b; font-size: 11px; }
            .action-summary > b { font-size: 22px; }
            a.action-summary:hover strong { text-decoration: underline; }
            .action-symbol { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; font-size: 13px; }
            .pending-symbol { background: #fff8d6; color: #927000; }
            .soon-symbol { background: #ecfdf5; color: #047857; }
            .overdue-symbol { background: #fff1f2; color: #be123c; }
            .quick-filters { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 14px; }
            .quick-filter { padding: 7px 12px; border-radius: 8px; border: 1px solid #e5e7eb; font-size: 12px; font-weight: 600; background: #fff; }
            .quick-filter.is-selected { background: #111827; color: #fff; border-color: #111827; }
            .quick-filter:hover { border-color: #a78b00; }
            .student-table tbody tr:nth-child(even) { background: #f8fafc; }
            .student-table tbody tr:hover { background: #fffbea; }
        }
        @media screen and (max-width: 900px) { .overview-grid { grid-template-columns: 1fr; } }

        @media screen {
            .action-overview-items { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
            .action-overview-items .action-summary { align-items: center; }
        }
        @media screen and (max-width: 900px) { .action-overview-items { grid-template-columns: 1fr; gap: 0; } }

        /* Larger, readable screen typography; print rules remain unchanged. */
        @media screen {
            body { font-size: 14px; line-height: 1.5; }
            .text-xs, .text-sm { font-size: 14px; line-height: 1.5; }
            [class*="text-[8px]"], [class*="text-[9px]"],
            [class*="text-[10px]"], [class*="text-[11px]"] {
                font-size: 12px !important; line-height: 1.5;
            }
            main > div:first-child h2 { font-size: clamp(28px, 3vw, 34px); line-height: 1.25; }
            main > div:first-child p { font-size: 14px; line-height: 1.5; }
            .search-input, .filter-select, select[name="sort"], select[name="order"] {
                font-size: 12px !important; min-height: 40px; line-height: 1.5;
            }
            .search-icon { font-size: 14px; }
            .search-input { padding: 9px 12px 11px 40px; }
            .filter-select { padding: 9px 12px; }
            .student-table-wrapper { overflow-x: auto; }
            .student-table { min-width: 1050px; }
            .student-table th { font-size: 12px; padding: 12px 8px; line-height: 1.5; }
            .student-table td { font-size: 14px; padding: 12px 8px; line-height: 1.5; }
            .student-table td .text-xs, .student-table td .text-sm { font-size: 14px; }
            .student-table td [class*="text-["] { font-size: 13px !important; }
            .reference-number, .status-badge { font-size: 14px; padding: 5px 8px; line-height: 1.5; }
            .student-table .w-8.h-8 { width: 36px; height: 36px; }
            .action-summary strong { font-size: 14px; }
            .action-summary small { font-size: 14px; color: #475569; }
            .action-summary > b { font-size: 24px; }
            .quick-filter { font-size: 14px; min-height: 38px; padding: 7px 11px; }
            button, a[title="Reset"], header nav a { min-height: 38px; }
            header nav { font-size: 14px; }
            header h1 { font-size: 22px; }
            header a > div:last-child { max-width: 280px; }
            label { font-size: 12px !important; }
            .text-gray-400 { color: #596273 !important; }
            .text-gray-500 { color: #4b5563 !important; }
        }
        @media screen and (max-width: 639px) {
            header a > div:last-child { max-width: 175px; }
            header h1 { font-size: 22px; }
        }


        /* Use the available screen width for a more spacious student table. */
        @media screen {
            main.max-w-7xl { max-width: 1680px; padding-left: 20px; padding-right: 20px; }
            .student-table-wrapper { width: 100%; overflow-x: auto; }
            .student-table { width: 100%; min-width: 1050px; }
        }
        @media screen and (min-width: 1536px) {
            main.max-w-7xl { padding-left: 28px; padding-right: 28px; }
        }
        @media screen and (max-width: 639px) {
            main.max-w-7xl { padding-left: 12px; padding-right: 12px; }
        }


        /* Calm hierarchy and consistent spacing for the records dashboard. */
        @media screen {
            body { background: #f5f6f8; color: #263244; }
            main.max-w-7xl { max-width: 1600px; padding-top: 28px; }
            .main-card { border-radius: 14px; border: 1px solid #dfe3e8; box-shadow: 0 3px 14px rgba(15,23,42,.035); }
            .main-card > div:first-child { gap: 16px; padding: 20px 24px; }
            .main-card h3 { font-size: 18px; font-weight: 700; }
            .main-card form label { font-weight: 600; text-transform: none; letter-spacing: 0; color: #475569 !important; }
            .main-card > .no-print > .p-4 { padding: 18px 24px; background: #fafbfc; }
            .quick-filters { gap: 8px; margin-bottom: 18px; }
            .quick-filter { border-radius: 7px; font-weight: 500; background: white; }
            .quick-filter.is-selected { background: #fff3bf; border-color: #e8ce61; color: #574300; font-weight: 600; }
            .stat-card { padding: 18px 20px; box-shadow: none; border-radius: 12px; }
            .stat-card [class*="uppercase"] { text-transform: none; letter-spacing: 0; font-weight: 600; font-size: 13px !important; }
            .stat-card .text-3xl { font-size: 28px; font-weight: 700; margin-top: 6px; }
            .compact-notice { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 18px; padding: 12px 16px; border: 1px solid #ece3bc; border-radius: 9px; background: #fffcf0; font-size: 13px; }
            .compact-notice a { text-decoration: underline; text-underline-offset: 3px; }
            .student-table { table-layout: fixed; min-width: 1200px; }
            .student-table th { background: #f0f2f5; color: #475569; font-size: 12px; font-weight: 600; text-transform: none; letter-spacing: 0; padding: 14px 14px; border-bottom: 1px solid #dfe3e8; }
            .student-table td { padding: 18px 14px; font-size: 14px; line-height: 1.6; vertical-align: top; border-bottom: 1px solid #e9edf1; overflow-wrap: break-word; }
            .student-table th:nth-child(1) { width: 4%; }
            .student-table th:nth-child(2) { width: 11%; }
            .student-table th:nth-child(3) { width: 21%; }
            .student-table th:nth-child(4) { width: 17%; }
            .student-table th:nth-child(5) { width: 11%; }
            .student-table th:nth-child(6) { width: 12%; }
            .student-table th:nth-child(7) { width: 7%; }
            .student-table th:nth-child(8) { width: 10%; }
            .student-table th:nth-child(9) { width: 7%; }
            .student-table td [class*="text-["] { font-size: 13px !important; }
            .student-table td p { margin-top: 5px; }
            .student-table td p:first-child { margin-top: 0; }
            .student-table td .uppercase { text-transform: none; }
            .student-table td .font-extrabold { font-weight: 600; }
            .student-table td .font-bold { font-weight: 500; }
            .student-table td .text-gray-500, .student-table td .text-gray-400 { color: #536174 !important; }
            .student-table tbody tr:nth-child(even) { background: #fcfcfd; }
            .student-table tbody tr:hover { background: #fffdf3; }
            .reference-number { background: transparent; border: 0; padding: 0; color: #475569; font-size: 13px; font-weight: 500; }
            .reference-number i { display: none; }
            .status-badge { font-size: 12px; font-weight: 600; border-radius: 6px; padding: 5px 8px; }
            .student-table td:last-child .flex { gap: 6px; }
            .student-table td:last-child a, .student-table td:last-child button { box-shadow: none; }
            .search-input, .filter-select { border-color: #d6dce4; background: #fff; border-radius: 8px; }
        }


        @media screen {
            .student-table td:nth-child(7) a,
            .student-table td:nth-child(7) a span {
                white-space: nowrap;
                overflow-wrap: normal;
                word-break: normal;
            }
            .student-table td:nth-child(7) a {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 8px;
                flex-shrink: 0;
            }
            .student-table td:nth-child(7) a i { flex-shrink: 0; }
            .student-table th:nth-child(7) { width: 8%; }
            .student-table th:nth-child(4) { width: 16%; }
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

        /* Saiz dan ketebalan tulisan mengikut alumni.php. */
        @media screen {
            body { font-size: 14px; line-height: 1.5; }
            .text-xs, .text-sm { font-size: 14px; line-height: 1.5; }
            main h2, main > div:first-child h2 { font-size: clamp(28px, 3vw, 34px); line-height: 1.25; font-weight: 700; }
            main h3 { font-size: 18px; font-weight: 700; }
            main label { font-size: 12px !important; font-weight: 600; }
            main input, main select { font-size: 14px !important; }
            .student-table th { font-size: 12px !important; font-weight: 600; letter-spacing: 0; }
            .student-table td { font-size: 14px; line-height: 1.6; }
            .student-table td .text-xs, .student-table td .text-sm { font-size: 14px; line-height: 1.5; }
            .student-table td [class*="text-["] { font-size: 13px !important; }
            .student-table td .font-black, .student-table td .font-extrabold { font-weight: 600; }
            .student-table td .font-bold { font-weight: 500; }
            .student-table td .uppercase { text-transform: none; }
            .student-table td .reference-number { font-size: 13px; font-weight: 500; }
            .student-table td .status-badge { font-size: 13px; font-weight: 600; }
        }

        /* Padanan khusus dengan alumni.php yang dibekalkan. */
        @media screen {
            body, input, select, button { font-family: 'Segoe UI', Arial, sans-serif !important; }
            body { font-size: 14px !important; }
            .student-table th { font-size: 12px !important; font-weight: 600 !important; }
            .student-table td { font-size: 14px !important; line-height: 1.6 !important; }
            .student-table td p, .student-table td span, .student-table td a,
            .student-table td div, .student-table td button { font-size: 13px !important; line-height: 1.5 !important; }
            .student-table td [class*="text-["] { font-size: 13px !important; }
            .student-table td p.text-xs.font-extrabold.text-gray-900 { font-size: 14px !important; font-weight: 600 !important; }
            .student-table td .font-black, .student-table td .font-extrabold { font-weight: 600 !important; }
            .student-table td .font-bold { font-weight: 500 !important; }
            .student-table td .reference-number, .student-table td .status-badge { font-size: 13px !important; }
            .student-table td i { font-size: 12px !important; }
            .search-input, .filter-select, main input, main select { font-size: 14px !important; }
        }
</style>

</head>

<body class="min-h-screen flex flex-col">

<!-- =====================================================
     TOP BAR
====================================================== -->

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

                <a href="dashboard.php" aria-current="page" class="px-4 py-2.5 rounded-lg bg-[#F4C400] text-[#111111] flex items-center gap-2"><i class="fa-solid fa-table-list"></i>Dashboard</a>

                <a href="alumni.php" class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"><i class="fa-solid fa-user-graduate"></i>Alumni</a>

                <a href="tambah.php" class="ml-2 px-4 py-2.5 rounded-lg bg-[#111111] text-white hover:bg-[#292929] transition flex items-center gap-2"><i class="fa-solid fa-plus text-[#F4C400]"></i>Permohonan Baru</a>

            <a href="logout.php" class="px-4 py-2.5 rounded-lg text-gray-700 hover:bg-gray-100 transition flex items-center gap-2"><i class="fa-solid fa-right-from-bracket text-gray-400" aria-hidden="true"></i>Log Keluar</a>
            </nav>

            <button id="dashboard-menu-toggle" type="button" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="dashboard-mobile-menu" class="lg:hidden w-10 h-10 shrink-0 rounded-lg border border-black/10 bg-white flex items-center justify-center"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>

        </div>

    </div>

    <nav id="dashboard-mobile-menu" hidden aria-label="Navigasi mudah alih" class="lg:hidden border-t border-black/10 bg-white px-5 py-3 space-y-1 text-xs font-bold">

        <a href="dashboard.php" aria-current="page" class="flex items-center gap-2 px-4 py-3 rounded-lg bg-[#F4C400] text-[#111111]"><i class="fa-solid fa-table-list"></i>Dashboard</a>

        <a href="alumni.php" class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition"><i class="fa-solid fa-user-graduate"></i>Alumni</a>

        <a href="tambah.php" class="flex items-center gap-2 px-4 py-3 rounded-lg bg-[#111111] text-white"><i class="fa-solid fa-plus text-[#F4C400]"></i>Permohonan Baru</a>

        <a href="logout.php" class="flex items-center gap-2 px-4 py-3 rounded-lg text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Log Keluar</a>

    </nav>

</header>

<!-- =====================================================
     PRINT HEADER
====================================================== -->

<!-- =====================================================
     MAIN
====================================================== -->

<main
    class="max-w-7xl mx-auto w-full px-5 sm:px-8 lg:px-10 py-8 flex-grow"
>

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

                    Laporan Pelajar

                </span>

                <p class="text-[9px] mt-2">

                    Tarikh Cetakan:

                    <b><?= date('d/m/Y h:i A') ?></b>

                </p>

            </div>

        </div>

        <div class="mt-4 pt-3 border-t border-slate-300 flex justify-between">

            <h2 class="text-sm font-black uppercase">

                Senarai Rekod Pelajar Latihan Industri

            </h2>

            <p class="text-[10px]">

                Jumlah:

                <b><?= $students_result->num_rows ?> Orang</b>

            </p>

        </div>

    </div>

    <!-- PAGE HEADER -->

    <div
        class="no-print flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 mb-5"
    >

        <div>

            <div
                class="flex items-center gap-2 mb-2"
            >

                <span
                    class="px-2.5 py-1 rounded-full bg-[#FFF7CC] text-[#8a6900] text-[9px] font-extrabold uppercase tracking-wider"
                >

                    PENGURUSAN REKOD

                </span>

            </div>

            <h2
                class="text-3xl sm:text-4xl font-black tracking-tight"
            >
                Dashboard
            </h2>

            <p
                class="text-xs sm:text-sm text-gray-500 mt-2"
            >
                Pengurusan rekod pelajar latihan industri JKR Daerah Kuala Kangsar.
            </p>

        </div>

        <!-- ACTIONS -->

        <div
            class="flex flex-wrap gap-2"
        >

            <a
                href="tambah.php"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#F4C400] border border-black text-xs font-extrabold hover:bg-yellow-300 transition"
            >

                <i
                    class="fa-solid fa-plus"
                ></i>

                Permohonan Baru

            </a>

        </div>

    </div>

    <!-- =====================================================
         FLASH MESSAGES
    ====================================================== -->

    <?php if ($success === 'saved'): ?>

        <div
            class="flash-message no-print mb-5 flex items-center gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800"
        >

            <div
                class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"
            >

                <i
                    class="fa-solid fa-check text-emerald-600 text-xs"
                ></i>

            </div>

            <div>

                <p
                    class="text-xs font-extrabold"
                >
                    Rekod berjaya disimpan.
                </p>

                <p
                    class="text-[10px] mt-0.5 text-emerald-600"
                >
                    Maklumat pelajar telah dikemaskini dalam sistem.
                </p>

            </div>

        </div>

    <?php endif; ?>

    <?php if ($success === 'deleted'): ?>

        <div
            class="flash-message no-print mb-5 flex items-center gap-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800"
        >

            <div
                class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"
            >

                <i
                    class="fa-solid fa-trash text-emerald-600 text-xs"
                ></i>

            </div>

            <div>

                <p
                    class="text-xs font-extrabold"
                >
                    Rekod berjaya dipadam.
                </p>

            </div>

        </div>

    <?php endif; ?>

    <?php if ($error === 'alumni_protected'): ?>

        <div
            class="flash-message no-print mb-5 flex items-center gap-3 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800"
        >

            <div
                class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"
            >

                <i
                    class="fa-solid fa-lock text-amber-600 text-xs"
                ></i>

            </div>

            <div>

                <p
                    class="text-xs font-extrabold"
                >
                    Rekod alumni dilindungi.
                </p>

                <p
                    class="text-[10px] mt-0.5 text-amber-700"
                >
                    Pelajar yang telah tamat latihan tidak boleh dipadam dari dashboard.
                </p>

            </div>

        </div>

    <?php endif; ?>

    <?php if ($error === 'not_found'): ?>

        <div
            class="flash-message no-print mb-5 flex items-center gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800"
        >

            <i
                class="fa-solid fa-circle-exclamation text-red-600"
            ></i>

            <p
                class="text-xs font-bold"
            >
                Rekod pelajar tidak dijumpai.
            </p>

        </div>

    <?php endif; ?>

    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div
        class="no-print grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6"
    >

        <!-- TOTAL -->

        <div
            class="stat-card overview-total p-3 sm:p-4"
        >

            <div
                class="flex items-start justify-between gap-3"
            >

                <div>

                    <p
                        class="text-[9px] uppercase tracking-wider text-gray-400 font-extrabold"
                    >
                        Jumlah Pelajar
                    </p>

                    <p
                        class="text-3xl font-black mt-2"
                    >
                        <?= number_format($total_students) ?>
                    </p>

                </div>

                <div
                    class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center"
                >

                    <i
                        class="fa-solid fa-users text-gray-600 text-xs"
                    ></i>

                </div>

            </div>

        </div>

        <!-- ACTIVE -->

        <div
            class="stat-card overview-active p-3 sm:p-4"
        >

            <div
                class="flex items-start justify-between gap-3"
            >

                <div>

                    <p
                        class="text-[9px] uppercase tracking-wider text-gray-400 font-extrabold"
                    >
                        Sedang Latihan
                    </p>

                    <p
                        class="text-3xl font-black mt-2"
                    >
                        <?= number_format($active_students) ?>
                    </p>

                </div>

                <div
                    class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center"
                >

                    <i
                        class="fa-solid fa-person-walking text-emerald-600 text-xs"
                    ></i>

                </div>

            </div>

        </div>

        <!-- PENDING -->

        <div
            class="stat-card overview-pending p-3 sm:p-4"
        >

            <div
                class="flex items-start justify-between gap-3"
            >

                <div>

                    <p
                        class="text-[9px] uppercase tracking-wider text-gray-400 font-extrabold"
                    >
                        Dalam Proses
                    </p>

                    <p
                        class="text-3xl font-black mt-2"
                    >
                        <?= number_format($pending_students) ?>
                    </p>

                </div>

                <div
                    class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center"
                >

                    <i
                        class="fa-solid fa-hourglass-half text-amber-600 text-xs"
                    ></i>

                </div>

            </div>

        </div>

        <!-- ALUMNI -->

        <div
            class="stat-card overview-alumni p-3 sm:p-4"
        >

            <div
                class="flex items-start justify-between gap-3"
            >

                <div>

                    <p
                        class="text-[9px] uppercase tracking-wider text-gray-400 font-extrabold"
                    >
                        Alumni
                    </p>

                    <p
                        class="text-3xl font-black mt-2"
                    >
                        <?= number_format($alumni_students) ?>
                    </p>

                </div>

                <div
                    class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center"
                >

                    <i
                        class="fa-solid fa-user-graduate text-blue-600 text-xs"
                    ></i>

                </div>

            </div>

        </div>

    </div>

    <?php if ($pending_students > 0 || $ending_soon > 0 || $overdue_training > 0): ?>
    <div class="no-print compact-notice mb-5" role="status">
        <i class="fa-regular fa-bell" aria-hidden="true"></i>
        <strong>Perlu semakan:</strong>
        <?php if ($pending_students > 0): ?>
        <a href="dashboard.php?status=<?= rawurlencode('Dalam Proses Permohonan') ?>"><?= $pending_students ?> permohonan dalam proses</a>
        <?php endif; ?>
        <?php if ($ending_soon > 0): ?>
        <span><?= $ending_soon ?> latihan tamat dalam 7 hari</span>
        <?php endif; ?>
        <?php if ($overdue_training > 0): ?>
        <a href="dashboard.php?status=<?= rawurlencode('Aktif (Sedang Latihan)') ?>"><?= $overdue_training ?> status tamat perlu disemak</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- =====================================================
         SEARCH / FILTER
    ====================================================== -->

    <!-- =====================================================
         STUDENT TABLE
    ====================================================== -->

    <div
        class="main-card"
    >

        <div
            class="no-print px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
        >

            <div>

                <h3
                    class="text-sm font-extrabold"
                >
                    Rekod Pelajar
                </h3>

                <p
                    class="text-[10px] text-gray-400 mt-1"
                >

                    Senarai pelajar berdasarkan carian dan penapis semasa.

                </p>

            </div>

            <div
                class="text-[10px] font-bold text-gray-400"
            >

                <?= $students_result->num_rows ?>
                rekod dijumpai

            </div>

            <div class="flex flex-wrap items-center gap-2">
            <a
                href="dashboard.php?export=xlsx"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-gray-200 text-xs font-bold hover:bg-gray-50 transition"
            >

                <i
                    class="fa-solid fa-file-excel text-emerald-600"
                ></i>

                Export Excel

            </a>

            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-gray-200 text-xs font-bold hover:bg-gray-50 transition"
            >

                <i
                    class="fa-solid fa-print text-gray-500"
                ></i>

                Cetak

            </button>

            </div>
        </div>

    <div
        class="no-print border-b border-gray-100"
    >

        <div
            class="p-4 sm:p-5"
        >

            <div class="quick-filters" aria-label="Penapis cepat status">
            <?php foreach (['' => 'Semua', 'Aktif (Sedang Latihan)' => 'Sedang Latihan', 'Dalam Proses Permohonan' => 'Dalam Proses', 'Tamat Latihan' => 'Tamat', 'Ditolak / Batal' => 'Ditolak / Batal'] as $quick_value => $quick_label):
                $quick_params = $_GET;
                unset($quick_params['export'], $quick_params['delete'], $quick_params['page']);
                $quick_params['status'] = $quick_value;
            ?>
                <a class="quick-filter <?= $filter_status === $quick_value ? 'is-selected' : '' ?>" href="dashboard.php?<?= htmlspecialchars(http_build_query($quick_params), ENT_QUOTES, 'UTF-8') ?>" <?= $filter_status === $quick_value ? 'aria-current="true"' : '' ?>><?= $quick_label ?></a>
            <?php endforeach; ?>
            </div>
            <form
                method="GET"
                action="dashboard.php"
            >

                <div
                    class="grid grid-cols-1 lg:grid-cols-12 gap-3"
                >

                    <!-- SEARCH -->

                    <div
                        class="lg:col-span-6"
                    >

                        <label
                            for="student-search"
                            class="block text-[9px] uppercase tracking-wider font-extrabold text-gray-400 mb-2"
                        >
                            Carian
                        </label>

                        <div
                            class="search-wrapper"
                        >

                            <i
                                class="fa-solid fa-magnifying-glass search-icon"
                            ></i>

                            <input
                                type="text"
                                id="student-search"
                                name="search"
                                value="<?= htmlspecialchars($search) ?>"
                                class="search-input"
                                placeholder="Cari nama, no. rujukan, IC, matrik, IPT atau kursus..."
                            >

                        </div>

                    </div>

                    <!-- BAHAGIAN -->

                    <div
                        class="lg:col-span-4"
                    >

                        <label
                            class="block text-[9px] uppercase tracking-wider font-extrabold text-gray-400 mb-2"
                        >
                            Bahagian
                        </label>

                        <select
                            name="bahagian"
                            class="filter-select"
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

                            foreach (
                                $bahagian_list
                                as $b
                            ):

                            ?>

                                <option
                                    value="<?= htmlspecialchars($b) ?>"
                                    <?= $filter_bahagian === $b
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= htmlspecialchars($b) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($order, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- BUTTONS -->

                    <div
                        class="lg:col-span-2 flex items-end gap-2"
                    >

                        <button
                            type="submit"
                            class="flex-1 px-4 py-[11px] rounded-lg bg-[#111111] text-white text-[10px] font-extrabold hover:bg-black transition"
                        >

                            <i
                                class="fa-solid fa-filter mr-1 text-[#F4C400]"
                            ></i>

                            Tapis

                        </button>

                        <a
                            href="dashboard.php"
                            class="px-3 py-[11px] rounded-lg border border-gray-200 bg-gray-50 text-gray-500 hover:bg-gray-100 transition"
                            title="Reset"
                        >

                            <i
                                class="fa-solid fa-rotate-left text-[10px]"
                            ></i>

                        </a>

                    </div>

                </div>

            </form>

        </div>
        
        </div>

        <!-- TABLE WRAPPER -->

        <div
            class="student-table-wrapper"
        >

            <table
                class="student-table w-full"
            >

                <thead>

                    <tr>

                        <!-- NO -->

                        <th
                            class="text-center"
                            
                        >
                            No.
                        </th>

                        <th>No. Rujukan</th>

                        <!-- STUDENT -->

                        <th>
                            Maklumat Pelajar
                        </th>

                        <!-- IPT -->

                        <th>
                            IPT & Kursus
                        </th>

                        <!-- BAHAGIAN -->

                        <th>
                            Bahagian JKR
                        </th>

                        <!-- TEMPOH -->

                        <th>
                            Tempoh Latihan
                        </th>

                        <!-- DOCUMENT -->

                        <th>
                            Dokumen
                        </th>

                        <!-- STATUS -->

                        <th>
                            Status / Catatan
                        </th>

                        <!-- ACTION -->

                        <th
                            class="no-print text-center"
                        >
                            Tindakan
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php

                    $counter = 1;

                    ?>

                    <?php if ($students_result->num_rows > 0): ?>

                        <?php while ($row = $students_result->fetch_assoc()): ?>

                            <tr>

                                <!-- NO -->

                                <td
                                    class="text-center text-[10px] font-bold text-gray-400"
                                >

                                    <?= $counter++ ?>

                                </td>

                                <td class="reference-cell">
                                        <!-- NO RUJUKAN -->

                                        <div
                                            class="mb-2"
                                        >

                                            <?php if (!empty($row['no_rujukan'])): ?>

                                                <span
                                                    class="reference-number"
                                                >

                                                    <i
                                                        class="fa-solid fa-hashtag text-[8px]"
                                                    ></i>

                                                    <?= htmlspecialchars(
                                                        $row['no_rujukan']
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 text-gray-400 text-[8px] font-bold"
                                                >

                                                    <i
                                                        class="fa-solid fa-triangle-exclamation"
                                                    ></i>

                                                    Belum Dijana

                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <div class="mt-2 text-[9px] text-gray-500" style="overflow-wrap: anywhere;">
                                        <span class="font-bold">No. Rujukan Fail:</span>
                                        <span class="block"><?= htmlspecialchars(trim($row['no_rujukan_fail'] ?? '') !== '' ? $row['no_rujukan_fail'] : '—', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>

                                </td>

                                <!-- =================================================
                                     MAKLUMAT PELAJAR
                                ================================================== -->

                                <td>

                                    <div
                                        class="min-w-0"
                                    >

                                        <!-- NAME -->

                                        <p
                                            class="text-xs font-extrabold text-gray-900 uppercase"
                                        >

                                            <?= htmlspecialchars(
                                                $row['nama'] ?? ''
                                            ) ?>

                                        </p>

                                        <p class="text-[9px] text-gray-500 mt-1">
                                            <span class="font-bold text-gray-400">Jantina:</span>
                                            <?= htmlspecialchars(trim($row['jantina'] ?? '') !== '' ? $row['jantina'] : '—', ENT_QUOTES, 'UTF-8') ?>
                                        </p>

                                        <!-- IC -->

                                        <p
                                            class="text-[9px] text-gray-500 mt-1"
                                        >

                                            <span
                                                class="font-bold text-gray-400"
                                            >
                                                IC:
                                            </span>

                                            <?= htmlspecialchars(
                                                $row['ic'] ?? '-'
                                            ) ?>

                                        </p>

                                        <!-- MATRIC -->

                                        <p
                                            class="text-[9px] text-gray-500 mt-0.5"
                                        >

                                            <span
                                                class="font-bold text-gray-400"
                                            >
                                                Matrik:
                                            </span>

                                            <?= htmlspecialchars(
                                                $row['matric'] ?? '-'
                                            ) ?>

                                        </p>

                                        <!-- PHONE -->

                                        <p
                                            class="text-[9px] text-gray-500 mt-0.5"
                                        >

                                            <span
                                                class="font-bold text-gray-400"
                                            >
                                                Tel:
                                            </span>

                                            <?= htmlspecialchars(
                                                $row['telefon'] ?? '-'
                                            ) ?>

                                        </p>

                                    </div>

                                </td>

                                <!-- =================================================
                                     IPT & KURSUS
                                ================================================== -->

                                <td>

                                    <div
                                        class="min-w-0"
                                    >

                                        <p
                                            class="text-[10px] font-extrabold text-gray-800"
                                        >

                                            <?= htmlspecialchars(
                                                $row['ipt'] ?? '-'
                                            ) ?>

                                        </p>

                                        <p
                                            class="text-[9px] text-gray-500 mt-1 leading-relaxed"
                                        >

                                            <?= htmlspecialchars(
                                                $row['kursus'] ?? '-'
                                            ) ?>

                                        </p>

                                    </div>

                                </td>

                                <!-- =================================================
                                     BAHAGIAN
                                ================================================== -->

                                <td>

                                    <span
                                        class="inline-flex items-center gap-1.5 px-2 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-[8px] font-bold"
                                    >

                                        <i
                                            class="fa-solid fa-sitemap text-gray-400"
                                        ></i>

                                        <?= htmlspecialchars(
                                            $row['bahagian'] ?? '-'
                                        ) ?>

                                    </span>

                                </td>

                                <!-- =================================================
                                     TEMPOH LATIHAN
                                ================================================== -->

                                <td>

                                    <div
                                        class="min-w-0"
                                    >

                                        <div
                                            class="flex items-center gap-2"
                                        >

                                            <i
                                                class="fa-regular fa-calendar text-gray-400 text-[10px]"
                                            ></i>

                                            <span
                                                class="text-[9px] font-bold text-gray-700"
                                            >

                                                <?= !empty($row['tarikh_mula'])
                                                    ? date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $row['tarikh_mula']
                                                        )
                                                    )
                                                    : '-'
                                                ?>

                                            </span>

                                        </div>

                                        <div
                                            class="flex items-center gap-2 mt-1"
                                        >

                                            <i
                                                class="fa-solid fa-arrow-right text-gray-300 text-[8px]"
                                            ></i>

                                            <span
                                                class="text-[9px] font-bold text-gray-700"
                                            >

                                                <?= !empty($row['tarikh_tamat'])
                                                    ? date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $row['tarikh_tamat']
                                                        )
                                                    )
                                                    : '-'
                                                ?>

                                            </span>

                                        </div>

                                    </div>

                                </td>

                                <!-- =================================================
                                     DOCUMENT
                                ================================================== -->

                                <td>

                                    
<?php $document_info = eintern_document_checklist($row); ?>
<span class="inline-flex px-2 py-1 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 text-xs font-semibold">
    <?= $document_info['count'] ?>/6 diterima
</span>


                                </td>

                                <!-- =================================================
                                     STATUS
                                ================================================== -->

                                <td>

                                    <div
                                        class="min-w-0"
                                    >

                                        <?php

                                        $status =
                                            $row['status_tindakan']
                                            ?? '';

                                        ?>

                                        <?php if ($status === 'Aktif (Sedang Latihan)'): ?>

                                            <span
                                                class="status-badge bg-emerald-50 text-emerald-700 border border-emerald-100"
                                            >

                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-emerald-500"
                                                ></span>

                                                Aktif

                                            </span>

                                        <?php elseif ($status === 'Dalam Proses Permohonan'): ?>

                                            <span
                                                class="status-badge bg-amber-50 text-amber-700 border border-amber-100"
                                            >

                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-amber-500"
                                                ></span>

                                                Dalam Proses

                                            </span>

                                        <?php elseif ($status === 'Tamat Latihan'): ?>

                                            <span
                                                class="status-badge bg-blue-50 text-blue-700 border border-blue-100"
                                            >

                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-blue-500"
                                                ></span>

                                                Tamat Latihan

                                            </span>

                                        <?php elseif ($status === 'Ditolak / Batal'): ?>

                                            <span
                                                class="status-badge bg-red-50 text-red-700 border border-red-100"
                                            >

                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-red-500"
                                                ></span>

                                                Ditolak / Batal

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="status-badge bg-gray-100 text-gray-500"
                                            >
                                                <?= htmlspecialchars(
                                                    $status ?: 'Tiada Status'
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                        <?php if (!empty($row['catatan'])): ?>

                                            <p
                                                class="text-[8px] text-gray-400 mt-2 leading-relaxed"
                                            >

                                                <i
                                                    class="fa-regular fa-note-sticky mr-1"
                                                ></i>

                                                <?= htmlspecialchars(
                                                    $row['catatan']
                                                ) ?>

                                            </p>

                                        <?php endif; ?>

                                    </div>

                                </td>

                                <!-- =================================================
                                     ACTION
                                ================================================== -->

                                <td
                                    class="no-print"
                                >

                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >

                                        <!-- EDIT -->

                                        <a
                                            href="tambah.php?id=<?= (int) $row['id'] ?>"
                                            class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-gray-500 hover:bg-gray-100 hover:text-black transition"
                                            title="Kemaskini"
                                        >

                                            <i
                                                class="fa-solid fa-pen text-[10px]"
                                            ></i>

                                        </a>

                                        <!-- ALUMNI -->

                                        <?php if ($status === 'Tamat Latihan'): ?>

                                            <a
                                                href="alumni.php"
                                                class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 hover:bg-blue-100 transition"
                                                title="Rekod Alumni"
                                            >

                                                <i
                                                    class="fa-solid fa-user-graduate text-[10px]"
                                                ></i>

                                            </a>

                                            <!-- LOCK -->

                                            <span
                                                class="w-8 h-8 rounded-lg bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-300"
                                                title="Rekod alumni dilindungi"
                                            >

                                                <i
                                                    class="fa-solid fa-lock text-[10px]"
                                                ></i>

                                            </span>

                                        <?php else: ?>

                                            <!-- DELETE -->

                                            <button
                                                type="button"
                                                onclick="confirmDelete(<?= (int) $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama'] ?? 'Pelajar')) ?>')"
                                                class="w-8 h-8 rounded-lg bg-red-50 border border-red-100 flex items-center justify-center text-red-500 hover:bg-red-100 transition"
                                                title="Padam"
                                            >

                                                <i
                                                    class="fa-solid fa-trash text-[10px]"
                                                ></i>

                                            </button>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <!-- EMPTY -->

                        <tr>

                            <td
                                colspan="9"
                                class="py-16 text-center"
                            >

                                <div
                                    class="flex flex-col items-center justify-center"
                                >

                                    <div
                                        class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4"
                                    >

                                        <i
                                            class="fa-solid fa-folder-open text-gray-300 text-xl"
                                        ></i>

                                    </div>

                                    <p
                                        class="text-sm font-extrabold text-gray-700"
                                    >
                                        Tiada rekod dijumpai
                                    </p>

                                    <p
                                        class="text-[10px] text-gray-400 mt-1"
                                    >
                                        Cuba ubah carian atau penapis anda.
                                    </p>

                                    <a
                                        href="tambah.php"
                                        class="no-print mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#F4C400] border border-black text-[10px] font-extrabold hover:bg-yellow-300 transition"
                                    >

                                        <i
                                            class="fa-solid fa-plus"
                                        ></i>

                                        Daftar Pelajar

                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- TABLE FOOTER -->

        <div
            class="no-print px-5 py-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
        >

            <p
                class="text-[9px] text-gray-400"
            >

                <i
                    class="fa-solid fa-circle-info mr-1"
                ></i>

                No. Rujukan dijana secara automatik semasa pendaftaran pelajar.

            </p>

            <p
                class="text-[9px] font-bold text-gray-400"
            >

                Jumlah:
                <?= number_format($students_result->num_rows) ?>
                rekod

            </p>

        </div>

    </div>

</main>

<!-- =====================================================
     PRINT FOOTER
====================================================== -->

    <div class="hidden print-footer">

        <div>

            <p class="text-[9px] text-slate-500 font-mono">

                Dokumen ini dijana secara automatik menerusi Portal e-Intern JKR Kuala Kangsar.

            </p>

            <p class="text-[9px] text-slate-500 font-mono">

                Cetak Semula: ID-PELAJAR-<?= date('Ymd-His') ?>

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

<!-- =====================================================
     DELETE MODAL
====================================================== -->

<div
    id="deleteModal"
    class="no-print fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] hidden items-center justify-center px-4"
>

    <div
        class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden"
    >

        <div
            class="p-5 border-b border-gray-100"
        >

            <div
                class="w-10 h-10 rounded-xl bg-red-50 text-red-500 flex items-center justify-center mb-4"
            >

                <i
                    class="fa-solid fa-trash"
                ></i>

            </div>

            <h3
                class="text-sm font-extrabold"
            >
                Padam Rekod Pelajar?
            </h3>

            <p
                class="text-[10px] text-gray-500 mt-2 leading-relaxed"
            >

                Anda pasti mahu memadam rekod:

                <strong
                    id="deleteStudentName"
                    class="text-gray-800"
                >
                </strong>

                ?

                <br>

                Tindakan ini tidak boleh dibatalkan.

            </p>

        </div>

        <div
            class="p-4 flex gap-2 justify-end"
        >

            <button
                type="button"
                onclick="closeDeleteModal()"
                class="px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-[10px] font-bold text-gray-600 hover:bg-gray-100 transition"
            >
                Batal
            </button>

            <a
                id="confirmDeleteButton"
                href="#"
                class="px-4 py-2.5 rounded-lg bg-red-500 text-white text-[10px] font-extrabold hover:bg-red-600 transition"
            >

                <i
                    class="fa-solid fa-trash mr-1"
                ></i>

                Ya, Padam

            </a>

        </div>

    </div>

</div>

<!-- =====================================================
     FOOTER
====================================================== -->

<footer
    class="no-print bg-[#111111] text-white mt-8"
>

    <div
        class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10 py-6"
    >

        <div
            class="flex flex-col sm:flex-row items-center justify-between gap-3"
        >

            <div
                class="flex items-center gap-2"
            >

                <div
                    class="w-8 h-8 rounded-lg bg-[#F4C400] text-black flex items-center justify-center"
                >

                    <i
                        class="fa-solid fa-road text-xs"
                    ></i>

                </div>

                <div>

                    <p
                        class="text-xs font-bold"
                    >
                        e-Intern
                    </p>

                    <p
                        class="text-[10px] text-gray-400"
                    >
                        Jabatan Kerja Raya Daerah Kuala Kangsar
                    </p>

                </div>

            </div>

            <p
                class="text-[10px] text-gray-500 text-center"
            >

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

// =====================================================
// LIVE CLOCK
// =====================================================

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

// =====================================================
// MOBILE MENU
// =====================================================

(function () {
 const button=document.getElementById('dashboard-menu-toggle');
 const menu=document.getElementById('dashboard-mobile-menu');
 function setOpen(open){
  menu.hidden=!open;
  button.setAttribute('aria-expanded',String(open));
  button.setAttribute('aria-label',open?'Tutup menu navigasi':'Buka menu navigasi');
  button.querySelector('i').className=open?'fa-solid fa-xmark':'fa-solid fa-bars';
 }
 button.addEventListener('click',function(){setOpen(menu.hidden);});
 document.addEventListener('keydown',function(event){if(event.key==='Escape')setOpen(false);});
 window.addEventListener('resize',function(){if(window.innerWidth>=1024)setOpen(false);});
})();

// =====================================================
// DELETE MODAL
// =====================================================

function confirmDelete(
    id,
    name
) {

    const modal =
        document.getElementById(
            'deleteModal'
        );

    const studentName =
        document.getElementById(
            'deleteStudentName'
        );

    const confirmButton =
        document.getElementById(
            'confirmDeleteButton'
        );

    studentName.textContent =
        name;

    confirmButton.href =
        'dashboard.php?delete=' +
        encodeURIComponent(id);

    modal.classList.remove(
        'hidden'
    );

    modal.classList.add(
        'flex'
    );

}

function closeDeleteModal() {

    const modal =
        document.getElementById(
            'deleteModal'
        );

    modal.classList.add(
        'hidden'
    );

    modal.classList.remove(
        'flex'
    );

}

// Close modal when clicking outside

document
    .getElementById('deleteModal')
    .addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                closeDeleteModal();

            }

        }
    );

// =====================================================
// AUTO HIDE FLASH MESSAGE
// =====================================================

setTimeout(
    function() {

        const messages =
            document.querySelectorAll(
                '.flash-message'
            );

        messages.forEach(
            function(message) {

                message.style.transition =
                    'opacity .4s ease';

                message.style.opacity =
                    '0';

                setTimeout(
                    function() {

                        message.remove();

                    },
                    400
                );

            }
        );

    },
    5000
);

</script>

</body>

</html>