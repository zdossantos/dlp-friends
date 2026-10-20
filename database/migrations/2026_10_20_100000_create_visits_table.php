<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->index(['user_id', 'starts_on', 'ends_on']);
            $table->index(['ends_on', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
