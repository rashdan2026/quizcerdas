<?php
// This view generates an HTML table that Excel can open as .xls file
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Rekap Absensi</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
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
        .meeting-header {
            background-color: #EEF2FF;
            font-weight: bold;
        }
        .number {
            mso-number-format:"0";
        }
    </style>
</head>
<body>
<table border="1">
    <tr class="header">
        <th colspan="8">REKAP ABSENSI MAHASISWA</th>
    </tr>
    <?php if ($selectedKelas): ?>
    <tr class="header">
        <th colspan="8">Filter Kelas: <?= esc($selectedKelas) ?></th>
    </tr>
    <?php endif; ?>
    <tr class="header">
        <th colspan="8">Tanggal Export: <?= date('d/m/Y H:i:s') ?></th>
    </tr>
    <tr></tr>
    <?php foreach ($meetings as $index => $meeting): 
        $meetingId = $meeting['id'];
        $students = $groupedData[$meetingId]['students'] ?? [];
    ?>
    <tr class="meeting-header">
        <td colspan="8"><?= esc($meeting['kode_mk']) ?> - Pertemuan <?= esc($meeting['pertemuan_ke']) ?>: <?= esc($meeting['judul']) ?></td>
    </tr>
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
        <td colspan="8" style="font-weight:bold;">Total: <?= count($students) ?> mahasiswa</td>
    </tr>
    <tr></tr>
    <?php endforeach; ?>
</table>
</body>
</html>
