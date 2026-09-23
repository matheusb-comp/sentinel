<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Response;

class RevokeDeviceTokenController extends Controller
{
    /**
     * The device is what scopes the token binding, so it stays in the signature
     * although nothing here reads it.
     */
    public function __invoke(Device $device, PersonalAccessToken $token): Response
    {
        $token->delete();

        return response()->noContent();
    }
}
