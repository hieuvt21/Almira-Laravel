<?php

namespace App\Models;

use App\Models\Concerns\HasUploadedImage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'barcode', 'variant_label', 'category_id', 'tax_rate', 'image', 'description', 'is_active'])]
class Product extends Model
{
    use HasUploadedImage;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tax_rate' => 'float'];
    }

    /** Tìm theo tên, mã hoặc mã vạch. */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = trim($search);

        return $search === ''
            ? $query
            : $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhereHas('variants', fn ($v) => $v
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('factor');
    }
}
