<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerTiers;
use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public const SORTS = ['newest', 'oldest', 'name', 'name_desc', 'age_asc', 'age_desc', 'birthday', 'spent', 'spent_asc'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'newest';
        $scope = $request->query('scope') === 'extra' ? 'extra' : 'basic';
        $tier = is_numeric($request->query('tier')) ? (int) $request->query('tier') : null;

        $customers = Customer::query()
            ->search($search, $scope)
            ->inTier($tier)
            ->orderedBy($sort)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'avatar_url' => $c->avatar_url,
                'phone' => $c->phone,
                'address' => $c->address,
                'occupation' => $c->occupation,
                'note' => $c->note,
                'birth_date' => $c->birth_date?->format('Y-m-d'),
                'birth_year' => $c->birth_year,
                'birthday_in' => $this->birthdayIn($c->birth_date),
                'total_spent' => (float) $c->total_spent,
                'tier' => CustomerTiers::for($c->total_spent),
            ]);

        $tiers = CustomerTiers::config();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search, 'scope' => $scope, 'sort' => $sort, 'tier' => $tier],
            'tiersEnabled' => $tiers['enabled'],
            'tiers' => $tiers['tiers'],
            'avatarPresets' => User::avatarPresets(),
            // Nghề nghiệp đã dùng nhiều nhất trong cửa hàng, để gợi ý nhanh
            'occupations' => Customer::query()
                ->whereNotNull('occupation')
                ->where('occupation', '!=', '')
                ->groupBy('occupation')
                ->orderByRaw('count(*) desc')
                ->limit(30)
                ->pluck('occupation'),
            'importResult' => session('import_result'),
        ]);
    }

    /** Chi tiết khách (lịch sử mua hàng) cho khung xem nhanh. */
    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer->purchaseHistory());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = DocumentNumber::next('customer');

        $customer = Customer::create($data);
        $this->applyAvatar($request, $customer);

        return redirect()->route('customers.index')->with('success', 'Đã thêm khách hàng.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));
        $this->applyAvatar($request, $customer);

        return redirect()->route('customers.index')->with('success', 'Đã cập nhật khách hàng.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ((float) $customer->total_spent > 0) {
            return back()->with('error', 'Khách đã có giao dịch, không thể xóa.');
        }

        $customer->deleteUploadedAvatar();
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Đã xóa khách hàng.');
    }

    /**
     * Kiểm tra trùng số điện thoại ngay khi nhập.
     * Trả về cùng định dạng với users.check để dùng chung composable useUniqueCheck.
     */
    public function check(Request $request): JsonResponse
    {
        abort_unless($request->input('field') === 'phone', 422);

        $phone = preg_replace('/\D/', '', (string) $request->input('value'));
        $ignore = $request->integer('ignore') ?: null;

        if ($phone === '') {
            return response()->json(['valid' => true, 'available' => true]);
        }
        if (! preg_match('/^\d{10}$/', $phone)) {
            return response()->json(['valid' => false, 'message' => 'Số điện thoại gồm đúng 10 số.']);
        }

        $found = Customer::where('phone', $phone)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->first(['id', 'name']);

        return response()->json([
            'valid' => true,
            'available' => ! $found,
            'message' => $found ? "Số này đã dùng cho khách \"{$found->name}\"." : null,
        ]);
    }

    /** Số ngày tới sinh nhật kế tiếp (0 = hôm nay); null nếu chưa biết đủ ngày tháng. */
    private function birthdayIn(?CarbonInterface $birth): ?int
    {
        if (! $birth) {
            return null;
        }

        $today = now()->startOfDay();
        $next = $birth->copy()->setYear($today->year)->startOfDay();
        if ($next->lt($today)) {
            $next = $birth->copy()->setYear($today->year + 1)->startOfDay();
        }

        return (int) $today->diffInDays($next);
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'phone' => preg_replace('/\D+/', '', (string) $request->input('phone')) ?: null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'digits:10', Rule::unique('customers', 'phone')->ignore($customer?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_year' => ['nullable', 'integer', 'between:1900,'.now()->year],
            'avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'avatar_preset' => ['nullable', Rule::in(User::AVATAR_PRESETS)],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Vui lòng nhập họ tên.',
            'phone.digits' => 'Số điện thoại gồm đúng 10 số.',
            'phone.unique' => 'Số điện thoại này đã có khách hàng khác sử dụng.',
            'birth_date.date_format' => 'Ngày sinh không hợp lệ.',
            'birth_date.before_or_equal' => 'Ngày sinh không được ở tương lai.',
            'birth_year.integer' => 'Năm sinh không hợp lệ.',
            'birth_year.between' => 'Năm sinh phải từ 1900 đến năm hiện tại.',
            'avatar_file.image' => 'Ảnh đại diện phải là JPG, PNG hoặc WebP.',
            'avatar_file.mimes' => 'Ảnh đại diện phải là JPG, PNG hoặc WebP.',
            'avatar_file.max' => 'Ảnh đại diện tối đa 2 MB.',
        ]);

        // Có đủ ngày sinh thì lấy năm từ đó; chỉ có năm thì để trống ngày.
        if (! empty($data['birth_date'])) {
            $data['birth_year'] = (int) substr($data['birth_date'], 0, 4);
        } else {
            $data['birth_date'] = null;
        }

        return Arr::except($data, ['avatar_file', 'avatar_preset', 'remove_avatar']);
    }

    /** Ảnh tải lên > ảnh có sẵn > xóa ảnh. */
    private function applyAvatar(Request $request, Customer $customer): void
    {
        if ($request->hasFile('avatar_file')) {
            $file = $request->file('avatar_file');
            $name = Str::uuid().'.'.$file->extension();
            $file->move(public_path('uploads/avatars'), $name);

            $customer->deleteUploadedAvatar();
            $customer->avatar = 'uploads/avatars/'.$name;
        } elseif ($request->filled('avatar_preset')) {
            $customer->deleteUploadedAvatar();
            $customer->avatar = 'preset:'.$request->input('avatar_preset');
        } elseif ($request->boolean('remove_avatar')) {
            $customer->deleteUploadedAvatar();
            $customer->avatar = null;
        } else {
            return;
        }

        $customer->save();
    }
}
