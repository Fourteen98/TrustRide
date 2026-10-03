<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Access\Actions\SyncDeclaredAccess;
use Modules\Access\Models\Organisation;
use Modules\Access\Models\Staff;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(SyncDeclaredAccess::class)->handle();

        // The first super admin. Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding anywhere real.
        $admin = Staff::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@trustride.test')],
            [
                'organisation_id' => Organisation::platformOwner()->id,
                'name' => 'Admin',
                'password' => env('ADMIN_PASSWORD', 'change-me-now'),
            ],
        );

        $admin->assignRole(Staff::SUPER_ADMIN);
    }
}
