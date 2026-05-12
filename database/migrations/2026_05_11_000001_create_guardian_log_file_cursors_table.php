<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    
    public function up(): void
    {
        // Recover from a previously failed run where CREATE TABLE succeeded
        // but the unique-index ALTER did not (e.g. utf8mb4 + key length 3072).
        Schema::dropIfExists('guardian_log_file_cursors');

        Schema::create('guardian_log_file_cursors', function (Blueprint $table) {
            $table->id();
            // 512 × 4 bytes (utf8mb4) = 2048 — fits within InnoDB's 3072-byte index limit.
            $table->string('resolved_path', 512)->unique();
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void 
    {
        Schema::dropIfExists('guardian_log_file_cursors');
    }
};