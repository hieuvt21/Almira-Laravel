<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Unit;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $products = Product::with(['category:id,name', 'units.unit:id,name', 'units.prices'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'barcode' => $p->barcode,
                'category_id' => $p->category_id,
                'category' => $p->category?->name,
                'tax_rate' => $p->tax_rate,
                'description' => $p->description,
                'is_active' => $p->is_active,
                'image_url' => $p->image_url,
                'units' => $p->units->map(fn ($u) => [
                    'id' => $u->id,
                    'unit_id' => $u->unit_id,
                    'unit' => $u->unit?->name,
                    'factor' => $u->factor,
                    'prices' => $u->prices->map(fn ($pr) => [
                        'price_list_id' => $pr->price_list_id,
                        'price' => $pr->price,
                    ])->values(),
                ])->values(),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name', 'is_active']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'is_active']),
            'priceLists' => PriceList::orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            $product = new Product($this->productFields($data));
            $product->code = $data['code'] ?: $this->nextCode();
            $product->image = $this->storeImage($request);
            $product->save();
            $this->syncUnits($product, $data['units']);
        });

        return redirect()->route('products.index')->with('success', 'Đã thêm sản phẩm.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($request, $data, $product) {
            $product->fill($this->productFields($data));
            if ($data['code']) {
                $product->code = $data['code'];
            }

            if ($request->hasFile('image_file')) {
                $product->deleteUploadedImage();
                $product->image = $this->storeImage($request);
            } elseif ($request->boolean('remove_image')) {
                $product->deleteUploadedImage();
                $product->image = null;
            }

            $product->save();
            $this->syncUnits($product, $data['units']);
        });

        return redirect()->route('products.index')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->deleteUploadedImage();
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Đã xóa sản phẩm.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $id = $product?->id;

        $validator = validator($request->all(), [
            'code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('products', 'barcode')->ignore($id)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'units' => ['required', 'array', 'min:1', 'max:6'],
            'units.*.unit_id' => ['required', 'distinct', Rule::exists('units', 'id')],
            'units.*.factor' => ['required', 'integer', 'min:1', 'max:100000'],
            'units.*.prices' => ['nullable', 'array'],
            'units.*.prices.*.price_list_id' => ['required', Rule::exists('price_lists', 'id')],
            'units.*.prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'code.regex' => 'Mã sản phẩm chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'code.unique' => 'Mã sản phẩm này đã tồn tại.',
            'code.max' => 'Mã sản phẩm tối đa 50 ký tự.',
            'name.required' => 'Vui lòng nhập tên sản phẩm.',
            'name.max' => 'Tên sản phẩm tối đa 255 ký tự.',
            'barcode.regex' => 'Mã vạch chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'barcode.unique' => 'Mã vạch này đã được dùng cho sản phẩm khác.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'tax_rate.required' => 'Vui lòng chọn mức thuế.',
            'tax_rate.between' => 'Mức thuế phải từ 0 đến 100%.',
            'image_file.image' => 'Tệp phải là ảnh.',
            'image_file.mimes' => 'Ảnh chỉ nhận định dạng JPG, PNG hoặc WebP.',
            'image_file.max' => 'Ảnh tối đa 2 MB.',
            'units.required' => 'Cần ít nhất một đơn vị tính.',
            'units.min' => 'Cần ít nhất một đơn vị tính.',
            'units.max' => 'Tối đa 6 đơn vị tính cho một sản phẩm.',
            'units.*.unit_id.required' => 'Vui lòng chọn đơn vị tính.',
            'units.*.unit_id.distinct' => 'Có đơn vị tính bị chọn trùng.',
            'units.*.unit_id.exists' => 'Đơn vị tính không tồn tại.',
            'units.*.factor.required' => 'Vui lòng nhập số lượng quy đổi.',
            'units.*.factor.integer' => 'Quy đổi phải là số nguyên.',
            'units.*.factor.min' => 'Quy đổi tối thiểu là 1.',
            'units.*.prices.*.price.numeric' => 'Giá phải là số.',
            'units.*.prices.*.price.min' => 'Giá không được âm.',
        ]);

        // Phải có đúng một đơn vị cơ bản (quy đổi = 1)
        $validator->after(function (Validator $v) use ($request) {
            $bases = collect($request->input('units', []))->filter(fn ($u) => (int) ($u['factor'] ?? 0) === 1)->count();
            if ($bases !== 1 && ! $v->errors()->has('units')) {
                $v->errors()->add('units', 'Cần đúng một đơn vị cơ bản (quy đổi = 1).');
            }
        });

        return $validator->validate();
    }

    private function productFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'barcode' => $data['barcode'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'tax_rate' => $data['tax_rate'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /** Đồng bộ theo unit_id để product_units.id ổn định cho các module tồn kho / bán hàng sau này. */
    private function syncUnits(Product $product, array $units): void
    {
        $keep = [];

        foreach ($units as $row) {
            $pu = $product->units()->updateOrCreate(
                ['unit_id' => $row['unit_id']],
                ['factor' => $row['factor']],
            );
            $keep[] = $pu->id;

            $listIds = [];
            foreach ($row['prices'] ?? [] as $p) {
                $pu->prices()->updateOrCreate(
                    ['price_list_id' => $p['price_list_id']],
                    ['price' => $p['price'] ?? 0],
                );
                $listIds[] = $p['price_list_id'];
            }
            $pu->prices()->whereNotIn('price_list_id', $listIds)->delete();
        }

        $product->units()->whereNotIn('id', $keep)->delete();
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image_file')) {
            return null;
        }

        $file = $request->file('image_file');
        $name = Str::uuid().'.'.$file->extension();
        $file->move(public_path('uploads/products'), $name);

        return 'uploads/products/'.$name;
    }

    private function nextCode(): string
    {
        do {
            $code = DocumentNumber::next('product');
        } while (Product::where('code', $code)->exists());

        return $code;
    }
}
