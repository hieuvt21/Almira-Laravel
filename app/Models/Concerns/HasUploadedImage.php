<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/** Ảnh lưu ở public/uploads/products (cột `image`). */
trait HasUploadedImage
{
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
