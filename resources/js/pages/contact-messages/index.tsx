import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contact Messages', href: '/dashboard/contact-messages' },
];

type ContactMessage = {
    id: number;
    subject: string;
    message: string;
    status: string;
    read_at?: string | null;
    created_at?: string | null;
    user?: {
        name: string;
        email: string;
    } | null;
};

type PaginatedMessages = {
    data: ContactMessage[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function ContactMessagesIndex({ messages }: { messages: PaginatedMessages }) {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contact Messages" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Contact Messages</h1>
                    <p className="text-sm text-muted-foreground">
                        Review feedback and complaints sent by users from the app profile section.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>User Feedback Inbox</CardTitle>
                        <CardDescription>Unread messages are highlighted until you mark them as read.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {messages.data.length === 0 ? (
                            <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No contact messages yet.
                            </div>
                        ) : (
                            messages.data.map((message) => (
                                <article
                                    key={message.id}
                                    className={`rounded-2xl border p-4 ${
                                        message.status === 'new' ? 'border-emerald-200 bg-emerald-50/60' : 'bg-card'
                                    }`}
                                >
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="text-lg font-semibold">{message.subject}</h2>
                                                <span
                                                    className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                        message.status === 'new'
                                                            ? 'bg-emerald-600 text-white'
                                                            : 'bg-secondary text-secondary-foreground'
                                                    }`}
                                                >
                                                    {message.status}
                                                </span>
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {message.user?.name ?? 'Unknown user'} • {message.user?.email ?? 'No email'} • {formatDate(message.created_at)}
                                            </div>
                                        </div>

                                        {message.status === 'new' && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(`/dashboard/contact-messages/${message.id}/read`, {}, { preserveScroll: true })
                                                }
                                            >
                                                Mark Read
                                            </Button>
                                        )}
                                    </div>

                                    <p className="mt-4 whitespace-pre-wrap text-sm leading-6">{message.message}</p>
                                </article>
                            ))
                        )}

                        {messages.links.length > 3 && (
                            <div className="flex flex-wrap gap-2 pt-4">
                                {messages.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        type="button"
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.visit(link.url, { preserveScroll: true })}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function formatDate(value?: string | null) {
    if (!value) return 'Just now';

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
