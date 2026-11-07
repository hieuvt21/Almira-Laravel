<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\CustomerTiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerTierController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Customers/Tiers', [
            'config' => CustomerTiers::config(),
            'icons' => CustomerTiers::ICONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'tiers' => ['required', 'array', 'min:1', 'max:8'],
            'tiers.*.name' => ['required', 'string', 'max:30'],
            'tiers.*.min_spent' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'tiers.*.discount' => ['required', 'numeric', 'between:0,100'],
            'tiers.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tiers.*.icon' => ['required', Rule::in(CustomerTiers::ICONS)],
        ], [
            'tiers.*.color.required' => 'Chọn màu cho hạng.',
            'tiers.*.color.regex' => 'Màu không hợp lệ.',
            'tiers.*.icon.required' => 'Chọn biểu tượng cho hạng.',
            'tiers.*.icon.in' => 'Biểu tượng không hợp lệ.',
            'tiers.min' => 'Cần ít nhất một hạng.',
            'tiers.max' => 'Tối đa 8 hạng.',
            'tiers.*.name.required' => 'Nhập tên hạng.',
            'tiers.*.name.max' => 'Tên hạng tối đa 30 ký tự.',
            'tiers.*.min_spent.required' => 'Nhập mức chi tiêu.',
            'tiers.*.min_spent.min' => 'Mức chi tiêu không được âm.',
            'tiers.*.discount.required' => 'Nhập % giảm giá.',
            'tiers.*.discount.between' => 'Giảm giá phải từ 0 đến 100%.',
        ]);

        $tiers = array_values(array_map(fn ($t) => [
            'name' => trim($t['name']),
            'min_spent' => (float) $t['min_spent'],
            'discount' => (float) $t['discount'],
            'color' => strtolower($t['color']),
            'icon' => $t['icon'],
        ], $data['tiers']));

        $errors = [];
        if ($tiers[0]['min_spent'] != 0.0) {
            $errors['tiers.0.min_spent'] = 'Hạng đầu tiên phải bắt đầu từ 0.';
        }
        $names = [];
        foreach ($tiers as $i => $tier) {
            if ($i > 0 && $tier['min_spent'] <= $tiers[$i - 1]['min_spent']) {
                $errors["tiers.$i.min_spent"] = 'Mức chi tiêu phải lớn hơn hạng ngay trên.';
            }
            $key = mb_strtolower($tier['name']);
            if (isset($names[$key])) {
                $errors["tiers.$i.name"] = 'Tên hạng bị trùng.';
            }
            $names[$key] = true;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        Setting::putJson('customer_tiers', ['enabled' => (bool) $data['enabled'], 'tiers' => $tiers]);

        return back()->with('success', 'Đã lưu cài đặt hạng khách hàng.');
    }
}
