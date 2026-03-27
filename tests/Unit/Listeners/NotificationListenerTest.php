<?php

namespace Brigada\Guardian\Tests\Unit\Listeners;

use Brigada\Guardian\Listeners\NotificationListener;
use Brigada\Guardian\Models\NotificationLog;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;

class NotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_sent_notifications(): void
    {
        $listener = new NotificationListener();

        $notifiable = new class {
            public function getKey() { return 1; }
        };

        $notification = new class extends Notification {};

        $event = new NotificationSent($notifiable, $notification, 'mail');

        $listener->handleSent($event);

        $this->assertDatabaseHas('guardian_notification_logs', [
            'channel' => 'mail',
            'status' => 'sent',
        ]);
    }

    public function test_it_logs_failed_notifications(): void
    {
        $listener = new NotificationListener();

        $notifiable = new class {
            public function getKey() { return 1; }
        };

        $notification = new class extends Notification {};

        $event = new NotificationFailed($notifiable, $notification, 'mail', ['message' => 'SMTP error']);

        $listener->handleFailed($event);

        $this->assertDatabaseHas('guardian_notification_logs', [
            'channel' => 'mail',
            'status' => 'failed',
        ]);
    }
}
