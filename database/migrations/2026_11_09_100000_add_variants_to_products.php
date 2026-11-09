<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_units', 'image')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->string('image')->nullable()->after('factor');
            });
        }

        // Đơn vị cơ bản mặc định khi tạo sản phẩm
        if (! DB::table('units')->where('name', 'Cái')->exists()) {
            DB::table('units')->insert(['name' => 'Cái', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_units', 'image')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
