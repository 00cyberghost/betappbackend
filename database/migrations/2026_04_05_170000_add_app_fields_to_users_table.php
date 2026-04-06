<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('email');
            $table->string('phone')->nullable()->after('email_verified_at');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('avatar_url');
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token']);
            $table->dropColumn(['is_admin', 'phone', 'avatar_url', 'bio', 'api_token']);
        });
    }
};
