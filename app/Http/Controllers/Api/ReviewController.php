<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Provider;
use App\Models\Review;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Reviews
 *
 * Endpoints for viewing and submitting reviews on completed bookings.
 */
class ReviewController extends Controller
{
    /**
     * List Provider Reviews
     *
     * Retrieve a paginated list of visible reviews for a specific provider.
     *
     * @unauthenticated
     *
     * @urlParam provider int required The ID of the provider. Example: 1
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": 1,
     *       "rating": 5,
     *       "comment": "Excellent service, je recommande !",
     *       "is_visible": true,
     *       "client": {
     *         "id": 1,
     *         "name": "Jean Dupont"
     *       },
     *       "created_at": "2026-01-25T16:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "rating": 4,
     *       "comment": "Tres bon travail.",
     *       "is_visible": true,
     *       "client": {
     *         "id": 3,
     *         "name": "Marie Leclerc"
     *       },
     *       "created_at": "2026-01-22T11:30:00.000000Z"
     *     }
     *   ],
     *   "links": {
     *     "first": "http://localhost/api/providers/1/reviews?page=1",
     *     "last": "http://localhost/api/providers/1/reviews?page=1",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "last_page": 1,
     *     "per_page": 15,
     *     "total": 2
     *   }
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Provider]."
     * }
     */
    public function providerReviews(Provider $provider): AnonymousResourceCollection
    {
        $reviews = $provider->reviews()
            ->where('is_visible', true)
            ->with('client')
            ->latest()
            ->paginate(15);

        return ReviewResource::collection($reviews);
    }

    /**
     * Submit Review
     *
     * Submit a review for a completed booking. The authenticated user must be the booking owner and authorized to review.
     *
     * @authenticated
     *
     * @bodyParam booking_id int required The ID of the completed booking to review. Example: 1
     * @bodyParam rating int required Rating from 1 to 5. Example: 5
     * @bodyParam comment string Optional review comment. Example: Excellent service, je recommande !
     *
     * @response 201 {
     *   "data": {
     *     "id": 1,
     *     "booking_id": 1,
     *     "client_id": 1,
     *     "provider_id": 1,
     *     "rating": 5,
     *     "comment": "Excellent service, je recommande !",
     *     "is_visible": true,
     *     "created_at": "2026-01-25T16:00:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "rating": ["La note est obligatoire."]
     *   }
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     * @response 401 {
     *   "message": "Unauthenticated."
     * }
     */
    public function store(StoreReviewRequest $request)
    {
        $booking = Booking::findOrFail($request->booking_id);

        if ($request->user()->id !== $booking->client_id) {
            throw new AccessDeniedHttpException('Vous ne pouvez noter que vos propres réservations.');
        }

        if ($booking->status !== BookingStatus::Completed) {
            throw new AccessDeniedHttpException('Vous ne pouvez noter qu\'une réservation terminée.');
        }

        if ($booking->review) {
            throw new AccessDeniedHttpException('Vous avez déjà noté cette réservation.');
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'client_id' => $request->user()->id,
            'provider_id' => $booking->provider_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return (new ReviewResource($review->load('client')))
            ->response()
            ->setStatusCode(201);
    }
}
