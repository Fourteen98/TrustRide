<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * One conversation with one person about one problem. A person has at most one open case.
         * A case that matters for safety, or that we must be able to show later, is marked as an incident.
         */
        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            // The app account it came from. Null for someone who called and has no account.
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('role')->nullable(); // rider, driver, mate
            $table->string('source')->default('app'); // app, phone, whatsapp
            $table->string('category');
            // What she was riding when she wrote: route, stop, time, car. Filled in by the side that takes the message.
            $table->string('context', 500)->nullable();
            $table->string('status')->default('open')->index(); // open, resolved
            $table->foreignId('assigned_to')->nullable()->constrained('staff')->nullOnDelete();
            $table->boolean('is_incident')->default(false)->index();
            $table->string('severity')->nullable(); // low, medium, high
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        // The timeline: everything said and done on a case, in order. Never edited.
        Schema::create('case_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained()->cascadeOnDelete();
            // message (from the person), reply (staff to the person), note (staff only),
            // assigned, incident, resolved, reopened
            $table->string('type');
            $table->text('body')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_entries');
        Schema::dropIfExists('support_cases');
    }
};
