<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('football_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('payload');
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('football_snapshots');
    }
};
