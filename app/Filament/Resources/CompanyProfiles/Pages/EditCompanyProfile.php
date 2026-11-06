<?php

namespace App\Filament\Resources\CompanyProfiles\Pages;

use App\Filament\Resources\CompanyProfiles\CompanyProfileResource;
use App\Models\CompanyProfile;
use Filament\Resources\Pages\EditRecord;

class EditCompanyProfile extends EditRecord
{
    protected static string $resource = CompanyProfileResource::class;

    protected static ?string $title = 'Datos de la empresa';

    /** Siempre se edita la única ficha, sin id en la dirección. */
    public function mount(int|string|null $record = null): void
    {
        parent::mount(CompanyProfile::singleton()->getKey());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
