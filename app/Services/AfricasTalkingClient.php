<?php

namespace App\Services;

use App\Models\MessagingAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AfricasTalkingClient
{
    /** @return array{reference: ?string} */
    public function send(MessagingAccount $account, string $phone, string $message): array
    {
        $host = $account->environment === 'sandbox' ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';
        $response = Http::asForm()->acceptJson()->withHeaders(['apiKey' => $account->api_key])
            ->post($host.'/version1/messaging', array_filter([
                'username' => $account->username,
                'to' => $phone,
                'message' => $message,
                'from' => $account->sender_id,
            ], fn ($value) => $value !== null && $value !== ''));
        if (! $response->successful()) {
            throw new RuntimeException('provider_http_'.$response->status());
        }
        $recipient = $response->json('SMSMessageData.Recipients.0');
        if (! is_array($recipient) || ! in_array((int) ($recipient['statusCode'] ?? 0), [100, 101, 102], true)) {
            throw new RuntimeException('provider_rejected');
        }

        return ['reference' => isset($recipient['messageId']) ? (string) $recipient['messageId'] : null];
    }
}
