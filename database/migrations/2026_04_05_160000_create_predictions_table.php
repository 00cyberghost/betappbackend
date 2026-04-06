<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fixture_id')->nullable()->index();
            $table->unsignedBigInteger('league_id')->nullable()->index();
            $table->string('league_name');
            $table->string('country_name')->nullable();
            $table->unsignedBigInteger('home_team_id')->nullable()->index();
            $table->string('home_team_name');
            $table->string('home_team_logo')->nullable();
            $table->unsignedBigInteger('away_team_id')->nullable()->index();
            $table->string('away_team_name');
            $table->string('away_team_logo')->nullable();
            $table->timestamp('match_starts_at');
            $table->string('prediction_type');
            $table->string('prediction_value');
            $table->unsignedTinyInteger('probability')->nullable();
            $table->decimal('odds', 8, 2)->nullable();
            $table->longText('analysis');
            $table->string('status')->default('draft')->index();
            $table->string('scope')->default('editorial');
            $table->string('source')->default('manual');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};
