<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Alarms\CloseAlarmPeriods;
use App\Alarms\AlarmEndReason;
use App\Http\Controllers\Controller;
use App\Models\AlarmRule;
use Illuminate\Http\Response;

class DeleteAlarmRuleController extends Controller
{
    /**
     * Deleting the rule takes every monitor applying it, by the foreign key, so
     * their open periods are closed here while the monitors still exist.
     */
    public function __invoke(AlarmRule $alarmRule, CloseAlarmPeriods $closeAlarmPeriods): Response
    {
        $alarmRule->getConnection()->transaction(function () use ($alarmRule, $closeAlarmPeriods): void {
            $closeAlarmPeriods->forRule($alarmRule, AlarmEndReason::Unwatched);

            $alarmRule->delete();
        });

        return response()->noContent();
    }
}
