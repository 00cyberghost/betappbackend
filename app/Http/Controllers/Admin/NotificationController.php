<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\FirebasePushService;
use App\Services\NotificationTopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(NotificationTopicService $notificationTopicService): Response
    {
        return Inertia::render('notifications/index', [
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'avatar_url'])
                ->values(),
            'topics' => $notificationTopicService->options(),
        ]);
    }

    public function store(
        Request $request,
        AppNotificationService $appNotificationService,
        FirebasePushService $firebasePushService,
        NotificationTopicService $notificationTopicService,
    ): RedirectResponse {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'audience' => ['required', 'in:all,selected,topic'],
            'topic' => ['nullable', 'required_if:audience,topic', 'string', 'in:' . implode(',', $notificationTopicService->keys())],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $imageUrl = null;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('notifications', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        $users = match ($data['audience']) {
            'all' => User::query()->get(),
            'topic' => User::query()
                ->whereJsonContains('notification_topics', $data['topic'])
                ->when(
                    $notificationTopicService->isDefaultEnabled($data['topic']),
                    fn ($query) => $query->orWhereNull('notification_topics'),
                )
                ->get(),
            default => User::query()->whereIn('id', $data['user_ids'] ?? [])->get(),
        };

        $appNotificationService->sendAdminBroadcast(
            $users,
            $data['title'],
            $data['body'],
            $imageUrl,
        );

        if ($data['audience'] === 'all') {
            $firebasePushService->sendToTopic(
                FirebasePushService::ALL_USERS_TOPIC,
                $data['title'],
                $data['body'],
                ['image_url' => $imageUrl, 'type' => 'admin_broadcast'],
                $imageUrl,
            );
        } elseif ($data['audience'] === 'topic') {
            $firebasePushService->sendToTopic(
                $data['topic'],
                $data['title'],
                $data['body'],
                ['image_url' => $imageUrl, 'type' => 'admin_broadcast', 'topic' => $data['topic']],
                $imageUrl,
            );
        } else {
            $firebasePushService->sendToTokens(
                $users->flatMap(fn (User $user) => $user->deviceTokens()->pluck('token'))->all(),
                $data['title'],
                $data['body'],
                ['image_url' => $imageUrl, 'type' => 'admin_broadcast'],
                $imageUrl,
            );
        }

        return redirect('/dashboard/notifications')->with('success', 'Notification sent successfully.');
    }
}
