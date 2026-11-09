<?php

namespace App\Models;

use App\Models\Concerns\HasUploadedImage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'name', 'code', 'barcode', 'image', 'is_active', 'sort_order'])]
class ProductVariant extends Model
{
    use HasUploadedImage;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
