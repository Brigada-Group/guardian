<?php

namespace Brigada\Guardian\Listeners;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Listeners\Concerns\SendsDiscordAlerts;
use Brigada\Guardian\Models\NotificationLog;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

class NotificationListener
{
    use SendsDiscordAlerts;

    public function handleSent(NotificationSent $event): void
    {
        try {
            $data = [
                'notification_class' => get_class($event->notification),
                'channel' => $event->channel,
                'notifiable_type' => get_class($event->notifiable),
                'notifiable_id' => $event->notifiable->getKey() ?? null,
                'status' => 'sent',
                'created_at' => now(),
            ];

            NotificationLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('notifications', $data);
            } else {
                app(NightwatchClient::class)->send('notifications', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $errorMessage = '';
        if (is_array($event->data)) {
            $errorMessage = $event->data['message'] ?? json_encode($event->data);
        } elseif (is_string($event->data)) {
            $errorMessage = $event->data;
        }

        try {
            $data = [
                'notification_class' => get_class($event->notification),
                'channel' => $event->channel,
                'notifiable_type' => get_class($event->notifiable),
                'notifiable_id' => $event->notifiable->getKey() ?? null,
                'status' => 'failed',
                'error_message' => mb_substr($errorMessage, 0, 1000),
                'created_at' => now(),
            ];

            NotificationLog::create($data);

            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch('notifications', $data);
            } else {
                app(NightwatchClient::class)->send('notifications', $data);
            }
        } catch (\Throwable) {
            // Don't break the app
        }

        $notificationClass = class_basename($event->notification);
        $this->sendAlert(
            'Notification Failed',
            "{$notificationClass} failed on [{$event->channel}]: " . mb_substr($errorMessage, 0, 200),
            Status::Warning,
            ['notification' => $notificationClass, 'channel' => $event->channel],
        );
    }
}
