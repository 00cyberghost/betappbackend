<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersionSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppVersionSettingController extends Controller
{
    public function index(): Response
    {
        $settings = collect(['android', 'ios', 'web'])
            ->map(fn (string $platform) => AppVersionSetting::query()->firstOrCreate(
                ['platform' => $platform],
                [
                    'message' => 'A new version of Focliq is available. Please update for the best football experience.',
                    'is_active' => true,
                    'is_required' => false,
                ],
            ));

        return Inertia::render('app-versions/index', [
            'settings' => $settings->values(),
        ]);
    }

    public function update(Request $request, AppVersionSetting $appVersionSetting): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'latest_version' => ['nullable', 'string', 'max:50'],
            'latest_build' => ['nullable', 'integer', 'min:0'],
            'minimum_version' => ['nullable', 'string', 'max:50'],
            'minimum_build' => ['nullable', 'integer', 'min:0'],
            'update_url' => ['nullable', 'url', 'max:2048'],
            'message' => ['nullable', 'string', 'max:255'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $appVersionSetting->update($data);

        return redirect('/dashboard/app-versions')->with('success', 'App version setting updated.');
    }
}
