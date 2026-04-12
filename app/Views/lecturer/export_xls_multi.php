<?php
// This view generates an HTML table with multiple sheets for Excel
// Each meeting gets its own sheet
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <?php foreach ($meetings as $index => $meeting): ?>
                <x:ExcelWorksheet>
                    <x:Name>pertemuan-<?= esc($meeting['pertemuan_ke']) ?></x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
                <?php endforeach; ?>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        td, th {
            mso-number-format:"\@";
            white-space: normal;
        }
        .header {
            background-color: #4F46E5;
            color: #FFFFFF;
            font-weight: bold;
        }
        .info-row {
            background-color: #EEF2FF;
            font-weight: bold;
        }
        .number {
            mso-number-format:"0";
        }
        .sheet-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

<?php foreach ($meetings as $sheetIndex => $meeting): 
    $students = $attendanceData[$meeting['id']] ?? [];
    $sheetClass = $sheetIndex > 0 ? 'sheet-break' : '';
?>
<div class="<?= $sheetClass ?>">
<table border="1">
    <tr class="header">
        <th colspan="8">REKAP ABSENSI MAHASISWA</th>
    </tr>
    <tr class="info-row">
        <th colspan="8">Matakuliah: <?= esc($subject['kode_mk']) ?> - <?= esc($subject['nama_mk']) ?></th>
    </tr>
    <tr class="info-row">
        <th colspan="8">Pertemuan <?= esc($meeting['pertemuan_ke']) ?>: <?= esc($meeting['judul']) ?></th>
    </tr>
    <?php if (!empty($meeting['deskripsi'])): ?>
    <tr class="info-row">
        <th colspan="8">Deskripsi: <?= esc($meeting['deskripsi']) ?></th>
    </tr>
    <?php endif; ?>
    <tr class="info-row">
        <th colspan="8">Waktu Berakhir: <?= esc($meeting['expired_at']) ?></th>
    </tr>
    <tr></tr>
    <tr class="header">
        <th>No</th>
        <th>NPM</th>
        <th>Nama Mahasiswa</th>
        <th>Email</th>
        <th>Kelas</th>
        <th>Waktu Absen</th>
        <th>Latitude</th>
        <th>Longitude</th>
    </tr>
    <?php if (empty($students)): ?>
    <tr>
        <td colspan="8" style="text-align:center;color:#999;">Tidak ada data absensi</td>
    </tr>
    <?php else: ?>
    <?php foreach ($students as $i => $student): ?>
    <tr>
        <td class="number"><?= $i + 1 ?></td>
        <td><?= esc($student['npm'] ?? '-') ?></td>
        <td><?= esc($student['nama']) ?></td>
        <td><?= esc($student['email']) ?></td>
        <td><?= esc($student['kelas'] ?? '-') ?></td>
        <td><?= esc($student['waktu_absen']) ?></td>
        <td><?= esc($student['latitude'] ?? '-') ?></td>
        <td><?= esc($student['longitude'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
    <tr>
        <td colspan="8" style="font-weight:bold;background:#F0F9FF;">Total: <?= count($students) ?> mahasiswa</td>
    </tr>
</table>
</div>
<?php endforeach; ?>

</body>
</html>
