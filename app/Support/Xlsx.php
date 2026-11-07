<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Đọc/ghi file .xlsx đơn giản (một sheet, chỉ chữ và số), không cần thư viện ngoài.
 */
class Xlsx
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows  dòng đầu là tiêu đề
     * @param  array<int, float>  $widths  độ rộng từng cột
     * @param  array<int>  $numberColumns  chỉ số cột (từ 0) hiển thị số có dấu phân cách nghìn
     */
    public static function write(string $path, array $rows, array $widths = [], array $numberColumns = []): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được file Excel.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Khách hàng" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        $cols = '';
        foreach ($widths as $i => $w) {
            $n = $i + 1;
            $cols .= '<col min="'.$n.'" max="'.$n.'" width="'.$w.'" customWidth="1"/>';
        }

        $data = '';
        foreach (array_values($rows) as $r => $cells) {
            $rn = $r + 1;
            $data .= '<row r="'.$rn.'">';
            foreach (array_values($cells) as $c => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $ref = self::columnName($c).$rn;
                if ($r > 0 && (is_int($v) || is_float($v))) {
                    $style = in_array($c, $numberColumns, true) ? ' s="2"' : '';
                    $data .= '<c r="'.$ref.'"'.$style.'><v>'.$v.'</v></c>';
                } else {
                    $style = $r === 0 ? ' s="1"' : '';
                    $data .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.self::escape((string) $v).'</t></is></c>';
                }
            }
            $data .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$data.'</sheetData></worksheet>');

        $zip->close();
    }

    /**
     * Đọc sheet đầu tiên. Trả về danh sách ['row' => số dòng trong file, 'cells' => [chuỗi...]].
     *
     * @return array<int, array{row: int, cells: array<int, string>}>
     */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel không hợp lệ.');
        }

        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            foreach (self::xml($ss)->xpath('//si') ?: [] as $si) {
                $text = '';
                foreach ($si->xpath('t|r/t') ?: [] as $t) {
                    $text .= (string) $t;
                }
                $shared[] = $text;
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $name)) {
                    $sheet = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheet === false || $sheet === null) {
            throw new RuntimeException('Không tìm thấy dữ liệu trong file Excel.');
        }

        $rows = [];
        foreach (self::xml($sheet)->xpath('//sheetData/row') ?: [] as $row) {
            $cells = [];
            $max = -1;

            foreach ($row->c as $c) {
                $col = self::columnIndex((string) preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];

                if ($type === 's') {
                    $value = $shared[(int) $c->v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($c->xpath('is/t|is/r/t') ?: [] as $t) {
                        $value .= (string) $t;
                    }
                } else {
                    $value = (string) $c->v;
                }

                $cells[$col] = trim($value);
                $max = max($max, $col);
            }

            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[$i] = $cells[$i] ?? '';
            }
            $rows[] = ['row' => (int) $row['r'], 'cells' => $line];
        }

        return $rows;
    }

    private static function xml(string $content): SimpleXMLElement
    {
        // Bỏ khai báo namespace mặc định để dùng xpath đơn giản
        $content = preg_replace('/\sxmlns="[^"]*"/', '', $content, 1);

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (! $xml) {
            throw new RuntimeException('File Excel không đọc được.');
        }

        return $xml;
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function columnName(int $index): string
    {
        $name = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $name = chr(65 + ($i - 1) % 26).$name;
        }

        return $name;
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}
