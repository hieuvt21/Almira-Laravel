<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'barcode', 'category_id', 'tax_rate', 'image', 'description', 'is_active'])]
class Product extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tax_rate' => 'float'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('factor');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? asset($this->image) : null);
    }

    public function deleteUploadedImage(): void
    {
        if ($this->image && str_starts_with($this->image, 'uploads/products/')) {
            @unlink(public_path($this->image));
        }
    }
}
