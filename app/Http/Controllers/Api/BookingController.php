<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Bookings
 *
 * Endpoints for managing bookings. All endpoints require authentication.
 *
 * @authenticated
 */
class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService
    ) {}

    /**
     * List Bookings
     *
     * Retrieve a paginated list of the authenticated user's bookings. Supports filtering by status.
     *
     * @authenticated
     *
     * @queryParam status string Filter by booking status (e.g., pending, confirmed, cancelled, completed). Example: confirmed
     * @queryParam per_page int Number of results per page. Defaults to 15. Example: 10
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "status": "confirmed",
     *       "notes": "Premiere visite",
     *       "total_price": 25.00,
     *       "provider": {
     *         "id": 1,
     *         "user": {
     *           "id": 2,
     *           "name": "Pierre Martin"
     *         }
     *       },
     *       "service": {
     *         "id": 1,
     *         "name": "Coupe homme",
     *         "price": 25.00
     *       },
     *       "payment": {
     *         "id": 1,
     *         "status": "paid",
     *         "amount": 25.00
     *       },
     *       "created_at": "2026-01-20T14:30:00.000000Z"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/bookings?page=1",
     *     "last": "http://localhost/api/bookings?page=1",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 15,
     *     "total": 3
     *   }
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['provider.user', 'service', 'payment'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return BookingResource::collection($bookings);
    }

    /**
     * Create Booking
     *
     * Create a new booking for the authenticated user with the specified provider, service, and time slot.
     *
     * @authenticated
     *
     * @bodyParam provider_id int required The ID of the provider. Example: 1
     * @bodyParam service_id int required The ID of the service. Example: 1
     * @bodyParam time_slot_id int required The ID of the available time slot. Example: 5
     * @bodyParam notes string Optional notes for the booking. Example: Premiere visite
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "status": "pending",
     *     "notes": "Premiere visite",
     *     "total_price": 25.00,
     *     "provider": {
     *       "id": 1,
     *       "user": {
     *         "id": 2,
     *         "name": "Pierre Martin"
     *       }
     *     },
     *     "service": {
     *       "id": 1,
     *       "name": "Coupe homme",
     *       "price": 25.00
     *     },
     *     "created_at": "2026-01-20T14:30:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "time_slot_id": ["Le créneau sélectionné n'existe pas."]
     *   }
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function store(StoreBookingRequest $request)
    {
        $booking = $this->bookingService->createBooking(
            client: $request->user(),
            providerId: $request->provider_id,
            serviceId: $request->service_id,
            timeSlotId: $request->time_slot_id,
            notes: $request->notes,
        );

        return (new BookingResource($booking->load(['provider.user', 'service'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show Booking
     *
     * Retrieve details of a specific booking including provider, service, payment, and review.
     *
     * @authenticated
     *
     * @urlParam booking int required The ID of the booking. Example: 1
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "status": "confirmed",
     *     "notes": "Premiere visite",
     *     "total_price": 25.00,
     *     "provider": {
     *       "id": 1,
     *       "user": {
     *         "id": 2,
     *         "name": "Pierre Martin"
     *       }
     *     },
     *     "service": {
     *       "id": 1,
     *       "name": "Coupe homme",
     *       "price": 25.00
     *     },
     *     "payment": {
     *       "id": 1,
     *       "status": "paid",
     *       "amount": 25.00
     *     },
     *     "review": null,
     *     "created_at": "2026-01-20T14:30:00.000000Z"
     *   }
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Booking]."
     * }
     */
    public function show(Booking $booking): BookingResource
    {
        $this->authorize('view', $booking);

        return new BookingResource(
            $booking->load(['provider.user', 'service', 'payment', 'review'])
        );
    }

    /**
     * Cancel Booking
     *
     * Cancel an existing booking. Only the booking owner can cancel it.
     *
     * @authenticated
     *
     * @urlParam booking int required The ID of the booking to cancel. Example: 1
     *
     * @bodyParam reason string Optional cancellation reason. Example: Changement de planning
     *
     * @response 200 {
     *   "data": {
     *     "id": 1,
     *     "status": "cancelled",
     *     "notes": "Premiere visite",
     *     "total_price": 25.00,
     *     "provider": {
     *       "id": 1,
     *       "user": {
     *         "id": 2,
     *         "name": "Pierre Martin"
     *       }
     *     },
     *     "service": {
     *       "id": 1,
     *       "name": "Coupe homme",
     *       "price": 25.00
     *     },
     *     "created_at": "2026-01-20T14:30:00.000000Z"
     *   }
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Booking]."
     * }
     */
    public function cancel(Booking $booking, Request $request): BookingResource
    {
        $this->authorize('cancel', $booking);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bookingService->cancelBooking($booking, $request->reason);

        return new BookingResource($booking->fresh()->load(['provider.user', 'service']));
    }
}
