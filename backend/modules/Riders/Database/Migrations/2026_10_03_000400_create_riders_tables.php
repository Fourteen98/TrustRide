<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('applicant')->index(); // applicant, approved, inactive
            $table->timestamps();
        });

        // What she asked for: a route, a pickup stop, and the times she wants.
        Schema::create('rider_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->restrictOnDelete();
            $table->foreignId('stop_id')->constrained()->restrictOnDelete();
            $table->time('morning_time')->nullable();
            $table->time('evening_time')->nullable();
            $table->string('status')->default('pending')->index(); // pending, approved, rejected, withdrawn
            // A rejection is about what we can serve, not about her.
            $table->string('rejection_reason')->nullable(); // no_capacity, route_not_served, time_not_served, other
            $table->text('rejection_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_applications');
        Schema::dropIfExists('riders');
    }
};
