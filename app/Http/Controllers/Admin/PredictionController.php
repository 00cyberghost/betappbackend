<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prediction;
use App\Services\ApiFootballService;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        Prediction::create($data);

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

    public function update(Request $request, Prediction $prediction): RedirectResponse
    {
        $data = $this->validated($request);
        $data['published_at'] = $data['status'] === 'published'
            ? ($prediction->published_at ?? now())
            : null;

        $prediction->update($data);

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
            'country_name' => ['nullable', 'string', 'max:255'],
            'home_team_id' => ['nullable', 'integer'],
            'home_team_name' => ['required', 'string', 'max:255'],
            'home_team_logo' => ['nullable', 'url', 'max:2048'],
            'away_team_id' => ['nullable', 'integer'],
            'away_team_name' => ['required', 'string', 'max:255'],
            'away_team_logo' => ['nullable', 'url', 'max:2048'],
            'match_starts_at' => ['required', 'date'],
            'prediction_type' => ['required', 'string', 'max:100'],
            'prediction_value' => ['required', 'string', 'max:100'],
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
            $countries = collect($apiFootballService->countries()['response'] ?? [])
                ->map(fn (array $country) => [
                    'name' => $country['name'],
                    'code' => $country['code'],
                ])
                ->take(24)
                ->values();

            $leagues = collect($apiFootballService->leagues()['response'] ?? [])
                ->map(fn (array $item) => [
                    'id' => $item['league']['id'] ?? null,
                    'name' => $item['league']['name'] ?? null,
                    'country' => $item['country']['name'] ?? null,
                    'season' => collect($item['seasons'] ?? [])->firstWhere('current', true)['year'] ?? null,
                ])
                ->filter(fn (array $item) => $item['id'] && $item['name'])
                ->take(40)
                ->values();

            return [
                'countries' => $countries,
                'leagues' => $leagues,
                'apiConfigured' => true,
            ];
        } catch (\Throwable $exception) {
            return [
                'countries' => [],
                'leagues' => [],
                'apiConfigured' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
