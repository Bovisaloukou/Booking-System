<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Utilisateurs';

    protected static ?string $modelLabel = 'Prestataire';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Utilisateur')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nom')
                                    ->required(),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email'),
                                Forms\Components\TextInput::make('password')
                                    ->label('Mot de passe')
                                    ->password()
                                    ->required(),
                            ]),
                    ]),
                Forms\Components\Section::make('Profil prestataire')
                    ->schema([
                        Forms\Components\Textarea::make('bio')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('speciality')
                            ->label('Spécialité')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('hourly_rate')
                            ->label('Taux horaire')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ])->columns(2),
                Forms\Components\Section::make('Services proposés')
                    ->schema([
                        Forms\Components\Select::make('services')
                            ->relationship('services', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('speciality')
                    ->label('Spécialité')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('Taux horaire')
                    ->money('eur')
                    ->sortable(),
                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Réservations')
                    ->counts('bookings')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}
