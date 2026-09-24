<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AlarmRule;
use Illuminate\Http\Response;

class DeleteAlarmRuleController extends Controller
{
    /**
     * Deleting the rule takes every monitor applying it, by the foreign key.
     */
    public function __invoke(AlarmRule $alarmRule): Response
    {
        $alarmRule->delete();

        return response()->noContent();
    }
}
