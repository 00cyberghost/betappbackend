<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersionSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppVersionController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'version' => ['nullable', 'string', 'max:50'],
            'build' => ['nullable', 'integer', 'min:0'],
        ]);

        $setting = AppVersionSetting::query()
            ->where('platform', $data['platform'])
            ->where('is_active', true)
            ->first();

        if (! $setting) {
            return response()->json([
                'update_available' => false,
                'force_update' => false,
                'latest_version' => null,
                'latest_build' => null,
                'message' => null,
                'update_url' => null,
            ]);
        }

        $version = $data['version'] ?? null;
        $build = $data['build'] ?? null;

        $belowLatest = $this->isBehind($version, $build, $setting->latest_version, $setting->latest_build);
        $belowMinimum = $this->isBehind($version, $build, $setting->minimum_version, $setting->minimum_build);
        $forceUpdate = $belowMinimum || ($belowLatest && $setting->is_required);

        return response()->json([
            'update_available' => $belowLatest || $forceUpdate,
            'force_update' => $forceUpdate,
            'latest_version' => $setting->latest_version,
            'latest_build' => $setting->latest_build,
            'message' => $setting->message ?: 'A new version of Focliq is available.',
            'update_url' => $setting->update_url,
        ]);
    }

    protected function isBehind(?string $currentVersion, ?int $currentBuild, ?string $targetVersion, ?int $targetBuild): bool
    {
        if ($targetBuild !== null && $currentBuild !== null) {
            return $currentBuild < $targetBuild;
        }

        if ($targetVersion !== null && $currentVersion !== null) {
            return version_compare($currentVersion, $targetVersion, '<');
        }

        return false;
    }
}
