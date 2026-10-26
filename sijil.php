<?php

require_once __DIR__ . '/sijil_helpers.php';

$settings = cert_settings($conn);

$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);

if (!$id) { http_response_code(400); exit('ID pelajar tidak sah.'); }

function findIssuedCertificate($conn, $id) {

    try { $stmt = $conn->prepare('SELECT * FROM internship_certificates WHERE student_id = ?'); }

    catch (Throwable $e) { cert_setup_error(); }

    if (!$stmt) { cert_setup_error(); }

    $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

    return $row;

}

function findCertificateStudent($conn, $id, $lock = false) {

    $stmt = $conn->prepare('SELECT * FROM students WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));

    $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

    return $row;

}

function certificateEligibility($student, $settings) {

    if (!$student || ($student['status_tindakan'] ?? '') !== 'Tamat Latihan') { return 'Sijil hanya boleh dikeluarkan untuk pelajar berstatus Tamat Latihan.'; }

    $start = $student['tarikh_mula'] ?? ''; $end = $student['tarikh_tamat'] ?? '';

    if (cert_date($start) === '-' || cert_date($end) === '-' || $start > $end || $end > date('Y-m-d')) { return 'Semak tarikh latihan. Tarikh mesti sah dan latihan mesti sudah tamat sebelum sijil dikeluarkan.'; }

    if (trim($student['nama'] ?? '') === '') { return 'Nama pelajar belum diisi.'; }

    if (trim($settings['signer_name']) === '' || trim($settings['activity_name']) === '' || trim($settings['placement_name']) === '' || trim($settings['signer_title']) === '' || trim($settings['signer_department']) === '') { return 'Lengkapkan maklumat penandatangan dan kandungan dalam Tetapan Sijil dahulu.'; }

    return '';

}

$issued = findIssuedCertificate($conn, $id);

$student = findCertificateStudent($conn, $id);

if (!$student && !$issued) { http_response_code(404); exit('Rekod pelajar tidak dijumpai.'); }

function cert_edit_period($payload) {
    $start = $payload['tarikh_mula'] ?? '';
    $end = $payload['tarikh_tamat'] ?? '';
    if (cert_date($start) !== '-' && cert_date($end) !== '-') { return ['tarikh_mula'=>$start, 'tarikh_tamat'=>$end]; }
    $months = ['januari'=>1,'februari'=>2,'mac'=>3,'april'=>4,'mei'=>5,'jun'=>6,'julai'=>7,'ogos'=>8,'september'=>9,'oktober'=>10,'november'=>11,'disember'=>12];
    $range = $payload['date_range'] ?? '';
    $pattern = '/^(\d{1,2})\s+([a-z]+)\s+(\d{4})\s+hingga\s+(\d{1,2})\s+([a-z]+)\s+(\d{4})$/i';
    if (preg_match($pattern, trim($range), $m)) {
        $firstMonth = $months[strtolower($m[2])] ?? 0;
        $lastMonth = $months[strtolower($m[5])] ?? 0;
        $start = sprintf('%04d-%02d-%02d', (int) $m[3], $firstMonth, (int) $m[1]);
        $end = sprintf('%04d-%02d-%02d', (int) $m[6], $lastMonth, (int) $m[4]);
        if (cert_date($start) !== '-' && cert_date($end) !== '-') { return ['tarikh_mula'=>$start, 'tarikh_tamat'=>$end]; }
    }
    return ['tarikh_mula'=>'', 'tarikh_tamat'=>''];
}
function certificateEditFields($payload) {

    $fields = ['nama'=>['Nama pelajar',180,true], 'ipt'=>['Institusi pengajian',250,true], 'opening'=>['Ayat pengesahan',160,true]];

    if (($payload['layout_version'] ?? 1) >= 2) {

        $fields += [

            'from_label'=>['Label institusi',30,true], 'activity_label'=>['Label jenis latihan',50,true],

            'activity_name'=>['Jenis latihan',100,true], 'placement_label'=>['Label tempat latihan',30,true],

            'placement_name'=>['Tempat latihan',140,true], 'date_label'=>['Label tarikh',30,true],

            'tarikh_mula'=>['Tarikh Mula',10,true], 'tarikh_tamat'=>['Tarikh Tamat',10,true]

        ];

    } else {

        $fields += ['ic'=>['No. kad pengenalan',30,false], 'body'=>['Teks penghargaan',650,true], 'closing'=>['Ayat penutup',160,false]];

    }

    $fields += ['signer_name'=>['Nama penuh penandatangan',120,true], 'signer_title'=>['Jawatan',100,true], 'signer_department'=>['Jabatan penandatangan',140,true]];

    return $fields;

}

$editMode = $issued && (($_GET['edit'] ?? '') === '1');

$editValues = null;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_certificate') {

    cert_check_token();

    $editMode = true;

    if (!$issued) { http_response_code(409); exit('Sijil belum dikeluarkan. Keluarkan sijil dahulu sebelum mengedit salinan yang disimpan.'); }

    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare('SELECT payload_json FROM internship_certificates WHERE student_id = ? FOR UPDATE');

        $stmt->bind_param('i', $id); $stmt->execute(); $current = $stmt->get_result()->fetch_assoc(); $stmt->close();

        $expected = $_POST['edit_hash'] ?? '';

        if (!$current || !is_string($expected) || !hash_equals(hash('sha256', $current['payload_json']), $expected)) {

            $error = 'Sijil telah diubah oleh pengguna lain. Muat semula halaman dan semak sijil terkini.';

        } else {

            $previous = json_decode($current['payload_json'], true, 512, JSON_THROW_ON_ERROR);

            $originalVersion = $previous;

            $previous = cert_reference_payload($previous);
            $previous = array_merge($previous, cert_edit_period($previous));

            $editValues = $previous;

            foreach (certificateEditFields($previous) as $key => $definition) {

                $value = $_POST[$key] ?? '';

                if (!is_string($value)) { $error = 'Nilai borang tidak sah.'; break; }

                $value = trim($value);

                $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

                if ($length > $definition[1] || ($definition[2] && $value === '')) { $error = 'Semak medan ' . $definition[0] . '. Nilai kosong atau terlalu panjang.'; break; }

                $editValues[$key] = $value;

            }

            if ($error === '' && ($editValues['layout_version'] ?? 1) >= 2) {
                $start = $editValues['tarikh_mula']; $end = $editValues['tarikh_tamat'];
                if (cert_date($start) === '-' || cert_date($end) === '-') {
                    $error = 'Sila pilih tarikh mula dan tarikh tamat yang sah.';
                } else {
                    $editValues['date_range'] = cert_date($start) . ' hingga ' . cert_date($end);
                    if ($start > $end) { $error = 'Tarikh tamat mesti sama atau selepas tarikh mula.'; }
                    elseif ($end > date('Y-m-d')) { $error = 'Latihan mesti sudah tamat sebelum sijil disimpan.'; }
                }
            }
            if ($error === '') {

                $history = $previous['_revision_history'] ?? [];

                unset($originalVersion['_revision_history']);

                $history[] = ['changed_at'=>date('Y-m-d H:i:s'), 'changed_by'=>(int) $_SESSION['user_id'], 'previous_payload'=>$originalVersion];

                $editValues['_revision_history'] = $history;

                $json = json_encode($editValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                $stmt = $conn->prepare('UPDATE internship_certificates SET payload_json = ? WHERE student_id = ?');

                $stmt->bind_param('si', $json, $id); $stmt->execute(); $stmt->close();

                $conn->commit(); header('Location: sijil.php?id=' . $id . '&saved=1'); exit;

            }

        }

        $conn->rollback();

    } catch (Throwable $e) {

        $conn->rollback(); $error = 'Perubahan sijil tidak dapat disimpan. Sila cuba lagi.';

    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    cert_check_token();

    if (!$issued) {

        $conn->begin_transaction();

        try {

            // Recheck status and dates inside the transaction; never trust the URL or preview.

            $student = findCertificateStudent($conn, $id, true);

            $issued = findIssuedCertificate($conn, $id);

            $settings = cert_settings($conn);

            if (!$issued) {

                $error = certificateEligibility($student, $settings);

                $payload = cert_payload($settings, $student ?: [], date('Y-m-d'));

                $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                $reviewHash = $_POST['review_hash'] ?? '';

                if ($error === '' && (!is_string($reviewHash) || !hash_equals(hash('sha256', $json), $reviewHash))) { $error = 'Maklumat pelajar atau templat telah berubah. Semak pratonton terkini sebelum mengeluarkan sijil.'; }

                if ($error === '') {

                    $userId = (int) $_SESSION['user_id'];

                    $stmt = $conn->prepare('INSERT INTO internship_certificates (student_id, payload_json, issued_by) VALUES (?, ?, ?)');

                    $stmt->bind_param('isi', $id, $json, $userId); $stmt->execute(); $stmt->close();

                }

            }

            if ($error !== '') { $conn->rollback(); } else { $conn->commit(); header('Location: sijil.php?id=' . $id); exit; }

        } catch (Throwable $e) {

            $conn->rollback();

            $issued = findIssuedCertificate($conn, $id);

            $error = $issued ? '' : 'Sijil tidak dapat disimpan. Sila cuba lagi.';

        }

    } else { header('Location: sijil.php?id=' . $id); exit; }

}

$payload = $issued ? json_decode($issued['payload_json'], true) : cert_payload($settings, $student, date('Y-m-d'));

if (!is_array($payload)) { http_response_code(500); exit('Rekod sijil tidak dapat dibaca.'); }

$payload = cert_reference_payload($payload);
$payload = array_merge($payload, cert_edit_period($payload));

if ($editMode && is_array($editValues)) { $payload = $editValues; }

$printReady = trim($payload['date_range'] ?? '') !== '';

$eligibility = $issued ? ($printReady ? '' : 'Tempoh latihan sijil lama tidak dapat dikenal pasti. Isi tempoh latihan melalui Edit Sijil sebelum mencetak.') : certificateEligibility($student, $settings);

$reviewHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

cert_header($editMode ? 'Edit Sijil' : ($issued ? 'Sijil Dikeluarkan' : 'Pratonton Sijil'));
?>
<style>
@media screen {
 body{background:#f5f5f1;color:#17202b}
 body>header{padding:16px max(24px,calc((100vw - 1400px)/2));border-bottom:3px solid #f4c400;box-shadow:0 1px 8px #00000005}
 body>header strong{font-size:14px;letter-spacing:-.2px}body>header a{font-size:12px;text-decoration:none;color:#505966}
 body>header a:hover{color:#111;text-decoration:underline}
 main{max-width:1400px;padding:0 28px;margin:26px auto 40px}
 .workspace-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:22px}
 .breadcrumb{font-size:11px;color:#777e87;margin:0 0 12px}.breadcrumb a{color:#777e87;text-decoration:none}
 .eyebrow{display:flex;align-items:center;gap:7px;font-size:10px;letter-spacing:1.4px;text-transform:uppercase;color:#8b6900;font-weight:700;margin-bottom:7px}.eyebrow:before{content:'';width:6px;height:6px;background:#f4c400;border-radius:50%}
 .workspace-hero h1{font-size:27px;line-height:1.2;letter-spacing:-.8px;margin:0 0 8px}.workspace-hero p{font-size:12px;color:#66707c;margin:0;max-width:680px}
 .status-pill{white-space:nowrap;display:inline-flex;align-items:center;gap:7px;border:1px solid #dfcf91;background:#fff8de;color:#806200;padding:8px 11px;border-radius:999px;font-size:11px;font-weight:700}.status-pill:before{content:'';width:6px;height:6px;border-radius:50%;background:#c49d15}
 .status-pill.issued{border-color:#cce5d9;background:#eef8f2;color:#286748}.status-pill.issued:before{background:#398761}
 .workspace-grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:22px;align-items:start}
 .workspace-side{min-width:0}.workspace-card{background:#fff;border:1px solid #e2e5e5;border-radius:14px;padding:20px;margin-bottom:15px;box-shadow:0 2px 5px #00000002}
 .workspace-card h2{font-size:13px;letter-spacing:-.15px;margin:0 0 16px}.student-mark{width:39px;height:39px;display:grid;place-items:center;border:1px solid #e3cb7c;background:#fff7d6;border-radius:10px;font-weight:bold;font-size:13px;color:#7f6200;margin-bottom:12px}
 .student-name{font-size:14px;font-weight:700;line-height:1.5;margin:0 0 5px;overflow-wrap:anywhere}.student-ipt{font-size:12px;color:#78808a;line-height:1.5;margin:0 0 16px}
 .detail-list{margin:0;border-top:1px solid #f0f1f2;padding-top:12px}.detail-list div{margin:0 0 12px}.detail-list dt{font-size:10px;letter-spacing:.6px;text-transform:uppercase;color:#7b828c;font-weight:700;margin:0 0 4px}.detail-list dd{font-size:12px;color:#34404e;line-height:1.5;margin:0;overflow-wrap:anywhere}
 .action-stack{display:flex;flex-direction:column;gap:9px}.action-stack .button,.action-stack button{width:100%;text-align:center;padding:11px 12px;font-size:12px;border-radius:9px;font-weight:600;text-decoration:none;line-height:1.5}.action-stack .primary{background:#f4c400;border-color:#e6b900;color:#171717}.action-stack .primary:hover{background:#e9ba00}.action-stack .button:hover{background:#f7f8f9}.button.subtle{background:#f7f8f9;border-color:#e6e8ea;color:#59616d}
 .action-note{font-size:11px;line-height:1.65;color:#727b87;margin:13px 0 0}.helper-card{background:#fffbea;border:1px solid #ede2b3;border-radius:12px;padding:16px;font-size:11px;line-height:1.7;color:#756237}.helper-card strong{display:block;margin-bottom:4px;color:#54421a;font-size:12px}
 .workspace-alert{margin:0 0 16px;font-size:12px;line-height:1.6;border-radius:10px}.workspace-alert.success{background:#eff8f2;border-color:#c9e6d3;color:#286748}
 .editor-form{padding:0;overflow:hidden}.editor-heading{padding:18px 20px 14px;border-bottom:1px solid #eef0f1}.editor-heading h2{margin:0 0 5px}.editor-heading p{font-size:11px;color:#727b87;line-height:1.6;margin:0}
 .editor-section{border:0;border-bottom:1px solid #eef0f1;margin:0;padding:16px 20px}.editor-section legend{float:left;width:100%;font-size:10px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#8b6900;margin:0 0 10px}.editor-section legend+*{clear:both}.editor-section label{font-size:11px;font-weight:600;color:#525e6c;margin:11px 0 5px}.editor-section input,.editor-section textarea{font-size:12px;color:#283342;background:#fafbfb;padding:9px 10px;border:1px solid #dfe3e6;border-radius:8px;line-height:1.6}.editor-section input:focus,.editor-section textarea:focus{outline:2px solid #f4c40066;border-color:#cda600;background:#fff}.editor-actions{padding:17px 20px}.editor-actions .action-note{margin-top:10px}
 .preview-panel{min-width:0;background:#fff;border:1px solid #e0e3e4;border-radius:15px;overflow:hidden;box-shadow:0 5px 24px #00000004}.preview-heading{padding:15px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid #e9ebed}.preview-heading h2{font-size:12px;margin:0}.preview-heading span{font-size:10px;color:#747e89;border:1px solid #e4e7e9;border-radius:6px;padding:4px 8px;background:#fafbfc}.preview-body{background:#eceeed;padding:24px;overflow:auto}
 .preview-body .certificate-viewport{padding:0;overflow:visible;width:var(--preview-width,794px);height:var(--preview-height,1123px);margin:0 auto}
 .preview-body .certificate{margin:0;transform:scale(var(--preview-scale,1));transform-origin:top left;box-shadow:0 3px 16px #00000015}
 .preview-caption{padding:12px 20px;font-size:10px;line-height:1.6;color:#7a838e;border-top:1px solid #e9ebed}
 @media(max-width:1000px){.workspace-grid{grid-template-columns:290px minmax(0,1fr)}main{padding:0 18px}.preview-body{padding:16px}}
 @media(max-width:760px){.workspace-grid{grid-template-columns:1fr}.workspace-hero{align-items:flex-start;flex-direction:column;gap:12px}.workspace-hero h1{font-size:23px}.workspace-side{display:block}.preview-body{padding:14px}.workspace-card{padding:17px}}
}
@media print{
 .workspace-grid,.preview-panel,.preview-body{display:block!important;margin:0!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;box-shadow:none!important;overflow:visible!important}
 .preview-body .certificate-viewport{width:auto!important;height:auto!important;margin:0!important;padding:0!important;overflow:visible!important}
 .preview-body .certificate{transform:none!important;margin:0!important;box-shadow:none!important}
}
</style>
<div class="certificate-workspace">
<div class="workspace-hero no-print">
 <div><div class="breadcrumb"><a href="alumni.php">Alumni</a> &nbsp;/&nbsp; Sijil Penghargaan</div><div class="eyebrow">Pengurusan sijil</div><h1><?= $editMode ? 'Edit Sijil Penghargaan' : ($issued ? 'Sijil Penghargaan' : 'Pratonton Sijil') ?></h1><p><?= $editMode ? 'Semak perubahan pada pratonton sebelum menyimpan sijil pelajar ini.' : 'Urus, semak dan cetak sijil tamat latihan industri pelajar.' ?></p></div>
 <span class="status-pill <?= $issued && !$editMode ? 'issued' : '' ?>"><?= $editMode ? 'Sedang diedit' : ($issued ? 'Telah dikeluarkan' : 'Belum dikeluarkan') ?></span>
</div>
<?php if ($error): ?><div role="alert" class="notice error workspace-alert no-print"><?= cert_h($error) ?></div><?php endif; ?>
<?php if ($eligibility): ?><div role="alert" class="notice error workspace-alert no-print"><?= cert_h($eligibility) ?> <a href="<?= $issued ? 'sijil.php?id=' . (int) $id . '&amp;edit=1' : 'tetapan_sijil.php' ?>"><?= $issued ? 'Edit Sijil' : 'Tetapan Sijil' ?></a></div><?php endif; ?>
<?php if (!$editMode && ($_GET['saved'] ?? '') === '1'): ?><div role="status" class="notice success workspace-alert no-print">Perubahan berjaya disimpan. Sijil terkini sedia untuk dicetak.</div><?php endif; ?>
<div id="layout-warning" hidden role="alert" class="notice error workspace-alert no-print">Kandungan terlalu panjang untuk ruang sijil. Ringkaskan teks sebelum menyimpan atau mencetak.</div>
<div class="workspace-grid">
<aside class="workspace-side no-print">
<?php if ($editMode): ?>
<form method="post" action="sijil.php?id=<?= (int) $id ?>&amp;edit=1" class="workspace-card editor-form" id="certificate-edit-form">
 <div class="editor-heading"><h2>Maklumat sijil</h2><p>Perubahan hanya untuk sijil ini. Versi sebelumnya disimpan dalam rekod perubahan.</p></div>
 <input type="hidden" name="csrf" value="<?= cert_h(cert_token()) ?>"><input type="hidden" name="action" value="save_certificate"><input type="hidden" name="edit_hash" value="<?= cert_h(hash('sha256', $issued['payload_json'])) ?>">
 <fieldset class="editor-section"><legend>Pelajar</legend>
 <?php foreach (certificateEditFields($payload) as $key => $definition): ?>
 <?php if ($key === 'opening'): ?></fieldset><fieldset class="editor-section"><legend>Kandungan sijil</legend><?php endif; ?>
 <?php if ($key === 'signer_name'): ?></fieldset><fieldset class="editor-section"><legend>Penandatangan</legend><?php endif; ?>
 <label for="edit-<?= cert_h($key) ?>"><?= cert_h($definition[0]) ?></label>
 <?php if ($key === 'body'): ?><textarea id="edit-<?= cert_h($key) ?>" name="<?= cert_h($key) ?>" rows="4" maxlength="<?= (int) $definition[1] ?>" <?= $definition[2] ? 'required' : '' ?>><?= cert_h($payload[$key] ?? '') ?></textarea>
 <?php elseif ($key === 'tarikh_mula' || $key === 'tarikh_tamat'): ?><input type="date" id="edit-<?= cert_h($key) ?>" name="<?= cert_h($key) ?>" value="<?= cert_h($payload[$key] ?? '') ?>" max="<?= cert_h(date('Y-m-d')) ?>" <?= $key === 'tarikh_tamat' && !empty($payload['tarikh_mula']) ? 'min="' . cert_h($payload['tarikh_mula']) . '"' : '' ?> required>
 <?php else: ?><input id="edit-<?= cert_h($key) ?>" name="<?= cert_h($key) ?>" maxlength="<?= (int) $definition[1] ?>" value="<?= cert_h($payload[$key] ?? '') ?>" <?= $definition[2] ? 'required' : '' ?>><?php endif; ?>
 <?php endforeach; ?>
 </fieldset>
 <div class="editor-actions"><div class="action-stack"><button type="submit" class="primary" data-cert-action>Simpan Perubahan</button><a class="button subtle" href="sijil.php?id=<?= (int) $id ?>">Batal Edit</a></div><p class="action-note">Rekod pelajar dan tetapan sijil umum tidak diubah.</p></div>
</form>
<?php else: ?>
<div class="workspace-card">
 <h2>Maklumat pelajar</h2><div class="student-mark" aria-hidden="true"><?= cert_h(function_exists('mb_substr') ? mb_substr($payload['nama'],0,1,'UTF-8') : substr($payload['nama'],0,1)) ?></div>
 <p class="student-name"><?= cert_h($payload['nama']) ?></p><p class="student-ipt"><?= cert_h($payload['ipt']) ?></p>
 <dl class="detail-list"><div><dt>Tempoh latihan</dt><dd><?= cert_h($payload['date_range'] ?: 'Belum ditetapkan') ?></dd></div><div><dt>Penandatangan</dt><dd><?= cert_h($payload['signer_name'] ?: 'Belum ditetapkan') ?></dd></div><div><dt>Jawatan</dt><dd><?= cert_h($payload['signer_title']) ?></dd></div></dl>
</div>
<div class="workspace-card"><h2>Tindakan sijil</h2>
<?php if ($issued): ?>
 <div class="action-stack"><?php if ($printReady): ?><button type="button" class="primary" data-cert-action onclick="window.print()">Cetak / Simpan PDF</button><?php endif; ?><a class="button" href="sijil.php?id=<?= (int) $id ?>&amp;edit=1">Edit Sijil</a><a class="button subtle" href="alumni.php">Kembali ke Alumni</a></div>
 <p class="action-note">Cetakan menggunakan maklumat sijil yang telah disimpan.</p>
<?php else: ?>
 <form method="post" class="action-stack" onsubmit="return confirm('Keluarkan sijil menggunakan maklumat dalam pratonton ini? Maklumat akan disimpan untuk cetakan semula.');"><input type="hidden" name="csrf" value="<?= cert_h(cert_token()) ?>"><input type="hidden" name="review_hash" value="<?= cert_h($reviewHash) ?>"><?php if (!$eligibility): ?><button class="primary" type="submit" data-cert-action>Keluarkan Sijil</button><?php endif; ?><a class="button" href="tetapan_sijil.php">Edit Tetapan Sijil</a><a class="button subtle" href="alumni.php">Kembali ke Alumni</a></form>
 <p class="action-note">Semak pratonton sebelum mengeluarkan sijil. Selepas itu, sijil boleh diedit dan dicetak semula.</p>
<?php endif; ?>
</div>
<?php endif; ?>
<div class="helper-card"><strong>Panduan cetakan</strong>Pilih kertas A4, skala 100% dan margin Tiada. Matikan pengepala serta pengaki. Untuk fail PDF, pilih Save as PDF.</div>
</aside>
<section class="preview-panel" aria-label="Pratonton sijil">
 <div class="preview-heading no-print"><h2>Pratonton sijil</h2><span>A4 · Potret</span></div>
 <div class="preview-body"><?php cert_render($payload, !$issued || $editMode); ?></div>
 <div class="preview-caption no-print"><?= $editMode ? 'Pratonton berubah semasa anda mengedit. Tekan Simpan Perubahan untuk menyimpan.' : 'Reka letak sijil JKR dikekalkan. Paparan disesuaikan dengan lebar skrin.' ?></div>
</section>
</div>
</div>
<script>
(function(){
 function fitPreview(){
  const body=document.querySelector('.preview-body'),cert=body&&body.querySelector('.certificate');if(!body||!cert)return;
  const padding=getComputedStyle(body);const available=body.clientWidth-parseFloat(padding.paddingLeft)-parseFloat(padding.paddingRight);
  const width=cert.offsetWidth,height=cert.offsetHeight;const scale=Math.min(1,Math.max(.15,available/width));
  body.style.setProperty('--preview-scale',scale);body.style.setProperty('--preview-width',(width*scale)+'px');body.style.setProperty('--preview-height',(height*scale)+'px');
 }
 window.addEventListener('load',fitPreview);window.addEventListener('resize',fitPreview);
 if(window.ResizeObserver){const observer=new ResizeObserver(fitPreview);observer.observe(document.querySelector('.preview-body'));}
})();
</script>
<?php if ($issued && $printReady && !$editMode && ($_GET['print'] ?? '') === '1'): ?>

<script>

window.addEventListener('load', async function () {

    if (document.fonts && document.fonts.ready) { await document.fonts.ready; }

    fitCertificate();

    const image = document.querySelector('.certificate-bg');

    const button = document.querySelector('[data-cert-action]');

    if (image && image.naturalWidth > 0 && button && !button.disabled) {

        window.setTimeout(function () { window.print(); }, 200);

    }

});

</script>

<?php endif; ?>

<?php if ($editMode): ?>

<script>

function certificateMalayDate(value){
    const months=['Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
    const match=/^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if(!match)return '[Pilih tarikh]';
    const year=Number(match[1]),month=Number(match[2]),day=Number(match[3]);
    if(month<1||month>12)return '[Pilih tarikh]';
    const checked=new Date(Date.UTC(year,month-1,day));
    if(checked.getUTCFullYear()!==year||checked.getUTCMonth()!==month-1||checked.getUTCDate()!==day)return '[Pilih tarikh]';
    return day+' '+months[month-1]+' '+year;
}
document.getElementById('certificate-edit-form').addEventListener('input', function () {

    const form=this;

    const reference=!!document.querySelector('.reference-layout');

    const selectors={nama:'.cert-name',ipt:'.cert-ipt',ic:'.cert-ic',body:'.cert-body',closing:'.cert-closing',date_range:'.cert-dates',signer_name:'.cert-signer'};

    form.querySelectorAll('input[name],textarea[name]').forEach(input=>{

        let target=document.querySelector('[data-setting="'+input.name+'"]');

        if(!target&&selectors[input.name])target=document.querySelector(selectors[input.name]);

        if(input.name==='opening'&&!reference)target=document.querySelector('.cert-content > p');

        if(target){let text=input.value;if(input.name==='signer_name'&&reference)text='('+text.trim().replace(/^\(+|\)+$/g,'')+')';if(input.name==='ic')text='No. Kad Pengenalan: '+text;target.textContent=text;}

    });

    if(reference && form.elements.tarikh_mula && form.elements.tarikh_tamat){
        const start=form.elements.tarikh_mula,end=form.elements.tarikh_tamat;
        end.min=start.value;
        end.setCustomValidity(start.value && end.value && end.value < start.value ? 'Tarikh tamat mesti sama atau selepas tarikh mula.' : '');
        document.querySelector('.cert-dates').textContent=certificateMalayDate(start.value)+' hingga '+certificateMalayDate(end.value);
    }
    const sign=document.querySelector('.cert-signature');

    if(reference){sign.querySelector('.cert-signer-title').textContent=form.elements.signer_title.value;sign.querySelector('.cert-signer-department').textContent=form.elements.signer_department.value;}

    else {sign.children[2].textContent=form.elements.signer_title.value;sign.children[3].textContent=form.elements.signer_department.value;}

    fitCertificate();

});

</script>

<?php endif; ?>

<?php cert_footer(); ?>
