<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmationNotification extends Notification
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
            ->subject('Réservation confirmée - '.$this->booking->reference)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre réservation a été confirmée !')
            ->line('**Référence :** '.$this->booking->reference)
            ->line('**Service :** '.$this->booking->service->name)
            ->line('**Prestataire :** '.$this->booking->provider->user->name)
            ->line('**Date :** '.$this->booking->date->format('d/m/Y'))
            ->line('**Horaire :** '.$this->booking->start_time.' - '.$this->booking->end_time)
            ->action('Voir ma réservation', url('/bookings/'.$this->booking->id))
            ->salutation('L\'équipe Booking System');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'message' => 'Réservation confirmée : '.$this->booking->reference,
            'type' => 'booking_confirmed',
        ];
    }
}
