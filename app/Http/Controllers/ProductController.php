<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Model;
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

        $products = Product::with(['category:id,name', 'variants', 'units.unit:id,name', 'units.prices'])
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
                'tax_rate' => $p->tax_rate,
                'description' => $p->description,
                'is_active' => $p->is_active,
                'image_url' => $p->image_url,
                'variant_label' => $p->variant_label,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'code' => $v->code,
                    'barcode' => $v->barcode,
                    'is_active' => $v->is_active,
                    'image_url' => $v->image_url,
                ])->values(),
                'units' => $p->units->map(fn ($u) => [
                    'id' => $u->id,
                    'unit_id' => $u->unit_id,
                    'unit' => $u->unit?->name,
                    'factor' => $u->factor,
                    'image_url' => $u->image_url,
                    'prices' => $u->prices->map(fn ($pr) => [
                        'price_list_id' => $pr->price_list_id,
                        'price' => $pr->price,
                    ])->values(),
                ])->values(),
            ]);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search, 'category' => $category ?: null, 'status' => $status],
            'categories' => Category::orderBy('name')->get(['id', 'name', 'is_active']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'is_active']),
            'priceLists' => PriceList::orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'is_active']),
        ]);
    }

    /**
     * Kiểm tra trùng mã sản phẩm / mã vạch ngay khi nhập (có tính cả mã của biến thể).
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
            ->first(['id', 'name'])?->name;

        $found ??= ProductVariant::where($field, $value)
            ->when($ignore, fn ($q) => $q->where('product_id', '!=', $ignore))
            ->with('product:id,name')
            ->first()?->product?->name;

        $label = $field === 'code' ? 'Mã này' : 'Mã vạch này';

        return response()->json([
            'valid' => true,
            'available' => $found === null,
            'message' => $found !== null ? "{$label} đã dùng cho sản phẩm \"{$found}\"." : null,
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

            $this->syncUnits($product, $data['units']);
            $this->syncVariants($product, $data['variants']);
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

            $this->syncUnits($product, $data['units']);
            $this->syncVariants($product, $data['variants']);
        });

        return redirect()->route('products.index')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->load(['units', 'variants']);
        $product->units->each->deleteUploadedImage();
        $product->variants->each->deleteUploadedImage();
        $product->deleteUploadedImage();
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Đã xóa sản phẩm.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $id = $product?->id;
        $hasVariants = $request->boolean('has_variants');
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

        $validator = validator($request->all(), [
            'code' => ['nullable', 'string', 'max:50', 'regex:'.self::CODE_REGEX, Rule::unique('products', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:50', 'regex:'.self::CODE_REGEX, Rule::unique('products', 'barcode')->ignore($id)],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'image_file' => $image,
            'remove_image' => ['nullable', 'boolean'],
            'has_variants' => ['nullable', 'boolean'],
            'variant_label' => [$hasVariants ? 'required' : 'nullable', 'string', 'max:50'],

            'units' => ['required', 'array', 'min:1', 'max:6'],
            'units.*.unit_id' => ['required', 'distinct', Rule::exists('units', 'id')],
            'units.*.factor' => ['required', 'integer', 'min:1', 'max:100000'],
            'units.*.image_file' => $image,
            'units.*.remove_image' => ['nullable', 'boolean'],
            'units.*.prices' => ['nullable', 'array'],
            'units.*.prices.*.price_list_id' => ['required', Rule::exists('price_lists', 'id')],
            'units.*.prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            'variants' => [$hasVariants ? 'required' : 'nullable', 'array', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'variants.*.code' => ['nullable', 'string', 'max:50', 'regex:'.self::CODE_REGEX, 'distinct'],
            'variants.*.barcode' => ['nullable', 'string', 'max:50', 'regex:'.self::CODE_REGEX, 'distinct'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.image_file' => $image,
            'variants.*.remove_image' => ['nullable', 'boolean'],
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
            'tax_rate.required' => 'Vui lòng chọn mức thuế.',
            'tax_rate.between' => 'Mức thuế phải từ 0 đến 100%.',
            'variant_label.required' => 'Vui lòng nhập tên thuộc tính (ví dụ: Màu sắc).',
            'units.*.image_file.image' => 'Tệp phải là ảnh.',
            'units.*.image_file.mimes' => 'Ảnh chỉ nhận định dạng JPG, PNG hoặc WebP.',
            'units.*.image_file.max' => 'Ảnh tối đa 2 MB.',
            'variants.*.image_file.image' => 'Tệp phải là ảnh.',
            'variants.*.image_file.mimes' => 'Ảnh chỉ nhận định dạng JPG, PNG hoặc WebP.',
            'variants.*.image_file.max' => 'Ảnh tối đa 2 MB.',
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
            'units.*.factor.integer' => 'Số lượng quy đổi phải là số nguyên.',
            'units.*.factor.min' => 'Số lượng quy đổi tối thiểu là 1.',
            'units.*.prices.*.price.numeric' => 'Giá phải là số.',
            'units.*.prices.*.price.min' => 'Giá không được âm.',
            'variants.required' => 'Cần ít nhất một biến thể.',
            'variants.max' => 'Tối đa 30 biến thể cho một sản phẩm.',
            'variants.*.name.required' => 'Vui lòng nhập tên biến thể.',
            'variants.*.name.max' => 'Tên biến thể tối đa 100 ký tự.',
            'variants.*.name.distinct' => 'Tên biến thể bị trùng.',
            'variants.*.code.regex' => 'Mã chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'variants.*.code.distinct' => 'Mã biến thể bị trùng.',
            'variants.*.barcode.regex' => 'Mã vạch chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
            'variants.*.barcode.distinct' => 'Mã vạch biến thể bị trùng.',
        ]);

        $validator->after(function (Validator $v) use ($request, $id, $hasVariants) {
            $units = array_values((array) $request->input('units', []));

            // Phải có đúng một đơn vị cơ bản (quy đổi = 1)
            $baseIndexes = collect($units)->keys()->filter(fn ($i) => (int) ($units[$i]['factor'] ?? 0) === 1)->values();
            if ($baseIndexes->count() !== 1 && ! $v->errors()->has('units')) {
                $v->errors()->add('units', 'Cần đúng một đơn vị cơ bản (số lượng quy đổi = 1).');
            }

            // Giá bán lẻ (bảng giá mặc định, đơn vị cơ bản) là bắt buộc
            if ($baseIndexes->count() === 1) {
                $i = $baseIndexes->first();
                $defaultId = PriceList::where('is_default', true)->value('id');
                $retail = collect($units[$i]['prices'] ?? [])->first(fn ($p) => (int) ($p['price_list_id'] ?? 0) === (int) $defaultId);
                if (! isset($retail['price']) || $retail['price'] === '') {
                    $v->errors()->add("units.{$i}.retail_price", 'Vui lòng nhập giá bán lẻ.');
                }
            }

            // Mã / mã vạch của sản phẩm không được trùng với mã của biến thể thuộc sản phẩm khác
            foreach (['code' => 'Mã sản phẩm này đã được dùng cho một biến thể.', 'barcode' => 'Mã vạch này đã được dùng cho một biến thể.'] as $field => $msg) {
                $value = trim((string) $request->input($field, ''));
                if ($value !== '' && ! $v->errors()->has($field)
                    && ProductVariant::where($field, $value)->when($id, fn ($q) => $q->where('product_id', '!=', $id))->exists()) {
                    $v->errors()->add($field, $msg);
                }
            }

            // Mã / mã vạch của biến thể không được trùng với sản phẩm khác (kể cả mã sản phẩm)
            if ($hasVariants) {
                foreach (array_values((array) $request->input('variants', [])) as $n => $row) {
                    $vid = isset($row['id']) ? (int) $row['id'] : null;
                    foreach (['code', 'barcode'] as $field) {
                        $value = trim((string) ($row[$field] ?? ''));
                        if ($value === '' || $v->errors()->has("variants.{$n}.{$field}")) {
                            continue;
                        }
                        $clash = ProductVariant::where($field, $value)->when($vid, fn ($q) => $q->whereKeyNot($vid))->exists()
                            || Product::where($field, $value)->exists();
                        if ($clash) {
                            $v->errors()->add("variants.{$n}.{$field}", $field === 'code' ? 'Mã này đã được dùng.' : 'Mã vạch này đã được dùng.');
                        }
                    }
                }
            }
        });

        $data = $validator->validate();

        $data['has_variants'] = $hasVariants;
        $data['variants'] = $hasVariants ? array_values($data['variants'] ?? []) : [];
        $data['units'] = array_values($data['units']);

        return $data;
    }

    private function productFields(array $data): array
    {
        return [
            'name' => $data['name'],
            // Có biến thể thì mã vạch nằm ở từng biến thể
            'barcode' => $data['has_variants'] ? null : ($data['barcode'] ?? null),
            'variant_label' => $data['has_variants'] ? $data['variant_label'] : null,
            'category_id' => $data['category_id'],
            'tax_rate' => $data['tax_rate'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /** Đồng bộ theo unit_id để product_units.id ổn định cho các module tồn kho / bán hàng sau này. */
    private function syncUnits(Product $product, array $units): void
    {
        $existing = $product->units()->get()->keyBy('unit_id');
        $keep = [];

        foreach ($units as $row) {
            /** @var ProductUnit $pu */
            $pu = $existing->get((int) $row['unit_id']) ?? $product->units()->make(['unit_id' => $row['unit_id']]);
            $pu->factor = $row['factor'];
            $pu->fill($this->imageAttrs($pu, $row));
            $pu->save();
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

        $product->units()->whereNotIn('id', $keep)->get()->each(function (ProductUnit $pu) {
            $pu->deleteUploadedImage();
            $pu->delete();
        });
    }

    /** Đồng bộ theo id để product_variants.id ổn định cho các module tồn kho / bán hàng sau này. */
    private function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keep = [];

        foreach ($variants as $n => $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            /** @var ProductVariant $variant */
            $variant = $existing->get($id) ?? $product->variants()->make();

            $variant->name = trim($row['name']);
            $variant->barcode = $row['barcode'] ?? null;
            $variant->is_active = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $variant->sort_order = $n;
            $variant->code = ($row['code'] ?? null) ?: ($variant->code ?: $this->nextVariantCode($product));
            $variant->fill($this->imageAttrs($variant, $row));
            $variant->save();
            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep)->get()->each(function (ProductVariant $v) {
            $v->deleteUploadedImage();
            $v->delete();
        });
    }

    /** Ảnh tải lên > xóa ảnh > giữ nguyên. Trả về thuộc tính `image` cần cập nhật (rỗng nếu giữ nguyên). */
    private function imageAttrs(?Model $model, array $row): array
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
        } while (Product::where('code', $code)->exists() || ProductVariant::where('code', $code)->exists());

        return $code;
    }

    private function nextVariantCode(Product $product): string
    {
        $n = 1;
        do {
            $code = $product->code.'-'.str_pad((string) $n++, 2, '0', STR_PAD_LEFT);
        } while (ProductVariant::where('code', $code)->exists() || Product::where('code', $code)->exists());

        return $code;
    }
}
