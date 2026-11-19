<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMeasurementRequest;
use App\Http\Resources\Api\V1\CustomerMeasurementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerMeasurementController extends Controller
{
    /**
     * Display a listing of saved measurements for the authenticated customer.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $measurements = $request->user()
            ->customerMeasurements()
            ->latest()
            ->get();

        return CustomerMeasurementResource::collection($measurements);
    }

    /**
     * Store a newly created measurement profile for the authenticated customer.
     */
    public function store(StoreMeasurementRequest $request): JsonResponse
    {
        $measurement = $request->user()
            ->customerMeasurements()
            ->create($request->validated());

        return (new CustomerMeasurementResource($measurement))
            ->response()
            ->setStatusCode(201);
    }
}
