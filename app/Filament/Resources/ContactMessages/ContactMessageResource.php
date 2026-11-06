<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Mensajes del formulario de contacto. Son datos personales (regla 7): solo administradores, sin crear ni editar
 * a mano, y cada vez que se abre o se cambia algo queda registrado.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $modelLabel = 'mensaje';

    protected static ?string $pluralModelLabel = 'mensajes';

    protected static ?string $navigationLabel = 'Mensajes';

    protected static string|\UnitEnum|null $navigationGroup = 'Contacto';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false; // el borrado es una acción propia, con motivo y registro
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = ContactMessage::where('estado', 'nuevo')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Quién escribe')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('nombre')->label('Nombre'),
                TextEntry::make('empresa')->label('Empresa')->placeholder('—'),
                TextEntry::make('email')->label('Correo')->copyable(),
                TextEntry::make('telefono')->label('Teléfono')->placeholder('—')->copyable(),
                TextEntry::make('servicio')->label('Servicio de interés')->placeholder('—'),
                TextEntry::make('presupuesto')->label('Presupuesto')->placeholder('—'),
            ]),
            Section::make('Mensaje')->columnSpanFull()->schema([
                // Texto plano: Filament lo escapa; el salto de línea se respeta.
                TextEntry::make('mensaje')->hiddenLabel()->extraAttributes(['style' => 'white-space: pre-wrap;']),
            ]),
            Section::make('Datos del envío')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('created_at')->label('Recibido')->dateTime('d/m/Y H:i'),
                TextEntry::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state) => ContactMessage::ESTADOS[$state] ?? $state),
                TextEntry::make('pagina')->label('Página de origen')->placeholder('—'),
                TextEntry::make('idioma')->label('Idioma')->formatStateUsing(fn (string $state) => strtoupper($state)),
                TextEntry::make('consentimiento_info')->label('Consentimiento')
                    ->state(fn (ContactMessage $r) => $r->consentimiento ? 'Aceptado el '.$r->consentimiento_en?->format('d/m/Y H:i').' · aviso '.$r->consentimiento_version : 'No consta'),
                TextEntry::make('conservar_hasta')->label('Conservar hasta')->date('d/m/Y')->placeholder('Sin plazo definido (pendiente del abogado)'),
            ]),
            Section::make('Notas internas')->columnSpanFull()->schema([
                TextEntry::make('notas')->hiddenLabel()->placeholder('Sin notas.')->extraAttributes(['style' => 'white-space: pre-wrap;']),
            ]),
            Section::make('Quién lo ha visto o cambiado')->columnSpanFull()->collapsed()->schema([
                \Filament\Schemas\Components\View::make('filament.contacto.historial'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Recibido')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('nombre')->label('Nombre')->searchable()->description(fn (ContactMessage $r) => $r->empresa),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('servicio')->label('Servicio')->placeholder('—')->toggleable(),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => ContactMessage::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'nuevo' => 'warning', 'respondido' => 'success', 'spam' => 'danger', default => 'gray',
                    })->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('estado')->label('Estado')->options(ContactMessage::ESTADOS)])
            ->recordActions([ViewAction::make()->label('Abrir')])
            ->recordUrl(fn (ContactMessage $r) => static::getUrl('view', ['record' => $r]))
            ->emptyStateHeading('Todavía no hay mensajes')
            ->emptyStateDescription('Llegan desde el formulario de contacto cuando la bandeja esté activada.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
