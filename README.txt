e-INTERN: MODUL SIJIL PENGHARGAAN

INSTALLATION (XAMPP / phpMyAdmin)
1. Back up alumni.php and your existing database.
2. Extract this package into the same e-Intern project folder as db.php.
   Replace alumni.php. Keep your existing db.php, login.php, students and users.
   Keep the assets folder and all three new sijil PHP files together.
3. Select the e-Intern database in phpMyAdmin and import setup_sijil.sql once.
4. Log in and open Alumni > Tetapan Sijil.
5. Enter the Jurutera Daerah's full name, designation and department; save.
6. Open a completed student's Sijil, review the preview, click Keluarkan Sijil.
7. Click Cetak / Simpan PDF. A4 portrait; scale 100%; no margins; headers/footers off.
   The signature space is blank for the authorised person to sign physically.

WHAT IS EDITABLE
The opening, appreciation text, closing, signer name, title and department.
The supplied JKR artwork, logo and Sijil Penghargaan heading are preserved.
The settings page provides a live preview using clearly marked sample data.
Supported automatic fields: [nama], [ic], [ipt], [bahagian], [tarikh_mula], [tarikh_tamat].
Do not include HTML in the template; content is displayed as plain text.

ISSUING AND HISTORY
Only Tamat Latihan students with valid dates and an end date of today or earlier
can receive a newly issued certificate. Each student has one issued certificate.
Opening or previewing a certificate does not issue it.
Issuing saves the student's details, wording, issue date, signer and background
path. Changing the student record or signer settings does not change this snapshot.
Changing the settings applies only to certificates not yet issued.
This version does not provide revocation/reissue or electronic signatures.
Do not overwrite assets/sijil-jkr-v1.png: old certificates reference this artwork.

ACCESS
Alumni, certificate previews, settings and issuance require an existing user_id
login. This follows the shared staff-login model: all signed-in staff can edit
settings and issue certificates. There is no new separate administrator role.
If your account model needs different privileges, map the allowed roles explicitly
before deploying (the role names were not supplied with the alumni code).
Requests that save settings or issue certificates use CSRF tokens. SQL operations
use prepared statements and issued certificates are protected by a unique index.
No external service or new PHP package is required; uses existing mysqli/mysqlnd.

FILES
alumni.php                Complete updated alumni page
sijil.php                 Preview, issue, reprint and browser PDF output
sijil_helpers.php         Shared rendering, login and template functions
tetapan_sijil.php         Editable wording and signer settings
setup_sijil.sql           Two new tables; existing records are not altered
assets/sijil-jkr-v1.png    High-resolution rendering of your supplied template

VALIDATION
Completed: supplied PDF inspected; certificate positions checked with a sample
layout rendering; JavaScript syntax and source-level integration checks passed.
PHP/MySQL execution and actual browser print output could not be tested in the
creation environment. Verify in XAMPP against a backup/test database:
- A logged-out request redirects to index.php.
- An active student cannot receive a certificate by changing the URL.
- A future end date blocks issuance even when the status says Tamat Latihan.
- A completed student with valid past dates can preview, issue and reprint.
- Changing the signer affects a new certificate; the issued copy stays the same.
- Repeated clicks do not create duplicate certificate rows.
- Long names and wording fit; preview sample is clearly marked as a draft.
- Save as PDF produces exactly one A4 page with the JKR background.
The supplied alumni Markdown also contained escaped tags and a damaged initials
regular expression; these were restored to valid source text.
