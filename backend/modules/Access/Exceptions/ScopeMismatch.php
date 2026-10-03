<?php

namespace Modules\Access\Exceptions;

use DomainException;

class ScopeMismatch extends DomainException
{
    public static function for(string $what, string $expected): self
    {
        return new self("{$what} must all belong to the {$expected} scope.");
    }
}
