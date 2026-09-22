<?php

namespace App\Console\Commands;

use App\Database\Partitioning\PartitionInterval;
use App\Database\Partitioning\PartitionMaintainer;
use Illuminate\Console\Command;
use Throwable;

class MaintainSeriesPartitions extends Command
{
    protected $signature = 'series:maintain-partitions';

    protected $description = 'Create the time range partitions and drop expired ones';

    public function handle(PartitionMaintainer $maintainer): int
    {
        $failed = false;

        foreach (config('series.tables') as $table => $settings) {
            try {
                $report = $maintainer->maintain(
                    $table,
                    PartitionInterval::from($settings['partition']),
                    $settings['premake'],
                    $settings['retention'],
                    config('ingestion.backfill'),
                );
            } catch (Throwable $e) {
                // One unusable table must not leave the others without partitions.
                report($e);
                $this->error(sprintf('%s: %s', $table, $e->getMessage()));
                $failed = true;

                continue;
            }

            $this->line(sprintf(
                '%s: %d created, %d dropped, covered until %s',
                $table,
                count($report->created),
                count($report->dropped),
                $report->coveredUntil?->toDateTimeString() ?? 'nothing',
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
