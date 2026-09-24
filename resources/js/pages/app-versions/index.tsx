import { Head, useForm, usePage } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'App Versions', href: '/dashboard/app-versions' },
];

type AppVersionSetting = {
    id: number;
    platform: 'android' | 'ios' | 'web';
    latest_version?: string | null;
    latest_build?: number | null;
    minimum_version?: string | null;
    minimum_build?: number | null;
    update_url?: string | null;
    message?: string | null;
    is_required: boolean;
    is_active: boolean;
};

export default function AppVersionsIndex({ settings }: { settings: AppVersionSetting[] }) {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="App Versions" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">App Versions</h1>
                    <p className="text-sm text-muted-foreground">
                        Control optional and required app updates for Android, iOS, and web from the backend.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                <div className="grid gap-5 xl:grid-cols-3">
                    {settings.map((setting) => (
                        <VersionCard key={setting.id} setting={setting} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function VersionCard({ setting }: { setting: AppVersionSetting }) {
    const form = useForm({
        platform: setting.platform,
        latest_version: setting.latest_version ?? '',
        latest_build: setting.latest_build ?? '',
        minimum_version: setting.minimum_version ?? '',
        minimum_build: setting.minimum_build ?? '',
        update_url: setting.update_url ?? '',
        message: setting.message ?? '',
        is_required: setting.is_required,
        is_active: setting.is_active,
    });

    const submit = () => {
        form.put(`/dashboard/app-versions/${setting.id}`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="capitalize">{setting.platform}</CardTitle>
                <CardDescription>
                    Latest version shows an optional update. Minimum version/build forces old apps to update.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    <Field label="Latest version">
                        <Input
                            value={form.data.latest_version}
                            placeholder="1.0.1"
                            onChange={(event) => form.setData('latest_version', event.target.value)}
                        />
                    </Field>
                    <Field label="Latest build">
                        <Input
                            type="number"
                            value={form.data.latest_build}
                            placeholder="2"
                            onChange={(event) => form.setData('latest_build', event.target.value === '' ? '' : Number(event.target.value))}
                        />
                    </Field>
                    <Field label="Minimum version">
                        <Input
                            value={form.data.minimum_version}
                            placeholder="1.0.0"
                            onChange={(event) => form.setData('minimum_version', event.target.value)}
                        />
                    </Field>
                    <Field label="Minimum build">
                        <Input
                            type="number"
                            value={form.data.minimum_build}
                            placeholder="1"
                            onChange={(event) => form.setData('minimum_build', event.target.value === '' ? '' : Number(event.target.value))}
                        />
                    </Field>
                </div>

                <Field label="Store/update URL">
                    <Input
                        value={form.data.update_url}
                        placeholder="https://play.google.com/store/apps/details?id=com.betextract"
                        onChange={(event) => form.setData('update_url', event.target.value)}
                    />
                </Field>

                <Field label="Update message">
                    <Input
                        value={form.data.message}
                        placeholder="A new version of Focliq is available."
                        onChange={(event) => form.setData('message', event.target.value)}
                    />
                </Field>

                <label className="flex items-center gap-3 rounded-2xl border p-3 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_active}
                        onChange={(event) => form.setData('is_active', event.target.checked)}
                    />
                    Enable update checks for this platform
                </label>

                <label className="flex items-center gap-3 rounded-2xl border p-3 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_required}
                        onChange={(event) => form.setData('is_required', event.target.checked)}
                    />
                    Require update when below latest version
                </label>

                <Button type="button" className="w-full" disabled={form.processing} onClick={submit}>
                    <Save className="mr-2 size-4" />
                    {form.processing ? 'Saving...' : 'Save version settings'}
                </Button>
            </CardContent>
        </Card>
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
