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
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('target_price');

            $table->string('direction');

            $table->string('status', 20)->default('active');

            $table->timestamp('processing_at')->nullable();

            $table->timestamp('triggered_at')->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'direction', 'target_price'],
                'price_alerts_matching_index'
            );

            $table->index(
                ['user_id', 'status'],
                'price_alerts_user_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
