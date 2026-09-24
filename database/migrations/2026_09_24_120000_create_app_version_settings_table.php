<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_version_settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->unique();
            $table->string('latest_version', 50)->nullable();
            $table->unsignedInteger('latest_build')->nullable();
            $table->string('minimum_version', 50)->nullable();
            $table->unsignedInteger('minimum_build')->nullable();
            $table->string('update_url', 2048)->nullable();
            $table->string('message')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_version_settings');
    }
};
