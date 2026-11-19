<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StitchingServiceResource;
use App\Models\StitchingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StitchingServiceController extends Controller
{
    /**
     * Display a listing of active stitching services.
     */
    public function index(): AnonymousResourceCollection
    {
        $services = StitchingService::query()
            ->where('is_active', true)
            ->orderBy('base_price', 'asc')
            ->get();

        return StitchingServiceResource::collection($services);
    }
}
