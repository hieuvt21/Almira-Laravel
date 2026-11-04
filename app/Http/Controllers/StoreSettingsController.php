<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Store/Edit', [
            'store' => Setting::storeInfo(),
            'numbering' => DocumentNumber::config(),
            'types' => collect(DocumentNumber::TYPES)
                ->map(fn (array $t, string $key) => ['key' => $key, 'label' => $t['label']])
                ->values(),
            'counters' => DocumentNumber::counters(),
        ]);
    }

    /** Tab "Cửa hàng". */
    public function updateInfo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 .()+\-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_code' => ['nullable', 'regex:/^\d{10}(-?\d{3})?$/'],
            'owner' => ['nullable', 'string', 'max:100'],
        ], [
            'phone.regex' => 'Số điện thoại chỉ gồm chữ số và các ký tự + - . ( ).',
            'email.email' => 'Email chưa đúng định dạng.',
            'tax_code.regex' => 'Mã số thuế gồm 10 số, hoặc 13 số (có thể có dấu gạch ngang trước 3 số cuối).',
        ]);

        Setting::putJson('store_info', array_map(
            fn ($v) => trim((string) $v),
            $data + Setting::storeInfo(),
        ));

        return back()->with('success', 'Đã lưu thông tin cửa hàng.');
    }

    /** Tab "Tiền tệ & thuế". */
    public function updateFinance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency_symbol' => ['present', 'nullable', Rule::in(['₫', 'đ', 'VND', null, ''])],
            'symbol_position' => ['required', Rule::in(['before', 'after'])],
            'number_format' => ['required', Rule::in(['vi', 'en', 'space'])],
            'decimals' => ['required', 'integer', 'between:0,2'],
            'rounding_step' => ['required', Rule::in([1, 10, 100, 500, 1000])],
            'rounding_mode' => ['required', Rule::in(['nearest', 'up', 'down'])],
            'tax_enabled' => ['required', 'boolean'],
            'tax_rates' => ['required', 'array', 'min:1', 'max:8'],
            'tax_rates.*' => ['required', 'numeric', 'between:0,100', 'distinct'],
            'tax_default_rate' => ['required', 'numeric'],
            'prices_include_tax' => ['required', 'boolean'],
        ], [
            'tax_rates.min' => 'Cần ít nhất một mức thuế.',
            'tax_rates.max' => 'Tối đa 8 mức thuế.',
            'tax_rates.*.between' => 'Mức thuế phải từ 0 đến 100%.',
            'tax_rates.*.distinct' => 'Có mức thuế bị trùng.',
        ]);

        $rates = array_map('floatval', $data['tax_rates']);
        sort($rates);
        $default = (float) $data['tax_default_rate'];

        if (! in_array($default, $rates, true)) {
            throw ValidationException::withMessages([
                'tax_default_rate' => 'Mức thuế mặc định phải nằm trong danh sách các mức thuế.',
            ]);
        }

        Setting::putJson('finance', [
            'currency_symbol' => (string) ($data['currency_symbol'] ?? ''),
            'symbol_position' => $data['symbol_position'],
            'number_format' => $data['number_format'],
            'decimals' => (int) $data['decimals'],
            'rounding_step' => (int) $data['rounding_step'],
            'rounding_mode' => $data['rounding_mode'],
            'tax_enabled' => (bool) $data['tax_enabled'],
            'tax_rates' => $rates,
            'tax_default_rate' => $default,
            'prices_include_tax' => (bool) $data['prices_include_tax'],
        ]);

        return back()->with('success', 'Đã lưu cài đặt tiền tệ & thuế.');
    }

    /** Tab "Đánh số chứng từ". */
    public function updateNumbering(Request $request): RedirectResponse
    {
        // Tiền tố luôn viết hoa, bỏ khoảng trắng
        $numbering = collect($request->input('numbering', []))
            ->map(fn ($row) => is_array($row)
                ? [...$row, 'prefix' => strtoupper(trim((string) ($row['prefix'] ?? '')))]
                : $row)
            ->all();
        $request->merge(['numbering' => $numbering]);

        $rules = [];
        foreach (array_keys(DocumentNumber::TYPES) as $key) {
            $rules["numbering.$key.prefix"] = ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'];
            $rules["numbering.$key.date_format"] = ['required', Rule::in(DocumentNumber::DATE_FORMATS)];
            $rules["numbering.$key.separator"] = ['required', Rule::in(array_keys(DocumentNumber::SEPARATORS))];
            $rules["numbering.$key.padding"] = ['required', 'integer', 'between:3,8'];
        }

        $data = $request->validate($rules, [
            'numbering.*.prefix.required' => 'Nhập tiền tố.',
            'numbering.*.prefix.regex' => 'Tiền tố chỉ gồm chữ không dấu và số.',
            'numbering.*.prefix.max' => 'Tiền tố tối đa 10 ký tự.',
            'numbering.*.padding.between' => 'Số chữ số từ 3 đến 8.',
        ]);

        // Hai loại chứng từ không được dùng chung một tiền tố
        $seen = [];
        $errors = [];
        foreach ($data['numbering'] as $key => $row) {
            if (isset($seen[$row['prefix']])) {
                $errors["numbering.$key.prefix"] = 'Tiền tố này đã dùng cho loại khác.';
            }
            $seen[$row['prefix']] = true;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        Setting::putJson('numbering', collect($data['numbering'])->map(fn ($row) => [
            'prefix' => $row['prefix'],
            'date_format' => $row['date_format'],
            'separator' => $row['separator'],
            'padding' => (int) $row['padding'],
        ])->all());

        return back()->with('success', 'Đã lưu cách đánh số chứng từ.');
    }
}
