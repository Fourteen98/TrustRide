<?php

namespace Modules\Access\Exceptions;

use DomainException;

class AccountInactive extends DomainException
{
    public function __construct()
    {
        parent::__construct('This account has been switched off. Contact support.');
    }
}
