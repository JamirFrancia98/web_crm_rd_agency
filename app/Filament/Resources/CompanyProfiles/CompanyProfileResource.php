<?php

namespace App\Filament\Resources\CompanyProfiles;

use App\Filament\Resources\CompanyProfiles\Pages\EditCompanyProfile;
use App\Filament\Resources\CompanyProfiles\Schemas\CompanyProfileForm;
use App\Models\CompanyProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/** Datos de la empresa: una sola ficha, solo para administradores. */
class CompanyProfileResource extends Resource
{
    protected static ?string $model = CompanyProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'datos de la empresa';

    protected static ?string $pluralModelLabel = 'datos de la empresa';

    protected static ?string $navigationLabel = 'Datos de la empresa';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyProfileForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => EditCompanyProfile::route('/'),
        ];
    }
}
