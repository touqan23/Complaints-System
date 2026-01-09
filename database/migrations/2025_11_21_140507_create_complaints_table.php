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

            $table->foreignId('department_id')
                ->constrained('departments')
                ->onDelete('cascade');

            $table->string('type'); // نوع الشكوى
            $table->string('location'); // موقع المشكلة
            $table->text('description')->nullable(); // وصف المشكلة
            $table->string('reference_number')->unique(); // رقم مرجعي auto
            $table->enum('status', ['new',
                'need_more_info',
                'resubmitted',
                'processing',
                'resolved',
                'rejected'
            ])->default('new');
            //resourcs competition (soft lock )
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->foreign('locked_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            ////مشان احسب متوسط زمن الشكوى
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_finished_at')->nullable();
            $table->integer('processing_time_minutes')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->foreign('processed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
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
