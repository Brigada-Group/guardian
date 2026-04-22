<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\MailLog;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class MailListener
{
    use SendsDiscordAlerts;

    public function handleSent(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        try {
            $data = [
                'mailable' => $event->data['__mailable'] ?? null,
                'subject' => $message->getSubject(),
                'to' => $this->maybeHashRecipients($this->formatAddresses($message->getTo())),
                'status' => 'sent',
                'created_at' => now(),
            ];

            MailLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('mail', $data);
            } else {
                app(NightwatchClient::class)->send('mail', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }
    }

    public function handleFailed(\Throwable $exception, string $to = '', string $subject = ''): void
    {
        try {
            $data = [
                'subject' => $subject,
                'to' => $to,
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'created_at' => now(),
            ];

            MailLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('mail', $data);
            } else {
                app(NightwatchClient::class)->send('mail', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        $this->sendAlert(
            'Mail Delivery Failed',
            "Failed to send email to {$to}: " . mb_substr($exception->getMessage(), 0, 200),
            Status::Warning,
            ['to' => $to, 'subject' => $subject],
        );
    }

    private function maybeHashRecipients(string $recipients): string
    {
        if (! config('guardian.security.hash_mail_recipients', false)) {
            return $recipients;
        }

        return implode(', ', array_map(
            fn ($email) => hash('sha256', trim($email)),
            explode(',', $recipients)
        ));
    }

    private function formatAddresses($addresses): string
    {
        if (is_array($addresses)) {
            return implode(', ', array_map(
                fn ($address) => is_object($address) ? $address->getAddress() : (string) $address,
                $addresses,
            ));
        }

        return (string) $addresses;
    }
}
