<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProviderResource;
use App\Http\Resources\TimeSlotResource;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Providers
 *
 * Endpoints for browsing service providers and their available time slots.
 */
class ProviderController extends Controller
{
    /**
     * List Providers
     *
     * Retrieve a paginated list of active providers. Supports filtering by service and searching by provider name.
     *
     * @unauthenticated
     *
     * @queryParam service_id int Filter providers by service ID. Example: 1
     * @queryParam search string Search providers by name. Example: Martin
     * @queryParam per_page int Number of results per page. Defaults to 15. Example: 10
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "bio": "Coiffeur professionnel depuis 10 ans.",
     *       "is_active": true,
     *       "user": {
     *         "id": 2,
     *         "name": "Pierre Martin",
     *         "email": "pierre@example.com"
     *       },
     *       "services": [
     *         {
     *           "id": 1,
     *           "name": "Coupe homme",
     *           "price": 25.00
     *         }
     *       ],
     *       "available_slots_count": 12
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/providers?page=1",
     *     "last": "http://localhost/api/providers?page=2",
     *     "prev": null,
     *     "next": "http://localhost/api/providers?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 2,
     *     "per_page": 15,
     *     "total": 20
     *   }
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $providers = Provider::query()
            ->where('is_active', true)
            ->with(['user', 'services'])
            ->withCount('availableSlots')
            ->when($request->service_id, fn ($q, $id) => $q->whereHas('services', fn ($q) => $q->where('services.id', $id)))
            ->when($request->search, fn ($q, $s) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%")))
            ->paginate($request->per_page ?? 15);

        return ProviderResource::collection($providers);
    }

    /**
     * Show Provider
     *
     * Retrieve a single provider with their user profile, services, and available slot count.
     *
     * @unauthenticated
     *
     * @urlParam provider int required The ID of the provider. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "bio": "Coiffeur professionnel depuis 10 ans.",
     *     "is_active": true,
     *     "user": {
     *       "id": 2,
     *       "name": "Pierre Martin",
     *       "email": "pierre@example.com"
     *     },
     *     "services": [
     *       {
     *         "id": 1,
     *         "name": "Coupe homme",
     *         "price": 25.00
     *       }
     *     ],
     *     "available_slots_count": 12
     *   }
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Provider]."
     * }
     */
    public function show(Provider $provider): ProviderResource
    {
        return new ProviderResource(
            $provider->load(['user', 'services'])->loadCount('availableSlots')
        );
    }

    /**
     * List Available Slots
     *
     * Retrieve available time slots for a specific provider. Only future slots marked as available are returned.
     *
     * @unauthenticated
     *
     * @urlParam provider int required The ID of the provider. Example: 1
     *
     * @queryParam date string Filter slots by a specific date (YYYY-MM-DD). Example: 2026-03-01
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "provider_id": 1,
     *       "date": "2026-03-01",
     *       "start_time": "09:00:00",
     *       "end_time": "09:30:00",
     *       "is_available": true
     *     },
     *     {
     *       "id": 2,
     *       "provider_id": 1,
     *       "date": "2026-03-01",
     *       "start_time": "09:30:00",
     *       "end_time": "10:00:00",
     *       "is_available": true
     *     }
     *   ]
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Provider]."
     * }
     */
    public function availableSlots(Provider $provider, Request $request): AnonymousResourceCollection
    {
        $slots = $provider->timeSlots()
            ->where('is_available', true)
            ->where('date', '>=', now()->toDateString())
            ->when($request->date, fn ($q, $date) => $q->whereDate('date', $date))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return TimeSlotResource::collection($slots);
    }
}
