<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A rider trying to buy a plan in the app. It ties a payment to what the payment is for.
         * When the payment succeeds, the purchase becomes a subscription.
         */
        Schema::create('plan_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->unsignedInteger('amount'); // pesewas, the plan's price on the day she paid
            $table->string('network');
            // pending, activated, failed, needs_attention
            $table->string('status')->default('pending')->index();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            // Why a paid purchase could not be turned into a subscription. For the operator.
            $table->string('problem')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_purchases');
    }
};
