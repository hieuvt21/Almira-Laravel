<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\CustomerTiers;
use App\Support\DocumentNumber;
use App\Support\Xlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Xuất / nhập khách hàng bằng Excel (.xlsx), nhập được cả .csv. */
class CustomerExchangeController extends Controller
{
    private const MAX_ROWS = 2000;
    private const MAX_ERRORS = 100;

    /** Cột nhận diện được khi nhập (tên tiêu đề đã bỏ dấu, viết thường). */
    private const HEADERS = [
        'name' => ['ho ten', 'ho va ten', 'ten', 'ten khach hang', 'name'],
        'phone' => ['so dien thoai', 'dien thoai', 'sdt', 'phone'],
        'birth' => ['ngay sinh', 'nam sinh', 'sinh nhat', 'birth'],
        'occupation' => ['nghe nghiep'],
        'address' => ['dia chi'],
        'note' => ['ghi chu'],
    ];

    public function export(Request $request): BinaryFileResponse
    {
        $sort = in_array($request->query('sort'), CustomerController::SORTS, true) ? $request->query('sort') : 'newest';
        $tier = is_numeric($request->query('tier')) ? (int) $request->query('tier') : null;
        $tiersOn = CustomerTiers::enabled();

        $header = ['Mã khách', 'Họ tên', 'Số điện thoại', 'Ngày sinh', 'Nghề nghiệp', 'Địa chỉ', 'Ghi chú', 'Chi tiêu'];
        if ($tiersOn) {
            $header[] = 'Hạng';
        }
        $rows = [$header];

        Customer::query()
            ->search((string) $request->query('search', ''), in_array($request->query('scope'), ['extra', 'year'], true) ? $request->query('scope') : 'basic')
            ->inTier($tier)
            ->orderedBy($sort)
            ->each(function (Customer $c) use (&$rows, $tiersOn) {
                $row = [
                    $c->code,
                    $c->name,
                    $c->phone ? $this->formatPhone($c->phone) : '',
                    $c->birth_date ? $c->birth_date->format('d/m/Y') : ($c->birth_year ? (string) $c->birth_year : ''),
                    $c->occupation,
                    $c->address,
                    $c->note,
                    (float) $c->total_spent,
                ];
                if ($tiersOn) {
                    $row[] = CustomerTiers::for($c->total_spent)['name'] ?? '';
                }
                $rows[] = $row;
            });

        return $this->download($rows, 'khach-hang-'.now()->format('Ymd'), [12, 28, 16, 13, 20, 36, 30, 16, 14], [7]);
    }

    public function template(): BinaryFileResponse
    {
        return $this->download(
            [['Họ tên', 'Số điện thoại', 'Ngày sinh', 'Nghề nghiệp', 'Địa chỉ', 'Ghi chú']],
            'mau-nhap-khach-hang',
            [28, 16, 13, 20, 36, 30],
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120']], [
            'file.required' => 'Hãy chọn file cần nhập.',
            'file.max' => 'File tối đa 5 MB.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['file' => 'Chỉ nhận file .xlsx hoặc .csv.']);
        }

        try {
            $rows = $ext === 'xlsx' ? Xlsx::read($file->getRealPath()) : $this->readCsv($file->getRealPath());
        } catch (Throwable) {
            return back()->withErrors(['file' => 'Không đọc được file. Hãy dùng file mẫu hoặc file đã xuất từ hệ thống.']);
        }

        // Dòng tiêu đề = dòng đầu tiên có chữ
        $rows = array_values(array_filter($rows, fn ($r) => array_filter($r['cells'], fn ($v) => $v !== '') !== []));
        $head = array_shift($rows);
        $map = $head ? $this->mapColumns($head['cells']) : [];

        if (! isset($map['name'])) {
            return back()->withErrors(['file' => 'Không thấy cột "Họ tên" ở dòng tiêu đề. Hãy dùng file mẫu.']);
        }
        if (count($rows) > self::MAX_ROWS) {
            return back()->withErrors(['file' => 'Mỗi lần nhập tối đa '.self::MAX_ROWS.' dòng.']);
        }

        $existing = array_flip(Customer::whereNotNull('phone')->pluck('phone')->all());
        $created = 0;
        $errors = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($row['cells'][$map[$key]] ?? '')) : '';

