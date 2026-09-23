<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFollowedMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppMatchFollowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = UserFollowedMatch::query()
            ->where('user_id', $request->user()->id)
            ->get(['fixture_id', 'topic', 'home_team_name', 'away_team_name', 'status_short'])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, int $fixture): JsonResponse
    {
        $follow = UserFollowedMatch::query()
            ->where('user_id', $request->user()->id)
            ->where('fixture_id', $fixture)
            ->first();

        return response()->json([
            'following' => (bool) $follow,
            'topic' => $this->topicFor($fixture),
        ]);
    }

    public function store(Request $request, int $fixture): JsonResponse
    {
        $data = $request->validate([
            'home_team_name' => ['nullable', 'string', 'max:255'],
            'away_team_name' => ['nullable', 'string', 'max:255'],
            'home_score' => ['nullable', 'integer', 'min:0'],
            'away_score' => ['nullable', 'integer', 'min:0'],
            'status_short' => ['nullable', 'string', 'max:20'],
        ]);

        $follow = UserFollowedMatch::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'fixture_id' => $fixture,
            ],
            [
                'topic' => $this->topicFor($fixture),
                'home_team_name' => $data['home_team_name'] ?? null,
                'away_team_name' => $data['away_team_name'] ?? null,
                'home_score' => $data['home_score'] ?? null,
                'away_score' => $data['away_score'] ?? null,
                'status_short' => $data['status_short'] ?? null,
            ]
        );

        return response()->json([
            'following' => true,
            'topic' => $follow->topic,
            'message' => 'Match followed.',
        ]);
    }

    public function destroy(Request $request, int $fixture): JsonResponse
    {
        UserFollowedMatch::query()
            ->where('user_id', $request->user()->id)
            ->where('fixture_id', $fixture)
            ->delete();

        return response()->json([
            'following' => false,
            'topic' => $this->topicFor($fixture),
            'message' => 'Match unfollowed.',
        ]);
    }

    protected function topicFor(int $fixture): string
    {
        return "match-{$fixture}";
    }
}
