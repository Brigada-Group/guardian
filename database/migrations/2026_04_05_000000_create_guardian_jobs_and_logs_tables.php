<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void 
    {
        Schema::create('guardian_job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_class');
            $table->string('queue', 100)->nullable();
            $table->string('connection', 50)->nullable();
            $table->string('status', 20);              // completed, failed
            $table->float('duration_ms')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['job_class', 'created_at']);
            $table->index(['queue', 'created_at']);
        });

        Schema::create('guardian_log_entries', function (Blueprint $table) {
            $table->id();
            $table->string('level',20);
            $table->text('message');
            $table->string('channel',50)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at');


            $table->index('created_at');
            $table->index(['level','created_at']);
            $table->index(['channel','created_at']);
        });
    }
}
