<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Filament\Resources\SocialPosts\SocialPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSocialPosts extends ListRecords
{
    protected static string $resource = SocialPostResource::class;

    protected static ?string $title = 'Redes sociales · calendario de piezas';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nueva pieza')];
    }
}
