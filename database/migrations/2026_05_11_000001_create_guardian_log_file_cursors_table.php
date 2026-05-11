<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    
    public function up(): void 
    {
        Schema::create('guardian_log_file_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('resolved_path', 1024)->unique();
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void 
    {
        Schema::dropIfExists('guardian_log_file_cursors');
    }
};