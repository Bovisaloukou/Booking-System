<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Réservation créée - '.$this->booking->reference)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre réservation a bien été créée.')
            ->line('**Référence :** '.$this->booking->reference)
            ->line('**Service :** '.$this->booking->service->name)
            ->line('**Date :** '.$this->booking->date->format('d/m/Y'))
            ->line('**Horaire :** '.$this->booking->start_time.' - '.$this->booking->end_time)
            ->line('**Prix :** '.number_format($this->booking->total_price, 2, ',', ' ').' €')
            ->line('Votre réservation est en attente de confirmation.')
            ->action('Voir ma réservation', url('/bookings/'.$this->booking->id))
            ->salutation('L\'équipe Booking System');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'message' => 'Nouvelle réservation créée : '.$this->booking->reference,
            'type' => 'booking_created',
        ];
    }
}
