import { Head } from '@inertiajs/react';
import { PredictionForm } from '@/components/predictions/prediction-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Predictions', href: '/dashboard/predictions' },
    { title: 'Edit', href: '#' },
];

export default function EditPrediction({
    prediction,
    lookup,
    categories,
}: {
    prediction: any;
    lookup: any;
    categories: { value: string; label: string }[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Prediction" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Edit Prediction</h1>
                    <p className="text-sm text-muted-foreground">
                        Update the editorial record without breaking the mobile app contract.
                    </p>
                </div>

                <PredictionForm
                    action={`/dashboard/predictions/${prediction.id}`}
                    method="put"
                    lookup={lookup}
                    categories={categories}
                    submitLabel="Save Changes"
                    initialValues={{
                        fixture_id: prediction.fixture_id,
                        league_id: prediction.league_id,
                        league_name: prediction.league_name ?? '',
                        league_logo: prediction.league_logo ?? '',
                        country_name: prediction.country_name ?? '',
                        country_code: prediction.country_code ?? '',
                        home_team_id: prediction.home_team_id,
                        home_team_name: prediction.home_team_name ?? '',
                        home_team_logo: prediction.home_team_logo ?? '',
                        away_team_id: prediction.away_team_id,
                        away_team_name: prediction.away_team_name ?? '',
                        away_team_logo: prediction.away_team_logo ?? '',
                        match_starts_at: prediction.match_starts_at ? prediction.match_starts_at.slice(0, 10) : '',
                        prediction_type: prediction.prediction_type ?? '1X2',
                        prediction_value: prediction.prediction_value ?? '',
                        predicted_score_home: prediction.predicted_score_home,
                        predicted_score_away: prediction.predicted_score_away,
                        probability: prediction.probability,
                        odds: prediction.odds ? Number(prediction.odds) : null,
                        analysis: prediction.analysis ?? '',
                        status: prediction.status,
                        scope: prediction.scope,
                        source: prediction.source,
                        category: prediction.category ?? 'today_prediction',
                    }}
                />
            </div>
        </AppLayout>
    );
}
