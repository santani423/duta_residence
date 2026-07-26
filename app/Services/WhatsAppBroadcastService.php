<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use Illuminate\Support\Facades\Http;

/**
 * Sends a Broadcast to its resolved recipients via Fonnte (a WhatsApp gateway service) -
 * the token is read only from config/env (services.fonnte.token, backed by FONNTE_TOKEN
 * in .env), never hardcoded in source, per the spec's explicit constraint. If no token is
 * configured, every recipient is recorded as failed with reason "provider_not_configured"
 * rather than throwing, so the feature degrades safely in environments without a real
 * Fonnte account (local dev, tests).
 */
class WhatsAppBroadcastService
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * @param  array<int, array{type: string, id: ?string, name: ?string, phone: ?string}>  $recipients
     */
    public function send(Broadcast $broadcast, array $recipients, bool $viaWhatsApp = true): Broadcast
    {
        $token = config('services.fonnte.token');
        $endpoint = config('services.fonnte.endpoint');

        $successCount = 0;
        $failCount = 0;

        foreach ($recipients as $recipient) {
            $phone = $this->normalizePhone($recipient['phone'] ?? null);

            $row = BroadcastRecipient::query()->create([
                'broadcast_id' => $broadcast->id,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['id'] ?? null,
                'name' => $recipient['name'] ?? null,
                'phone' => $phone ?? (string) ($recipient['phone'] ?? ''),
                'delivery_status' => BroadcastRecipient::STATUS_PENDING,
            ]);

            if (! $viaWhatsApp) {
                // Internal announcement - not a WhatsApp message, delivered in-app immediately.
                $row->update(['delivery_status' => BroadcastRecipient::STATUS_SENT, 'sent_at' => now()]);
                $successCount++;

                continue;
            }

            if (! $token || ! $phone) {
                $row->update([
                    'delivery_status' => BroadcastRecipient::STATUS_FAILED,
                    'provider_response' => ! $token ? 'provider_not_configured' : 'invalid_phone_number',
                    'sent_at' => now(),
                ]);
                $failCount++;

                continue;
            }

            [$ok, $response] = $this->sendViaFonnte($token, $endpoint, $phone, $broadcast->message);

            $row->update([
                'delivery_status' => $ok ? BroadcastRecipient::STATUS_SENT : BroadcastRecipient::STATUS_FAILED,
                'provider_response' => $response,
                'sent_at' => now(),
            ]);

            $ok ? $successCount++ : $failCount++;
        }

        $broadcast->update([
            'recipient_count' => count($recipients),
            'success_count' => $successCount,
            'fail_count' => $failCount,
        ]);

        $this->auditService->log('broadcast_sent', 'broadcasts', 'CREATE', $broadcast, [], $broadcast->refresh()->toArray());

        return $broadcast->refresh();
    }

    private function sendViaFonnte(string $token, string $endpoint, string $phone, string $message): array
    {
        try {
            $response = Http::withHeaders(['Authorization' => $token])
                ->asForm()
                ->timeout(10)
                ->post($endpoint, ['target' => $phone, 'message' => $message]);

            return [$response->successful(), substr($response->body(), 0, 1000)];
        } catch (\Throwable $e) {
            return [false, substr($e->getMessage(), 0, 1000)];
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return '62'.$digits;
    }
}
