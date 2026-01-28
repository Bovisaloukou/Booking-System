<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingConfirmationNotification;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class StripePaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function createPaymentIntent(Booking $booking): array
    {
        $paymentIntent = PaymentIntent::create([
            'amount' => (int) ($booking->total_price * 100), // Stripe uses cents
            'currency' => 'eur',
            'metadata' => [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference,
            ],
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'amount' => $booking->total_price,
            'currency' => 'eur',
            'status' => PaymentStatus::Processing,
        ]);

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
        ];
    }

    public function handlePaymentSuccess(string $paymentIntentId): Payment
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->firstOrFail();

        $stripeIntent = PaymentIntent::retrieve($paymentIntentId);

        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'stripe_charge_id' => $stripeIntent->latest_charge,
            'payment_method' => $stripeIntent->payment_method_types[0] ?? null,
            'paid_at' => now(),
        ]);

        // Confirm the booking after successful payment
        $booking = $payment->booking;
        $booking->confirm();

        $booking->client->notify(new BookingConfirmationNotification($booking));
        event(new BookingConfirmed($booking));

        return $payment;
    }

    public function handlePaymentFailure(string $paymentIntentId): Payment
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->firstOrFail();
        $payment->markAsFailed();

        return $payment;
    }

    public function refund(Payment $payment): Payment
    {
        if ($payment->stripe_charge_id) {
            \Stripe\Refund::create([
                'charge' => $payment->stripe_charge_id,
            ]);
        }

        $payment->markAsRefunded();

        return $payment;
    }
}
