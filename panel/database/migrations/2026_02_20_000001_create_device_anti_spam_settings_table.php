<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_anti_spam_settings', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('max_messages_per_minute')->default(20);
            $table->unsignedInteger('delay_between_messages_ms')->default(1000);
            $table->unsignedInteger('same_recipient_interval_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_anti_spam_settings');
    }
};
