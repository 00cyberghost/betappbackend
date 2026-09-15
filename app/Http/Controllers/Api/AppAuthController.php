<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AppAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);

        $token = $this->issueToken($user);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user->fresh()),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        $token = $this->issueToken($user);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function google(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $googleUser = $this->verifyGoogleToken($data['id_token']);
        } catch (RequestException) {
            return response()->json(['message' => 'Unable to verify Google account right now.'], 422);
        }

        if (! $googleUser || empty($googleUser['email']) || empty($googleUser['sub'])) {
            return response()->json(['message' => 'Invalid Google account response.'], 422);
        }

        $user = User::query()
            ->where('google_id', $googleUser['sub'])
            ->orWhere('email', $googleUser['email'])
            ->first();

        if ($user) {
            $user->forceFill([
                'google_id' => $user->google_id ?: $googleUser['sub'],
                'avatar_url' => $user->avatar_url ?: ($googleUser['picture'] ?? null),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser['name'] ?? Str::before($googleUser['email'], '@'),
                'email' => $googleUser['email'],
                'google_id' => $googleUser['sub'],
                'avatar_url' => $googleUser['picture'] ?? null,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ]);
        }

        $token = $this->issueToken($user);

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'api_token' => null,
        ])->save();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    protected function issueToken(User $user): string
    {
        $plainTextToken = Str::random(64);

        $user->forceFill([
            'api_token' => hash('sha256', $plainTextToken),
        ])->save();

        return $plainTextToken;
    }

    protected function verifyGoogleToken(string $idToken): ?array
    {
        $payload = Http::acceptJson()
            ->timeout(10)
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ])
            ->throw()
            ->json();

        $allowedClientIds = config('services.google.client_ids', []);

        if (! $allowedClientIds || ! in_array($payload['aud'] ?? null, $allowedClientIds, true)) {
            return null;
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            return null;
        }

        return $payload;
    }

    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'is_admin' => $user->is_admin,
            'created_at' => optional($user->created_at)?->toIso8601String(),
        ];
    }
}
