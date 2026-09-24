<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_followed_matches')) {
            $this->repairPartiallyCreatedTable();

            return;
        }

        Schema::create('user_followed_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fixture_id');
            $table->string('topic');
            $table->string('home_team_name')->nullable();
            $table->string('away_team_name')->nullable();
            $table->unsignedInteger('home_score')->nullable();
            $table->unsignedInteger('away_score')->nullable();
            $table->string('status_short', 20)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fixture_id']);
            $table->index(['fixture_id', 'status_short'], 'ufm_fixture_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_followed_matches');
    }

    private function repairPartiallyCreatedTable(): void
    {
        if (Schema::hasColumn('user_followed_matches', 'status_short')) {
            DB::statement('ALTER TABLE `user_followed_matches` MODIFY `status_short` VARCHAR(20) NULL');
        }

        if (! $this->indexExists('user_followed_matches', 'ufm_fixture_status_idx')) {
            Schema::table('user_followed_matches', function (Blueprint $table) {
                $table->index(['fixture_id', 'status_short'], 'ufm_fixture_status_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
