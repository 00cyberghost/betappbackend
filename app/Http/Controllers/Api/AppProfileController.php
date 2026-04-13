<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'predictionComments' => fn ($query) => $query->with('prediction:id,home_team_name,away_team_name')->latest()->limit(10),
            'predictions' => fn ($query) => $query->latest()->limit(10),
        ]);
        $authorityScore = $user->predictions()->sum('likes_count') + ($user->predictions()->sum('comments_count') * 2);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'is_admin' => $user->is_admin,
                'joined_at' => optional($user->created_at)?->toDateString(),
            ],
            'stats' => [
                'predictions' => $user->predictions()->count(),
                'comments' => $user->predictionComments()->count(),
                'likes' => $user->predictionLikes()->count(),
                'authority_score' => $authorityScore,
                'unread_notifications' => $user->unreadNotifications()->count(),
            ],
            'comments' => $user->predictionComments->map(fn ($comment) => [
                'id' => $comment->id,
                'body' => $comment->body,
                'status' => $comment->status,
                'created_at' => optional($comment->created_at)?->toIso8601String(),
                'prediction' => $comment->prediction ? [
                    'id' => $comment->prediction->id,
                    'title' => "{$comment->prediction->home_team_name} vs {$comment->prediction->away_team_name}",
                ] : null,
            ])->values(),
            'predictions' => $user->predictions->map(fn ($prediction) => [
                'id' => $prediction->id,
                'title' => "{$prediction->home_team_name} vs {$prediction->away_team_name}",
                'league_name' => $prediction->league_name,
                'prediction_value' => $prediction->prediction_value,
                'status' => $prediction->status,
                'published_at' => optional($prediction->published_at)?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'is_admin' => $user->is_admin,
                'joined_at' => optional($user->created_at)?->toDateString(),
            ],
        ]);
    }
}
