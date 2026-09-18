<?php

namespace App\Services;

use App\Models\UserDeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebasePushService
{
    public function sendToTokens(array $tokens, string $title, string $body, array $data = [], ?string $imageUrl = null): void
    {
        $tokens = collect($tokens)
            ->filter(fn ($token) => is_string($token) && $token !== '')
            ->unique()
            ->values()
            ->all();

        $credentialsPath = (string) config('services.firebase.credentials');

        if ($tokens === [] || $credentialsPath === '') {
            return;
        }

        $stringData = $this->stringifyData($data);

        $this->sendViaHttpV1($credentialsPath, $tokens, $title, $body, $stringData, $imageUrl);
    }

    protected function sendViaHttpV1(
        string $credentialsPath,
        array $tokens,
        string $title,
        string $body,
        array $data,
        ?string $imageUrl = null
    ): void {
        if (! is_file($credentialsPath)) {
            Log::warning('Firebase credentials file was not found.', [
                'path' => $credentialsPath,
            ]);

            return;
        }

        $projectId = $this->resolveProjectId($credentialsPath);

        if (! $projectId) {
            Log::warning('Firebase project id could not be resolved from credentials.');

            return;
        }

        try {
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $credentialsPath
            );

            $authToken = $credentials->fetchAuthToken();
            $accessToken = $authToken['access_token'] ?? null;

            if (! $accessToken) {
                Log::warning('Firebase access token could not be created.', [
                    'payload' => $authToken,
                ]);

                return;
            }

            foreach ($tokens as $token) {
                $payload = [
                    'message' => [
                        'token' => $token,
                        'notification' => array_filter([
                            'title' => $title,
                            'body' => $body,
                            'image' => $imageUrl,
                        ]),
                        'data' => $data,
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'default',
                            ],
                        ],
                        'apns' => [
                            'headers' => [
                                'apns-priority' => '10',
                            ],
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ],
                ];

                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

                if ($response->failed()) {
                    $errorCode = $this->firebaseErrorCode($response->json() ?? []);

                    if ($this->isUnregisteredTokenError($response->status(), $errorCode)) {
                        UserDeviceToken::query()->where('token', $token)->delete();

                        Log::info('Removed unregistered Firebase device token.', [
                            'error_code' => $errorCode,
                            'token_suffix' => substr($token, -12),
                        ]);

                        continue;
                    }

                    Log::warning('Firebase HTTP v1 push delivery failed.', [
                        'status' => $response->status(),
                        'body' => $response->json() ?? $response->body(),
                        'error_code' => $errorCode,
                        'token_suffix' => substr($token, -12),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::error('Firebase HTTP v1 push delivery crashed.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function resolveProjectId(string $credentialsPath): ?string
    {
        $contents = json_decode((string) file_get_contents($credentialsPath), true);

        return is_array($contents) ? ($contents['project_id'] ?? null) : null;
    }

    protected function stringifyData(array $data): array
    {
        return array_map(
            fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
            $data
        );
    }

    protected function firebaseErrorCode(array $payload): ?string
    {
        $error = $payload['error'] ?? [];

        foreach ($error['details'] ?? [] as $detail) {
            if (($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                return $detail['errorCode'] ?? null;
            }
        }

        return $error['status'] ?? $error['message'] ?? null;
    }

    protected function isUnregisteredTokenError(int $status, ?string $errorCode): bool
    {
        return $status === 404 && in_array($errorCode, [
            'UNREGISTERED',
            'NOT_FOUND',
            'NotRegistered',
        ], true);
    }
}
