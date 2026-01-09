<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaint_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->onDelete('cascade');
            $table->string('url')->nullable();
            $table->string('type')->nullable(); // image/png, pdf…
            $table->enum('status', [
                'none',
                'pending',
                'uploaded',
                'failed'
            ])->default('none');

            $table->string('original_name')->nullable();
            //$table->bigInteger('file_size')->nullable();
            $table->string('local_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_files');
    }
};
