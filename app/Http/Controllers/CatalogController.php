<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PriceList;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Danh mục, đơn vị tính và bảng giá: ba danh sách nhỏ dùng chung một trang.
 */
class CatalogController extends Controller
{
    private const TYPES = [
        'categories' => ['model' => Category::class, 'table' => 'categories', 'label' => 'danh mục'],
        'units' => ['model' => Unit::class, 'table' => 'units', 'label' => 'đơn vị tính'],
        'price-lists' => ['model' => PriceList::class, 'table' => 'price_lists', 'label' => 'bảng giá'],
    ];

    public function index(): Response
    {
        return Inertia::render('Catalog/Index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
            'units' => Unit::withCount('products')->orderBy('name')->get(),
            'priceLists' => PriceList::withCount('prices')->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $cfg = $this->cfg($type);
        $data = $this->validated($request, $type);

        $item = new $cfg['model'];
        $item->fill($data)->save();
        $this->afterSave($item, $type);

        return back()->with('success', 'Đã thêm '.$cfg['label'].'.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $cfg = $this->cfg($type);
        $item = $cfg['model']::findOrFail($id);
        $data = $this->validated($request, $type, $item);

        if ($type === 'price-lists' && $item->is_default && ! ($data['is_active'] ?? true)) {
            return back()->withErrors(['is_active' => 'Không thể ngừng dùng bảng giá mặc định.']);
        }

        if ($type === 'units' && $item->is_default) {
            $data['is_default'] = true; // muốn đổi mặc định thì chọn đơn vị khác làm mặc định
        }
        if ($type === 'price-lists' && $item->is_default) {
            $data['is_default'] = true; // muốn đổi mặc định thì chọn bảng giá khác làm mặc định
        }

        $item->fill($data)->save();
        $this->afterSave($item, $type);

        return back()->with('success', 'Đã cập nhật '.$cfg['label'].'.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $cfg = $this->cfg($type);
        $item = $cfg['model']::findOrFail($id);

        if ($type === 'price-lists' && $item->is_default) {
            return back()->with('error', 'Không thể xóa bảng giá mặc định.');
        }
        if ($type === 'units' && $item->is_default) {
            return back()->with('error', 'Không thể xóa đơn vị mặc định. Hãy chọn đơn vị khác làm mặc định trước.');
        }
        if ($type === 'units' && $item->products()->exists()) {
            return back()->with('error', 'Đơn vị này đang được sản phẩm sử dụng nên không thể xóa.');
        }

        $item->delete();

        return back()->with('success', 'Đã xóa '.$cfg['label'].'.');
    }

    private function cfg(string $type): array
    {
        return self::TYPES[$type] ?? abort(404);
    }

    private function validated(Request $request, string $type, ?Model $item = null): array
    {
        $table = self::TYPES[$type]['table'];
        $rules = [
            'name' => ['required', 'string', 'max:'.($type === 'units' ? 50 : 100), Rule::unique($table, 'name')->ignore($item?->id)],
            'is_active' => ['required', 'boolean'],
        ];
        $messages = [
            'name.required' => 'Vui lòng nhập tên.',
            'name.max' => 'Tên quá dài.',
            'name.unique' => 'Tên này đã tồn tại.',
        ];

        if ($type === 'categories') {
            $rules['description'] = ['nullable', 'string', 'max:255'];
        }
        if ($type === 'price-lists') {
            $rules['is_default'] = ['required', 'boolean'];
        }
        if ($type === 'units') {
            $rules['is_default'] = ['required', 'boolean'];
        }

        return $request->validate($rules, $messages);
    }

    /** Chỉ một bảng giá được là mặc định; đơn vị cũng chỉ có một mặc định (được chọn sẵn khi thêm sản phẩm). */
    private function afterSave(Model $item, string $type): void
    {
        if ($type === 'units' && $item->is_default) {
            Unit::whereKeyNot($item->id)->update(['is_default' => false]);
            if (! $item->is_active) {
                $item->update(['is_active' => true]);
            }
        }

        if ($type === 'price-lists' && $item->is_default) {
            PriceList::whereKeyNot($item->id)->update(['is_default' => false]);
            if (! $item->is_active) {
                $item->update(['is_active' => true]);
            }
        }
    }
}
