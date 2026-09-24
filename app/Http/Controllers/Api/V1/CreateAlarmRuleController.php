<?php

namespace App\Http\Controllers\Api\V1;

use App\Alarms\AlarmType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAlarmRuleRequest;
use App\Http\Resources\Api\V1\AlarmRuleResource;
use App\Models\AlarmRule;
use Illuminate\Http\JsonResponse;

class CreateAlarmRuleController extends Controller
{
    public function __invoke(StoreAlarmRuleRequest $request): JsonResponse
    {
        // The company comes from the tenancy. The type is set here because this
        // endpoint writes threshold rules and nothing else.
        $rule = AlarmRule::create([...$request->validated(), 'type' => AlarmType::Threshold]);

        // Read back, so the response carries the defaults the columns applied.
        return (new AlarmRuleResource($rule->refresh()))->response()->setStatusCode(201);
    }
}
