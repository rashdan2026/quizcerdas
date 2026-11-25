<?php

namespace App\Libraries\Quiz;

/**
 * Generator file .xlsx sederhana (tanpa dependensi eksternal).
 * Menggunakan ZipArchive: worksheet tunggal + sharedStrings minimal.
 */
class XlsxExporter
{
    private array $rows = [];   // baris data (array of array of scalar)
    private array $headers = [];

    public function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    public function addRow(array $row): void
    {
        $this->rows[] = $row;
    }

    /** Simpan ke file dan return path. */
    public function save(string $path): string
    {
        $xml = $this->buildXml();

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Gagal membuat file Excel.');
        }
        $zip->addFromString('[Content_Types].xml', $xml['content_types']);
        $zip->addFromString('_rels/.rels', $xml['rels']);
        $zip->addFromString('xl/workbook.xml', $xml['workbook']);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $xml['workbook_rels']);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml['sheet']);
        $zip->addFromString('xl/styles.xml', $xml['styles']);
        $zip->close();

        return $path;
    }

    private function colLetter(int $n): string
    {
        $s = '';
        while ($n >= 0) {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        }

        return $s;
    }

    private function cell(int $col, int $row, $value): string
    {
        $ref = $this->colLetter($col) . ($row + 1);

        if (is_numeric($value) && ! preg_match('/^0[0-9]/', (string) $value)) {
            return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
        }

        return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars((string) $value, ENT_XML1) . '</t></is></c>';
    }

    private function buildXml(): array
    {
        // Header = baris 1, data mulai baris 2
        $rowsXml = '<row r="1">';
        foreach ($this->headers as $i => $h) {
            $rowsXml .= $this->cell($i, 0, $h);
        }
        $rowsXml .= '</row>';

        foreach ($this->rows as $r => $row) {
            $rowsXml .= '<row r="' . ($r + 2) . '">';
            foreach ($row as $i => $v) {
                $rowsXml .= $this->cell($i, $r + 1, $v);
            }
            $rowsXml .= '</row>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rowsXml . '</sheetData></worksheet>';

        return [
            'content_types' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            'rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'workbook' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Nilai Kuis" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'workbook_rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'styles' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                . '<borders count="1"><border/></borders>'
                . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
                . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
                . '</styleSheet>',
            'sheet' => $sheet,
        ];
    }
}
