<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppPasswordResetCode;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

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

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            AppPasswordResetCode::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->delete();

            AppPasswordResetCode::create([
                'user_id' => $user->id,
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(15),
            ]);

            Mail::raw(
                "Your Focliq password reset code is {$code}.\n\nThis code expires in 15 minutes. If you did not request it, you can ignore this email.",
                fn ($message) => $message
                    ->to($user->email)
                    ->subject('Your Focliq password reset code')
            );
        }

        return response()->json([
            'message' => 'If this email exists, a password reset code has been sent. The code expires in 15 minutes.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $email = strtolower(trim($data['email']));
        $resetCode = AppPasswordResetCode::query()
            ->with('user')
            ->where('email', $email)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (
            ! $resetCode ||
            $resetCode->expires_at->isPast() ||
            $resetCode->attempts >= 5 ||
            ! Hash::check($data['code'], $resetCode->code_hash)
        ) {
            if ($resetCode && $resetCode->attempts < 5) {
                $resetCode->increment('attempts');
            }

            throw ValidationException::withMessages([
                'code' => 'The reset code is invalid or has expired.',
            ]);
        }

        $resetCode->user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
            'api_token' => null,
        ])->save();

        $resetCode->update([
            'used_at' => now(),
        ]);

        return response()->json([
            'message' => 'Your password has been reset. You can now log in with your new password.',
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
