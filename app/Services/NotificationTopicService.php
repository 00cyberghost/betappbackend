<?php

namespace App\Services;

use App\Models\User;

class NotificationTopicService
{
    public const TOPICS = [
        [
            'key' => 'news',
            'label' => 'Football News',
            'description' => 'Breaking stories, football updates, and editorial posts.',
            'default' => true,
        ],
        [
            'key' => 'new-predictions',
            'label' => 'New Predictions',
            'description' => 'Admin and community predictions as they go live.',
            'default' => true,
        ],
        [
            'key' => 'ai-predictions',
            'label' => 'AI Predictions',
            'description' => 'Fresh AI-powered prediction picks and analysis.',
            'default' => true,
        ],
        [
            'key' => 'match-highlights',
            'label' => 'Match Highlights',
            'description' => 'New football highlight videos added to Focliq.',
            'default' => true,
        ],
        [
            'key' => 'draw-bets',
            'label' => 'Draw Bets',
            'description' => 'Matches selected as strong draw opportunities.',
            'default' => true,
        ],
        [
            'key' => 'live-matches',
            'label' => 'Live Matches',
            'description' => 'Live match updates and important game alerts.',
            'default' => false,
        ],
    ];

    public function options(): array
    {
        return self::TOPICS;
    }

    public function keys(): array
    {
        return array_column(self::TOPICS, 'key');
    }

    public function defaultKeys(): array
    {
        return collect(self::TOPICS)
            ->filter(fn (array $topic) => (bool) ($topic['default'] ?? false))
            ->pluck('key')
            ->values()
            ->all();
    }

    public function normalize(array $topics): array
    {
        $allowed = $this->keys();

        return collect($topics)
            ->filter(fn ($topic) => is_string($topic) && in_array($topic, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    public function selectedFor(User $user): array
    {
        return is_array($user->notification_topics)
            ? $this->normalize($user->notification_topics)
            : $this->defaultKeys();
    }

    public function isDefaultEnabled(string $topic): bool
    {
        foreach (self::TOPICS as $option) {
            if ($option['key'] === $topic) {
                return (bool) ($option['default'] ?? false);
            }
        }

        return false;
    }
}
