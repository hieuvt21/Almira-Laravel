<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // "Nguyễn Văn An" -> "An Nguyễn Văn": để sắp xếp theo tên gọi kiểu Việt Nam
            $table->string('sort_name')->nullable()->after('name');
            $table->index('sort_name');
        });

        DB::table('customers')->select('id', 'name')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $parts = preg_split('/\s+/u', trim($row->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $sort = count($parts) < 2 ? trim($row->name) : array_pop($parts).' '.implode(' ', $parts);
                DB::table('customers')->where('id', $row->id)->update(['sort_name' => $sort]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['sort_name']);
            $table->dropColumn('sort_name');
        });
    }
};
