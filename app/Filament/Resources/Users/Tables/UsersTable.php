<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('role')->label('Rol')->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label()),
                IconColumn::make('is_active')->label('Activo')->boolean(),
                IconColumn::make('app_authentication_secret')->label('2 pasos')->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->app_authentication_secret)),
                TextColumn::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label('Rol')->options(UserRole::options()),
            ])
            // Sin borrado: un usuario se desactiva. Así el historial de quién aprobó qué no queda huérfano.
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }
}
