import { Head } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function Dashboard({
    stats,
    recentPredictions,
}: {
    stats: {
        predictions: number;
        publishedPredictions: number;
        comments: number;
        likes: number;
    };
    recentPredictions: {
        id: number;
        league_name: string;
        home_team_name: string;
        away_team_name: string;
        prediction_value: string;
        status: string;
    }[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Bet Platform Dashboard</h1>
                    <p className="text-sm text-muted-foreground">
                        This is now the operational base for editorial predictions, moderation, and football data sync.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard title="Predictions" value={stats.predictions} description="Total records in the content pool" />
                    <MetricCard title="Published" value={stats.publishedPredictions} description="Live predictions visible to users" />
                    <MetricCard title="Comments" value={stats.comments} description="Community engagement to moderate" />
                    <MetricCard title="Likes" value={stats.likes} description="Quick pulse on audience response" />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Predictions</CardTitle>
                        <CardDescription>Useful while we wire the mobile app and admin workflows together.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {recentPredictions.map((prediction) => (
                            <div
                                key={prediction.id}
                                className="flex flex-col gap-2 rounded-2xl border p-4 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div>
                                    <div className="text-sm text-muted-foreground">{prediction.league_name}</div>
                                    <div className="font-medium">
                                        {prediction.home_team_name} vs {prediction.away_team_name}
                                    </div>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {prediction.prediction_value} • {prediction.status}
                                </div>
                            </div>
                        ))}

                        {recentPredictions.length === 0 && (
                            <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No predictions yet. Start from the Predictions section in the sidebar.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function MetricCard({
    title,
    value,
    description,
}: {
    title: string;
    value: number;
    description: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}
