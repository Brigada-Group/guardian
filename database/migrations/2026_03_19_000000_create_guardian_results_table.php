<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_results', function (Blueprint $table) {
            $table->id();
            $table->string('check_class');
            $table->string('status'); // ok, warning, critical, error
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['check_class', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_results');
    }
};
