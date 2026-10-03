<?php

namespace App\Support\Contracts;

/**
 * An event that says a person's data has been erased. Any module holding copies of that
 * person's details, keyed by phone number, listens for this and removes them.
 */
interface ErasesPersonalData
{
    /** E.164 phone number of the person erased. */
    public function phone(): string;
}
