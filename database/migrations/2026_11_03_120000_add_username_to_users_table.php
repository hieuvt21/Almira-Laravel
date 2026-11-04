<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
        });

        // Gán tên đăng nhập cho tài khoản đã có: lấy phần trước dấu @ của email
        $used = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::of(Str::before($user->email, '@'))
                ->lower()
                ->replaceMatches('/[^a-z0-9._-]/', '')
                ->toString() ?: 'user';

            $name = $base;
            $i = 1;
            while (in_array($name, $used, true)) {
                $name = $base.(++$i);
            }
            $used[] = $name;

            DB::table('users')->where('id', $user->id)->update(['username' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
