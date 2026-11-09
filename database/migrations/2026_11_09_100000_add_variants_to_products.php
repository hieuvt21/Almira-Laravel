<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Tên thuộc tính của biến thể, ví dụ "Màu sắc" (chỉ có khi sản phẩm có biến thể)
            $table->string('variant_label', 50)->nullable()->after('barcode');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->string('image')->nullable()->after('factor');
        });

        // Mỗi biến thể có mã, mã vạch và ảnh riêng; giá và đơn vị quy đổi dùng chung theo sản phẩm.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('barcode', 50)->nullable()->unique();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });

        // Đơn vị cơ bản mặc định khi tạo sản phẩm
        if (! DB::table('units')->where('name', 'Cái')->exists()) {
            DB::table('units')->insert(['name' => 'Cái', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('image');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('variant_label');
        });
    }
};
