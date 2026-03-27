<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\MailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class MailListener
{
    use SendsDiscordAlerts;

    public function handleSent(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        try {
            MailLog::create([
                'mailable' => $event->data['__mailable'] ?? null,
                'subject' => $message->getSubject(),
                'to' => $this->formatAddresses($message->getTo()),
                'status' => 'sent',
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Don't break the app
        }
    }

    public function handleFailed(\Throwable $exception, string $to = '', string $subject = ''): void
    {
        try {
            MailLog::create([
                'subject' => $subject,
                'to' => $to,
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'created_at' => now(),
            ]);
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
