<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * A rider who has asked us to take her plan's price by direct debit each time it ends.
         * One row per rider. The permission itself (the mandate) lives in Payments.
         */
        Schema::create('auto_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('mandate_id')->nullable()->constrained('payment_mandates')->nullOnDelete();
            // pending: waiting for her to approve on her phone. active. off: stopped by her, by us, or never approved.
            $table->string('status')->default('pending')->index();
            // Charges in a row that did not go through. Reset by one that does.
            $table->unsignedTinyInteger('failures')->default(0);
            $table->string('last_error')->nullable();
            $table->date('last_attempt_on')->nullable();
            $table->timestamps();
        });

        // Set on a purchase we started ourselves through her mandate, so a failure counts against it.
        Schema::table('plan_purchases', fn (Blueprint $table) => $table->foreignId('auto_renewal_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('plan_purchases', fn (Blueprint $table) => $table->dropConstrainedForeignId('auto_renewal_id'));
        Schema::dropIfExists('auto_renewals');
    }
};
