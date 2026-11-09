<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ đơn vị quy đổi: mỗi sản phẩm chỉ có một đơn vị tính (products.unit_id) và một giá cho mỗi bảng giá
 * (product_prices). Thêm "giá theo số lượng": bảng giá khác bán lẻ có thêm số lượng tối thiểu để áp dụng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('category_id')->constrained('units')->restrictOnDelete();
            $table->boolean('qty_pricing')->default(false)->after('tax_rate');
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            // Số lượng tối thiểu để áp dụng bảng giá này (bảng giá bán lẻ để trống)
            $table->decimal('min_qty', 15, 3)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'price_list_id']);
        });

        // Giữ lại đơn vị cơ bản và giá của đơn vị cơ bản; đơn vị quy đổi (nếu có) bị bỏ
        if (Schema::hasTable('product_units')) {
            $now = now();

            foreach (DB::table('product_units')->where('factor', 1)->get() as $pu) {
                DB::table('products')->where('id', $pu->product_id)->update(['unit_id' => $pu->unit_id]);

                $rows = DB::table('product_unit_prices')->where('product_unit_id', $pu->id)->get()
                    ->map(fn ($p) => [
                        'product_id' => $pu->product_id,
                        'price_list_id' => $p->price_list_id,
                        'price' => $p->price,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();
                if ($rows) {
                    DB::table('product_prices')->insert($rows);
                }
            }

            foreach (DB::table('product_units')->whereNotNull('image')->pluck('image') as $image) {
                if (str_starts_with($image, 'uploads/products/')) {
                    @unlink(public_path($image));
                }
            }

            Schema::dropIfExists('product_unit_prices');
            Schema::dropIfExists('product_units');
        }

        // Sản phẩm nào còn thiếu đơn vị thì gán đơn vị mặc định (hoặc "Cái")
        $fallback = DB::table('units')->where('is_default', true)->orderBy('id')->value('id')
            ?? DB::table('units')->where('name', 'Cái')->value('id');
        if ($fallback) {
            DB::table('products')->whereNull('unit_id')->update(['unit_id' => $fallback]);
        }

        // Đơn vị không còn phân loại cơ bản / quy đổi; chỉ giữ một đơn vị mặc định
        if (Schema::hasColumn('units', 'kind')) {
            DB::table('units')->where('kind', 'conversion')->update(['is_default' => false]);
            Schema::table('units', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
        if (DB::table('units')->where('is_default', true)->count() === 0) {
            DB::table('units')->where('name', 'Cái')->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('qty_pricing');
        });
    }
};
