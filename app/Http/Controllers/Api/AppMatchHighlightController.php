<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchHighlight;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppMatchHighlightController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, $request->integer('limit') ?: 12));

        $items = MatchHighlight::query()
            ->where('is_published', true)
            ->latest('published_at')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (MatchHighlight $highlight) => [
                'id' => $highlight->id,
                'title' => $highlight->title,
                'youtube_url' => $highlight->youtube_url,
                'youtube_video_id' => $highlight->youtube_video_id,
                'thumbnail_url' => $highlight->thumbnail_url,
                'description' => $highlight->description,
                'published_at' => optional($highlight->published_at)?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $items]);
    }
}
