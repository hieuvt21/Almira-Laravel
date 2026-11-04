<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    /** Trang Giao diện: ai cũng vào được (phần cá nhân hóa). Phần cửa hàng cần quyền settings.manage. */
    public function edit(): Response
    {
        return Inertia::render('Appearance/Edit', [
            'themes' => Setting::THEMES,
            'icons' => Setting::LOGO_ICONS,
        ]);
    }

    /** Lưu tên cửa hàng, logo, màu chủ đề (dùng chung toàn hệ thống). */
    public function updateBranding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:60'],
            'theme' => ['required', Rule::in(Setting::THEMES)],
            'logo_type' => ['required', Rule::in(['icon', 'image'])],
            'logo_icon' => ['required', Rule::in(Setting::LOGO_ICONS)],
            'logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'shop_name.required' => 'Vui lòng nhập tên cửa hàng.',
            'logo_file.max' => 'Ảnh logo tối đa 1 MB.',
            'logo_file.image' => 'Logo phải là ảnh JPG, PNG hoặc WebP.',
        ]);

        $values = [
            'shop_name' => trim($data['shop_name']),
            'theme' => $data['theme'],
            'logo_type' => $data['logo_type'],
            'logo_icon' => $data['logo_icon'],
        ];

        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            $name = 'logo-'.Str::random(12).'.'.$file->extension();
            $file->move(public_path('uploads/branding'), $name);

            $this->deleteOldLogo();
            $values['logo_image'] = 'uploads/branding/'.$name;
            $values['logo_type'] = 'image';
        } elseif ($data['logo_type'] === 'image' && ! Setting::read('logo_image')) {
            return back()->withErrors(['logo_file' => 'Hãy chọn một ảnh logo để tải lên.']);
        }

        Setting::write($values);

        return back()->with('success', 'Đã lưu giao diện cửa hàng.');
    }

    private function deleteOldLogo(): void
    {
        $old = Setting::read('logo_image');

        if ($old && str_starts_with($old, 'uploads/branding/')) {
            @unlink(public_path($old));
        }
    }
}
