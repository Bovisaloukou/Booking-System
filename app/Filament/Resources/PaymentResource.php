<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Réservations';

    protected static ?string $modelLabel = 'Paiement';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('booking_id')
                            ->label('Réservation')
                            ->relationship('booking', 'reference')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('stripe_payment_intent_id')
                            ->label('Stripe Payment Intent'),
                        Forms\Components\TextInput::make('amount')
                            ->label('Montant')
                            ->numeric()
                            ->required()
                            ->prefix('€'),
                        Forms\Components\Select::make('currency')
                            ->label('Devise')
                            ->options(['eur' => 'EUR', 'usd' => 'USD'])
                            ->default('eur'),
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                            ->required(),
                        Forms\Components\TextInput::make('payment_method')
                            ->label('Méthode de paiement'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Réservation')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking.client.name')
                    ->label('Client')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Montant')
                    ->money('eur')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state): string => $state->label())
                    ->color(fn (PaymentStatus $state): string => $state->color()),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Méthode'),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Payé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
