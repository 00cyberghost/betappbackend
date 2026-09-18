<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use App\Services\NotificationTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => is_array($notification->data) ? $notification->data : (json_decode((string) $notification->data, true) ?: []),
                'read_at' => optional($notification->read_at)?->toIso8601String(),
                'created_at' => optional($notification->created_at)?->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function storeDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:2048'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        UserDeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Device token saved.']);
    }

    public function preferences(Request $request, NotificationTopicService $notificationTopicService): JsonResponse
    {
        return response()->json([
            'topics' => $notificationTopicService->options(),
            'selected_topics' => $notificationTopicService->selectedFor($request->user()),
        ]);
    }

    public function updatePreferences(Request $request, NotificationTopicService $notificationTopicService): JsonResponse
    {
        $data = $request->validate([
            'selected_topics' => ['array'],
            'selected_topics.*' => ['string', 'in:' . implode(',', $notificationTopicService->keys())],
        ]);

        $request->user()->forceFill([
            'notification_topics' => $notificationTopicService->normalize($data['selected_topics'] ?? []),
        ])->save();

        return response()->json([
            'topics' => $notificationTopicService->options(),
            'selected_topics' => $notificationTopicService->selectedFor($request->user()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifications marked as read.']);
    }
}
