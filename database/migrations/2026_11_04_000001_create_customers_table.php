<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('phone', 10)->nullable()->unique();
            $table->string('address')->nullable();
            $table->string('occupation')->nullable();
            $table->text('note')->nullable();
            $table->date('birth_date')->nullable();          // có đủ ngày/tháng/năm
            $table->unsignedSmallInteger('birth_year')->nullable(); // chỉ biết năm (hoặc năm của birth_date)
            $table->decimal('total_spent', 15, 2)->default(0);      // tổng chi tiêu, module Bán hàng sẽ cộng dồn
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
