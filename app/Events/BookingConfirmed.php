<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Booking $booking
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->booking->client_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'service' => $this->booking->service->name,
            'date' => $this->booking->date->format('d/m/Y'),
            'message' => 'Votre réservation '.$this->booking->reference.' a été confirmée !',
        ];
    }
}
