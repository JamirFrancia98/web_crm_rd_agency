<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120),
                TextInput::make('email')
                    ->label('Correo')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(190),
                Select::make('role')
                    ->label('Rol')
                    ->options(UserRole::options())
                    ->required()
                    ->default(UserRole::Revisor->value)
                    ->helperText('Administrador: todo. Editor: escribe y gestiona clientes, no aprueba. Revisor: aprueba artículos. Revisor legal: marca la revisión legal de los artículos con riesgo.'),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Un usuario inactivo no puede entrar.'),
                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Mínimo 12 caracteres. En edición, déjala vacía para no cambiarla.'),
            ]);
    }
}
