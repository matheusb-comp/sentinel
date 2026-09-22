<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CompanyResource;

class ShowCompanyController extends Controller
{
    public function __invoke(): CompanyResource
    {
        return new CompanyResource(tenant());
    }
}
