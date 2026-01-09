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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('f_name');
            $table->string('l_name');
            $table->string('phone_number')->unique()->nullable();
            $table->string('password');
            $table->foreignId('role_id')
                ->constrained('roles')
                ->onDelete('cascade');
            $table->string('otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->longText('fcm_token')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('lock_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
