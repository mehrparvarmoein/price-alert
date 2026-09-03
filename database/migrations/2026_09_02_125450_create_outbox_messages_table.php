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
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();

            $table->string('type');

            $table->string('aggregate_type');

            $table->unsignedBigInteger('aggregate_id');

            $table->json('payload');

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['processed_at', 'id'],
                'outbox_unprocessed_index'
            );

            $table->index(
                ['aggregate_type', 'aggregate_id'],
                'outbox_aggregate_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
