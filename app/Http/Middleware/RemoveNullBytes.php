<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Removes NUL bytes from the values in the request's query string, form body
 * and JSON body.
 *
 * Postgres cannot hold a NUL in a text column and does not report it: the
 * driver cuts the value at the first one, so two different inputs can end up
 * stored as the same value.
 */
class RemoveNullBytes extends TransformsRequest
{
    protected function transform($key, $value): mixed
    {
        return is_string($value) ? str_replace("\0", '', $value) : $value;
    }
}
