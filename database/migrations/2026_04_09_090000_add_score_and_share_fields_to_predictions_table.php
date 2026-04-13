<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->unsignedTinyInteger('predicted_score_home')->nullable()->after('prediction_value');
            $table->unsignedTinyInteger('predicted_score_away')->nullable()->after('predicted_score_home');
            $table->unsignedInteger('shares_count')->default(0)->after('comments_count');
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table) {
            $table->dropColumn(['predicted_score_home', 'predicted_score_away', 'shares_count']);
        });
    }
};
