<?php

namespace App\Filament\Resources\SocialPosts;

use App\Enums\UserRole;
use App\Filament\Resources\SocialPosts\Pages\CreateSocialPost;
use App\Filament\Resources\SocialPosts\Pages\EditSocialPost;
use App\Filament\Resources\SocialPosts\Pages\ListSocialPosts;
use App\Models\SocialPost;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Calendario y piezas de redes sociales (hoy LinkedIn). El CRM guarda el texto, el estado y quién aprobó cada pieza;
 * publicar en la red sigue siendo manual y con aprobación de Jamir pieza por pieza (regla 6).
 */
class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $modelLabel = 'pieza';

    protected static ?string $pluralModelLabel = 'piezas de redes';

    protected static ?string $navigationLabel = 'Redes sociales';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(UserRole::Admin, UserRole::Editor) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = SocialPost::where('estado', 'en revisión')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        $bloqueado = fn (?SocialPost $record): bool => (bool) $record?->isLocked() || ! (auth()->user()?->hasRole(UserRole::Admin, UserRole::Editor, UserRole::Revisor));

        // Una sola columna: cada bloque a todo el ancho y en orden de lectura (estado, pieza, texto, historial).
        return $schema->columns(1)->components([
            Section::make('Estado')->visible(fn (?SocialPost $r) => $r !== null)->columns(3)->schema([
                TextEntry::make('estado')->label('Estado actual')->badge()
                    ->formatStateUsing(fn (string $state) => SocialPost::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'por redactar', 'borrador' => 'gray', 'en revisión' => 'warning', 'aprobada' => 'success', 'programada', 'publicada' => 'info', default => 'danger',
                    }),
                TextEntry::make('aprobacion')->label('Aprobación del texto')
                    ->state(fn (?SocialPost $r) => $r?->aprobado_en ? ($r->aprobado_por.' · '.$r->aprobado_en->format('d/m/Y')) : 'Sin aprobar'),
                TextEntry::make('bloqueo')->label('Edición')
                    ->state(fn (?SocialPost $r) => $r?->isLocked() ? 'Bloqueada: usa «Retirar aprobación» para cambiar el texto.' : 'Editable')
                    ->color(fn (?SocialPost $r) => $r?->isLocked() ? 'warning' : 'gray'),
            ]),
            Section::make('Pieza')->columns(3)->schema([
                TextInput::make('ref')->label('Código')->required()->maxLength(16)->unique(ignoreRecord: true)->placeholder('LI-09')
                    ->disabled(fn (?SocialPost $r) => $r !== null)->dehydrated(),
                TextInput::make('titulo')->label('Título interno')->required()->maxLength(200)->columnSpan(2),
                Select::make('red')->label('Red')->options(['linkedin' => 'LinkedIn'])->default('linkedin')->required(),
                DatePicker::make('fecha_propuesta')->label('Fecha propuesta')->native(false)->displayFormat('d/m/Y'),
                TextInput::make('formato')->label('Formato')->maxLength(200),
                TextInput::make('pilar')->label('Pilar de contenido')->maxLength(200)->columnSpan(2),
                TextInput::make('enlace')->label('Enlace que lleva la pieza')->maxLength(300)->placeholder('Sin enlace')
                    ->helperText('Sin enlace hasta que la web y el blog estén en producción.'),
                TextInput::make('fuente_interna')->label('Fuente interna')->maxLength(300)->columnSpanFull(),
            ])->disabled($bloqueado),
            Section::make('Texto')->schema([
                MarkdownEditor::make('body_md')->label('Texto de la publicación y láminas')->minHeight('380px')->disabled($bloqueado),
                Textarea::make('notas')->label('Notas')->rows(3)->maxLength(2000),
            ]),
            Section::make('Historial')->collapsed()->visible(fn (?SocialPost $r) => $r !== null)->schema([
                View::make('filament.social.historial'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_propuesta')->label('Fecha')->date('D d/m/Y')->placeholder('Sin fecha')->sortable(),
                TextColumn::make('ref')->label('Código')->badge()->color('gray')->searchable(),
                TextColumn::make('titulo')->label('Pieza')->searchable()->wrap()->limit(80)
                    ->description(fn (SocialPost $r) => $r->formato),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => SocialPost::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'por redactar', 'borrador' => 'gray', 'en revisión' => 'warning', 'aprobada' => 'success', 'programada', 'publicada' => 'info', default => 'danger',
                    })->sortable(),
                TextColumn::make('pilar')->label('Pilar')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('fecha_propuesta')
            ->filters([
                SelectFilter::make('estado')->label('Estado')->options(SocialPost::ESTADOS),
                SelectFilter::make('red')->label('Red')->options(['linkedin' => 'LinkedIn']),
            ])
            ->recordActions([EditAction::make()->label('Abrir')])
            ->emptyStateHeading('Todavía no hay piezas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialPosts::route('/'),
            'create' => CreateSocialPost::route('/create'),
            'edit' => EditSocialPost::route('/{record}/edit'),
        ];
    }
}
