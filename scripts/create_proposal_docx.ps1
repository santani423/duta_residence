param(
    [string]$OutPath = "docs\Proposal_Pengajuan_Aplikasi_Grand_Duta.docx"
)

$ErrorActionPreference = "Stop"

function XmlEscape([string]$Text) {
    if ($null -eq $Text) { return "" }
    return [System.Security.SecurityElement]::Escape($Text)
}

function RunXml([string]$Text, [string]$Bold = "false", [string]$Color = "000000", [int]$Size = 22) {
    $b = if ($Bold -eq "true") { "<w:b/>" } else { "" }
    return "<w:r><w:rPr>$b<w:color w:val=`"$Color`"/><w:sz w:val=`"$Size`"/><w:szCs w:val=`"$Size`"/></w:rPr><w:t xml:space=`"preserve`">$(XmlEscape $Text)</w:t></w:r>"
}

function ParagraphXml([string]$Text, [string]$Style = "Normal", [string]$Align = "left", [int]$Before = 0, [int]$After = 160, [string]$Bold = "false", [string]$Color = "000000", [int]$Size = 22) {
    $jc = if ($Align -ne "left") { "<w:jc w:val=`"$Align`"/>" } else { "" }
    return "<w:p><w:pPr><w:pStyle w:val=`"$Style`"/><w:spacing w:before=`"$Before`" w:after=`"$After`" w:line=`"320`" w:lineRule=`"auto`"/>$jc</w:pPr>$(RunXml $Text $Bold $Color $Size)</w:p>"
}

function SpacerXml([int]$After = 160) {
    return "<w:p><w:pPr><w:spacing w:after=`"$After`"/></w:pPr></w:p>"
}

function NumberedParagraphXml([string]$Text, [int]$NumId = 1) {
    return "<w:p><w:pPr><w:numPr><w:ilvl w:val=`"0`"/><w:numId w:val=`"$NumId`"/></w:numPr><w:spacing w:after=`"80`" w:line=`"290`" w:lineRule=`"auto`"/></w:pPr>$(RunXml $Text "false" "000000" 22)</w:p>"
}

function BulletParagraphXml([string]$Text) {
    return NumberedParagraphXml $Text 2
}

