import { Head } from '@inertiajs/react';
import { PredictionForm } from '@/components/predictions/prediction-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Predictions', href: '/dashboard/predictions' },
    { title: 'Create', href: '/dashboard/predictions/create' },
];

export default function CreatePrediction({ lookup, categories }: { lookup: any; categories: { value: string; label: string }[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Prediction" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Create Prediction</h1>
                    <p className="text-sm text-muted-foreground">
                        Editorial predictions created here will become the backend source of truth for the app.
                    </p>
                </div>

                <PredictionForm
                    action="/dashboard/predictions"
                    method="post"
                    lookup={lookup}
                    categories={categories}
                    submitLabel="Post Prediction"
                    initialValues={{
                        fixture_id: null,
                        league_id: null,
                        league_name: '',
                        country_name: '',
                        home_team_id: null,
                        home_team_name: '',
                        home_team_logo: '',
                        away_team_id: null,
                        away_team_name: '',
                        away_team_logo: '',
                        match_starts_at: '',
                        prediction_type: '1X2',
                        prediction_value: '',
                        probability: null,
                        odds: null,
                        analysis: '',
                        status: 'draft',
                        scope: 'editorial',
                        source: 'manual',
                        category: 'today_prediction',
                    }}
                />
            </div>
        </AppLayout>
    );
}
