<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A standing permission to take money from a wallet without a prompt each time (direct debit).
         * The owner approves it once on her phone. Payments knows the owner only as a type and an id.
         */
        Schema::create('payment_mandates', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique(); // ours, handed to the gateway
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('phone')->nullable()->index();
            $table->string('network');
            $table->string('provider');
            $table->string('status')->default('pending')->index(); // pending, active, failed, cancelled
            $table->string('provider_mandate_id')->nullable();
            // How she approves it: ussd (a prompt on her phone) or otp (a code she types into the app).
            $table->string('verification')->nullable();
            $table->string('otp_prefix')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });

        // Set when a payment was taken through a mandate, with no prompt.
        Schema::table('payments', fn (Blueprint $table) => $table->foreignId('mandate_id')->nullable()->constrained('payment_mandates')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('mandate_id'));
        Schema::dropIfExists('payment_mandates');
    }
};
