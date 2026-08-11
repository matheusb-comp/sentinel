<?php

namespace App\Tenancy;

use RuntimeException;
use Stancl\Tenancy\RLS\PolicyManagers\TableRLSManager;

/**
 * Emits RLS policies that cast the session variable instead of the column.
 *
 * The stock manager generates `company_id::text = current_setting(...)`, which
 * casts the column and makes the predicate non-indexable. Measured on 2M rows,
 * that plan reads every row (14,408 buffers) while the inverted form uses the
 * index (6 buffers).
 *
 * Only generateQueries() is overridden. The parent's foreign key traversal is
 * reused as is, and the same substring appears in both direct and indirect
 * policies, so one replacement covers both.
 */
class BigintTableRLSManager extends TableRLSManager
{
    /**
     * @param  array<string, mixed>  $paths
     * @return array<string, string>
     */
    public function generateQueries(array $paths = []): array
    {
        $sessionKey = config('tenancy.rls.session_variable_name');

        $search = "::text = current_setting('{$sessionKey}')";
        $replace = " = current_setting('{$sessionKey}')::bigint";

        return array_map(function (string $query) use ($search, $replace): string {
            $rewritten = str_replace($search, $replace, $query, $count);

            if ($count === 0) {
                throw new RuntimeException(
                    'The generated RLS policy no longer compares the tenant key as text. '
                    .'stancl/tenancy is on dev-master and its policy format changed; '
                    .'review '.self::class.' before running tenants:rls.'
                );
            }

            return $rewritten;
        }, parent::generateQueries($paths));
    }
}
