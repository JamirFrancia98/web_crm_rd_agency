<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\UserRole;
use App\Models\Article;
use App\Services\Blog\ArticleGate;
use App\Support\BlogCatalog as C;
use App\Support\Portada;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        // Bloqueado: aprobado/publicado, o quien solo revisa lo legal (lee, no edita el texto).
        $bloqueado = fn (?Article $record): bool => (bool) $record?->isLocked() || auth()->user()?->role === UserRole::Legal;

        return $schema->columns(1)->components([
            // Estado y lista de pendientes: lo primero que ve quien revisa. Ancho completo, avisos en dos columnas.
            Section::make('Estado del artículo')
                ->columnSpanFull()
                ->columns(2)
                ->visible(fn (?Article $record) => $record !== null)
                ->schema([
                    TextEntry::make('estado')->label('Estado actual')->badge()
                        ->formatStateUsing(fn (string $state) => C::etiquetaEstado($state))
                        ->color(fn (string $state) => match ($state) {
                            C::BORRADOR => 'gray', C::EN_REVISION => 'warning', C::APROBADO => 'success', C::PUBLICADO => 'info', default => 'danger',
                        }),
                    TextEntry::make('bloqueado')->label('Edición')
                        ->state(fn (?Article $record) => $record?->estado === C::PUBLICADO
                            ? 'Bloqueada: el artículo está publicado. Para corregirlo usa «Despublicar para editar»; después hay que volver a aprobarlo y a marcarlo como publicado.'
                            : 'Bloqueada: lo aprobado es lo que se revisó. Para cambiar algo, usa «Retirar aprobación».')
                        ->color('warning')
                        ->visible(fn (?Article $record) => (bool) $record?->isLocked()),
                    TextEntry::make('solo_lectura')->label('Edición')
                        ->state('Tu rol (revisor legal) puede leer el artículo y marcar la revisión legal, pero no editar el texto.')
                        ->color('gray')
                        ->visible(fn (?Article $record) => $record && ! $record->isLocked() && auth()->user()?->role === UserRole::Legal),
                    TextEntry::make('problemas')->label('Falta para poder aprobarlo')
                        ->state(fn (?Article $record) => $record && ! $record->isLocked() ? ArticleGate::problems($record->load('sources'), paraAprobar: true) : [])
                        ->listWithLineBreaks()->bulleted()->color('danger')
                        ->visible(fn (?Article $record) => $record && ! $record->isLocked() && ArticleGate::problems($record->load('sources'), paraAprobar: true) !== []),
                    TextEntry::make('avisos')->label('Conviene resolver antes de publicar')
                        ->state(fn (?Article $record) => $record ? ArticleGate::warnings($record) : [])
                        ->listWithLineBreaks()->bulleted()->color('warning')
                        ->visible(fn (?Article $record) => $record && ArticleGate::warnings($record) !== []),
                ]),

            Tabs::make('Artículo')->columnSpanFull()->persistTabInQueryString()->schema([
                Tab::make('Contenido')->icon('heroicon-o-pencil-square')->schema([
                    TextInput::make('titulo')->label('Título')->required()->minLength(10)->maxLength(90)
                        ->helperText('Entre 10 y 90 caracteres. Claro y sin gancho engañoso.'),
                    Textarea::make('descripcion')->label('Descripción')->required()->rows(2)->minLength(50)->maxLength(200)
                        ->helperText('Entre 50 y 200 caracteres. Aparece en el listado y debajo del título del artículo.'),
                    Section::make()->columns(4)->schema([
                        TextInput::make('slug')->label('Dirección (slug)')->required()->maxLength(120)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->validationMessages(['regex' => 'Solo minúsculas, números y guiones, sin fecha.'])
                            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('idioma', $get('idioma')))
                            ->columnSpan(2),
                        // El idioma se elige al crear y no cambia: cada idioma es su propio artículo (el texto no se traduce solo).
                        Select::make('idioma')->label('Idioma')->options(C::IDIOMAS)->required()->default('es')->live()
                            ->disabled(fn (?Article $record) => $record !== null)->dehydrated()
                            ->helperText(fn (?Article $record) => $record ? 'Fijo: este texto está escrito en este idioma.' : 'Elige con cuidado: después no se puede cambiar.'),
                        Select::make('categoria')->label('Categoría')->options(C::CATEGORIAS)->required(),
                        Select::make('tipo')->label('Tipo')->options(C::TIPOS)->required()->default('guia'),
                        TextInput::make('id_tema')->label('Tema (ID)')->maxLength(8)->placeholder('T01'),
                        TextInput::make('grupo_traduccion')->label('Grupo de traducción')->maxLength(120)
                            ->helperText('Mismo texto en las versiones es/en/it del mismo artículo.')->columnSpan(2),
                    ]),
                    View::make('filament.articles.traducciones')->visible(fn (?Article $record) => $record !== null)->columnSpanFull(),
                    MarkdownEditor::make('body_md')->label('Texto del artículo')->required()->minHeight('480px')
                        ->helperText('Usa «## Resumen» y «## Fuentes» para que la web los muestre como secciones propias.')
                        ->columnSpanFull(),
                ]),

                Tab::make('SEO')->icon('heroicon-o-magnifying-glass')->schema([
                    TextInput::make('seo_title')->label('Título SEO')->maxLength(70)
                        ->helperText('Lo que muestra Google. Ideal: hasta 60 caracteres.'),
                    Textarea::make('meta_description')->label('Meta descripción')->rows(3)->maxLength(300)
                        ->helperText('Ideal: entre 120 y 160 caracteres, sin promesas que no se puedan probar.'),
                    Section::make('Imagen de portada')
                        ->description('Una sola imagen: es el banner del artículo en la web y la miniatura al compartirlo en redes. No hay que subir dos.')
                        ->columns(2)->schema([
                            FileUpload::make('imagen_archivo')->label('Archivo de imagen')
                                ->image()->disk('public')->directory('blog-portadas')->visibility('public')
                                ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png'])
                                ->maxSize(Portada::MAX_KB_DURO)
                                ->imageEditor()->imageCropAspectRatio('16:9')
                                ->imageResizeMode('cover')->imageResizeTargetWidth((string) Portada::ANCHO)->imageResizeTargetHeight((string) Portada::ALTO)
                                ->imagePreviewHeight('240')->openable()->downloadable()->live()
                                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => (Str::slug((string) $get('slug')) ?: 'portada').'.'.strtolower($file->getClientOriginalExtension()))
                                ->helperText('Se ajusta a 16:9 al subirla. Máximo 1 MB; ideal menos de 200 KB y en WebP.')
                                ->columnSpanFull(),
                            TextEntry::make('imagen_medidas')->label('Medidas de la imagen guardada')
                                ->state(fn (?Article $r) => Portada::resumen($r?->imagen_ancho, $r?->imagen_alto, $r?->imagen_bytes, $r?->imagen_archivo))
                                ->helperText('Recomendado: '.Portada::ANCHO.'×'.Portada::ALTO.' px (16:9). Las redes muestran 1200×630 px: lo importante, en el centro. Se actualiza al guardar.'),
                            TextEntry::make('imagen_ruta')->label('Ruta que usará la web')
                                ->state(fn (?Article $r) => $r?->imagen_src ?: 'Se genera al subir la imagen')
                                ->copyable()
                                ->helperText('El archivo se copia a esa ruta al exportar o al publicar la web.'),
                            TextInput::make('imagen_alt')->label('Texto alternativo')->maxLength(200)
                                ->helperText('Describe la imagen. Obligatorio si hay imagen.'),
                            TextInput::make('imagen_licencia')->label('Licencia o herramienta')->maxLength(200)
                                ->helperText('Obligatoria si hay imagen: de dónde sale o con qué se creó.'),
                        ]),
                    Section::make('Al compartir en redes')->description('Lo que se ve cuando alguien comparte el enlace (miniatura, título y descripción). Vacío = se usa el título y la descripción del artículo.')->columns(1)->schema([
                        TextInput::make('og_title')->label('Título para redes')->maxLength(90)->live(onBlur: true)
                            ->helperText('Ideal: hasta 60 caracteres.'),
                        Textarea::make('og_description')->label('Descripción para redes')->rows(2)->maxLength(200)->live(onBlur: true)
                            ->helperText('Ideal: entre 100 y 160 caracteres.'),
                        View::make('filament.articles.share-preview'),
                    ]),
                    Section::make('Palabra clave')->columns(2)->schema([
                        TextInput::make('keyword_oficial')->label('Palabra clave oficial')->maxLength(160)
                            ->placeholder('Pendiente')->helperText('La fija Web y SEO tras validar el volumen. No inventes volúmenes.'),
                        Select::make('keyword_estado')->label('Estado de la palabra clave')
                            ->options(['propuesta' => 'Propuesta', 'validada' => 'Validada'])->default('propuesta'),
                        Select::make('intencion_busqueda')->label('Intención de búsqueda')->options(C::INTENCIONES)->placeholder('Sin definir'),
                        Select::make('servicio_relacionado')->label('Servicio relacionado')->options(C::SERVICIOS)->placeholder('Ninguno')
                            ->helperText('Página de servicio a la que enlaza el botón final.'),
                    ]),
                    Repeater::make('keywords')->label('Palabras clave propuestas y validadas')
                        ->relationship('keywords')->collapsed()->defaultItems(0)->addActionLabel('Añadir palabra clave')
                        ->itemLabel(fn (array $state) => ($state['keyword'] ?? 'Palabra clave').' · '.($state['rol'] ?? ''))
                        ->columns(3)->schema([
                            TextInput::make('keyword')->label('Palabra clave')->required()->maxLength(160)->columnSpan(2),
                            Select::make('rol')->label('Rol')->options(['principal' => 'Principal', 'secundaria' => 'Secundaria'])->default('secundaria')->required(),
                            Select::make('estado')->label('Estado')->options(['propuesta' => 'Propuesta', 'validada' => 'Validada'])->default('propuesta')->required(),
                            TextInput::make('volumen_mensual')->label('Volumen mensual')->numeric()->minValue(0)
                                ->helperText('Solo con fuente. Nunca estimado.'),
                            TextInput::make('fuente_volumen')->label('Fuente del volumen')->maxLength(160),
                        ]),
                    Section::make('Dirección y buscadores')->columns(2)->collapsed()->schema([
                        TextInput::make('canonical_path')->label('Ruta')->maxLength(200)->placeholder('/blog/mi-articulo/'),
                        TextInput::make('canonical_url')->label('URL canónica')->maxLength(300)->url(),
                        TextEntry::make('robots_info')->label('Indexación')
                            ->state(fn (?Article $record) => ($record?->robots ?? 'noindex,nofollow').' — pasa a index,follow solo al marcarlo como publicado.'),
                    ]),
                ]),

                Tab::make('Fuentes')->icon('heroicon-o-link')->badge(fn (?Article $record) => $record?->sources()->count())->schema([
                    Repeater::make('sources')->label('Fuentes')->relationship('sources')->orderColumn('orden')
                        ->defaultItems(0)->addActionLabel('Añadir fuente')->collapsed()
                        ->itemLabel(fn (array $state) => ($state['editorial'] ?? 'Fuente').' — '.($state['titulo'] ?? ''))
                        ->helperText('Mínimo 2 con enlace http(s) y fecha de consulta para aprobar.')
                        ->columns(2)->schema([
                            TextInput::make('titulo')->label('Título')->required()->maxLength(300),
                            TextInput::make('editorial')->label('Editorial u organización')->required()->maxLength(200),
                            TextInput::make('url')->label('Enlace')->url()->maxLength(500),
                            DatePicker::make('fecha_consulta')->label('Fecha de consulta')->native(false)->displayFormat('d/m/Y'),
                        ]),
                ]),

                Tab::make('Riesgo y pruebas')->icon('heroicon-o-shield-check')->schema([
                    Section::make('Riesgo legal')->columns(2)->schema([
                        Select::make('riesgo_legal')->label('Riesgo legal')->options(C::RIESGOS)->required()->default('ninguno')->live(),
                        // Solo lectura: la cambia el revisor legal o el administrador con la acción «Revisión legal» (queda en el historial).
                        TextEntry::make('revision_legal_info')->label('Revisión legal')
                            ->state(fn (?Article $record) => C::REVISIONES_LEGALES[$record?->revision_legal ?? 'no-requerida'] ?? '—')
                            ->badge()
                            ->helperText('Con riesgo distinto de «Ninguno», debe estar «Aprobada» para aprobar el artículo. La marca el revisor legal.'),
                    ]),
                    Section::make('Pruebas')->columns(2)->schema([
                        Toggle::make('cifras_propias')->label('Cita cifras propias de RD')->live(),
                        Toggle::make('menciona_clientes')->label('Menciona clientes')
                            ->helperText('Activado, la web no compila el artículo (no hay forma de acreditar el permiso escrito).'),
                        Textarea::make('evidencia')->label('Evidencia de las cifras')->rows(2)->columnSpanFull()
                            ->required(fn (Get $get) => (bool) $get('cifras_propias'))
                            ->helperText('Ruta o enlace a la prueba. Obligatoria si hay cifras propias.'),
                        Textarea::make('aporte_propio')->label('Aporte propio de Revolution Digital')->rows(3)->columnSpanFull()
                            ->helperText('Qué aporta RD que no está en las fuentes. Obligatorio para aprobar.'),
                        Toggle::make('generado_con_ia')->label('Redactado con ayuda de IA (dato interno)')->default(true),
                    ]),
                ]),

                Tab::make('Vista previa')->icon('heroicon-o-eye')->schema([
                    View::make('filament.articles.markdown-preview'),
                ]),

                Tab::make('Revisión')->icon('heroicon-o-clipboard-document-check')->schema([
                    Textarea::make('nota_revisor')->label('Nota interna para el revisor')->rows(5)->columnSpanFull()
                        ->helperText('Dudas del redactor y afirmaciones por comprobar. Nunca se exporta ni se publica.'),
                    Section::make('Responsables y fechas')->columns(3)->schema([
                        TextInput::make('autor')->label('Autor')->maxLength(80)->default('Revolution Digital'),
                        TextInput::make('revisor')->label('Revisor')->maxLength(80),
                        TextEntry::make('f_borrador')->label('Borrador')->state(fn (?Article $r) => $r?->fecha_borrador?->format('d/m/Y') ?? '—'),
                        TextEntry::make('f_revision')->label('Enviado a revisión')->state(fn (?Article $r) => $r?->fecha_revision?->format('d/m/Y') ?? '—'),
                        TextEntry::make('f_aprobacion')->label('Aprobado')->state(fn (?Article $r) => $r?->fecha_aprobacion?->format('d/m/Y') ?? '—'),
                        TextEntry::make('f_publicacion')->label('Publicado')->state(fn (?Article $r) => $r?->fecha_publicacion?->format('d/m/Y') ?? '—'),
                    ]),
                    Section::make('Historial de estados')->collapsed()->visible(fn (?Article $r) => $r !== null)->schema([
                        TextEntry::make('historial')->hiddenLabel()->listWithLineBreaks()
                            ->state(fn (?Article $r) => $r ? $r->statusLogs->map(fn ($l) => sprintf(
                                '%s — %s → %s — %s%s',
                                $l->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                                $l->estado_anterior ? C::etiquetaEstado($l->estado_anterior) : 'inicio',
                                C::etiquetaEstado($l->estado_nuevo),
                                $l->usuario,
                                $l->nota ? ' — «'.$l->nota.'»' : '',
                            ))->all() : []),
                    ]),
                ]),
            ])->disabled($bloqueado),
        ]);
    }
}
