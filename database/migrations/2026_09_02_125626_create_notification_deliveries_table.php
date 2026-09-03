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
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alert_id')->constrained('price_alerts')->cascadeOnDelete();

            $table->string('idempotency_key')->unique();

            $table->string('status', 20)->default('pending');

            $table->timestamp('sent_at')->nullable();

            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['alert_id', 'status'],
                'notification_deliveries_alert_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
