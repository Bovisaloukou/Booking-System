<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestBookings extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Booking::query()->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Réf.'),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client'),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service'),
                Tables\Columns\TextColumn::make('date')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Heure')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Prix')
                    ->money('eur'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => $state->color()),
            ]);
    }
}
