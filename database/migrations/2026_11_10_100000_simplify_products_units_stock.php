<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bỏ biến thể (nếu bản trước đã tạo)
        Schema::dropIfExists('product_variants');
        if (Schema::hasColumn('products', 'variant_label')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('variant_label');
            });
        }

        // Đơn vị: loại (cơ bản / quy đổi) và đơn vị mặc định của từng loại
        Schema::table('units', function (Blueprint $table) {
            $table->string('kind', 12)->default('base')->after('name');
            $table->boolean('is_default')->default(false)->after('kind');
        });

        // Đơn vị đang được dùng làm quy đổi (quy đổi > 1) thì xếp vào loại quy đổi
        $conversionIds = DB::table('product_units')->where('factor', '>', 1)->distinct()->pluck('unit_id');
        $baseIds = DB::table('product_units')->where('factor', 1)->distinct()->pluck('unit_id');
        DB::table('units')->whereIn('id', $conversionIds->diff($baseIds))->update(['kind' => 'conversion']);

        DB::table('units')->where('name', 'Cái')->where('kind', 'base')->update(['is_default' => true]);

        // Giá vốn và tồn kho ban đầu
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->default(0)->after('tax_rate');
            $table->boolean('track_stock')->default(false)->after('cost_price');
            $table->decimal('opening_stock', 15, 3)->default(0)->after('track_stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cost_price', 'track_stock', 'opening_stock']);
        });
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['kind', 'is_default']);
        });
    }
};
