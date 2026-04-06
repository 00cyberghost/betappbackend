import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type LookupItem = {
    id?: number | null;
    name: string | null;
    country?: string | null;
    code?: string | null;
    season?: number | null;
};

type PredictionFormValues = {
    fixture_id: number | null;
    league_id: number | null;
    league_name: string;
    country_name: string;
    home_team_id: number | null;
    home_team_name: string;
    home_team_logo: string;
    away_team_id: number | null;
    away_team_name: string;
    away_team_logo: string;
    match_starts_at: string;
    prediction_type: string;
    prediction_value: string;
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
        countries: LookupItem[];
        leagues: LookupItem[];
        apiConfigured: boolean;
        message?: string;
    };
    initialValues: PredictionFormValues;
    submitLabel: string;
    categories: { value: string; label: string }[];
};

export function PredictionForm({ action, method, lookup, initialValues, submitLabel, categories }: Props) {
    const form = useForm(initialValues);

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
                <Field label="League Name">
                    <Input
                        value={form.data.league_name}
                        onChange={(event) => form.setData('league_name', event.target.value)}
                    />
                </Field>

                <Field label="Country">
                    <Select
                        value={form.data.country_name || undefined}
                        onValueChange={(value) => form.setData('country_name', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select country" />
                        </SelectTrigger>
                        <SelectContent>
                            {lookup.countries.map((country) => (
                                <SelectItem key={country.name ?? country.code} value={country.name ?? ''}>
                                    {country.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="League">
                    <Select
                        value={form.data.league_id ? String(form.data.league_id) : undefined}
                        onValueChange={(value) => {
                            const league = lookup.leagues.find((item) => String(item.id) === value);
                            form.setData('league_id', Number(value));
                            form.setData('league_name', league?.name ?? form.data.league_name);
                            form.setData('country_name', league?.country ?? form.data.country_name);
                        }}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select league" />
                        </SelectTrigger>
                        <SelectContent>
                            {lookup.leagues.map((league) => (
                                <SelectItem key={league.id} value={String(league.id)}>
                                    {league.name} {league.country ? `(${league.country})` : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field label="Fixture ID">
                    <Input
                        type="number"
                        value={form.data.fixture_id ?? ''}
                        onChange={(event) =>
                            form.setData('fixture_id', event.target.value ? Number(event.target.value) : null)
                        }
                    />
                </Field>

                <Field label="Home Team">
                    <Input
                        value={form.data.home_team_name}
                        onChange={(event) => form.setData('home_team_name', event.target.value)}
                    />
                </Field>

                <Field label="Away Team">
                    <Input
                        value={form.data.away_team_name}
                        onChange={(event) => form.setData('away_team_name', event.target.value)}
                    />
                </Field>

                <Field label="Home Team Logo URL">
                    <Input
                        value={form.data.home_team_logo}
                        onChange={(event) => form.setData('home_team_logo', event.target.value)}
                    />
                </Field>

                <Field label="Away Team Logo URL">
                    <Input
                        value={form.data.away_team_logo}
                        onChange={(event) => form.setData('away_team_logo', event.target.value)}
                    />
                </Field>

                <Field label="Kickoff">
                    <Input
                        type="datetime-local"
                        value={form.data.match_starts_at}
                        onChange={(event) => form.setData('match_starts_at', event.target.value)}
                    />
                </Field>

                <Field label="Prediction Type">
                    <Input
                        placeholder="1X2, Under/Over 2.5, BTTS..."
                        value={form.data.prediction_type}
                        onChange={(event) => form.setData('prediction_type', event.target.value)}
                    />
                </Field>

                <Field label="Prediction Value">
                    <Input
                        placeholder="1X, Over, Home win..."
                        value={form.data.prediction_value}
                        onChange={(event) => form.setData('prediction_value', event.target.value)}
                    />
                </Field>

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
