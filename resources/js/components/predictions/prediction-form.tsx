import { useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type CountryItem = {
    name: string | null;
    code?: string | null;
    flag?: string | null;
};

type LeagueItem = {
    id?: number | null;
    name: string | null;
    logo?: string | null;
    country?: string | null;
    country_code?: string | null;
    season?: number | null;
};

type FixtureItem = {
    id?: number | null;
    label: string;
    date?: string | null;
    status?: string | null;
    league?: {
        id?: number | null;
        name?: string | null;
        logo?: string | null;
        country?: string | null;
        season?: number | null;
    };
    teams?: {
        home?: { id?: number | null; name?: string | null; logo?: string | null };
        away?: { id?: number | null; name?: string | null; logo?: string | null };
    };
};

type TipItem = {
    id?: number | null;
    prediction_type: string;
    label: string;
    value: string;
    description?: string | null;
};

type PredictionFormValues = {
    fixture_id: number | null;
    league_id: number | null;
    league_name: string;
    league_logo: string;
    country_name: string;
    country_code: string;
    home_team_id: number | null;
    home_team_name: string;
    home_team_logo: string;
    away_team_id: number | null;
    away_team_name: string;
    away_team_logo: string;
    match_starts_at: string;
    prediction_type: string;
    prediction_value: string;
    predicted_score_home: number | null;
    predicted_score_away: number | null;
    probability: number | null;
    odds: number | null;
    analysis: string;
    status: 'draft' | 'published' | 'archived' | 'pending_review';
    scope: 'editorial' | 'community';
    source: 'api_football' | 'manual';
    category: 'today_prediction' | 'upcoming_matches' | 'football_trend' | 'popular_matches' | 'community_prediction' | 'ai_prediction';
};

type Props = {
    action: string;
    method: 'post' | 'put';
    lookup: {
        countries: CountryItem[];
        leagues: LeagueItem[];
        tips: TipItem[];
        apiConfigured: boolean;
        message?: string;
    };
    initialValues: PredictionFormValues;
    submitLabel: string;
    categories: { value: string; label: string }[];
};

export function PredictionForm({ action, method, lookup, initialValues, submitLabel, categories }: Props) {
    const form = useForm(initialValues);
    const [countryQuery, setCountryQuery] = useState(initialValues.country_name);
    const [leagueQuery, setLeagueQuery] = useState(initialValues.league_name);
    const [fixtureQuery, setFixtureQuery] = useState('');
    const [leagueOptions, setLeagueOptions] = useState<LeagueItem[]>(lookup.leagues ?? []);
    const [fixtureOptions, setFixtureOptions] = useState<FixtureItem[]>([]);

    const countries = useMemo(() => lookup.countries ?? [], [lookup.countries]);
    const tips = useMemo(() => lookup.tips ?? [], [lookup.tips]);
    const predictionTypes = useMemo(
        () => Array.from(new Set(tips.map((item) => item.prediction_type))).sort((left, right) => left.localeCompare(right)),
        [tips]
    );
    const predictionValueOptions = useMemo(
        () => tips.filter((item) => item.prediction_type === form.data.prediction_type),
        [tips, form.data.prediction_type]
    );

    useEffect(() => {
        const country = countries.find((item) => item.name === countryQuery);

        if (!country) {
            form.setData('country_name', countryQuery);
            form.setData('country_code', '');

            return;
        }

        form.setData('country_name', country.name ?? '');
        form.setData('country_code', country.code ?? '');
    }, [countries, countryQuery]);

    useEffect(() => {
        let cancelled = false;

        const loadLeagues = async () => {
            const params = new URLSearchParams();

            if (form.data.country_code) {
                params.set('code', form.data.country_code);
            } else if (leagueQuery.trim() !== '') {
                params.set('search', leagueQuery.trim());
            } else {
                setLeagueOptions([]);

                return;
            }

            const response = await fetch(`/api/football/leagues?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!cancelled) {
                setLeagueOptions(payload.data ?? []);
            }
        };

        void loadLeagues();

        return () => {
            cancelled = true;
        };
    }, [form.data.country_code, leagueQuery]);

    useEffect(() => {
        const league = leagueOptions.find((item) => item.name === leagueQuery);

        if (!league) {
            form.setData('league_name', leagueQuery);

            return;
        }

        form.setData('league_id', league.id ? Number(league.id) : null);
        form.setData('league_name', league.name ?? '');
        form.setData('league_logo', league.logo ?? '');
        form.setData('country_name', league.country ?? form.data.country_name);
        form.setData('country_code', league.country_code ?? form.data.country_code);
    }, [leagueOptions, leagueQuery]);

    useEffect(() => {
        let cancelled = false;

        const loadFixtures = async () => {
            if (!form.data.league_id || !form.data.match_starts_at) {
                setFixtureOptions([]);

                return;
            }

            const params = new URLSearchParams({
                league: String(form.data.league_id),
                date: form.data.match_starts_at,
            });

            const season =
                form.data.league_id
                    ? leagueOptions.find((item) => item.id === form.data.league_id)?.season
                    : null;

            if (season) {
                params.set('season', String(season));
            }

            const response = await fetch(`/api/football/fixtures?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json();

            if (!cancelled) {
                setFixtureOptions(payload.data ?? []);
            }
        };

        void loadFixtures();

        return () => {
            cancelled = true;
        };
    }, [form.data.league_id, form.data.match_starts_at, leagueOptions]);

    useEffect(() => {
        if (fixtureQuery.trim() === '') {
            return;
        }

        const fixture = fixtureOptions.find((item) => item.label === fixtureQuery);

        if (!fixture) {
            return;
        }

        form.setData('fixture_id', fixture.id ? Number(fixture.id) : null);
        form.setData('league_id', fixture.league?.id ? Number(fixture.league.id) : form.data.league_id);
        form.setData('league_name', fixture.league?.name ?? form.data.league_name);
        form.setData('league_logo', fixture.league?.logo ?? form.data.league_logo);
        form.setData('country_name', fixture.league?.country ?? form.data.country_name);
        form.setData('home_team_id', fixture.teams?.home?.id ? Number(fixture.teams.home.id) : null);
        form.setData('home_team_name', fixture.teams?.home?.name ?? '');
        form.setData('home_team_logo', fixture.teams?.home?.logo ?? '');
        form.setData('away_team_id', fixture.teams?.away?.id ? Number(fixture.teams.away.id) : null);
        form.setData('away_team_name', fixture.teams?.away?.name ?? '');
        form.setData('away_team_logo', fixture.teams?.away?.logo ?? '');
    }, [fixtureOptions, fixtureQuery]);

    useEffect(() => {
        if (predictionValueOptions.length === 0) {
            return;
        }

        const hasSelectedValue = predictionValueOptions.some((item) => item.value === form.data.prediction_value);

        if (!hasSelectedValue) {
            form.setData('prediction_value', '');
        }
    }, [predictionValueOptions, form.data.prediction_value]);

    const submit = () => {
        if (method === 'post') {
            form.post(action);

            return;
        }

        form.put(action);
    };

    return (
        <div className="space-y-6">
            {!lookup.apiConfigured && (
                <div className="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    API-Football is not configured yet. You can still create manual predictions.
                    {lookup.message ? ` ${lookup.message}` : ''}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-2">
                <Field label="Country">
                    <Input
                        list="prediction-country-options"
                        value={countryQuery}
                        onChange={(event) => setCountryQuery(event.target.value)}
                        placeholder="Search country"
                    />
                    <datalist id="prediction-country-options">
                        {countries.map((country) => (
                            <option key={country.code ?? country.name} value={country.name ?? ''}>
                                {country.code ?? ''}
                            </option>
                        ))}
                    </datalist>
                </Field>

                <Field label="League">
                    <Input
                        list="prediction-league-options"
                        value={leagueQuery}
                        onChange={(event) => setLeagueQuery(event.target.value)}
                        placeholder={form.data.country_code ? 'Select league for country' : 'Search league'}
                    />
                    <datalist id="prediction-league-options">
                        {leagueOptions.map((league) => (
                            <option key={league.id ?? league.name} value={league.name ?? ''}>
                                {league.country ?? ''}
                            </option>
                        ))}
                    </datalist>
                </Field>

                <Field label="Schedule Date">
                    <Input
                        type="date"
                        value={form.data.match_starts_at}
                        onChange={(event) => {
                            form.setData('match_starts_at', event.target.value);
                            setFixtureQuery('');
                            form.setData('fixture_id', null);
                        }}
                    />
                </Field>

                <Field label="Fixture">
                    <Input
                        list="prediction-fixture-options"
                        value={fixtureQuery}
                        onChange={(event) => setFixtureQuery(event.target.value)}
                        placeholder={form.data.league_id ? 'Select exact fixture for this date' : 'Choose a league first'}
                    />
                    <datalist id="prediction-fixture-options">
                        {fixtureOptions.map((fixture) => (
                            <option key={fixture.id ?? fixture.label} value={fixture.label}>
                                {fixture.date ?? ''}
                            </option>
                        ))}
                    </datalist>
                    <p className="text-xs text-muted-foreground">
                        Selecting a fixture auto-fills the teams, logos, and stores the accurate fixture ID for API-Football widgets.
                    </p>
                </Field>

                {/* 
                <Field label="Home Team">
                    <Input
                        value={form.data.home_team_name}
                        readOnly
                        placeholder="Auto-filled from selected fixture"
                    />
                </Field>

                <Field label="Away Team">
                    <Input
                        value={form.data.away_team_name}
                        readOnly
                        placeholder="Auto-filled from selected fixture"
                    />
                </Field>
                */}

                <div className="lg:col-span-2">
                    <Field label="Selected Match">
                        {form.data.fixture_id ? (
                            <div className="rounded-2xl border border-input bg-muted/30 px-4 py-4">
                                <p className="font-medium">
                                    {form.data.home_team_name || 'Home Team'} vs {form.data.away_team_name || 'Away Team'}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Fixture ID: {form.data.fixture_id}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Teams and logos were auto-filled from the selected fixture.
                                </p>
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-input bg-muted/20 px-4 py-4 text-sm text-muted-foreground">
                                Select a fixture above to auto-fill the match teams and attach the correct API-Football fixture.
                            </div>
                        )}
                    </Field>
                </div>

                
                <div className="space-y-2 lg:col-span-2">
                    <Field label="Prediction Type">
                        <Select
                            value={form.data.prediction_type}
                            onValueChange={(value) => {
                                form.setData('prediction_type', value);
                                form.setData('prediction_value', '');
                            }}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select prediction market" />
                            </SelectTrigger>
                            <SelectContent>
                                {predictionTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Prediction type is the market, such as `1X2`, `Double Chance`, or `Both Teams To Score`.
                        </p>
                    </Field>
                </div>
                
                
                <div className='min-w-full'>
                <Field label="Prediction Value">
                    <Select
                        value={form.data.prediction_value}
                        onValueChange={(value) => form.setData('prediction_value', value)}
                        disabled={!form.data.prediction_type}
                        
                    >
                        <SelectTrigger>
                            <SelectValue placeholder={form.data.prediction_type ? 'Select betting tip' : 'Select prediction type first'} />
                        </SelectTrigger>
                        <SelectContent>
                            {predictionValueOptions.map((tip) => (
                                <SelectItem key={`${tip.prediction_type}-${tip.value}`} value={tip.value}>
                                    {tip.label} ({tip.value})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="text-xs text-muted-foreground">
                        Prediction value is the exact betting tip inside that market, for example `1`, `X2`, `Yes`, or `Over 2.5`.
                    </p>
                </Field>
                </div>
                

                <div className="space-y-2 lg:col-span-2">
                    <Label>Predicted Score</Label>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            type="number"
                            min="0"
                            max="99"
                            placeholder="Home score"
                            value={form.data.predicted_score_home ?? ''}
                            onChange={(event) =>
                                form.setData('predicted_score_home', event.target.value ? Number(event.target.value) : null)
                            }
                        />
                        <Input
                            type="number"
                            min="0"
                            max="99"
                            placeholder="Away score"
                            value={form.data.predicted_score_away ?? ''}
                            onChange={(event) =>
                                form.setData('predicted_score_away', event.target.value ? Number(event.target.value) : null)
                            }
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        These score inputs are optional. Leave them blank if you only want to post the market tip.
                    </p>
                </div>

                <Field label="Probability (%)">
                    <Input
                        type="number"
                        min="0"
                        max="100"
                        value={form.data.probability ?? ''}
                        onChange={(event) =>
                            form.setData('probability', event.target.value ? Number(event.target.value) : null)
                        }
                    />
                </Field>

                <Field label="Odds">
                    <Input
                        type="number"
                        step="0.01"
                        value={form.data.odds ?? ''}
                        onChange={(event) =>
                            form.setData('odds', event.target.value ? Number(event.target.value) : null)
                        }
                    />
                </Field>

                <Field label="Status">
                    <Select value={form.data.status} onValueChange={(value: Props['initialValues']['status']) => form.setData('status', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="pending_review">Pending Review</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Scope">
                    <Select value={form.data.scope} onValueChange={(value: Props['initialValues']['scope']) => form.setData('scope', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="editorial">Editorial</SelectItem>
                            <SelectItem value="community">Community</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Source">
                    <Select value={form.data.source} onValueChange={(value: Props['initialValues']['source']) => form.setData('source', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual">Manual</SelectItem>
                            <SelectItem value="api_football">API-Football</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Homepage Category">
                    <Select
                        value={form.data.category}
                        onValueChange={(value: Props['initialValues']['category']) => form.setData('category', value)}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem key={category.value} value={category.value}>
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            </div>

            <Field label="Analysis">
                <textarea
                    className="min-h-40 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    value={form.data.analysis}
                    onChange={(event) => form.setData('analysis', event.target.value)}
                />
            </Field>

            {Object.keys(form.errors).length > 0 && (
                <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Please review the form fields and try again.
                </div>
            )}

            <div className="flex justify-end">
                <Button disabled={form.processing} onClick={submit}>
                    {submitLabel}
                </Button>
            </div>
        </div>
    );
}

function Field({ children, label }: { children: React.ReactNode; label: string }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}
