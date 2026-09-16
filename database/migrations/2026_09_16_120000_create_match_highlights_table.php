<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_highlights', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('youtube_url', 2048);
            $table->string('youtube_video_id', 32)->index();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_highlights');
    }
};
