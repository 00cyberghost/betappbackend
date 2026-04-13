<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use App\Models\Prediction;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\ApiFootballService;
use App\Services\FirebasePushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PredictionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $status = trim((string) $request->string('status'));
        $category = trim((string) $request->string('category'));

        $predictions = Prediction::query()
            ->with('user:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('league_name', 'like', "%{$search}%")
                        ->orWhere('home_team_name', 'like', "%{$search}%")
                        ->orWhere('away_team_name', 'like', "%{$search}%")
                        ->orWhere('prediction_value', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('predictions/index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
            ],
            'predictions' => $predictions,
            'categories' => $this->editorialCategories(),
        ]);
    }

    public function create(ApiFootballService $apiFootballService): Response
    {
        return Inertia::render('predictions/create', [
            'lookup' => $this->lookupPayload($apiFootballService),
            'categories' => $this->editorialCategories(),
        ]);
    }

    public function store(Request $request, AppNotificationService $appNotificationService, FirebasePushService $firebasePushService): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        $prediction = Prediction::create($data);

        if ($prediction->status === 'published') {
            $users = User::query()->where('id', '!=', $request->user()->id)->get();
            $appNotificationService->notifyPredictionPublished($users, $prediction);
            $firebasePushService->sendToTokens(
                $users->flatMap(fn (User $user) => $user->deviceTokens()->pluck('token'))->all(),
                'New prediction posted',
                "{$prediction->home_team_name} vs {$prediction->away_team_name} is now live.",
                ['prediction_id' => $prediction->id]
            );
        }

        return redirect('/dashboard/predictions')->with('success', 'Prediction created.');
    }

    public function edit(Prediction $prediction, ApiFootballService $apiFootballService): Response
    {
        return Inertia::render('predictions/edit', [
            'prediction' => $prediction,
            'lookup' => $this->lookupPayload($apiFootballService),
            'categories' => $this->editorialCategories(),
        ]);
    }

    public function update(Request $request, Prediction $prediction, AppNotificationService $appNotificationService, FirebasePushService $firebasePushService): RedirectResponse
    {
        $data = $this->validated($request);
        $wasPublished = $prediction->status === 'published';
        $data['published_at'] = $data['status'] === 'published'
            ? ($prediction->published_at ?? now())
            : null;

        $prediction->update($data);

        if (! $wasPublished && $prediction->status === 'published') {
            $users = User::query()->where('id', '!=', $request->user()->id)->get();
            $appNotificationService->notifyPredictionPublished($users, $prediction);
            $firebasePushService->sendToTokens(
                $users->flatMap(fn (User $user) => $user->deviceTokens()->pluck('token'))->all(),
                'New prediction posted',
                "{$prediction->home_team_name} vs {$prediction->away_team_name} is now live.",
                ['prediction_id' => $prediction->id]
            );
        }

        return redirect('/dashboard/predictions')->with('success', 'Prediction updated.');
    }

    public function destroy(Prediction $prediction): RedirectResponse
    {
        $prediction->delete();

        return redirect('/dashboard/predictions')->with('success', 'Prediction deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'fixture_id' => ['nullable', 'integer'],
            'league_id' => ['nullable', 'integer'],
            'league_name' => ['required', 'string', 'max:255'],
            'league_logo' => ['nullable', 'url', 'max:2048'],
            'country_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'home_team_id' => ['nullable', 'integer'],
            'home_team_name' => ['required', 'string', 'max:255'],
            'home_team_logo' => ['nullable', 'url', 'max:2048'],
            'away_team_id' => ['nullable', 'integer'],
            'away_team_name' => ['required', 'string', 'max:255'],
            'away_team_logo' => ['nullable', 'url', 'max:2048'],
            'match_starts_at' => ['required', 'date'],
            'prediction_type' => ['required', 'string', 'max:100'],
            'prediction_value' => ['required', 'string', 'max:100'],
            'predicted_score_home' => ['nullable', 'integer', 'min:0', 'max:99'],
            'predicted_score_away' => ['nullable', 'integer', 'min:0', 'max:99'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'odds' => ['nullable', 'numeric', 'min:0'],
            'analysis' => ['required', 'string'],
            'status' => ['required', 'in:draft,published,archived,pending_review'],
            'scope' => ['required', 'in:editorial,community'],
            'source' => ['required', 'in:api_football,manual'],
            'category' => ['required', 'in:today_prediction,upcoming_matches,football_trend,popular_matches,community_prediction,ai_prediction'],
        ]);
    }

    protected function editorialCategories(): array
    {
        return [
            ['value' => 'today_prediction', 'label' => 'Today Prediction'],
            ['value' => 'upcoming_matches', 'label' => 'Upcoming Matches'],
            ['value' => 'football_trend', 'label' => 'Football Trend'],
            ['value' => 'popular_matches', 'label' => 'Popular Matches'],
        ];
    }

    protected function lookupPayload(ApiFootballService $apiFootballService): array
    {
        try {
            $countries = \App\Models\Country::query()
                ->orderBy('name')
                ->get(['name', 'code', 'flag'])
                ->map(fn ($country) => [
                    'name' => $country->name,
                    'code' => $country->code,
                    'flag' => $country->flag,
                ])
                ->values();

            $tips = Tip::query()
                ->where('is_active', true)
                ->orderBy('prediction_type')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(['id', 'prediction_type', 'label', 'value', 'description'])
                ->values();

            return [
                'countries' => $countries,
                'leagues' => [],
                'tips' => $tips,
                'apiConfigured' => true,
            ];
        } catch (\Throwable $exception) {
            return [
                'countries' => [],
                'leagues' => [],
                'tips' => [],
                'apiConfigured' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