            $name = trim(preg_replace('/\s+/u', ' ', $get('name')));
            if ($name === '') {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'message' => 'Thiếu họ tên.'];
                continue;
            }
            if (mb_strlen($name) > 255) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'message' => 'Họ tên quá dài (tối đa 255 ký tự).'];
                continue;
            }

            $phone = preg_replace('/\D+/', '', $get('phone'));
            if ($phone !== '' && strlen($phone) === 9) {
                $phone = '0'.$phone; // Excel hay làm mất số 0 đầu
            }
            if ($phone !== '' && ! preg_match('/^\d{10}$/', $phone)) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'message' => "Số điện thoại \"{$get('phone')}\" không đủ 10 số."];
                continue;
            }
            if ($phone !== '' && isset($existing[$phone])) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'message' => 'Số điện thoại '.$this->formatPhone($phone).' đã tồn tại.'];
                continue;
            }

            try {
                [$birthDate, $birthYear] = $this->parseBirth($get('birth'));
            } catch (\InvalidArgumentException $e) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'message' => $e->getMessage()];
                continue;
            }

            Customer::create([
                'code' => DocumentNumber::next('customer'),
                'name' => $name,
                'phone' => $phone ?: null,
                'birth_date' => $birthDate,
                'birth_year' => $birthYear,
                'occupation' => Str::limit($get('occupation'), 255, '') ?: null,
                'address' => Str::limit($get('address'), 255, '') ?: null,
                'note' => Str::limit($get('note'), 2000, '') ?: null,
            ]);

            if ($phone !== '') {
                $existing[$phone] = true;
            }
            $created++;
        }

        $result = [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, self::MAX_ERRORS),
            'more_errors' => max(0, count($errors) - self::MAX_ERRORS),
        ];

        $redirect = redirect()->route('customers.index')->with('import_result', $result);

        return $created > 0
            ? $redirect->with('success', "Đã nhập {$created} khách hàng.")
            : $redirect->with('error', 'Không nhập được dòng nào.');
    }

    /* ---------- Hỗ trợ ---------- */

    private function download(array $rows, string $filename, array $widths, array $numberColumns = []): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kh');
        Xlsx::write($tmp, $rows, $widths, $numberColumns);

        return response()
            ->download($tmp, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend(true);
    }

    private function formatPhone(string $digits): string
    {
        return implode('.', array_filter([substr($digits, 0, 4), substr($digits, 4, 3), substr($digits, 7, 3)], fn ($p) => $p !== ''));
    }

    /** @return array<string, int> khóa => chỉ số cột */
    private function mapColumns(array $cells): array
    {
        $map = [];
        foreach ($cells as $i => $title) {
            $key = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $title))));
            foreach (self::HEADERS as $field => $aliases) {
                if (! isset($map[$field]) && in_array($key, $aliases, true)) {
                    $map[$field] = $i;
                }
            }
        }

        return $map;
    }

    private function readCsv(string $path): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $first = strtok($text, "\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $rows = [];
        $n = 0;
        while (($cells = fgetcsv($stream, 0, $delimiter)) !== false) {
            $n++;
            $rows[] = ['row' => $n, 'cells' => array_map(fn ($v) => trim((string) $v), $cells)];
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Ngày sinh: dd/mm/yyyy, yyyy-mm-dd, chỉ năm (1990) hoặc số ngày của Excel.
     *
     * @return array{0: ?string, 1: ?int} [ngày Y-m-d, năm]
     */
    private function parseBirth(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [null, null];
        }

        $year = (int) now()->year;
        $fail = fn () => new \InvalidArgumentException("Ngày sinh \"{$value}\" không hợp lệ (dùng dd/mm/yyyy hoặc chỉ năm).");

        if (preg_match('/^\d{4}$/', $value)) {
            if ((int) $value < 1900 || (int) $value > $year) {
                throw $fail();
            }

            return [null, (int) $value];
        }

        $y = $m = $d = null;
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $value, $p)) {
            [$d, $m, $y] = [(int) $p[1], (int) $p[2], (int) $p[3]];
        } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $value, $p)) {
            [$y, $m, $d] = [(int) $p[1], (int) $p[2], (int) $p[3]];
        } elseif (preg_match('/^\d{4,6}(\.0+)?$/', $value)) {
            $date = \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value); // số ngày Excel
            [$y, $m, $d] = [$date->year, $date->month, $date->day];
        } else {
            throw $fail();
        }

        if (! checkdate($m, $d, $y) || $y < 1900 || \Carbon\Carbon::create($y, $m, $d)->isFuture()) {
            throw $fail();
        }

        return [sprintf('%04d-%02d-%02d', $y, $m, $d), $y];
    }
}
