<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });

        DB::table('users')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('social_accounts')
                    ->whereColumn('social_accounts.user_id', 'users.id');
            })
            ->update(['password' => null]);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('password')
            ->orderBy('id')
            ->eachById(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => Hash::make(Str::password(64))]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
        });
    }
};
