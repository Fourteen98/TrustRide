<?php

namespace Modules\Access\Exceptions;

use DomainException;

class InvalidLoginCode extends DomainException
{
    public function __construct()
    {
        parent::__construct('That code is wrong or has expired.');
    }
}
