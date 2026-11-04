<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Email không còn bắt buộc (cột unique vẫn cho phép nhiều giá trị rỗng)
            $table->string('email')->nullable()->change();

            // Số điện thoại: đúng 10 chữ số, lưu không dấu chấm
            $table->string('phone', 10)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
