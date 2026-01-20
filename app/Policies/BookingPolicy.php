<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            || $user->hasRole('admin')
            || ($user->provider && $user->provider->id === $booking->provider_id);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return ($user->id === $booking->client_id || $user->hasRole('admin'))
            && $booking->isCancellable();
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            && $booking->isPending()
            && ! $booking->payment;
    }

    public function review(User $user, Booking $booking): bool
    {
        return $user->id === $booking->client_id
            && $booking->status === BookingStatus::Completed
            && ! $booking->review;
    }
}
