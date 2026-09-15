<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AppProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'predictionComments' => fn ($query) => $query->with('prediction:id,home_team_name,away_team_name')->latest()->limit(10),
            'predictions' => fn ($query) => $query->latest()->limit(50),
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

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->deviceTokens()->delete();
            $user->notifications()->delete();

            $user->forceFill([
                'name' => 'Deleted User',
                'email' => sprintf('deleted-user-%d-%s@deleted.betextract.local', $user->id, (string) Str::uuid()),
                'google_id' => null,
                'phone' => null,
                'avatar_url' => null,
                'bio' => null,
                'password' => Hash::make(Str::random(48)),
                'api_token' => null,
                'account_deleted_at' => now(),
            ])->save();
        });

        return response()->json([
            'message' => 'Your account has been deleted.',
        ]);
    }
}
