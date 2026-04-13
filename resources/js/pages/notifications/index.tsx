import { Head, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notifications', href: '/dashboard/notifications' },
];

type UserItem = {
    id: number;
    name: string;
    email: string;
    avatar_url?: string | null;
};

export default function NotificationsIndex({ users }: { users: UserItem[] }) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;
    const form = useForm({
        title: '',
        body: '',
        image: null as File | null,
        audience: 'all',
        user_ids: [] as number[],
    });

    const toggleUser = (userId: number) => {
        form.setData(
            'user_ids',
            form.data.user_ids.includes(userId)
                ? form.data.user_ids.filter((id) => id !== userId)
                : [...form.data.user_ids, userId],
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Push Notifications</h1>
                    <p className="text-sm text-muted-foreground">
                        Send a broadcast to all users or a selected audience. You can upload an image for richer notifications.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Create Broadcast</CardTitle>
                        <CardDescription>These notifications are stored in-app and sent to Firebase device tokens.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <Field label="Title">
                            <Input value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                        </Field>

                        <Field label="Body">
                            <textarea
                                className="min-h-32 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={form.data.body}
                                onChange={(event) => form.setData('body', event.target.value)}
                            />
                        </Field>

                        <Field label="Image Upload">
                            <Input
                                type="file"
                                accept="image/*"
                                onChange={(event) => form.setData('image', event.target.files?.[0] ?? null)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Upload JPG, PNG, or WebP. The backend will store it and attach it to the notification.
                            </p>
                        </Field>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Button
                                type="button"
                                variant={form.data.audience === 'all' ? 'default' : 'outline'}
                                onClick={() => form.setData('audience', 'all')}
                            >
                                All Users
                            </Button>
                            <Button
                                type="button"
                                variant={form.data.audience === 'selected' ? 'default' : 'outline'}
                                onClick={() => form.setData('audience', 'selected')}
                            >
                                Selected Users
                            </Button>
                        </div>

                        {form.data.audience === 'selected' && (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {users.map((user) => (
                                    <label key={user.id} className="flex items-center gap-3 rounded-2xl border p-3">
                                        <input
                                            type="checkbox"
                                            checked={form.data.user_ids.includes(user.id)}
                                            onChange={() => toggleUser(user.id)}
                                        />
                                        <div>
                                            <div className="font-medium">{user.name}</div>
                                            <div className="text-sm text-muted-foreground">{user.email}</div>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        )}

                        <div className="flex justify-end">
                            <Button
                                disabled={form.processing}
                                onClick={() => form.post('/dashboard/notifications', { forceFormData: true })}
                            >
                                {form.processing ? 'Sending...' : 'Send Notification'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
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
