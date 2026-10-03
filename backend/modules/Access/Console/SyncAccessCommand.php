<?php

namespace Modules\Access\Console;

use Illuminate\Console\Command;
use Modules\Access\Actions\SyncDeclaredAccess;
use Modules\Access\Models\Permission;
use Modules\Access\Models\PermissionGroup;
use Modules\Access\Models\Role;

class SyncAccessCommand extends Command
{
    protected $signature = 'access:sync';

    protected $description = 'Store the permissions, groups and system roles the modules declare. Run on every deploy.';

    public function handle(SyncDeclaredAccess $sync): int
    {
        $sync->handle();

        $this->info(sprintf(
            'Access synced: %d permissions, %d groups, %d roles.',
            Permission::count(), PermissionGroup::count(), Role::count(),
        ));

        return self::SUCCESS;
    }
}
