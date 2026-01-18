<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Réservations';

    protected static ?string $modelLabel = 'Avis';

    protected static ?string $pluralModelLabel = 'Avis';

    protected static ?int $navigationSort = 3;

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
                        Forms\Components\Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'name')
                            ->required(),
                        Forms\Components\Select::make('provider_id')
                            ->label('Prestataire')
                            ->relationship('provider', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user->name)
                            ->required(),
                        Forms\Components\Select::make('rating')
                            ->label('Note')
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                            ->required(),
                        Forms\Components\Textarea::make('comment')
                            ->label('Commentaire')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Réservation')
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider.user.name')
                    ->label('Prestataire')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Note')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Commentaire')
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
