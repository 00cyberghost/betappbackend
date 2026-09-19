import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, Save, Search, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Popular Leagues', href: '/dashboard/popular-leagues' },
];

type PopularLeague = {
    id: number;
    league_id: number;
    name: string;
    country?: string | null;
    logo?: string | null;
    season?: number | null;
    is_active: boolean;
    sort_order: number;
};

type LeagueResult = {
    league_id: number;
    name: string;
    type?: string | null;
    logo?: string | null;
    country?: string | null;
    flag?: string | null;
    season?: number | null;
};

export default function PopularLeaguesIndex({
    leagues,
    filters,
    results,
}: {
    leagues: PopularLeague[];
    filters: { search: string };
    results: LeagueResult[];
}) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Popular Leagues" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Popular Leagues</h1>
                    <p className="text-sm text-muted-foreground">
                        Choose the leagues used by the daily API-Football AI prediction sync. Active leagues are synced for today and the next two days.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Find A League</CardTitle>
                        <CardDescription>Search API-Football and add competitions to the controlled sync list.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-col gap-3 md:flex-row">
                            <Input
                                defaultValue={filters.search}
                                placeholder="Search Premier League, Champions League, MLS..."
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        router.get('/dashboard/popular-leagues', { search: event.currentTarget.value }, { preserveState: true });
                                    }
                                }}
                            />
                            <Button
                                type="button"
                                onClick={() => {
                                    const input = document.querySelector<HTMLInputElement>('input[placeholder^="Search Premier League"]');
                                    router.get('/dashboard/popular-leagues', { search: input?.value ?? '' }, { preserveState: true });
                                }}
                            >
                                <Search className="mr-2 size-4" />
                                Search
                            </Button>
                        </div>

                        {results.length > 0 && (
                            <div className="grid gap-3 lg:grid-cols-2">
                                {results.map((result) => (
                                    <LeagueResultCard key={`${result.league_id}-${result.season}`} result={result} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Sync List</CardTitle>
                        <CardDescription>
                            Only active leagues here are used by <code>php artisan football:sync-data</code>.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {leagues.length === 0 ? (
                            <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No popular leagues have been configured yet.
                            </div>
                        ) : (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {leagues.map((league) => (
                                    <PopularLeagueCard key={league.id} league={league} />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function LeagueResultCard({ result }: { result: LeagueResult }) {
    const form = useForm({
        league_id: result.league_id,
        name: result.name,
        country: result.country ?? '',
        logo: result.logo ?? result.flag ?? '',
        season: result.season ?? new Date().getFullYear(),
    });

    return (
        <article className="flex items-center gap-4 rounded-2xl border p-4">
            <LeagueLogo src={result.logo ?? result.flag} />
            <div className="min-w-0 flex-1">
                <h2 className="truncate font-semibold">{result.name}</h2>
                <p className="text-sm text-muted-foreground">
                    {[result.country, result.type, result.season ? `Season ${result.season}` : null].filter(Boolean).join(' • ')}
                </p>
            </div>
            <Button
                type="button"
                disabled={form.processing}
                onClick={() => form.post('/dashboard/popular-leagues', { preserveScroll: true })}
            >
                <Plus className="mr-2 size-4" />
                Add
            </Button>
        </article>
    );
}

function PopularLeagueCard({ league }: { league: PopularLeague }) {
    const form = useForm({
        league_id: league.league_id,
        name: league.name,
        country: league.country ?? '',
        logo: league.logo ?? '',
        season: league.season ?? new Date().getFullYear(),
        is_active: league.is_active,
        sort_order: league.sort_order,
    });

    return (
        <article className="rounded-3xl border bg-card p-4 shadow-sm">
            <div className="flex items-start gap-4">
                <LeagueLogo src={form.data.logo} />
                <div className="min-w-0 flex-1 space-y-4">
                    <div>
                        <h2 className="truncate font-semibold">{league.name}</h2>
                        <p className="text-sm text-muted-foreground">
                            API league ID: {league.league_id} {league.country ? `• ${league.country}` : ''}
                        </p>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                        <Field label="Name">
                            <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                        </Field>
                        <Field label="Country">
                            <Input value={form.data.country} onChange={(event) => form.setData('country', event.target.value)} />
                        </Field>
                        <Field label="Season">
                            <Input
                                type="number"
                                value={form.data.season}
                                onChange={(event) => form.setData('season', Number(event.target.value))}
                            />
                        </Field>
                        <Field label="Sort order">
                            <Input
                                type="number"
                                value={form.data.sort_order}
                                onChange={(event) => form.setData('sort_order', Number(event.target.value))}
                            />
                        </Field>
                    </div>

                    <Field label="Logo URL">
                        <Input value={form.data.logo} onChange={(event) => form.setData('logo', event.target.value)} />
                    </Field>

                    <label className="flex items-center gap-3 rounded-2xl border p-3 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) => form.setData('is_active', event.target.checked)}
                        />
                        Active for daily sync
                    </label>

                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => form.put(`/dashboard/popular-leagues/${league.id}`, { preserveScroll: true })}
                        >
                            <Save className="mr-2 size-4" />
                            Save
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => {
                                if (confirm('Remove this league from the popular sync list?')) {
                                    router.delete(`/dashboard/popular-leagues/${league.id}`, { preserveScroll: true });
                                }
                            }}
                        >
                            <Trash2 className="mr-2 size-4" />
                            Remove
                        </Button>
                    </div>
                </div>
            </div>
        </article>
    );
}

function LeagueLogo({ src }: { src?: string | null }) {
    return (
        <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-slate-100">
            {src ? <img src={src} alt="" className="size-10 rounded-full object-contain" /> : <span className="text-xs text-muted-foreground">Logo</span>}
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
