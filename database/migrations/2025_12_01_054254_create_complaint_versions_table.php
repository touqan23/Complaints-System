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
        Schema::create('complaint_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->onDelete('cascade');
            $table->json('snapshot');
            $table->integer('version');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('action'); // 'status_changed', 'note_added', 'attachment_added'
            $table->string('field_name')->nullable(); // اسم الحقل المتغير
            $table->text('old_value')->nullable(); // القيمة القديمة
            $table->text('new_value')->nullable(); // القيمة الجديدة
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_versions');
    }
};
