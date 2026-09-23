<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_followed_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fixture_id');
            $table->string('topic');
            $table->string('home_team_name')->nullable();
            $table->string('away_team_name')->nullable();
            $table->unsignedInteger('home_score')->nullable();
            $table->unsignedInteger('away_score')->nullable();
            $table->string('status_short')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fixture_id']);
            $table->index(['fixture_id', 'status_short']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_followed_matches');
    }
};
