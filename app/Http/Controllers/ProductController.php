<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Unit;
use App\Support\DocumentNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const CODE_REGEX = '/^[A-Za-z0-9._-]+$/';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $category = $request->query('category');
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        $products = Product::with(['category:id,name', 'unit:id,name', 'prices'])
            ->search($search)
            ->when($category === 'none', fn ($q) => $q->whereNull('category_id'))
            ->when(is_numeric($category), fn ($q) => $q->where('category_id', (int) $category))
            ->when($status, fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'barcode' => $p->barcode,
                'category_id' => $p->category_id,
                'category' => $p->category?->name,
                'unit_id' => $p->unit_id,
                'unit' => $p->unit?->name,
                'tax_rate' => $p->tax_rate,
                'description' => $p->description,
                'is_active' => $p->is_active,
                'image_url' => $p->image_url,
                'qty_pricing' => $p->qty_pricing,
                'track_stock' => $p->track_stock,
                'opening_stock' => $p->opening_stock,
                'prices' => $p->prices->map(fn ($pr) => [
                    'price_list_id' => $pr->price_list_id,
                    'price' => $pr->price,
                    'min_qty' => $pr->min_qty,
                ])->values(),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search, 'category' => $category ?: null, 'status' => $status],
            'categories' => Category::orderBy('name')->get(['id', 'name', 'is_active']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'is_default', 'is_active']),
            'priceLists' => PriceList::orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'is_active']),
        ]);
    }

    /**
     * Kiểm tra trùng mã sản phẩm / mã vạch ngay khi nhập.
     * Cùng định dạng trả về với users.check để dùng chung composable useUniqueCheck.
     */
    public function check(Request $request): JsonResponse
    {
        $field = (string) $request->input('field');
        abort_unless(in_array($field, ['code', 'barcode'], true), 422);

        $value = trim((string) $request->input('value'));
        $ignore = $request->integer('ignore') ?: null;

        if ($value === '') {
            return response()->json(['valid' => true, 'available' => true]);
        }
        if (! preg_match(self::CODE_REGEX, $value) || strlen($value) > 50) {
            return response()->json(['valid' => false, 'message' => 'Chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang (tối đa 50 ký tự).']);
        }

        $found = Product::where($field, $value)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->first(['id', 'name']);

        $label = $field === 'code' ? 'Mã này' : 'Mã vạch này';

        return response()->json([
            'valid' => true,
            'available' => ! $found,
            'message' => $found ? "{$label} đã dùng cho sản phẩm \"{$found->name}\"." : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $product = new Product($this->productFields($data));
            $product->code = ($data['code'] ?? null) ?: $this->nextCode();
            $product->image = $this->imageAttrs(null, $data)['image'] ?? null;
            $product->save();

            $this->syncPrices($product, $data);
        });

        return redirect()->route('products.index')->with('success', 'Đã thêm sản phẩm.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($data, $product) {
            $product->fill($this->productFields($data));
            if (! empty($data['code'])) {
                $product->code = $data['code'];
            }
            $product->fill($this->imageAttrs($product, $data));
            $product->save();

            $this->syncPrices($product, $data);
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
        $qtyPricing = $request->boolean('qty_pricing');

        $validator = validator($request->all(), [
            'code' => ['nullable', 'string', 'max:50', 'regex:'.self::CODE_REGEX, Rule::unique('products', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:50', 'regex:'.self::CODE_REGEX, Rule::unique('products', 'barcode')->ignore($id)],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'qty_pricing' => ['nullable', 'boolean'],

            'prices' => ['required', 'array', 'max:20'],
            'prices.*.price_list_id' => ['required', 'distinct', Rule::exists('price_lists', 'id')],
            'prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'prices.*.min_qty' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
        ], [
            'code.regex' => 'Mã sản phẩm chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'code.unique' => 'Mã sản phẩm này đã tồn tại.',
            'code.max' => 'Mã sản phẩm tối đa 50 ký tự.',
            'name.required' => 'Vui lòng nhập tên sản phẩm.',
            'name.max' => 'Tên sản phẩm tối đa 255 ký tự.',
            'barcode.regex' => 'Mã vạch chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'barcode.unique' => 'Mã vạch này đã được dùng cho sản phẩm khác.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'unit_id.required' => 'Vui lòng chọn đơn vị tính.',
            'unit_id.exists' => 'Đơn vị tính không tồn tại.',
            'tax_rate.required' => 'Vui lòng chọn mức thuế.',
            'tax_rate.between' => 'Mức thuế phải từ 0 đến 100%.',
            'image_file.image' => 'Tệp phải là ảnh.',
            'image_file.mimes' => 'Ảnh chỉ nhận định dạng JPG, PNG hoặc WebP.',
            'image_file.max' => 'Ảnh tối đa 2 MB.',
            'opening_stock.numeric' => 'Tồn kho ban đầu phải là số.',
            'opening_stock.min' => 'Tồn kho ban đầu không được âm.',
            'prices.*.price.numeric' => 'Giá phải là số.',
            'prices.*.price.min' => 'Giá không được âm.',
            'prices.*.min_qty.numeric' => 'Số lượng phải là số.',
            'prices.*.min_qty.gt' => 'Số lượng phải lớn hơn 0.',
        ]);

        $validator->after(function (Validator $v) use ($request, $qtyPricing) {
            $prices = array_values((array) $request->input('prices', []));
            $defaultId = (int) PriceList::where('is_default', true)->value('id');
            $hasTier = false;

            foreach ($prices as $i => $row) {
                $price = $row['price'] ?? null;
                $filled = $price !== null && $price !== '';

                // Giá bán lẻ (bảng giá mặc định) là bắt buộc
                if ((int) ($row['price_list_id'] ?? 0) === $defaultId) {
                    if (! $filled) {
                        $v->errors()->add("prices.{$i}.price", 'Vui lòng nhập giá bán lẻ.');
                    }

                    continue;
                }

                if (! $qtyPricing) {
                    continue;
                }

                // Giá theo số lượng: đã nhập giá thì phải có số lượng tối thiểu, và ngược lại
                $minQty = $row['min_qty'] ?? null;
                $hasQty = $minQty !== null && $minQty !== '';
                if ($filled && ! $hasQty) {
                    $v->errors()->add("prices.{$i}.min_qty", 'Nhập số lượng tối thiểu.');
                }
                if ($hasQty && ! $filled) {
                    $v->errors()->add("prices.{$i}.price", 'Nhập giá.');
                }
                $hasTier = $hasTier || ($filled && $hasQty);
            }

            if ($qtyPricing && ! $hasTier && ! $v->errors()->has('prices')) {
                $v->errors()->add('qty_pricing', 'Hãy khai báo ít nhất một mức giá theo số lượng, hoặc tắt "Giá theo số lượng".');
            }
        });

        $data = $validator->validate();
        $data['prices'] = array_values($data['prices']);

        return $data;
    }

    private function productFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'barcode' => $data['barcode'] ?? null,
            'category_id' => $data['category_id'],
            'unit_id' => $data['unit_id'],
            'tax_rate' => $data['tax_rate'],
            'qty_pricing' => (bool) ($data['qty_pricing'] ?? false),
            // Trạng thái này được lưu để module Kho / Bán hàng áp dụng: chỉ sản phẩm bật quản lý tồn kho
            // mới bị trừ kho khi bán và không được bán quá số lượng tồn.
            'track_stock' => $track = (bool) ($data['track_stock'] ?? false),
            'opening_stock' => $track ? ($data['opening_stock'] ?? 0) : 0,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /**
     * Giá bán lẻ luôn được lưu. Các bảng giá khác (giá theo số lượng) chỉ được cập nhật khi bật
     * "Giá theo số lượng"; khi tắt thì giữ nguyên dữ liệu cũ nhưng không áp dụng.
     */
    private function syncPrices(Product $product, array $data): void
    {
        $qtyPricing = (bool) ($data['qty_pricing'] ?? false);
        $defaultId = (int) PriceList::where('is_default', true)->value('id');

        foreach ($data['prices'] as $row) {
            $listId = (int) $row['price_list_id'];
            $isDefault = $listId === $defaultId;
            $price = $row['price'] ?? null;
            $hasPrice = $price !== null && $price !== '';

            if (! $isDefault && ! $qtyPricing) {
                continue;
            }

            if (! $isDefault && ! $hasPrice) {
                $product->prices()->where('price_list_id', $listId)->delete();

                continue;
            }

            $product->prices()->updateOrCreate(
                ['price_list_id' => $listId],
                ['price' => $price ?? 0, 'min_qty' => $isDefault ? null : ($row['min_qty'] ?? null)],
            );
        }
    }

    /** Ảnh tải lên > xóa ảnh > giữ nguyên. Trả về thuộc tính `image` cần cập nhật (rỗng nếu giữ nguyên). */
    private function imageAttrs(?Product $model, array $row): array
    {
        $file = $row['image_file'] ?? null;

        if ($file instanceof UploadedFile) {
            $model?->deleteUploadedImage();
            $name = Str::uuid().'.'.$file->extension();
            $file->move(public_path('uploads/products'), $name);

            return ['image' => 'uploads/products/'.$name];
        }

        if (filter_var($row['remove_image'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $model?->deleteUploadedImage();

            return ['image' => null];
        }

        return [];
    }

    private function nextCode(): string
    {
        do {
            $code = DocumentNumber::next('product');
        } while (Product::where('code', $code)->exists());

        return $code;
    }
}
