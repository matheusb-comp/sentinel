<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAlarmRulesRequest;
use App\Http\Resources\Api\V1\AlarmRuleResource;
use App\Models\AlarmRule;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAlarmRulesController extends Controller
{
    public function __invoke(ListAlarmRulesRequest $request): AnonymousResourceCollection
    {
        $rules = AlarmRule::orderByDesc('id')
            ->paginate($request->perPage())
            // So that following a link keeps the page size.
            ->withQueryString();

        return AlarmRuleResource::collection($rules);
    }
}
