<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_gateways')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                $table->longText('settings')->nullable()->change();
            });
        }

        if (! Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->string('gateway', 50);
                $table->string('event_id', 191);
                $table->string('event_type', 100)->nullable();
                $table->timestamp('processed_at')->useCurrent();

                $table->unique(['gateway', 'event_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
