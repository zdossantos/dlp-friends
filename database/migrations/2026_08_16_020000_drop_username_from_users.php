<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        $missingProfiles = DB::table('users')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->whereNotNull('users.username')
            ->whereNull('profiles.id')
            ->count();

        if ($missingProfiles > 0) {
            throw new RuntimeException('Legacy usernames must be copied to profiles before removal.');
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 30)->nullable();
            });

            $used = [];

            DB::table('users')->orderBy('id')->chunkById(100, function ($users) use (&$used): void {
                foreach ($users as $user) {
                    $displayName = DB::table('profiles')->where('user_id', $user->id)->value('display_name');
                    $base = mb_substr(trim((string) ($displayName ?: "member-{$user->id}")), 0, 30);
                    $candidate = $base;

                    if (isset($used[$candidate])) {
                        $suffix = "-{$user->id}";
                        $candidate = mb_substr($base, 0, 30 - mb_strlen($suffix)).$suffix;
                    }

                    $used[$candidate] = true;
                    DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
                }
            });

            if (DB::getDriverName() !== 'sqlite') {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('username', 30)->nullable(false)->change();
                });
            }

            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
