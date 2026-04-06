import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Predictions', href: '/dashboard/predictions' },
];

type PredictionItem = {
    id: number;
    league_name: string;
    home_team_name: string;
    away_team_name: string;
    prediction_value: string;
    status: string;
    category: string;
    probability: number | null;
    published_at: string | null;
    user: { name: string } | null;
};

export default function PredictionsIndex({
    predictions,
    filters,
    categories,
}: {
    predictions: {
        data: PredictionItem[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search: string; status: string; category: string };
    categories: { value: string; label: string }[];
}) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Predictions" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Predictions</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage editorial predictions that will feed the mobile app and web surfaces.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/dashboard/predictions/create">Create Prediction</Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Quickly narrow down the list. Editorial categories: {categories.map((category) => category.label).join(', ')}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 lg:flex-row">
                        <Input
                            defaultValue={filters.search}
                            placeholder="Search league, teams, or tip"
                            onBlur={(event) =>
                                router.get(
                                    '/dashboard/predictions',
                                    { ...filters, search: event.target.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                        <Input
                            defaultValue={filters.status}
                            placeholder="Status"
                            onBlur={(event) =>
                                router.get(
                                    '/dashboard/predictions',
                                    { ...filters, status: event.target.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                        <Input
                            defaultValue={filters.category}
                            placeholder="Category"
                            onBlur={(event) =>
                                router.get(
                                    '/dashboard/predictions',
                                    { ...filters, category: event.target.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Prediction Queue</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {predictions.data.map((prediction) => (
                            <div
                                key={prediction.id}
                                className="flex flex-col gap-3 rounded-2xl border p-4 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div className="space-y-1">
                                    <div className="text-sm text-muted-foreground">{prediction.league_name}</div>
                                    <div className="font-medium">
                                        {prediction.home_team_name} vs {prediction.away_team_name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Tip: {prediction.prediction_value}
                                        {prediction.probability ? ` • ${prediction.probability}% confidence` : ''}
                                        {prediction.user ? ` • by ${prediction.user.name}` : ''}
                                    </div>
                                    <div className="text-xs uppercase tracking-wide text-muted-foreground">
                                        {prediction.category.replaceAll('_', ' ')}
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <span className="rounded-full bg-secondary px-3 py-1 text-xs font-medium capitalize">
                                        {prediction.status.replace('_', ' ')}
                                    </span>
                                    <Button asChild variant="outline">
                                        <Link href={`/dashboard/predictions/${prediction.id}/edit`}>Edit</Link>
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {predictions.data.length === 0 && (
                            <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No predictions yet. Start with the first editorial post.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
