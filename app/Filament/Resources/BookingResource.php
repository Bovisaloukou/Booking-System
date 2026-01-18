<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Réservations';

    protected static ?string $modelLabel = 'Réservation';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Détails de la réservation')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('Référence')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('provider_id')
                            ->label('Prestataire')
                            ->relationship('provider', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('service_id', null)),
                        Forms\Components\Select::make('service_id')
                            ->label('Service')
                            ->relationship('service', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('time_slot_id')
                            ->label('Créneau')
                            ->relationship('timeSlot', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->date->format('d/m/Y').' '.$record->start_time->format('H:i').'-'.$record->end_time->format('H:i'))
                            ->required()
                            ->searchable(),
                    ])->columns(2),
                Forms\Components\Section::make('Horaire & Prix')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->required(),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Début')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Fin')
                            ->required()
                            ->seconds(false),
                        Forms\Components\TextInput::make('total_price')
                            ->label('Prix total')
                            ->numeric()
                            ->required()
                            ->prefix('€'),
                    ])->columns(2),
                Forms\Components\Section::make('Statut')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->options(collect(BookingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(1000),
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Raison d\'annulation')
                            ->maxLength(500)
                            ->visible(fn (Forms\Get $get) => $get('status') === 'cancelled'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Réf.')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider.user.name')
                    ->label('Prestataire')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Heure')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Prix')
                    ->money('eur')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label())
                    ->color(fn (BookingStatus $state): string => $state->color()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(BookingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('provider')
                    ->label('Prestataire')
                    ->relationship('provider.user', 'name'),
                Tables\Filters\SelectFilter::make('service')
                    ->label('Service')
                    ->relationship('service', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('confirm')
                    ->label('Confirmer')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Booking $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => $record->confirm()),
                Tables\Actions\Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Booking $record) => $record->isCancellable())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Raison')
                            ->required(),
                    ])
                    ->action(fn (Booking $record, array $data) => $record->cancel($data['cancellation_reason'])),
                Tables\Actions\Action::make('complete')
                    ->label('Terminer')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Booking $record) => $record->isConfirmed())
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => $record->complete()),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', BookingStatus::Pending)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
