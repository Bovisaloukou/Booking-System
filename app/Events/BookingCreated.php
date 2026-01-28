<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('bookings'),
            new PrivateChannel('provider.'.$this->booking->provider_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'client' => $this->booking->client->name,
            'service' => $this->booking->service->name,
            'date' => $this->booking->date->format('d/m/Y'),
            'start_time' => $this->booking->start_time,
            'status' => $this->booking->status->value,
        ];
    }
}
