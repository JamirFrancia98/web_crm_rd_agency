<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Models\SocialPostLog;
use Filament\Resources\Pages\CreateRecord;

class CreateSocialPost extends CreateRecord
{
    protected static string $resource = SocialPostResource::class;

    protected static ?string $title = 'Nueva pieza';

    protected function afterCreate(): void
    {
        SocialPostLog::create([
            'social_post_id' => $this->record->id,
            'estado_anterior' => null,
            'estado_nuevo' => $this->record->estado,
            'usuario' => auth()->user()->name.' <'.auth()->user()->email.'>',
            'nota' => 'Pieza creada.',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return SocialPostResource::getUrl('edit', ['record' => $this->record]);
    }
}
