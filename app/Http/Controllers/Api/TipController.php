<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = trim((string) $request->string('type'));

        $tips = Tip::query()
            ->where('is_active', true)
            ->when($type !== '', fn ($query) => $query->where('prediction_type', $type))
            ->orderBy('prediction_type')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'prediction_type', 'label', 'value', 'description']);

        return response()->json([
            'data' => $tips,
            'types' => $tips->pluck('prediction_type')->unique()->values(),
        ]);
    }
}
