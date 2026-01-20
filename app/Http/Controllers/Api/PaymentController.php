<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Payments
 *
 * Endpoints for processing payments via Stripe.
 */
class PaymentController extends Controller
{
    public function __construct(
        private StripePaymentService $paymentService
    ) {}

    /**
     * Create Payment Intent
     *
     * Create a Stripe Payment Intent for a specific booking. The authenticated user must be authorized to pay for this booking.
     *
     * @authenticated
     *
     * @urlParam booking int required The ID of the booking to pay for. Example: 1
     *
     * @response 200 {
     *   "client_secret": "pi_3abc123_secret_xyz789",
     *   "payment_intent_id": "pi_3abc123",
     *   "amount": 2500,
     *   "currency": "eur"
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 {
     *   "message": "No query results for model [App\\Models\\Booking]."
     * }
     */
    public function createIntent(Booking $booking): JsonResponse
    {
        $this->authorize('pay', $booking);

        $result = $this->paymentService->createPaymentIntent($booking);

        return response()->json($result);
    }

    /**
     * Stripe Webhook
     *
     * Handle incoming Stripe webhook events. Processes payment_intent.succeeded and payment_intent.payment_failed events.
     *
     * @unauthenticated
     *
     * @hideFromAPIDocumentation
     *
     * @header Stripe-Signature string required The Stripe webhook signature for verification.
     *
     * @response 200 {
     *   "status": "ok"
     * }
     * @response 400 {
     *   "error": "Invalid signature"
     * }
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->paymentService->handlePaymentSuccess($event->data->object->id);
                break;
            case 'payment_intent.payment_failed':
                $this->paymentService->handlePaymentFailure($event->data->object->id);
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
