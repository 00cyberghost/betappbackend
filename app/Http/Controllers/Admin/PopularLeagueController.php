<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncFootballDataJob;
use App\Models\PopularLeague;
use App\Services\ApiFootballService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PopularLeagueController extends Controller
{
    public function index(Request $request, ApiFootballService $apiFootballService): Response
    {
        $search = trim((string) $request->string('search'));
        $results = [];

        if ($search !== '') {
            $results = collect($apiFootballService->searchLeagues($search)['response'] ?? [])
                ->map(function (array $item) {
                    $currentSeason = collect($item['seasons'] ?? [])->firstWhere('current', true);

                    return [
                        'league_id' => $item['league']['id'] ?? null,
                        'name' => $item['league']['name'] ?? null,
                        'type' => $item['league']['type'] ?? null,
                        'logo' => $item['league']['logo'] ?? null,
                        'country' => $item['country']['name'] ?? null,
                        'flag' => $item['country']['flag'] ?? null,
                        'season' => $currentSeason['year'] ?? collect($item['seasons'] ?? [])->last()['year'] ?? now('Africa/Lagos')->year,
                    ];
                })
                ->filter(fn (array $item) => $item['league_id'] && $item['name'])
                ->take(20)
                ->values()
                ->all();
        }

        return Inertia::render('popular-leagues/index', [
            'leagues' => PopularLeague::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $search,
            ],
            'results' => $results,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'league_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'url', 'max:2048'],
            'season' => ['nullable', 'integer', 'min:1900', 'max:2100'],
        ]);

        PopularLeague::updateOrCreate(
            ['league_id' => $data['league_id']],
            [
                ...$data,
                'is_active' => true,
                'sort_order' => PopularLeague::query()->max('sort_order') + 10,
            ],
        );

        return redirect('/dashboard/popular-leagues')->with('success', 'Popular league saved.');
    }

    public function syncData(): RedirectResponse
    {
        try {
            SyncFootballDataJob::dispatchAfterResponse();

            return redirect('/dashboard/popular-leagues')
                ->with('success', 'Football data sync has been queued and will run in the background.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect('/dashboard/popular-leagues')
                ->with('error', 'Football data sync could not be queued: '.$exception->getMessage());
        }
    }

    public function update(Request $request, PopularLeague $popularLeague): RedirectResponse
    {
        $data = $request->validate([
            'season' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'url', 'max:2048'],
            'league_id' => [
                'required',
                'integer',
                Rule::unique('popular_leagues', 'league_id')->ignore($popularLeague->id),
            ],
        ]);

        $popularLeague->update($data);

        return redirect('/dashboard/popular-leagues')->with('success', 'Popular league updated.');
    }

    public function destroy(PopularLeague $popularLeague): RedirectResponse
    {
        $popularLeague->delete();

        return redirect('/dashboard/popular-leagues')->with('success', 'Popular league removed.');
    }
}
