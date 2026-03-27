<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Task 9: Request Monitoring
        Schema::create('guardian_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('uri', 2048);
            $table->string('route_name')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->float('duration_ms');
            $table->string('ip', 45)->nullable();
            $table->string('user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['status_code', 'created_at']);
            $table->index(['route_name', 'created_at']);
        });

        // Task 10: Outgoing HTTP Monitoring
        Schema::create('guardian_outgoing_http_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('url', 2048);
            $table->string('host');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->float('duration_ms')->nullable();
            $table->boolean('failed')->default(false);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['host', 'created_at']);
            $table->index(['failed', 'created_at']);
        });

        // Task 11: Database Query Monitoring
        Schema::create('guardian_query_logs', function (Blueprint $table) {
            $table->id();
            $table->text('sql');
            $table->float('duration_ms');
            $table->string('connection', 50);
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->boolean('is_slow')->default(false);
            $table->boolean('is_n_plus_one')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['is_slow', 'created_at']);
            $table->index(['is_n_plus_one', 'created_at']);
        });

        // Task 12: Mail Monitoring
        Schema::create('guardian_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mailable')->nullable();
            $table->string('subject')->nullable();
            $table->text('to');
            $table->string('status', 20); // sent, failed
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        // Task 13: Notification Monitoring
        Schema::create('guardian_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notification_class');
            $table->string('channel');
            $table->string('notifiable_type')->nullable();
            $table->string('notifiable_id')->nullable();
            $table->string('status', 20); // sent, failed
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['channel', 'created_at']);
        });

        // Task 14: Cache Monitoring (aggregated per minute to avoid volume issues)
        Schema::create('guardian_cache_logs', function (Blueprint $table) {
            $table->id();
            $table->string('store', 50);
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('misses')->default(0);
            $table->unsignedInteger('writes')->default(0);
            $table->unsignedInteger('forgets')->default(0);
            $table->float('hit_rate')->nullable();
            $table->timestamp('period_start');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['store', 'period_start']);
        });

        // Task 15: Command Monitoring
        Schema::create('guardian_command_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->integer('exit_code');
            $table->float('duration_ms')->nullable();
            $table->json('arguments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['command', 'created_at']);
            $table->index(['exit_code', 'created_at']);
        });

        // Task 16: Scheduled Task Monitoring
        Schema::create('guardian_scheduled_task_logs', function (Blueprint $table) {
            $table->id();
            $table->string('task');
            $table->string('description')->nullable();
            $table->string('expression'); // cron expression
            $table->string('status', 20); // completed, failed, skipped
            $table->float('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['task', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_scheduled_task_logs');
        Schema::dropIfExists('guardian_command_logs');
        Schema::dropIfExists('guardian_cache_logs');
        Schema::dropIfExists('guardian_notification_logs');
        Schema::dropIfExists('guardian_mail_logs');
        Schema::dropIfExists('guardian_query_logs');
        Schema::dropIfExists('guardian_outgoing_http_logs');
        Schema::dropIfExists('guardian_request_logs');
    }
};
