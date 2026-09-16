<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MatchHighlight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MatchHighlightController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('match-highlights/index', [
            'highlights' => MatchHighlight::query()
                ->latest('published_at')
                ->latest()
                ->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'youtube_url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
        ]);

        $videoId = $this->extractYoutubeVideoId($data['youtube_url']);

        if (! $videoId) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Please enter a valid YouTube video link.',
            ]);
        }

        MatchHighlight::create([
            ...$data,
            'youtube_video_id' => $videoId,
            'thumbnail_url' => "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg",
            'is_published' => (bool) ($data['is_published'] ?? true),
            'published_at' => ($data['is_published'] ?? true) ? now() : null,
        ]);

        return redirect('/dashboard/match-highlights')->with('success', 'Match highlight added.');
    }

    public function destroy(MatchHighlight $matchHighlight): RedirectResponse
    {
        $matchHighlight->delete();

        return redirect('/dashboard/match-highlights')->with('success', 'Match highlight deleted.');
    }

    protected function extractYoutubeVideoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($host, 'youtu.be')) {
            return $path !== '' ? explode('/', $path)[0] : null;
        }

        if (str_contains($host, 'youtube.com') && ! empty($query['v'])) {
            return (string) $query['v'];
        }

        if (str_contains($host, 'youtube.com') && str_starts_with($path, 'shorts/')) {
            return explode('/', substr($path, strlen('shorts/')))[0] ?? null;
        }

        if (str_contains($host, 'youtube.com') && str_starts_with($path, 'embed/')) {
            return explode('/', substr($path, strlen('embed/')))[0] ?? null;
        }

        return null;
    }
}
