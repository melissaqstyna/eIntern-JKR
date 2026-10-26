<?php
require_once __DIR__ . '/sijil_helpers.php';
$settings = cert_settings($conn);
$error = ''; $saved = false;
$fields = [
    'opening'=>['Ayat pengesahan',120],
    'from_label'=>['Label sebelum institusi',30],
    'activity_label'=>['Label sebelum jenis latihan',50],
    'activity_name'=>['Jenis latihan',100],
    'placement_label'=>['Label sebelum tempat latihan',30],
    'placement_name'=>['Tempat latihan',140],
    'date_label'=>['Label sebelum tarikh latihan',30],
    'signer_name'=>['Nama penuh Jurutera Daerah (termasuk gelaran)',120],
    'signer_title'=>['Jawatan',100],
    'signer_department'=>['Jabatan penandatangan',140]
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cert_check_token();
    $next = $settings;
    $next['layout_version'] = 2;
    foreach ($fields as $key => $definition) {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) { $error = 'Nilai borang tidak sah.'; break; }
        $next[$key] = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($next[$key], 'UTF-8') : strlen($next[$key]);
        if ($length > $definition[1]) { $error = 'Teks terlalu panjang. Sila ringkaskan kandungan.'; break; }
        if ($next[$key] === '') { $error = 'Sila lengkapkan semua medan.'; break; }
    }
    if ($error === '') {
        $json = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $conn->prepare('INSERT INTO certificate_settings (id, settings_json) VALUES (1, ?) ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json)');
        $stmt->bind_param('s', $json); $stmt->execute(); $stmt->close(); $saved = true;
    }
    $settings = $next;
}
$sample = ['nama'=>'CONTOH NAMA PELAJAR','ic'=>'000000-00-0000','ipt'=>'UNIVERSITI TEKNOLOGI MARA','bahagian'=>'Bahagian Pentadbiran & Kewangan','tarikh_mula'=>'2026-09-07','tarikh_tamat'=>'2026-12-11'];
cert_header('Tetapan Sijil');
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
@media screen{
 .workspace-grid{grid-template-columns:360px minmax(0,1fr)}
 .settings-help{font-size:11px;line-height:1.7;color:#727b87;margin:0 0 12px}
 .settings-preview{position:sticky;top:20px}
 .editor-section input{min-width:0}
 @media(max-width:1000px){.workspace-grid{grid-template-columns:310px minmax(0,1fr)}}
 @media(max-width:760px){.workspace-grid{grid-template-columns:1fr}.settings-preview{position:static}}
}
</style>
<div class="certificate-workspace">
 <div class="workspace-hero no-print">
  <div><div class="breadcrumb"><a href="alumni.php">Alumni</a> &nbsp;/&nbsp; Tetapan Sijil</div><div class="eyebrow">Pengurusan sijil</div><h1>Tetapan Sijil Penghargaan</h1><p>Kemas kini kandungan dan maklumat Jurutera Daerah untuk sijil baharu.</p></div>
  <span class="status-pill">Templat JKR</span>
 </div>
 <?php if ($saved): ?><div role="status" class="notice workspace-alert success no-print">Tetapan berjaya disimpan. Sijil baharu akan menggunakan maklumat ini.</div><?php endif; ?>
 <?php if ($error): ?><div role="alert" class="notice error workspace-alert no-print"><?= cert_h($error) ?></div><?php endif; ?>
 <div id="layout-warning" hidden role="alert" class="notice error workspace-alert no-print">Teks terlalu panjang untuk ruang sijil. Ringkaskan kandungan sebelum menyimpan tetapan.</div>
 <div class="workspace-grid">
  <aside class="workspace-side no-print">
   <form method="post" class="workspace-card editor-form" id="settings-form">
    <div class="editor-heading"><h2>Maklumat templat</h2><p>Pratonton berubah semasa anda menaip. Tekan Simpan Tetapan untuk menyimpan perubahan.</p></div>
    <input type="hidden" name="csrf" value="<?= cert_h(cert_token()) ?>">
    <fieldset class="editor-section"><legend>Kandungan sijil</legend>
    <?php foreach ($fields as $key => $definition): ?>
    <?php if ($key === 'signer_name'): ?></fieldset><fieldset class="editor-section"><legend>Penandatangan</legend><p class="settings-help">Masukkan nama termasuk gelaran, tanpa kurungan. Kurungan ditambah pada sijil secara automatik.</p><?php endif; ?>
    <label for="<?= cert_h($key) ?>"><?= cert_h($definition[0]) ?></label>
    <input id="<?= cert_h($key) ?>" name="<?= cert_h($key) ?>" maxlength="<?= (int) $definition[1] ?>" value="<?= cert_h($settings[$key] ?? '') ?>" required>
    <?php endforeach; ?>
    </fieldset>
    <div class="editor-actions"><div class="action-stack"><button class="primary" type="submit" data-cert-action>Simpan Tetapan</button><a class="button subtle" href="alumni.php">Kembali ke Alumni</a></div><p class="action-note">Tetapan ini digunakan untuk sijil baharu. Sijil yang telah dikeluarkan menyimpan maklumat asalnya.</p></div>
   </form>
   <div class="helper-card"><strong>Maklumat daripada rekod pelajar</strong>Nama, institusi serta tarikh mula dan tamat latihan diisi secara automatik apabila sijil pelajar dikeluarkan. Pratonton ini menggunakan data contoh.</div>
  </aside>
  <section class="preview-panel settings-preview" aria-label="Pratonton templat sijil">
   <div class="preview-heading no-print"><h2>Pratonton templat</h2><span>A4 · Data contoh</span></div>
   <div class="preview-body"><?php cert_render(cert_payload($settings, $sample, date('Y-m-d')), true); ?></div>
   <div class="preview-caption no-print">Susunan dan reka bentuk sijil mengikut contoh JKR. Nama pelajar dan tarikh di sini ialah data contoh.</div>
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

const sample=<?= json_encode($sample, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
function templateText(text){const values={'[nama]':sample.nama,'[ic]':sample.ic,'[ipt]':sample.ipt,'[bahagian]':sample.bahagian,'[tarikh_mula]':'7 September 2026','[tarikh_tamat]':'11 Disember 2026'};return text.replace(/\[(nama|ic|ipt|bahagian|tarikh_mula|tarikh_tamat)\]/g,key=>values[key]);}
document.getElementById('settings-form').addEventListener('input',()=>{
 document.querySelectorAll('[data-setting]').forEach(el=>{const input=document.getElementById(el.dataset.setting);if(input)el.textContent=templateText(input.value);});
 document.querySelector('.cert-signer').textContent='('+ (document.getElementById('signer_name').value.trim().replace(/^\(+|\)+$/g,'')||'[Nama Jurutera Daerah]') +')';
 document.querySelector('.cert-signer-title').textContent=document.getElementById('signer_title').value;
 document.querySelector('.cert-signer-department').textContent=document.getElementById('signer_department').value;
 fitCertificate();
});
</script>
<?php cert_footer(); ?>
