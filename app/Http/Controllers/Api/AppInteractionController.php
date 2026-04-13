<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Models\PredictionComment;
use App\Models\PredictionLike;
use App\Services\AppNotificationService;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppInteractionController extends Controller
{
    public function toggleLike(Request $request, Prediction $prediction, AppNotificationService $notificationService, FirebasePushService $firebasePushService): JsonResponse
    {
        $existing = PredictionLike::query()
            ->where('prediction_id', $prediction->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $liked = ! $existing;

        if ($existing) {
            $existing->delete();
        } else {
            PredictionLike::create([
                'prediction_id' => $prediction->id,
                'user_id' => $request->user()->id,
            ]);
        }

        $prediction->update([
            'likes_count' => $prediction->likes()->count(),
        ]);

        if ($liked && $prediction->user && ! $prediction->user->is($request->user())) {
            $notificationService->notifyPredictionLiked($prediction->user, $request->user(), $prediction);
            $firebasePushService->sendToTokens(
                $prediction->user->deviceTokens()->pluck('token')->all(),
                "{$request->user()->name} liked your prediction",
                "{$prediction->home_team_name} vs {$prediction->away_team_name} just got a new like.",
                ['prediction_id' => $prediction->id]
            );
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $prediction->likes_count,
        ]);
    }

    public function comment(Request $request, Prediction $prediction, AppNotificationService $notificationService, FirebasePushService $firebasePushService): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $comment = PredictionComment::create([
            'prediction_id' => $prediction->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'status' => 'published',
        ]);

        $prediction->update([
            'comments_count' => $prediction->comments()->count(),
        ]);

        if ($prediction->user && ! $prediction->user->is($request->user())) {
            $notificationService->notifyPredictionCommented($prediction->user, $request->user(), $prediction, $comment->body);
            $firebasePushService->sendToTokens(
                $prediction->user->deviceTokens()->pluck('token')->all(),
                "{$request->user()->name} commented on your prediction",
                $comment->body,
                ['prediction_id' => $prediction->id]
            );
        }

        $comment->load('user:id,name,avatar_url');

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => optional($comment->created_at)?->toIso8601String(),
                'user' => $comment->user ? [
                    'name' => $comment->user->name,
                    'avatar_url' => $comment->user->avatar_url,
                ] : null,
            ],
            'comments_count' => $prediction->comments_count,
        ], 201);
    }

    public function share(Request $request, Prediction $prediction): JsonResponse
    {
        $prediction->increment('shares_count');

        return response()->json([
            'shares_count' => $prediction->shares_count,
        ]);
    }
}
