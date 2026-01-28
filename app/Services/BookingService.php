<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\BookingConfirmationNotification;
use App\Notifications\BookingCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function createBooking(User $client, int $providerId, int $serviceId, int $timeSlotId, ?string $notes = null): Booking
    {
        return DB::transaction(function () use ($client, $providerId, $serviceId, $timeSlotId, $notes) {
            $timeSlot = TimeSlot::where('id', $timeSlotId)
                ->where('is_available', true)
                ->lockForUpdate()
                ->first();

            if (! $timeSlot) {
                throw ValidationException::withMessages([
                    'time_slot_id' => ['Ce créneau n\'est plus disponible.'],
                ]);
            }

            $service = Service::findOrFail($serviceId);

            $booking = Booking::create([
                'client_id' => $client->id,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'time_slot_id' => $timeSlotId,
                'date' => $timeSlot->date,
                'start_time' => $timeSlot->start_time,
                'end_time' => $timeSlot->end_time,
                'total_price' => $service->price,
                'status' => BookingStatus::Pending,
                'notes' => $notes,
            ]);

            $timeSlot->markAsBooked();

            $client->notify(new BookingCreatedNotification($booking));
            event(new BookingCreated($booking));

            return $booking;
        });
    }

    public function confirmBooking(Booking $booking): Booking
    {
        $booking->confirm();

        $booking->client->notify(new BookingConfirmationNotification($booking));
        event(new BookingConfirmed($booking));

        return $booking;
    }

    public function cancelBooking(Booking $booking, ?string $reason = null): Booking
    {
        $booking->cancel($reason);

        return $booking;
    }

    public function completeBooking(Booking $booking): Booking
    {
        $booking->complete();

        return $booking;
    }
}
