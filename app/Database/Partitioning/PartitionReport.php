<?php

namespace App\Database\Partitioning;

use Carbon\CarbonImmutable;

/** What one maintenance run did to a table, and how far ahead it is covered. */
readonly class PartitionReport
{
    /**
     * @param  list<string>  $created
     * @param  list<string>  $dropped
     */
    public function __construct(
        public array $created,
        public array $dropped,
        public ?CarbonImmutable $coveredUntil,
    ) {}
}
