<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Provider;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Réservations du jour', Booking::whereDate('date', today())->count())
                ->description('Aujourd\'hui')
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),
            Stat::make('En attente', Booking::where('status', BookingStatus::Pending)->count())
                ->description('À confirmer')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Revenus du mois', number_format(
                Payment::where('status', 'succeeded')
                    ->whereMonth('paid_at', now()->month)
                    ->sum('amount'),
                2, ',', ' '
            ).' €')
                ->description('Ce mois')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Prestataires actifs', Provider::where('is_active', true)->count())
                ->icon('heroicon-o-user-group')
                ->color('info'),
        ];
    }
}
