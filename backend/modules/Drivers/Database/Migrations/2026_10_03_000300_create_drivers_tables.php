<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->unique(); // E.164
            $table->string('licence_number')->nullable();
            $table->date('licence_expires_on')->nullable();
            $table->string('ghana_card_number')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            // What TrustRide pays him each month for holding seats, in pesewas. Not fare money.
            $table->unsignedInteger('monthly_commission')->default(0);
            $table->string('status')->default('pending')->index(); // pending, approved, suspended
            $table->text('status_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->string('registration_number')->unique();
            $table->string('make')->nullable();
            $table->string('colour');
            $table->unsignedSmallInteger('seats_total');
            $table->string('status')->default('pending')->index(); // pending, approved, off_road
            $table->text('condition_note')->nullable();
            $table->timestamps();
        });

        // The vetting checklist. Editable, so the list can grow without a deploy.
        Schema::create('vetting_items', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('applies_to'); // driver, vehicle
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // One item's result for one driver or one vehicle.
        Schema::create('vetting_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vetting_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('result'); // passed, failed
            $table->text('notes')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('checked_at');
            // An override lets a failed item through. It must say who and why.
            $table->foreignId('overridden_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->unique(['vetting_item_id', 'driver_id']);
            $table->unique(['vetting_item_id', 'vehicle_id']);
        });

        // One driver's month: commission, assessment, and whether he has been paid.
        Schema::create('driver_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->date('period'); // first day of the month
            $table->unsignedInteger('commission_amount');
            $table->unsignedInteger('bonus_amount')->default(0);
            $table->string('assessment_outcome')->nullable(); // met, not_met
            $table->text('assessment_notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'period']);
        });

        $items = [
            ['licence_valid', 'Driving licence seen and in date', 'driver'],
            ['identity_confirmed', 'Ghana Card seen and matches', 'driver'],
            ['next_of_kin_reached', 'Next of kin reached by phone', 'driver'],
            ['security_check', 'Security and background check done', 'driver'],
            ['roadworthy', 'Roadworthy certificate in date', 'vehicle'],
            ['insurance', 'Insurance in date', 'vehicle'],
            ['condition', 'Vehicle inspected: seats, doors, tyres, lights', 'vehicle'],
        ];

        foreach ($items as $position => [$key, $label, $appliesTo]) {
            DB::table('vetting_items')->insert([
                'key' => $key, 'label' => $label, 'applies_to' => $appliesTo, 'position' => $position + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_statements');
        Schema::dropIfExists('vetting_checks');
        Schema::dropIfExists('vetting_items');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('mates');
        Schema::dropIfExists('drivers');
    }
};
