<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Money TrustRide collects for itself: subscription fees. Never fares.
         * Payments knows nothing about riders or plans. It charges "a thing with a type and an id".
         */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // The reference we mint and hand to the gateway. It is the only thing a callback is trusted on.
            $table->string('reference', 40)->unique();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('description');
            $table->unsignedInteger('amount'); // pesewas
            $table->string('currency', 3)->default('GHS');
            $table->string('phone')->nullable()->index(); // the wallet that pays, E.164
            $table->string('network'); // mtn, telecel, airteltigo
            $table->string('provider'); // fake, hubtel
            $table->string('status')->default('initiated')->index(); // initiated, success, failed
            $table->string('provider_transaction_id')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            // Everything the gateway said, by source: create, callback, status_check, manual.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
