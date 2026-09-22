<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureMembership;
use App\Http\Resources\Api\V1\MembershipResource;
use Illuminate\Http\Request;

class ShowMembershipController extends Controller
{
    public function __invoke(Request $request): MembershipResource
    {
        return new MembershipResource($request->attributes->get(EnsureMembership::ATTRIBUTE));
    }
}
