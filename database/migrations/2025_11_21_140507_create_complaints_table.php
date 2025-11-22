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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('citizen_id')
                ->constrained('citizens')
                ->onDelete('cascade');

            $table->foreignId('government_entity_id')
                ->constrained('government_entities')
                ->onDelete('cascade');

            $table->string('type'); // نوع الشكوى
            $table->string('location'); // موقع المشكلة
            $table->text('description')->nullable(); // وصف المشكلة
            $table->string('reference_number')->unique(); // رقم مرجعي auto
            $table->enum('status', ['new', 'processing', 'resolved', 'rejected'])->default('new');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
