<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NapCallbackService
{
    /**
     * Build callback payload from row data and result.
     *
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    public static function buildPayload(
        array $rowData,
        ?string $napCode,
        string $status,
        string $comment = '',
    ): array {
        $rrForm = $rowData['rr_form'] ?? [];
        $identification = $rowData['identification'] ?? [];
        $person = $rowData['person'] ?? [];
        $context = $rowData['context'] ?? [];
        $service = $rowData['service'] ?? [];

        return [
            'source_id' => $service['source_id'] ?? null,
            'source' => $context['source'] ?? null,
            'uic' => $identification['uic'] ?? null,
            'id_card' => $identification['pid'] ?? $rrForm['pid'] ?? null,
            'kp' => $person['kp'] ?? null,
            'fy' => $context['fy'] ?? null,
            'nap_code' => $napCode,
            'nap_comment' => $comment ?: '',
            'nap_staff' => 'AutoNAP',
            'status' => $status,
        ];
    }

    /**
     * Send single callback to CAREMAT API.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    public static function send(array $payload, string $callbackUrl): array
    {
        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->post($callbackUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('NAP callback sent', [
                    'source_id' => $payload['source_id'],
                    'nap_code' => $payload['nap_code'],
                    'response' => $data,
                ]);

                return ['ok' => true, 'message' => $data['action'] ?? 'ok'];
            }

            Log::warning('NAP callback failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'message' => "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            Log::error('NAP callback error: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Send batch callback — multiple records in one request.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{ok: bool, message: string, results: array}
     */
    public static function sendBatch(array $items, string $callbackUrl): array
    {
        if (empty($items)) {
            return ['ok' => true, 'message' => 'empty', 'results' => []];
        }

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->post($callbackUrl, [
                    'batch' => true,
                    'items' => $items,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('NAP batch callback sent', [
                    'count' => count($items),
                    'response' => $data,
                ]);

                return ['ok' => true, 'message' => 'batch ok', 'results' => $data['results'] ?? []];
            }

            return ['ok' => false, 'message' => "HTTP {$response->status()}", 'results' => []];
        } catch (\Exception $e) {
            Log::error('NAP batch callback error: '.$e->getMessage());

            return ['ok' => false, 'message' => $e->getMessage(), 'results' => []];
        }
    }

    /**
     * Send callback for a completed job row (single).
     *
     * @param  array<string, mixed>  $rowData
     */
    public static function sendForRow(
        array $rowData,
        ?string $napCode,
        string $status,
        string $error = '',
        ?string $callbackUrl = null,
    ): array {
        $url = $callbackUrl ?? self::defaultUrl();
        $payload = self::buildPayload($rowData, $napCode, $status, $error);

        return self::send($payload, $url);
    }

    /**
     * Default callback URL.
     */
    public static function defaultUrl(): string
    {
        return config('services.caremat.callback_url', 'https://carematapp.com/api/autonap_callback.php');
    }
}
