<?php

namespace App\Models;

use App\Models\Concerns\HasUploadedImage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'barcode', 'category_id', 'tax_rate', 'cost_price', 'track_stock', 'opening_stock', 'image', 'description', 'is_active'])]
class Product extends Model
{
    use HasUploadedImage;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
            'tax_rate' => 'float',
            'cost_price' => 'float',
            'opening_stock' => 'float',
        ];
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
                ->orWhere('barcode', 'like', "%{$search}%"));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('factor');
    }
}
