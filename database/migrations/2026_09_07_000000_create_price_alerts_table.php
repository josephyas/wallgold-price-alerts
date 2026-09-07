<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 5);
            $table->unsignedBigInteger('target_price')->comment('Minor units: price x 10^4');
            $table->unsignedBigInteger('reference_price')->nullable()->comment('Price when the alert was created');
            $table->string('status', 10)->default('active');
            $table->unsignedBigInteger('triggered_price')->nullable();
            $table->timestamp('triggered_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // One alert per user per level and side.
            $table->unique(['user_id', 'direction', 'target_price']);
            // Listing a user's alerts.
            $table->index(['user_id', 'created_at']);
            // Rebuilding the Redis index and range scans by level.
            $table->index(['status', 'direction', 'target_price']);
            // Finding deliveries stuck in "sending".
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
