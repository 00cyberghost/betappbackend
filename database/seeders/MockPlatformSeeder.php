<?php

namespace Database\Seeders;

use App\Models\Prediction;
use App\Models\PredictionComment;
use App\Models\PredictionLike;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MockPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'ozorclinton@gmail.com'],
            [
                'name' => 'Ozor Clinton',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_admin' => true,
                'phone' => '08030000000',
                'avatar_url' => 'https://i.pravatar.cc/300?img=12',
                'bio' => 'Admin account for managing editorial football predictions.',
            ]
        );

        $communityUsers = collect([
            [
                'name' => 'John Olamide',
                'email' => 'johnolamide@example.com',
                'phone' => '08081112222',
                'avatar_url' => 'https://i.pravatar.cc/300?img=14',
                'bio' => 'I follow Premier League football every single weekend.',
            ],
            [
                'name' => 'Adamu Philomena',
                'email' => 'adamuphilomena@example.com',
                'phone' => '08083334444',
                'avatar_url' => 'https://i.pravatar.cc/300?img=32',
                'bio' => 'Community member and matchday analyst.',
            ],
            [
                'name' => 'Ekwueme Franklin',
                'email' => 'ekwueemefranklin@example.com',
                'phone' => '08085556666',
                'avatar_url' => 'https://i.pravatar.cc/300?img=52',
                'bio' => 'Always looking for smart value in team form and fixtures.',
            ],
        ])->map(fn (array $attributes) => User::updateOrCreate(
            ['email' => $attributes['email']],
            [
                ...$attributes,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        ));

        $predictions = collect([
            [
                'league_id' => 39,
                'league_name' => 'Premier League',
                'country_name' => 'England',
                'fixture_id' => 900001,
                'home_team_id' => 63,
                'home_team_name' => 'Leeds United',
                'home_team_logo' => 'https://media.api-sports.io/football/teams/63.png',
                'away_team_id' => 40,
                'away_team_name' => 'Liverpool',
                'away_team_logo' => 'https://media.api-sports.io/football/teams/40.png',
                'match_starts_at' => now()->addDay(),
                'prediction_type' => '1X2',
                'prediction_value' => '1X',
                'probability' => 80,
                'odds' => 1.85,
                'analysis' => 'Leeds can stay competitive at home, but Liverpool still carries the stronger midfield control. Double chance on the home side keeps the risk balanced.',
                'status' => 'published',
                'scope' => 'editorial',
                'source' => 'manual',
                'category' => 'today_prediction',
                'likes_count' => 12,
                'comments_count' => 3,
                'published_at' => now()->subHours(2),
            ],
            [
                'league_id' => 39,
                'league_name' => 'Premier League',
                'country_name' => 'England',
                'fixture_id' => 900002,
                'home_team_id' => 42,
                'home_team_name' => 'Arsenal',
                'home_team_logo' => 'https://media.api-sports.io/football/teams/42.png',
                'away_team_id' => 50,
                'away_team_name' => 'Manchester City',
                'away_team_logo' => 'https://media.api-sports.io/football/teams/50.png',
                'match_starts_at' => now()->addDays(2),
                'prediction_type' => 'Under/Over 2.5',
                'prediction_value' => 'Over 2.5',
                'probability' => 74,
                'odds' => 1.92,
                'analysis' => 'Both sides create high-quality chances and are comfortable playing through pressure. The matchup leans toward a high-event game.',
                'status' => 'published',
                'scope' => 'editorial',
                'source' => 'manual',
                'category' => 'upcoming_matches',
                'likes_count' => 8,
                'comments_count' => 2,
                'published_at' => now()->subHours(5),
            ],
            [
                'league_id' => 140,
                'league_name' => 'La Liga',
                'country_name' => 'Spain',
                'fixture_id' => 900003,
                'home_team_id' => 541,
                'home_team_name' => 'Real Madrid',
                'home_team_logo' => 'https://media.api-sports.io/football/teams/541.png',
                'away_team_id' => 529,
                'away_team_name' => 'Barcelona',
                'away_team_logo' => 'https://media.api-sports.io/football/teams/529.png',
                'match_starts_at' => now()->addDays(3),
                'prediction_type' => 'Both Teams To Score',
                'prediction_value' => 'Yes',
                'probability' => 69,
                'odds' => 1.70,
                'analysis' => 'This fixture usually creates chances at both ends and both attacking units are in good scoring rhythm.',
                'status' => 'draft',
                'scope' => 'editorial',
                'source' => 'manual',
                'category' => 'popular_matches',
                'likes_count' => 0,
                'comments_count' => 0,
                'published_at' => null,
            ],
            [
                'league_id' => 61,
                'league_name' => 'Ligue 1',
                'country_name' => 'France',
                'fixture_id' => 900004,
                'home_team_id' => 85,
                'home_team_name' => 'Paris Saint Germain',
                'home_team_logo' => 'https://media.api-sports.io/football/teams/85.png',
                'away_team_id' => 91,
                'away_team_name' => 'Monaco',
                'away_team_logo' => 'https://media.api-sports.io/football/teams/91.png',
                'match_starts_at' => now()->addDays(1),
                'prediction_type' => 'Form Angle',
                'prediction_value' => 'Home Win',
                'probability' => 71,
                'odds' => 1.66,
                'analysis' => 'PSG enter this one in stronger form and should still create enough high-value chances to edge the result.',
                'status' => 'published',
                'scope' => 'editorial',
                'source' => 'manual',
                'category' => 'football_trend',
                'likes_count' => 6,
                'comments_count' => 1,
                'published_at' => now()->subHours(1),
            ],
        ])->map(fn (array $attributes) => Prediction::updateOrCreate(
            ['fixture_id' => $attributes['fixture_id']],
            [
                ...$attributes,
                'user_id' => $admin->id,
            ]
        ));

        $firstPrediction = $predictions->first();
        $secondPrediction = $predictions->get(1);

        if ($firstPrediction) {
            $comments = [
                [
                    'prediction_id' => $firstPrediction->id,
                    'user_id' => $communityUsers[0]->id,
                    'body' => 'Tactics were spot on. You can really see how much work the manager has put into this team.',
                    'status' => 'published',
                ],
                [
                    'prediction_id' => $firstPrediction->id,
                    'user_id' => $communityUsers[1]->id,
                    'body' => 'Liverpool still has the edge, but I like the safer angle on the tip here.',
                    'status' => 'published',
                ],
                [
                    'prediction_id' => $firstPrediction->id,
                    'user_id' => $communityUsers[2]->id,
                    'body' => 'The confidence level makes sense if Leeds can keep the midfield compact.',
                    'status' => 'published',
                ],
            ];

            foreach ($comments as $index => $attributes) {
                PredictionComment::updateOrCreate(
                    [
                        'prediction_id' => $attributes['prediction_id'],
                        'user_id' => $attributes['user_id'],
                    ],
                    [
                        ...$attributes,
                        'created_at' => now()->subMinutes(30 - ($index * 5)),
                        'updated_at' => now()->subMinutes(30 - ($index * 5)),
                    ]
                );
            }

            foreach ($communityUsers as $user) {
                PredictionLike::updateOrCreate([
                    'prediction_id' => $firstPrediction->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        if ($secondPrediction) {
            PredictionComment::updateOrCreate(
                [
                    'prediction_id' => $secondPrediction->id,
                    'user_id' => $communityUsers[0]->id,
                ],
                [
                    'body' => 'This one feels open. The over looks fair if both sides press high.',
                    'status' => 'published',
                ]
            );
        }

        Prediction::updateOrCreate(
            [
                'fixture_id' => 990001,
                'category' => 'community_prediction',
            ],
            [
                'user_id' => $communityUsers[1]->id,
                'league_id' => 39,
                'league_name' => 'Premier League',
                'country_name' => 'England',
                'home_team_id' => 34,
                'home_team_name' => 'Newcastle United',
                'home_team_logo' => 'https://media.api-sports.io/football/teams/34.png',
                'away_team_id' => 33,
                'away_team_name' => 'Manchester United',
                'away_team_logo' => 'https://media.api-sports.io/football/teams/33.png',
                'match_starts_at' => now()->addDays(2),
                'prediction_type' => 'Community Pick',
                'prediction_value' => 'BTTS',
                'probability' => 67,
                'odds' => 1.88,
                'analysis' => 'Both attacks have been productive lately and the match profile suits a goals-at-both-ends call.',
                'status' => 'published',
                'scope' => 'community',
                'source' => 'manual',
                'category' => 'community_prediction',
                'likes_count' => 4,
                'comments_count' => 2,
                'published_at' => now()->subMinutes(45),
            ]
        );
    }
}
