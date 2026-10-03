<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('label')->nullable();
            $table->string('module')->nullable()->index();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable();
            // Which fixed set of mobile screens this role opens: rider, driver or mate. Null for staff roles.
            $table->string('screens')->nullable();
            // System roles ship with the code and can't be deleted in the console.
            $table->boolean('is_system')->default(false);
        });

        // A group is a named bundle of permissions. guard_name is the scope.
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('label');
            $table->string('guard_name');
            $table->string('module')->nullable()->index();
            $table->timestamps();

            $table->unique(['key', 'guard_name']);
        });

        Schema::create('permission_group_permission', function (Blueprint $table) {
            $table->foreignId('permission_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_group_id', 'permission_id']);
        });

        // A role keeps the group itself, so a permission added to the group later reaches the role.
        Schema::create('role_permission_group', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_group_id']);
        });

        // Single permissions ticked on a role, outside any group.
        Schema::create('role_ticked_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_ticked_permission');
        Schema::dropIfExists('role_permission_group');
        Schema::dropIfExists('permission_group_permission');
        Schema::dropIfExists('permission_groups');
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['label', 'screens', 'is_system']));
        Schema::table('permissions', fn (Blueprint $table) => $table->dropColumn(['label', 'module']));
    }
};
