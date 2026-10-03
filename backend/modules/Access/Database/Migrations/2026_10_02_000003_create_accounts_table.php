<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everyone on the mobile app: riders, drivers and mates. One account can hold several roles.
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique(); // E.164
            $table->string('name')->nullable();
            $table->string('preferred_channel')->default('sms'); // sms, whatsapp, push
            $table->boolean('is_active')->default(true);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_codes');
        Schema::dropIfExists('accounts');
    }
};
