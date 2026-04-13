<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AppNotificationService
{
    public function notifyPredictionLiked(User $recipient, User $actor, Prediction $prediction): void
    {
        if ($recipient->is($actor)) {
            return;
        }

        $this->sendToUsers(
            collect([$recipient]),
            'prediction_liked',
            "{$actor->name} liked your prediction",
            "{$prediction->home_team_name} vs {$prediction->away_team_name} just got a new like.",
            [
                'prediction_id' => $prediction->id,
                'actor_id' => $actor->id,
            ]
        );
    }

    public function notifyPredictionCommented(User $recipient, User $actor, Prediction $prediction, string $comment): void
    {
        if ($recipient->is($actor)) {
            return;
        }

        $this->sendToUsers(
            collect([$recipient]),
            'prediction_commented',
            "{$actor->name} commented on your prediction",
            Str::limit($comment, 120),
            [
                'prediction_id' => $prediction->id,
                'actor_id' => $actor->id,
            ]
        );
    }

    public function notifyPredictionPublished(Collection $users, Prediction $prediction): void
    {
        $this->sendToUsers(
            $users,
            'prediction_published',
            'New prediction posted',
            "{$prediction->home_team_name} vs {$prediction->away_team_name} is now live.",
            [
                'prediction_id' => $prediction->id,
            ]
        );
    }

    public function notifyNewsPublished(Collection $users, string $title, ?string $imageUrl = null): void
    {
        $this->sendToUsers(
            $users,
            'news_published',
            'New football update',
            $title,
            [
                'image_url' => $imageUrl,
            ]
        );
    }

    public function sendAdminBroadcast(Collection $users, string $title, string $body, ?string $imageUrl = null): void
    {
        $this->sendToUsers(
            $users,
            'admin_broadcast',
            $title,
            $body,
            [
                'image_url' => $imageUrl,
            ]
        );
    }

    protected function sendToUsers(Collection $users, string $type, string $title, string $body, array $data = []): void
    {
        foreach ($users->unique('id') as $user) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'data' => [
                    'title' => $title,
                    'body' => $body,
                    ...$data,
                ],
            ]);
        }
    }
}
