<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CustomerAddressResource;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerAddressController extends Controller
{
    /**
     * Display a listing of saved shipping addresses for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()
            ->addresses()
            ->orderBy('is_default', 'desc')
            ->latest()
            ->get();

        return CustomerAddressResource::collection($addresses);
    }

    /**
     * Store a newly created shipping address.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        // If this is user's first address, make it default automatically
        $isFirst = $user->addresses()->count() === 0;
        $makeDefault = $isFirst || ! empty($validated['is_default']);

        if ($makeDefault) {
            $user->addresses()->update(['is_default' => false]);
        }

        $address = $user->addresses()->create([
            'label' => $validated['label'] ?: 'Home',
            'recipient_name' => $validated['recipient_name'],
            'phone' => $validated['phone'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'is_default' => $makeDefault,
        ]);

        return (new CustomerAddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing shipping address.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'address' => ['sometimes', 'required', 'string'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        if (! empty($validated['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return (new CustomerAddressResource($address))->response();
    }

    /**
     * Delete an existing shipping address.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);
        $wasDefault = $address->is_default;

        $address->delete();

        // If deleted address was default, promote the newest remaining address
        if ($wasDefault) {
            $next = $user->addresses()->latest()->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }
}
