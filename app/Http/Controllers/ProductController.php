<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductUnit;
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

        $products = Product::with(['category:id,name', 'units.unit:id,name', 'units.prices'])
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
                'cost_price' => $p->cost_price,
                'track_stock' => $p->track_stock,
                'opening_stock' => $p->opening_stock,
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
            'units' => Unit::orderBy('name')->get(['id', 'name', 'kind', 'is_default', 'is_active']),
            'priceLists' => PriceList::orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'is_active']),
        ]);
    }

    /**
     * Kiểm tra trùng mã sản phẩm / mã vạch ngay khi nhập .
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
        });

        return redirect()->route('products.index')->with('success', 'Đã cập nhật sản phẩm.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->load('units');
        $product->units->each->deleteUploadedImage();
        $product->deleteUploadedImage();
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Đã xóa sản phẩm.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $id = $product?->id;
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
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'track_stock' => ['nullable', 'boolean'],
            'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],

            'units' => ['required', 'array', 'min:1', 'max:6'],
            'units.*.unit_id' => ['required', 'distinct', Rule::exists('units', 'id')],
            'units.*.factor' => ['required', 'integer', 'min:1', 'max:100000'],
            'units.*.image_file' => $image,
            'units.*.remove_image' => ['nullable', 'boolean'],
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
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'tax_rate.required' => 'Vui lòng chọn mức thuế.',
            'tax_rate.between' => 'Mức thuế phải từ 0 đến 100%.',
            'cost_price.numeric' => 'Giá vốn phải là số.',
            'cost_price.min' => 'Giá vốn không được âm.',
            'opening_stock.numeric' => 'Tồn kho ban đầu phải là số.',
            'opening_stock.min' => 'Tồn kho ban đầu không được âm.',
            'units.*.image_file.image' => 'Tệp phải là ảnh.',
            'units.*.image_file.mimes' => 'Ảnh chỉ nhận định dạng JPG, PNG hoặc WebP.',
            'units.*.image_file.max' => 'Ảnh tối đa 2 MB.',
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
        ]);

        $validator->after(function (Validator $v) use ($request, $id) {
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

            // Đơn vị cơ bản phải thuộc loại "cơ bản", đơn vị quy đổi thuộc loại "quy đổi" và quy đổi > 1
            $kinds = Unit::whereIn('id', collect($units)->pluck('unit_id')->filter()->all())->pluck('kind', 'id');
            foreach ($units as $i => $row) {
                $kind = $kinds[(int) ($row['unit_id'] ?? 0)] ?? null;
                if ($kind === null || $v->errors()->has("units.{$i}.unit_id")) {
                    continue;
                }
                $isBase = (int) ($row['factor'] ?? 0) === 1;
                if ($isBase && $kind !== Unit::BASE) {
                    $v->errors()->add("units.{$i}.unit_id", 'Hãy chọn một đơn vị thuộc loại "Đơn vị cơ bản".');
                } elseif (! $isBase && $kind !== Unit::CONVERSION) {
                    $v->errors()->add("units.{$i}.unit_id", 'Hãy chọn một đơn vị thuộc loại "Đơn vị quy đổi".');
                }
            }

        });

        $data = $validator->validate();

        $data['units'] = array_values($data['units']);

        return $data;
    }

    private function productFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'barcode' => $data['barcode'] ?? null,
            'category_id' => $data['category_id'],
            'tax_rate' => $data['tax_rate'],
            'cost_price' => $data['cost_price'] ?? 0, // chưa biết giá vốn thì để 0
            'track_stock' => $track = (bool) ($data['track_stock'] ?? false),
            'opening_stock' => $track ? ($data['opening_stock'] ?? 0) : 0,
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
        } while (Product::where('code', $code)->exists());

        return $code;
    }
}
