<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppMessageService
{
    public function sendText(?string $phoneNumber, string $message): bool
    {
        $url = config('services.whatsapp.messages_url');
        $accessToken = config('services.whatsapp.access_token');
        $recipient = $this->normalizePhoneNumber($phoneNumber);

        if (! $url || ! $accessToken || ! $recipient) {
            return false;
        }

        try {
            return Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $recipient,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ])
                ->successful();
        } catch (Throwable $exception) {
            Log::warning('WhatsApp approval notification failed.', [
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function normalizePhoneNumber(?string $phoneNumber): ?string
    {
        if (! $phoneNumber) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return str_starts_with($digits, '62') ? $digits : '62'.$digits;
    }
}
