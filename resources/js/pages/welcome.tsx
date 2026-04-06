import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props as { auth: { user?: { name: string } | null } };

    return (
        <>
            <Head title="Bet Platform">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
            </Head>

            <div className="min-h-screen bg-slate-950 text-white">
                <div className="mx-auto flex min-h-screen max-w-7xl flex-col px-6 py-8">
                    <header className="flex items-center justify-between">
                        <div>
                            <div className="text-sm font-medium uppercase tracking-[0.24em] text-emerald-300">
                                Bet Platform
                            </div>
                            <div className="mt-2 text-sm text-slate-300">
                                Editorial football predictions, community engagement, and API-Football-powered match context.
                            </div>
                        </div>

                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href="/dashboard"
                                    className="rounded-full bg-emerald-400 px-5 py-2 text-sm font-semibold text-slate-950"
                                >
                                    Open Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link href="/login" className="rounded-full border border-slate-700 px-5 py-2 text-sm">
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href="/register"
                                            className="rounded-full bg-emerald-400 px-5 py-2 text-sm font-semibold text-slate-950"
                                        >
                                            Register
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </header>

                    <main className="grid flex-1 gap-12 py-16 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                        <div className="space-y-8">
                            <div className="inline-flex rounded-full border border-emerald-400/30 bg-emerald-400/10 px-4 py-2 text-sm text-emerald-200">
                                Backend source of truth for the mobile app
                            </div>

                            <div className="space-y-5">
                                <h1 className="max-w-3xl text-5xl font-semibold tracking-tight text-white lg:text-6xl">
                                    Build, publish, and manage football predictions from one control room.
                                </h1>
                                <p className="max-w-2xl text-lg leading-8 text-slate-300">
                                    This Laravel and Inertia backend is set up to manage editorial predictions, power the React Native client,
                                    and safely integrate with API-Football for fixtures, standings, lineups, and stats.
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <FeatureCard
                                    title="Prediction Ops"
                                    body="Create and publish predictions with structured metadata for leagues, fixtures, and confidence."
                                />
                                <FeatureCard
                                    title="Community Layer"
                                    body="Support comments, likes, moderation, and eventually user-submitted predictions."
                                />
                                <FeatureCard
                                    title="Football Data"
                                    body="Cache API-Football responses server-side instead of exposing keys in the app."
                                />
                            </div>
                        </div>

                        <div className="rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-2xl shadow-emerald-500/10 backdrop-blur">
                            <div className="rounded-[1.5rem] bg-slate-900/80 p-6">
                                <div className="text-sm text-slate-400">Current implementation direction</div>
                                <ul className="mt-4 space-y-4 text-sm text-slate-200">
                                    <li>Prediction records store the editorial truth the app can consume.</li>
                                    <li>API endpoints are ready for countries, leagues, fixtures, and fixture detail blocks.</li>
                                    <li>The admin dashboard is being shaped for prediction operations first.</li>
                                    <li>The next UI pass can now safely wire `PredictionDetails.tsx` to backend data.</li>
                                </ul>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}

function FeatureCard({ title, body }: { title: string; body: string }) {
    return (
        <div className="rounded-[1.5rem] border border-white/10 bg-white/5 p-5">
            <div className="text-base font-semibold text-white">{title}</div>
            <div className="mt-2 text-sm leading-6 text-slate-300">{body}</div>
        </div>
    );
}
