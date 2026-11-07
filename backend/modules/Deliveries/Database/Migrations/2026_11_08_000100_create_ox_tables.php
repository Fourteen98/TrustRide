<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Aveo Ox. Businesses pay a monthly plan. Their customers' parcels are batched by zone and cut-off time,
 * and one driver takes each zone's run. Customers have no account: they get an SMS with a status link and
 * give the driver a code at the door.
 */
return new class extends Migration
{
    public function up(): void
    {
        // An area one driver covers in one run, such as Lapaz or Abokobi.
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained();
            $table->string('name', 60)->unique();
            $table->string('covers', 200)->nullable(); // the places in it, in words, to help the operator place an address
            $table->string('cutoff_time', 5)->default('14:00'); // orders in by then go on that day's run
            $table->string('promise', 40)->default('by 6pm'); // what the customer is told: "today by 6pm"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('delivery_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->unsignedInteger('monthly_fee'); // pesewas
            $table->unsignedInteger('delivery_allowance')->nullable(); // a month. Null: agreed per business
            $table->unsignedInteger('overage_fee')->default(0); // pesewas for each delivery past the allowance
            $table->string('at_limit', 20)->default('overage');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A shop or seller that sends parcels. Its plan's numbers are copied in, so a negotiated price or a later
        // change to the plan never moves another business.
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained();
            $table->ulid('public_id')->unique();
            $table->string('name', 100);
            $table->string('contact_name', 100);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('pickup_address', 200);
            $table->string('pickup_landmark', 200)->nullable();
            $table->foreignId('delivery_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('monthly_fee');
            $table->unsignedInteger('delivery_allowance')->nullable();
            $table->unsignedInteger('overage_fee')->default(0);
            $table->string('at_limit', 20)->default('overage');
            $table->boolean('live_map')->default(false);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('zone_id')->constrained();
            $table->date('date');
            $table->foreignId('driver_id')->constrained();
            $table->unsignedInteger('driver_pay')->default(0); // pesewas, for this run
            $table->string('status', 20)->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
            $table->index(['date', 'driver_id']);
        });

        // One parcel, from one business to one customer.
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained();
            $table->date('delivery_date');
            $table->string('reference', 60)->nullable(); // the business's own order number
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->string('address', 250);
            $table->string('landmark', 200)->nullable();
            $table->string('notes', 300)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('delivery_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('code_hash')->nullable();
            $table->unsignedTinyInteger('code_attempts')->default(0);
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivered_note', 300)->nullable(); // set when staff confirm without the code
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 300)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('customer_data_removed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
            $table->index(['zone_id', 'delivery_date', 'status']);
            $table->index(['business_id', 'delivery_date']);
        });

        // A business's month: the plan fee, what it used, and anything over. Paid outside the system for now.
        Schema::create('business_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('period'); // the first of the month
            $table->unsignedInteger('plan_fee');
            $table->unsignedInteger('delivery_allowance')->nullable();
            $table->unsignedInteger('deliveries_count');
            $table->unsignedInteger('overage_count');
            $table->unsignedInteger('overage_amount');
            $table->unsignedInteger('live_map_fee');
            $table->unsignedInteger('total');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_invoices');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('delivery_runs');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('delivery_plans');
        Schema::dropIfExists('zones');
    }
};
