<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popular_leagues', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('league_id')->unique();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('logo')->nullable();
            $table->unsignedInteger('season')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $season = (int) now('Africa/Lagos')->year;
        $now = now();

        DB::table('popular_leagues')->insert([
            ['league_id' => 2, 'name' => 'UEFA Champions League', 'country' => 'World', 'season' => $season, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 3, 'name' => 'UEFA Europa League', 'country' => 'World', 'season' => $season, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 848, 'name' => 'UEFA Europa Conference League', 'country' => 'World', 'season' => $season, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 39, 'name' => 'Premier League', 'country' => 'England', 'season' => $season, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 140, 'name' => 'La Liga', 'country' => 'Spain', 'season' => $season, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 135, 'name' => 'Serie A', 'country' => 'Italy', 'season' => $season, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 78, 'name' => 'Bundesliga', 'country' => 'Germany', 'season' => $season, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 61, 'name' => 'Ligue 1', 'country' => 'France', 'season' => $season, 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 88, 'name' => 'Eredivisie', 'country' => 'Netherlands', 'season' => $season, 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 94, 'name' => 'Primeira Liga', 'country' => 'Portugal', 'season' => $season, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 203, 'name' => 'Süper Lig', 'country' => 'Turkey', 'season' => $season, 'sort_order' => 110, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 307, 'name' => 'Saudi Pro League', 'country' => 'Saudi Arabia', 'season' => $season, 'sort_order' => 120, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 253, 'name' => 'Major League Soccer', 'country' => 'USA', 'season' => $season, 'sort_order' => 130, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 71, 'name' => 'Serie A', 'country' => 'Brazil', 'season' => $season, 'sort_order' => 140, 'created_at' => $now, 'updated_at' => $now],
            ['league_id' => 128, 'name' => 'Liga Profesional Argentina', 'country' => 'Argentina', 'season' => $season, 'sort_order' => 150, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_leagues');
    }
};
