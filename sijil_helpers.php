<?php
// Certificate module: plain-text templates, CSRF protection and immutable issue snapshots.
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');
$conn->set_charset('utf8mb4');
function cert_h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function cert_defaults() {
    return [
        'layout_version' => 2,
        'opening' => 'Dengan ini mengesahkan bahawa',
        'from_label' => 'dari',
        'activity_label' => 'telah menjalani',
        'activity_name' => 'LATIHAN INDUSTRI',
        'placement_label' => 'di',
        'placement_name' => 'JKR DAERAH KUALA KANGSAR',
        'date_label' => 'pada',
        'body' => 'Sebagai penghargaan atas komitmen dan sumbangan sepanjang menjalani latihan industri di Jabatan Kerja Raya Daerah Kuala Kangsar dari [tarikh_mula] hingga [tarikh_tamat].',
        'closing' => 'Semoga terus maju jaya dalam bidang yang diceburi.',
        'signer_name' => '',
        'signer_title' => 'Jurutera Daerah',
        'signer_department' => 'JKR Kuala Kangsar',
        'background' => 'assets/sijil-jkr-v1.png'
    ];
}
function cert_settings($conn) {
    try { $result = $conn->query('SELECT settings_json FROM certificate_settings WHERE id = 1'); }
    catch (Throwable $e) { cert_setup_error(); }
    if (!$result) { cert_setup_error(); }
    $row = $result->fetch_assoc();
    $stored = $row ? (json_decode($row['settings_json'], true) ?: []) : [];
    if (($stored['layout_version'] ?? 1) < 2) {
        // Adopt the supplied reference wording for new certificates; retain saved signer details.
        unset($stored['opening'], $stored['body'], $stored['closing']);
        if (($stored['signer_department'] ?? '') === 'JKR Daerah Kuala Kangsar') { $stored['signer_department'] = 'JKR Kuala Kangsar'; }
    }
    return array_merge(cert_defaults(), $stored);
}
function cert_setup_error() {
    http_response_code(503);
    exit('<!doctype html><html lang="ms"><meta charset="utf-8"><title>Persediaan Sijil</title><p>Import fail setup_sijil.sql ke pangkalan data e-Intern melalui phpMyAdmin dahulu.</p><a href="alumni.php">Kembali ke Alumni</a></html>');
}
function cert_token() {
    if (empty($_SESSION['certificate_csrf'])) { $_SESSION['certificate_csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['certificate_csrf'];
}
function cert_check_token() {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(cert_token(), $token)) { http_response_code(403); exit('Sesi borang tidak sah. Muat semula halaman.'); }
}
function cert_date($date) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date);
    if (!$d || $d->format('Y-m-d') !== $date) { return '-'; }
    $months = [1=>'Januari','Februari','Mac','April','Mei','Jun','Julai','Ogos','September','Oktober','November','Disember'];
    return $d->format('j') . ' ' . $months[(int) $d->format('n')] . ' ' . $d->format('Y');
}
function cert_replace($text, $student) {
    return strtr($text, [
        '[nama]' => $student['nama'] ?? '', '[ipt]' => $student['ipt'] ?? '',
        '[ic]' => $student['ic'] ?? '', '[bahagian]' => $student['bahagian'] ?? '',
        '[tarikh_mula]' => cert_date($student['tarikh_mula'] ?? ''),
        '[tarikh_tamat]' => cert_date($student['tarikh_tamat'] ?? '')
    ]);
}
function cert_payload($settings, $student, $issueDate) {
    // Render wording now, not on future reads: preserves names, dates and signer.
    return [
        'layout_version' => 2,
        'from_label' => cert_replace($settings['from_label'], $student),
        'activity_label' => cert_replace($settings['activity_label'], $student),
        'activity_name' => cert_replace($settings['activity_name'], $student),
        'placement_label' => cert_replace($settings['placement_label'], $student),
        'placement_name' => cert_replace($settings['placement_name'], $student),
        'date_label' => cert_replace($settings['date_label'], $student),
        'date_range' => cert_date($student['tarikh_mula'] ?? '') . ' hingga ' . cert_date($student['tarikh_tamat'] ?? ''),
        'nama' => $student['nama'] ?? '', 'ic' => $student['ic'] ?? '', 'ipt' => $student['ipt'] ?? '',
        'opening' => cert_replace($settings['opening'], $student),
        'body' => cert_replace($settings['body'], $student),
        'closing' => cert_replace($settings['closing'], $student),
        'signer_name' => $settings['signer_name'], 'signer_title' => $settings['signer_title'],
        'signer_department' => $settings['signer_department'],
        'issue_date' => $issueDate, 'background' => $settings['background']
    ];
}
function cert_header($title) {
    ?><!doctype html><html lang="ms"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= cert_h($title) ?> | e-Intern</title>
    <style>
    *{box-sizing:border-box}body{margin:0;background:#f6f6f3;color:#202020;font-family:Arial,sans-serif;font-size:14px;line-height:1.5}
    header{background:#fff;border-bottom:3px solid #f4c400;padding:16px 24px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
    header strong{margin-right:auto}a{color:#343434}main{max-width:1100px;margin:24px auto;padding:0 20px}
    .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:16px 0}button,.button{border:1px solid #d4d4d4;border-radius:8px;padding:10px 14px;background:#fff;color:#202020;cursor:pointer;text-decoration:none;font:inherit;display:inline-block}button.primary,.primary{background:#f4c400;border-color:#c6a000;font-weight:bold}button:disabled{opacity:.5;cursor:not-allowed}
    .card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:18px}.notice{padding:12px 16px;border:1px solid #e0c96a;border-radius:8px;background:#fff8d9;margin:12px 0}.error{background:#fff0ee;border-color:#d8847d}
    label{display:block;margin:14px 0 5px;font-weight:bold}input,textarea{font:inherit;width:100%;padding:10px;border:1px solid #bbb;border-radius:7px}textarea{resize:vertical}small{color:#555}h1{font-size:22px}h2{font-size:17px}
    .certificate-viewport{overflow:auto;padding:12px 0}.certificate{position:relative;width:210mm;height:297mm;margin:0 auto;background:#fff;color:#111;font-family:Georgia,'Times New Roman',serif;box-shadow:0 3px 18px #0002}
    .certificate-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:fill}
    .cert-content{position:absolute;top:81mm;left:24mm;right:24mm;height:117mm;text-align:center;font-size:15pt;line-height:1.55;display:flex;flex-direction:column;justify-content:center;gap:6mm}
    .cert-content p{margin:0;white-space:pre-line;overflow-wrap:anywhere}.cert-name{font-weight:bold;font-size:22pt;line-height:1.3}.cert-ic{font-size:12pt}.cert-ipt{font-size:14pt;font-weight:bold}.cert-body{font-size:15pt}.cert-closing{font-size:13pt}
    .cert-signature{position:absolute;top:219mm;left:25mm;right:25mm;text-align:center;line-height:1.45;font-size:12pt}.signature-line{width:65mm;margin:0 auto 3mm;border-top:1px solid #333}.cert-signer{font-weight:bold;overflow-wrap:anywhere}.cert-issued{font-size:10pt;margin-top:5mm}
    .cert-preview-label{position:absolute;top:77mm;left:20mm;right:20mm;text-align:center;font:10pt Arial;color:#777}
    .reference-layout .cert-content{top:84mm;left:22mm;right:22mm;height:138mm;display:block}
    .reference-layout .cert-content p{position:absolute;left:0;right:0;margin:0;line-height:1.35}
    .reference-layout .cert-label{font:italic 12pt Georgia,'Times New Roman',serif}
    .reference-layout .cert-value{font-family:Georgia,'Times New Roman',serif;font-weight:900;text-transform:uppercase;font-size:17pt}
    .reference-layout .cert-opening{top:0;height:7mm}
    .reference-layout .cert-name{top:9mm;height:17mm;font-size:20pt;border-bottom:1px solid #222;padding-bottom:2mm}
    .reference-layout .cert-from{top:31mm;height:7mm}
    .reference-layout .cert-ipt{top:39mm;height:20mm}
    .reference-layout .cert-activity-label{top:66mm;height:7mm}
    .reference-layout .cert-activity{top:74mm;height:11mm}
    .reference-layout .cert-placement-label{top:90mm;height:7mm}
    .reference-layout .cert-placement{top:98mm;height:11mm}
    .reference-layout .cert-date-label{top:115mm;height:7mm}
    .reference-layout .cert-dates{top:123mm;height:12mm;font-size:16pt}
    .reference-layout .cert-signature{top:257mm;font-size:12pt;line-height:1.45}
    .reference-layout .cert-signature .cert-signer{font-family:'Times New Roman',serif;font-size:12pt}
    @page{size:A4 portrait;margin:0}
    @media print{html,body{margin:0;padding:0;background:#fff}.no-print,header{display:none!important}main{margin:0;padding:0;max-width:none}.certificate-viewport{padding:0;overflow:visible}.certificate{margin:0;box-shadow:none;page-break-inside:avoid;print-color-adjust:exact;-webkit-print-color-adjust:exact}}
    @media screen{
      header.eintern-nav{display:block;position:sticky;top:0;z-index:100;background:#fff;padding:0;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 8px #00000005}
      .eintern-nav .nav-inner{max-width:1400px;margin:auto;padding:0 28px;min-height:74px;display:flex;align-items:center;justify-content:space-between;gap:20px}
      .eintern-nav .nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:#111;min-width:0}
      .eintern-nav .nav-logos{display:flex;align-items:center;gap:9px;flex-shrink:0}
      .eintern-nav .nav-logos img{height:36px;width:auto;object-fit:contain}
      .eintern-nav .logo-divider{height:28px;width:1px;background:#e5e7eb}
      .eintern-nav .brand-copy{border-left:1px solid #e5e7eb;padding-left:12px;min-width:0}
      .eintern-nav .brand-name{display:block;font-size:20px;font-weight:800;line-height:1.1;letter-spacing:-.5px}
      .eintern-nav .brand-department{display:block;font-size:10px;font-weight:700;letter-spacing:.3px;color:#6b7280;margin-top:5px}
      .eintern-nav .nav-links{display:flex;align-items:center;gap:4px}
      .eintern-nav .nav-links a{font-size:12px;font-weight:700;text-decoration:none;padding:10px 13px;border-radius:8px;color:#505966;white-space:nowrap}
      .eintern-nav .nav-links a:hover{background:#f3f4f6;color:#111;text-decoration:none}
      .eintern-nav .nav-links a[aria-current="page"]{background:#f4c400;color:#111}
      .eintern-nav .nav-links .nav-new{background:#111;color:#fff;margin-left:8px}
      .eintern-nav .nav-links .nav-new:hover{background:#292929;color:#fff}
      .eintern-nav .nav-new span{color:#f4c400;margin-right:5px}
      .eintern-nav .nav-toggle{display:none;align-items:center;justify-content:center;flex-shrink:0;height:38px;width:40px;padding:0;font-size:20px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;color:#111}
      .eintern-nav a:focus-visible,.eintern-nav button:focus-visible{outline:2px solid #b28f00;outline-offset:3px}
      @media(min-width:1101px){header.eintern-nav ~ main .settings-preview{top:94px}}
      @media(max-width:1100px){
        .eintern-nav .nav-inner{padding:0 20px;flex-wrap:wrap;gap:0}
        .eintern-nav .nav-brand{min-height:74px;max-width:calc(100% - 55px)}
        .eintern-nav .nav-toggle{display:flex}
        .eintern-nav .nav-links{display:none;flex-direction:column;align-items:stretch;width:100%;padding:10px 0 14px;border-top:1px solid #e5e7eb}
        .eintern-nav .nav-links.is-open{display:flex}
        .eintern-nav .nav-links .nav-new{margin-left:0}
      }
      @media(max-width:480px){.eintern-nav .nav-inner{padding:0 14px}.eintern-nav .nav-logos img{height:30px}.eintern-nav .brand-department{font-size:8px;max-width:180px}.eintern-nav .nav-brand{gap:8px}.eintern-nav .brand-copy{padding-left:8px}}
    }
    </style></head><body>
    <header class="no-print eintern-nav">
      <div class="nav-inner">
        <a class="nav-brand" href="index.php" aria-label="e-Intern — Halaman Utama">
          <span class="nav-logos"><img src="logo-perak.png" alt="Logo Perak" onerror="this.style.display='none'"><span class="logo-divider" aria-hidden="true"></span><img src="logo-jkr.png" alt="Logo JKR" onerror="this.style.display='none'"></span>
          <span class="brand-copy"><span class="brand-name">e-Intern</span><span class="brand-department">JABATAN KERJA RAYA DAERAH KUALA KANGSAR</span></span>
        </a>
        <button class="nav-toggle" id="cert-nav-toggle" type="button" aria-label="Buka menu navigasi" aria-controls="cert-nav-links" aria-expanded="false"><span aria-hidden="true">☰</span></button>
        <nav class="nav-links" id="cert-nav-links" aria-label="Navigasi utama">
          <a href="dashboard.php">Dashboard</a><a href="alumni.php">Alumni</a>
          <a href="tetapan_sijil.php" <?= $title === 'Tetapan Sijil' ? 'aria-current="page"' : '' ?>>Tetapan Sijil</a>
          <a class="nav-new" href="tambah.php"><span aria-hidden="true">+</span> Permohonan Baru</a>
        </nav>
      </div>
    </header>
    <script>
    (function(){
      const button=document.getElementById('cert-nav-toggle'),menu=document.getElementById('cert-nav-links');
      function closeMenu(){menu.classList.remove('is-open');button.setAttribute('aria-expanded','false');button.setAttribute('aria-label','Buka menu navigasi');}
      button.addEventListener('click',function(){const open=menu.classList.toggle('is-open');button.setAttribute('aria-expanded',String(open));button.setAttribute('aria-label',open?'Tutup menu navigasi':'Buka menu navigasi');});
      document.addEventListener('keydown',function(event){if(event.key==='Escape'&&menu.classList.contains('is-open')){closeMenu();button.focus();}});
      window.addEventListener('resize',function(){if(window.innerWidth>1100)closeMenu();});
    })();
    </script><main><?php
}
function cert_reference_payload($payload) {
    if (($payload['layout_version'] ?? 1) >= 2) { return $payload; }
    // Keep student/signatory snapshots; adopt the approved reference presentation.
    $dateRange = $payload['date_range'] ?? '';
    if ($dateRange === '') {
        $months = '(?:Januari|Februari|Mac|April|Mei|Jun|Julai|Ogos|September|Oktober|November|Disember)';
        $date = '\d{1,2}\s+' . $months . '\s+\d{4}';
        if (preg_match('/(' . $date . ')\s+hingga\s+(' . $date . ')/iu', $payload['body'] ?? '', $matches)) {
            $dateRange = $matches[1] . ' hingga ' . $matches[2];
        }
    }
    $payload['_previous_wording'] = ['opening'=>$payload['opening'] ?? '', 'body'=>$payload['body'] ?? '', 'closing'=>$payload['closing'] ?? ''];
    $payload['layout_version'] = 2;
    $payload['opening'] = 'Dengan ini mengesahkan bahawa';
    $payload['from_label'] = 'dari';
    $payload['activity_label'] = 'telah menjalani';
    $payload['activity_name'] = 'LATIHAN INDUSTRI';
    $payload['placement_label'] = 'di';
    $payload['placement_name'] = 'JKR DAERAH KUALA KANGSAR';
    $payload['date_label'] = 'pada';
    $payload['date_range'] = $dateRange;
    return $payload;
}
function cert_render($payload, $preview = false) {
    $payload = cert_reference_payload($payload);
    $signer = trim($payload['signer_name'] ?: '[Nama Jurutera Daerah]', " ()\t\n\r");
    ?><div class="certificate-viewport"><section class="certificate reference-layout" aria-label="Sijil Penghargaan">
    <img class="certificate-bg" src="<?= cert_h($payload['background']) ?>" alt="Templat sijil JKR">
    <?php if ($preview): ?><div class="cert-preview-label">PRATONTON · BELUM DIKELUARKAN</div><?php endif; ?>
    <div class="cert-content">
        <p class="cert-label cert-opening" data-setting="opening"><?= cert_h($payload['opening']) ?></p>
        <p class="cert-value cert-name"><?= cert_h($payload['nama']) ?></p>
        <p class="cert-label cert-from" data-setting="from_label"><?= cert_h($payload['from_label']) ?></p>
        <p class="cert-value cert-ipt"><?= cert_h($payload['ipt']) ?></p>
        <p class="cert-label cert-activity-label" data-setting="activity_label"><?= cert_h($payload['activity_label']) ?></p>
        <p class="cert-value cert-activity" data-setting="activity_name"><?= cert_h($payload['activity_name']) ?></p>
        <p class="cert-label cert-placement-label" data-setting="placement_label"><?= cert_h($payload['placement_label']) ?></p>
        <p class="cert-value cert-placement" data-setting="placement_name"><?= cert_h($payload['placement_name']) ?></p>
        <p class="cert-label cert-date-label" data-setting="date_label"><?= cert_h($payload['date_label']) ?></p>
        <p class="cert-value cert-dates"><?= cert_h($payload['date_range'] ?: '[Semak tempoh latihan melalui Edit Sijil]') ?></p>
    </div>
    <div class="cert-signature"><div class="cert-signer">(<?= cert_h($signer) ?>)</div><div class="cert-signer-title"><?= cert_h($payload['signer_title']) ?></div><div class="cert-signer-department"><?= cert_h($payload['signer_department']) ?></div></div>
    </section></div><?php
}
function cert_footer() {
    ?><script>
    // Fit unusually long names/content without clipping the signature area.
    function fitCertificate(){
        const box=document.querySelector('.cert-content');if(!box)return;
        let overflow=false;
        if(document.querySelector('.reference-layout')){
            box.querySelectorAll('p').forEach(el=>{
                el.style.fontSize='';
                let size=parseFloat(getComputedStyle(el).fontSize);
                while((el.scrollHeight>el.clientHeight+2||el.scrollWidth>el.clientWidth+2)&&size>13.5){size-=.5;el.style.fontSize=size+'px';}
                if(el.scrollHeight>el.clientHeight+2||el.scrollWidth>el.clientWidth+2)overflow=true;
            });
        }else{
            const name=box.querySelector('.cert-name');
            if(name){let size=22;while(name.scrollHeight>100&&size>14){size--;name.style.fontSize=size+'pt';}}
            let size=15;while(box.scrollHeight>box.clientHeight+1&&size>11){size-=.5;box.style.fontSize=size+'pt';box.querySelectorAll('.cert-body,.cert-ipt').forEach(el=>el.style.fontSize=size+'pt');}
            overflow=box.scrollHeight>box.clientHeight+2;
        }
        const signature=document.querySelector('.cert-signature');
        const limit=document.querySelector('.reference-layout')?95:150;
        overflow=overflow||(signature&&signature.scrollHeight>limit);
        document.querySelectorAll('[data-cert-action]').forEach(el=>el.disabled=overflow);
        const msg=document.getElementById('layout-warning');if(msg)msg.hidden=!overflow;
    }
    window.addEventListener('load',fitCertificate);window.addEventListener('beforeprint',fitCertificate);
    </script></main></body></html><?php
}
