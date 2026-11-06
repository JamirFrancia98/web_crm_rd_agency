<?php

namespace App\Filament\Resources\CompanyProfiles\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identidad')
                ->description('Se usa en los textos legales, facturas y el pie de la web. Solo datos confirmados.')
                ->columns(2)
                ->schema([
                    TextInput::make('nombre_comercial')->label('Nombre comercial')->required()->maxLength(190),
                    TextInput::make('razon_social')->label('Razón social')->required()->maxLength(190),
                    TextInput::make('ruc')->label('RUC')->regex('/^\d{11}$/')->validationMessages(['regex' => 'El RUC tiene 11 dígitos.'])->maxLength(11),
                    TextInput::make('responsable_datos')->label('Responsable del tratamiento de datos')->maxLength(190),
                ]),
            Section::make('Contacto')
                ->columns(2)
                ->schema([
                    TextInput::make('correo_comercial')->label('Correo comercial')->email()->maxLength(190),
                    TextInput::make('whatsapp')->label('WhatsApp')->tel()->maxLength(30),
                    TextInput::make('ciudad')->label('Ciudad')->maxLength(190),
                    TextInput::make('horario')->label('Horario')->maxLength(190),
                    TextInput::make('direccion')->label('Dirección registrada')->maxLength(190)
                        ->helperText('Pendiente de confirmar. Necesaria para los textos legales.')->columnSpanFull(),
                    TextInput::make('web_url')->label('Sitio web')->url()->maxLength(190)->columnSpanFull(),
                ]),
            Section::make('Redes sociales')
                ->description('Direcciones completas (https://…). Dejar vacío si no existe la red o no se conoce la dirección.')
                ->columns(2)
                ->schema([
                    TextInput::make('instagram_url')->label('Instagram')->url()->maxLength(190),
                    TextInput::make('facebook_url')->label('Facebook')->url()->maxLength(190),
                    TextInput::make('linkedin_url')->label('LinkedIn')->url()->maxLength(190),
                    TextInput::make('tiktok_url')->label('TikTok')->url()->maxLength(190),
                ]),
            Section::make('Notas internas')
                ->schema([
                    Textarea::make('notas')->label('Notas')->rows(4)->columnSpanFull(),
                ]),
        ]);
    }
}