function CellXml([string]$Text, [int]$Width, [string]$Fill = "", [string]$Bold = "false", [string]$Color = "000000") {
    $shd = if ($Fill) { "<w:shd w:fill=`"$Fill`"/>" } else { "" }
    return "<w:tc><w:tcPr><w:tcW w:w=`"$Width`" w:type=`"dxa`"/>$shd<w:tcMar><w:top w:w=`"100`" w:type=`"dxa`"/><w:bottom w:w=`"100`" w:type=`"dxa`"/><w:start w:w=`"140`" w:type=`"dxa`"/><w:end w:w=`"140`" w:type=`"dxa`"/></w:tcMar></w:tcPr><w:p><w:pPr><w:spacing w:after=`"40`" w:line=`"280`" w:lineRule=`"auto`"/></w:pPr>$(RunXml $Text $Bold $Color 21)</w:p></w:tc>"
}

function TableXml([array]$Rows, [array]$Widths, [switch]$Header) {
    $grid = ($Widths | ForEach-Object { "<w:gridCol w:w=`"$_`"/>" }) -join ""
    $xml = "<w:tbl><w:tblPr><w:tblW w:w=`"9360`" w:type=`"dxa`"/><w:tblInd w:w=`"120`" w:type=`"dxa`"/><w:tblBorders><w:top w:val=`"single`" w:sz=`"4`" w:space=`"0`" w:color=`"D9DEE8`"/><w:left w:val=`"single`" w:sz=`"4`" w:space=`"0`" w:color=`"D9DEE8`"/><w:bottom w:val=`"single`" w:sz=`"4`" w:space=`"0`" w:color=`"D9DEE8`"/><w:right w:val=`"single`" w:sz=`"4`" w:space=`"0`" w:color=`"D9DEE8`"/><w:insideH w:val=`"single`" w:sz=`"4`" w:space=`"0`" w:color=`"D9DEE8`"/><w:insideV w:val=`"single`" w:sz=`"4`" w:space=`"0`" w:color=`"D9DEE8`"/></w:tblBorders><w:tblLayout w:type=`"fixed`"/><w:tblCellMar><w:top w:w=`"80`" w:type=`"dxa`"/><w:bottom w:w=`"80`" w:type=`"dxa`"/><w:start w:w=`"120`" w:type=`"dxa`"/><w:end w:w=`"120`" w:type=`"dxa`"/></w:tblCellMar></w:tblPr><w:tblGrid>$grid</w:tblGrid>"
    for ($r = 0; $r -lt $Rows.Count; $r++) {
        $xml += "<w:tr>"
        for ($c = 0; $c -lt $Rows[$r].Count; $c++) {
            $isHeader = $Header -and $r -eq 0
            $fill = if ($isHeader) { "F4F6F9" } elseif ($c -eq 0 -and $Widths.Count -eq 2) { "F8FAFC" } else { "" }
            $bold = if ($isHeader -or ($c -eq 0 -and $Widths.Count -eq 2)) { "true" } else { "false" }
            $xml += CellXml $Rows[$r][$c] $Widths[$c] $fill $bold
        }
        $xml += "</w:tr>"
    }
    return $xml + "</w:tbl>"
}

$title = "Proposal Pengajuan Pengembangan Aplikasi Estate Management"
$subtitle = "Aplikasi Web, Android, Server, dan Implementasi Sistem"
$client = "Duta Indah Residence"
$provider = "Tim Pengembang Sistem"
$date = "Juni 2026"

$doc = ""
$doc += ParagraphXml "PROPOSAL PENGAJUAN" "Subtitle" "center" 0 160 "true" "6B7280" 22
$doc += ParagraphXml $title "Title" "center" 0 80 "true" "0B2545" 48
$doc += ParagraphXml $subtitle "Subtitle" "center" 0 300 "false" "4B5563" 26
$doc += TableXml @(
    @("Diajukan kepada", $client),
    @("Disiapkan oleh", $provider),
    @("Nilai Paket", "Rp35.000.000"),
    @("Durasi Pengerjaan", "5 bulan"),
    @("Tanggal", $date)
) @(2800,6560)
$doc += SpacerXml 360
$doc += ParagraphXml "Ringkasan Penawaran" "Heading1"
$doc += ParagraphXml "Proposal ini diajukan untuk pengembangan dan implementasi sistem Estate Management terpadu yang mencakup aplikasi web admin, aplikasi Android, backend API, database, deployment server, serta pendampingan implementasi. Sistem dirancang untuk membantu pengelolaan data penghuni, cluster/unit, tagihan IPL, pembayaran, piutang, laporan, dokumen, notifikasi, dan aktivitas operasional estate secara lebih tertib, transparan, dan mudah diaudit."
$doc += ParagraphXml "Nilai pekerjaan yang diajukan adalah Rp35.000.000 sudah termasuk server untuk kebutuhan operasional awal, pengembangan aplikasi web, pengembangan aplikasi Android, konfigurasi sistem, deployment, pengujian, dan pendampingan go-live sesuai ruang lingkup yang dijelaskan dalam proposal ini."

$doc += ParagraphXml "Tujuan Proyek" "Heading1"
$doc += NumberedParagraphXml "Menyediakan sistem digital terpusat untuk pengelolaan pelanggan, unit, cluster, tagihan, pembayaran, dan laporan estate."
$doc += NumberedParagraphXml "Mempermudah petugas operasional, finance, loket, dan customer service dalam menjalankan pekerjaan harian."
$doc += NumberedParagraphXml "Memberikan akses mobile kepada pengguna melalui aplikasi Android untuk informasi tagihan, pembayaran, notifikasi, dokumen, dan layanan pelanggan."
$doc += NumberedParagraphXml "Meningkatkan akurasi data, kontrol akses, pencatatan aktivitas, dan ketersediaan laporan manajemen."

$doc += ParagraphXml "Ruang Lingkup Pekerjaan" "Heading1"
$doc += ParagraphXml "1. Aplikasi Web Admin dan Operasional" "Heading2"
$doc += BulletParagraphXml "Dashboard ringkasan operasional estate, tagihan, pembayaran, dan piutang."
$doc += BulletParagraphXml "Manajemen data cluster, pelanggan, unit/properti, status kepemilikan, dan profil pelanggan."
$doc += BulletParagraphXml "Pembuatan tagihan bulanan, tagihan khusus, tagihan mundur, approval tagihan, dan riwayat tagihan."
$doc += BulletParagraphXml "Pembayaran loket, pencatatan transaksi, kuitansi, payment gateway/manual transfer, serta verifikasi pembayaran."
$doc += BulletParagraphXml "Cicilan, reversal, monitoring piutang, aging receivable, laporan bulanan, laporan harian, dan rekonsiliasi."
$doc += BulletParagraphXml "Manajemen dokumen, notifikasi, audit log, role, permission, dan pengaturan sistem."

$doc += ParagraphXml "2. Aplikasi Android" "Heading2"
$doc += BulletParagraphXml "Login pengguna/customer dengan akun yang terhubung ke backend."
$doc += BulletParagraphXml "Dashboard customer berisi ringkasan tagihan, invoice, pembayaran, dan status layanan."
$doc += BulletParagraphXml "Akses detail tagihan, invoice, bukti pembayaran, histori transaksi, dokumen, dan notifikasi."
$doc += BulletParagraphXml "Fitur upload bukti pembayaran manual, komplain, maintenance request, dan pengaturan profil sesuai kebutuhan implementasi."
$doc += BulletParagraphXml "Build APK/AAB untuk kebutuhan distribusi internal atau persiapan publikasi ke Play Store sesuai kesiapan akun developer."

$doc += ParagraphXml "3. Backend, Database, dan Integrasi" "Heading2"
$doc += BulletParagraphXml "Backend API berbasis autentikasi token, role-based access control, audit trail, dan validasi data."
$doc += BulletParagraphXml "Database produksi untuk data cluster, pelanggan, tagihan, pembayaran, dokumen, notifikasi, dan log aktivitas."
$doc += BulletParagraphXml "Integrasi pembayaran manual serta struktur integrasi payment gateway seperti Xendit/Midtrans apabila kredensial resmi tersedia."
$doc += BulletParagraphXml "Export atau generate dokumen/laporan sesuai fitur yang tersedia pada sistem."

$doc += ParagraphXml "4. Server, Deployment, dan Implementasi" "Heading2"
$doc += BulletParagraphXml "Pengadaan atau konfigurasi server produksi awal yang termasuk dalam nilai paket."
$doc += BulletParagraphXml "Setup environment backend, frontend, database, SSL, domain/subdomain, storage, dan konfigurasi dasar keamanan."
$doc += BulletParagraphXml "Deployment aplikasi web dan backend API."
$doc += BulletParagraphXml "Pendampingan uji coba, perbaikan bug dalam masa implementasi, dan persiapan go-live."

$doc += ParagraphXml "Timeline Pengerjaan" "Heading1"
$doc += TableXml @(
    @("Bulan", "Fokus Pekerjaan", "Output Utama"),
    @("Bulan 1", "Analisis kebutuhan, finalisasi scope, desain database, setup project, dan setup server awal.", "Dokumen kebutuhan, struktur sistem, server awal, fondasi backend/frontend."),
    @("Bulan 2", "Pengembangan modul master data, auth, role/permission, cluster, pelanggan, dan dashboard awal.", "Modul admin dasar siap diuji."),
    @("Bulan 3", "Pengembangan billing, pembayaran, payment gateway/manual transfer, piutang, laporan, dan dokumen.", "Modul transaksi dan laporan berjalan."),
    @("Bulan 4", "Pengembangan aplikasi Android, customer portal, notifikasi, komplain, maintenance, dan integrasi API mobile.", "Aplikasi Android versi uji coba."),
    @("Bulan 5", "Testing menyeluruh, perbaikan bug, deployment produksi, training singkat, dokumentasi, dan go-live.", "Sistem siap digunakan operasional.")
) @(1300,4300,3760) -Header

$doc += ParagraphXml "Anggaran Pekerjaan" "Heading1"
$doc += ParagraphXml "Total nilai paket pekerjaan adalah Rp35.000.000. Nilai tersebut sudah mencakup pengembangan aplikasi web, aplikasi Android, backend API, database, setup server, deployment, pengujian, dan pendampingan implementasi selama masa proyek."
$doc += TableXml @(
    @("Komponen", "Estimasi Nilai"),
    @("Pengembangan backend API dan database", "Termasuk paket"),
    @("Pengembangan aplikasi web admin/operasional", "Termasuk paket"),
    @("Pengembangan aplikasi Android/customer mobile", "Termasuk paket"),
    @("Setup server, deployment, SSL, konfigurasi environment", "Termasuk paket"),
    @("Testing, perbaikan bug, dokumentasi, dan pendampingan go-live", "Termasuk paket"),
    @("Total Paket", "Rp35.000.000")
) @(6200,3160) -Header

$doc += ParagraphXml "Skema Pembayaran" "Heading1"
$doc += ParagraphXml "Pembayaran dilakukan bertahap agar progres pekerjaan dapat berjalan terukur dan selaras dengan milestone proyek."
$doc += TableXml @(
    @("Tahap", "Waktu Pembayaran", "Nominal", "Keterangan"),
    @("DP", "Saat persetujuan proposal dan mulai pekerjaan", "Rp10.000.000", "Booking jadwal, kickoff, analisis, setup awal, dan inisiasi proyek."),
    @("Termin 1", "Akhir Bulan 1", "Rp5.000.000", "Setelah analisis, struktur sistem, dan setup awal selesai."),
    @("Termin 2", "Akhir Bulan 2", "Rp5.000.000", "Setelah modul admin dasar dan master data siap diuji."),
    @("Termin 3", "Akhir Bulan 3", "Rp5.000.000", "Setelah modul billing, pembayaran, dan laporan inti selesai."),
    @("Termin 4", "Akhir Bulan 4", "Rp5.000.000", "Setelah aplikasi Android/customer portal versi uji coba tersedia."),
    @("Termin 5", "Akhir Bulan 5 / sebelum go-live final", "Rp5.000.000", "Setelah testing, deployment produksi, dan serah terima final."),
    @("Total", "", "Rp35.000.000", "")
) @(1500,3100,1700,3060) -Header

$doc += ParagraphXml "Deliverables" "Heading1"
$doc += BulletParagraphXml "Aplikasi web admin/operasional yang dapat diakses melalui domain/subdomain produksi."
$doc += BulletParagraphXml "Backend API dan database produksi."
$doc += BulletParagraphXml "Aplikasi Android dalam bentuk file build APK/AAB sesuai kebutuhan distribusi."
$doc += BulletParagraphXml "Server produksi awal yang telah dikonfigurasi untuk menjalankan sistem."
$doc += BulletParagraphXml "Dokumentasi teknis ringkas, akun akses awal, dan panduan operasional dasar."
$doc += BulletParagraphXml "Pendampingan implementasi dan perbaikan bug selama masa pengerjaan sampai go-live."

$doc += ParagraphXml "Asumsi dan Ketentuan" "Heading1"
$doc += NumberedParagraphXml "Ruang lingkup pekerjaan mengikuti modul yang tercantum dalam proposal ini. Perubahan besar di luar scope dapat diajukan sebagai pekerjaan tambahan."
$doc += NumberedParagraphXml "Konten, data awal, logo, domain, akun payment gateway, akun Play Store, dan akses hosting/server yang dibutuhkan disediakan atau disetujui oleh pihak klien."
$doc += NumberedParagraphXml "Biaya sudah termasuk server untuk kebutuhan produksi awal. Perpanjangan server, upgrade kapasitas, domain premium, SMS/WhatsApp gateway, biaya payment gateway, atau biaya akun developer pihak ketiga mengikuti tagihan provider masing-masing apabila tidak disebutkan lain."
$doc += NumberedParagraphXml "Jadwal 5 bulan berjalan efektif setelah DP diterima dan kebutuhan utama proyek disepakati."
$doc += NumberedParagraphXml "Serah terima dilakukan setelah sistem selesai diuji, deployed, dan dinyatakan siap digunakan sesuai scope."

$doc += ParagraphXml "Penutup" "Heading1"
$doc += ParagraphXml "Demikian proposal pengajuan ini disusun sebagai dasar kerja sama pengembangan aplikasi Estate Management. Dengan adanya sistem ini, proses administrasi estate diharapkan menjadi lebih cepat, akurat, mudah dipantau, dan siap mendukung kebutuhan operasional jangka panjang."
$doc += SpacerXml 240
$doc += TableXml @(
    @("Diajukan oleh", "Disetujui oleh"),
    @("Tim Pengembang Sistem", $client),
    @("Tanggal: ____________________", "Tanggal: ____________________"),
    @("Tanda tangan: ________________", "Tanda tangan: ________________")
) @(4680,4680)

$documentXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup" xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk" xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" mc:Ignorable="w14 wp14">
<w:body>
$doc
<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>
</w:body>
</w:document>
"@

$stylesXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="320" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>
<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="160" w:line="320" w:lineRule="auto"/><w:jc w:val="both"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="0" w:after="80"/><w:jc w:val="center"/></w:pPr><w:rPr><w:b/><w:color w:val="0B2545"/><w:sz w:val="48"/><w:szCs w:val="48"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="160"/><w:jc w:val="center"/></w:pPr><w:rPr><w:color w:val="4B5563"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="360" w:after="200" w:line="320" w:lineRule="auto"/></w:pPr><w:rPr><w:b/><w:color w:val="2E74B5"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style>
<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120" w:line="320" w:lineRule="auto"/></w:pPr><w:rPr><w:b/><w:color w:val="2E74B5"/><w:sz w:val="26"/><w:szCs w:val="26"/></w:rPr></w:style>
</w:styles>
"@

$numberingXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/><w:lvlJc w:val="left"/><w:pPr><w:tabs><w:tab w:val="num" w:pos="540"/></w:tabs><w:ind w:left="540" w:hanging="280"/></w:pPr></w:lvl></w:abstractNum>
<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:tabs><w:tab w:val="num" w:pos="540"/></w:tabs><w:ind w:left="540" w:hanging="280"/></w:pPr><w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol" w:hint="default"/></w:rPr></w:lvl></w:abstractNum>
<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>
<w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num>
</w:numbering>
"@

$contentTypes = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
"@

$rels = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"@

$docRels = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
</Relationships>
"@

$settingsXml = "<?xml version=`"1.0`" encoding=`"UTF-8`" standalone=`"yes`"?><w:settings xmlns:w=`"http://schemas.openxmlformats.org/wordprocessingml/2006/main`"><w:zoom w:percent=`"100`"/><w:defaultTabStop w:val=`"720`"/></w:settings>"
$created = (Get-Date).ToUniversalTime().ToString("s") + "Z"
$coreXml = "<?xml version=`"1.0`" encoding=`"UTF-8`" standalone=`"yes`"?><cp:coreProperties xmlns:cp=`"http://schemas.openxmlformats.org/package/2006/metadata/core-properties`" xmlns:dc=`"http://purl.org/dc/elements/1.1/`" xmlns:dcterms=`"http://purl.org/dc/terms/`" xmlns:dcmitype=`"http://purl.org/dc/dcmitype/`" xmlns:xsi=`"http://www.w3.org/2001/XMLSchema-instance`"><dc:title>$(XmlEscape $title)</dc:title><dc:creator>Tim Pengembang Sistem</dc:creator><cp:lastModifiedBy>Tim Pengembang Sistem</cp:lastModifiedBy><dcterms:created xsi:type=`"dcterms:W3CDTF`">$created</dcterms:created><dcterms:modified xsi:type=`"dcterms:W3CDTF`">$created</dcterms:modified></cp:coreProperties>"
$appXml = "<?xml version=`"1.0`" encoding=`"UTF-8`" standalone=`"yes`"?><Properties xmlns=`"http://schemas.openxmlformats.org/officeDocument/2006/extended-properties`" xmlns:vt=`"http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes`"><Application>Codex</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><Company>Tim Pengembang Sistem</Company></Properties>"

$fullOut = Join-Path (Get-Location) $OutPath
$outDir = Split-Path $fullOut -Parent
if (!(Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
if (Test-Path $fullOut) { Remove-Item -LiteralPath $fullOut -Force }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function AddZipTextEntry($Archive, [string]$EntryName, [string]$Content) {
    $entry = $Archive.CreateEntry($EntryName)
    $stream = $entry.Open()
    $writer = New-Object System.IO.StreamWriter($stream, [System.Text.UTF8Encoding]::new($false))
    $writer.Write($Content)
    $writer.Dispose()
    $stream.Dispose()
}

$archive = [System.IO.Compression.ZipFile]::Open($fullOut, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    AddZipTextEntry $archive "[Content_Types].xml" $contentTypes
    AddZipTextEntry $archive "_rels/.rels" $rels
    AddZipTextEntry $archive "word/document.xml" $documentXml
    AddZipTextEntry $archive "word/styles.xml" $stylesXml
    AddZipTextEntry $archive "word/numbering.xml" $numberingXml
    AddZipTextEntry $archive "word/settings.xml" $settingsXml
    AddZipTextEntry $archive "word/_rels/document.xml.rels" $docRels
    AddZipTextEntry $archive "docProps/core.xml" $coreXml
    AddZipTextEntry $archive "docProps/app.xml" $appXml
}
finally {
    $archive.Dispose()
}

Write-Output $fullOut
