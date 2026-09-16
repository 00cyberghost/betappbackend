import { Head, router, useForm, usePage } from '@inertiajs/react';
import { PlayCircle, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Match Highlights', href: '/dashboard/match-highlights' },
];

type HighlightItem = {
    id: number;
    title: string;
    youtube_url: string;
    youtube_video_id: string;
    thumbnail_url?: string | null;
    description?: string | null;
    is_published: boolean;
    published_at?: string | null;
    created_at?: string | null;
};

type PaginatedHighlights = {
    data: HighlightItem[];
    links: { url: string | null; label: string; active: boolean }[];
};

export default function MatchHighlightsIndex({ highlights }: { highlights: PaginatedHighlights }) {
    const { flash, errors } = usePage<{ flash?: { success?: string }; errors?: Record<string, string> }>().props;
    const form = useForm({
        title: '',
        youtube_url: '',
        description: '',
        is_published: true,
    });

    const submit = () => {
        form.post('/dashboard/match-highlights', {
            preserveScroll: true,
            onSuccess: () => form.reset('title', 'youtube_url', 'description'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Match Highlights" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Match Highlights</h1>
                    <p className="text-sm text-muted-foreground">
                        Add YouTube match highlight links. Published videos appear on the app homepage and highlights page.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Add Highlight</CardTitle>
                        <CardDescription>Paste a YouTube, YouTube Shorts, or youtu.be link. The thumbnail is generated automatically.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <Field error={errors?.title} label="Title">
                            <Input
                                value={form.data.title}
                                onChange={(event) => form.setData('title', event.target.value)}
                                placeholder="Arsenal 3-1 Chelsea | Match Highlights"
                            />
                        </Field>

                        <Field error={errors?.youtube_url} label="YouTube Link">
                            <Input
                                value={form.data.youtube_url}
                                onChange={(event) => form.setData('youtube_url', event.target.value)}
                                placeholder="https://www.youtube.com/watch?v=..."
                            />
                        </Field>

                        <Field error={errors?.description} label="Description">
                            <textarea
                                className="min-h-28 w-full rounded-xl border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                placeholder="Optional short note for users."
                            />
                        </Field>

                        <label className="flex items-center gap-3 rounded-2xl border p-3 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_published}
                                onChange={(event) => form.setData('is_published', event.target.checked)}
                            />
                            Publish immediately
                        </label>

                        <div className="flex justify-end">
                            <Button disabled={form.processing} onClick={submit}>
                                {form.processing ? 'Saving...' : 'Save Highlight'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Latest Videos</CardTitle>
                        <CardDescription>Manage the videos shown in the Match Highlights section.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {highlights.data.length === 0 ? (
                            <div className="rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                No match highlights have been added yet.
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {highlights.data.map((highlight) => (
                                    <article key={highlight.id} className="overflow-hidden rounded-3xl border bg-card shadow-sm">
                                        <div className="relative aspect-video bg-slate-950">
                                            {highlight.thumbnail_url ? (
                                                <img
                                                    src={highlight.thumbnail_url}
                                                    alt=""
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full items-center justify-center text-muted-foreground">
                                                    No thumbnail
                                                </div>
                                            )}
                                            <div className="absolute inset-0 flex items-center justify-center bg-black/20">
                                                <div className="rounded-full bg-white/90 p-3 text-slate-950 shadow-lg">
                                                    <PlayCircle className="size-7" />
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-3 p-4">
                                            <div>
                                                <h2 className="line-clamp-2 font-semibold">{highlight.title}</h2>
                                                <a
                                                    href={highlight.youtube_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="mt-1 block truncate text-xs text-muted-foreground underline"
                                                >
                                                    {highlight.youtube_url}
                                                </a>
                                            </div>

                                            {highlight.description && (
                                                <p className="line-clamp-2 text-sm text-muted-foreground">{highlight.description}</p>
                                            )}

                                            <div className="flex items-center justify-between gap-3">
                                                <span
                                                    className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                                        highlight.is_published
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-slate-100 text-slate-600'
                                                    }`}
                                                >
                                                    {highlight.is_published ? 'Published' : 'Draft'}
                                                </span>

                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => {
                                                        if (confirm('Delete this match highlight?')) {
                                                            router.delete(`/dashboard/match-highlights/${highlight.id}`, {
                                                                preserveScroll: true,
                                                            });
                                                        }
                                                    }}
                                                >
                                                    <Trash2 className="mr-2 size-4" />
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}

                        {highlights.links.length > 3 && (
                            <div className="mt-6 flex flex-wrap gap-2">
                                {highlights.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        type="button"
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
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

function Field({ children, error, label }: { children: React.ReactNode; error?: string; label: string }) {
    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            {children}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
