<?php

use App\Enums\ProfileVisibility;
use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->change();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name', 80);
            $table->text('bio')->nullable();
            $table->string('visit_frequency')->nullable();
            $table->string('visibility')->default(ProfileVisibility::Visible->value);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => RoleName::User->value, 'created_at' => $now, 'updated_at' => $now],
            ['name' => RoleName::Admin->value, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $userRoleId = DB::table('roles')->where('name', RoleName::User->value)->value('id');

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) use ($now, $userRoleId): void {
            foreach ($users as $user) {
                DB::table('profiles')->insertOrIgnore([
                    'user_id' => $user->id,
                    'display_name' => $user->username,
                    'bio' => null,
                    'visit_frequency' => null,
                    'visibility' => ProfileVisibility::Visible->value,
                    'onboarding_completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('user_roles')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => $userRoleId,
                ]);
            }
        });

        if (DB::table('profiles')->count() !== DB::table('users')->count()) {
            throw new RuntimeException('Every user must have a profile before username becomes optional.');
        }

    }

    public function down(): void
    {
        $used = DB::table('users')->whereNotNull('username')->pluck('username')
            ->mapWithKeys(fn (string $username): array => [$username => true])->all();

        DB::table('users')->whereNull('username')->orderBy('id')->chunkById(100, function ($users) use (&$used): void {
            foreach ($users as $user) {
                $displayName = DB::table('profiles')->where('user_id', $user->id)->value('display_name');
                $base = mb_substr(trim((string) ($displayName ?: "member-{$user->id}")), 0, 30);
                $candidate = $base;
                $attempt = 1;

                while (isset($used[$candidate])) {
                    $suffix = "-{$user->id}".($attempt > 1 ? "-{$attempt}" : '');
                    $candidate = mb_substr($base, 0, 30 - mb_strlen($suffix)).$suffix;
                    $attempt++;
                }

                $used[$candidate] = true;
                DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            }
        });

        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
        });
    }
};
