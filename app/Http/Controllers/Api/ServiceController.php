<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Services
 *
 * Endpoints for browsing available services.
 */
class ServiceController extends Controller
{
    /**
     * List Services
     *
     * Retrieve a paginated list of active services. Supports filtering by category slug and searching by name.
     *
     * @unauthenticated
     *
     * @queryParam category string Filter services by category slug. Example: coiffure
     * @queryParam search string Search services by name. Example: coupe
     * @queryParam per_page int Number of results per page. Defaults to 15. Example: 10
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Coupe homme",
     *       "slug": "coupe-homme",
     *       "description": "Coupe classique pour homme",
     *       "price": 25.00,
     *       "duration": 30,
     *       "is_active": true,
     *       "category": {
     *         "id": 1,
     *         "name": "Coiffure",
     *         "slug": "coiffure"
     *       },
     *       "providers_count": 4
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/services?page=1",
     *     "last": "http://localhost/api/services?page=3",
     *     "prev": null,
     *     "next": "http://localhost/api/services?page=2"
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 3,
     *     "per_page": 15,
     *     "total": 42
     *   }
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $services = Service::query()
            ->where('is_active', true)
            ->with('category')
            ->withCount('providers')
            ->when($request->category, fn ($q, $cat) => $q->whereHas('category', fn ($q) => $q->where('slug', $cat)))
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return ServiceResource::collection($services);
    }

    /**
     * Show Service
     *
     * Retrieve a single service with its category and provider count.
     *
     * @unauthenticated
     *
     * @urlParam service int required The ID of the service. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "name": "Coupe homme",
     *     "slug": "coupe-homme",
     *     "description": "Coupe classique pour homme",
     *     "price": 25.00,
     *     "duration": 30,
     *     "is_active": true,
     *     "category": {
     *       "id": 1,
     *       "name": "Coiffure",
     *       "slug": "coiffure"
     *     },
     *     "providers_count": 4
     *   }
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Service]."
     * }
     */
    public function show(Service $service): ServiceResource
    {
        return new ServiceResource(
            $service->load('category')->loadCount('providers')
        );
    }
}
