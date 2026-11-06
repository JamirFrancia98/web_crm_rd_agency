<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Services\Blog\ArticleMarkdown;
use App\Services\Blog\ArticleWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use DomainException;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    public function getTitle(): string
    {
        return (string) $this->getRecord()->titulo;
    }

    protected function getHeaderActions(): array
    {
        $actions = [];
        $color = ['aprobar' => 'success', 'publicar' => 'info', 'descartar' => 'danger', 'devolver_a_borrador' => 'gray', 'retirar_aprobacion' => 'warning', 'enviar_a_revision' => 'primary', 'reabrir' => 'gray'];
        $icono = ['aprobar' => 'heroicon-o-check-circle', 'publicar' => 'heroicon-o-globe-alt', 'descartar' => 'heroicon-o-x-circle', 'devolver_a_borrador' => 'heroicon-o-arrow-uturn-left', 'retirar_aprobacion' => 'heroicon-o-lock-open', 'enviar_a_revision' => 'heroicon-o-paper-airplane', 'reabrir' => 'heroicon-o-arrow-path'];

        foreach (ArticleWorkflow::acciones() as $clave => $def) {
            $accion = Action::make($clave)
                ->label($def['label'])
                ->color($color[$clave] ?? 'gray')
                ->icon($icono[$clave] ?? null)
                ->visible(fn () => ArticleWorkflow::puede($this->getRecord(), $clave, auth()->user()))
                ->requiresConfirmation();

            if ($def['nota']) {
                $accion->schema([Textarea::make('nota')->label('Motivo (queda en el historial)')->required()->rows(3)->maxLength(500)]);
            }
            if ($clave === 'publicar') {
                $accion->modalDescription('Marca que el artículo YA está subido a la web. Subirlo sigue siendo un paso manual aprobado por Jamir.')
                    ->schema([DatePicker::make('fecha_publicacion')->label('Fecha de publicación')->required()->native(false)->displayFormat('d/m/Y')->default(now())]);
            }
            if ($clave === 'aprobar') {
                $accion->modalDescription('Se comprueban las reglas de la web (revisión legal, fuentes, aporte propio). Aprobar no publica nada.');
            }

            $actions[] = $accion->action(function (array $data) use ($clave) {
                $this->aplicar($clave, $data['nota'] ?? null, isset($data['fecha_publicacion']) ? \Illuminate\Support\Carbon::parse($data['fecha_publicacion'])->toDateString() : null);
            });
        }

        $actions[] = Action::make('exportar')->label('Descargar .md')->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->visible(fn () => $this->getRecord()->isLocked())
            ->action(function () {
                $out = ArticleMarkdown::export($this->getRecord());

                return response()->streamDownload(fn () => print ($out['content']), $out['filename'], ['Content-Type' => 'text/markdown; charset=utf-8']);
            });

        return $actions;
    }

    private function aplicar(string $clave, ?string $nota, ?string $fechaPublicacion): void
    {
        /** @var Article $record */
        $record = $this->getRecord();
        try {
            // Se guardan antes los cambios que haya en pantalla (no en artículos bloqueados).
            if (! $record->isLocked()) {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $record = $record->fresh();
            }
            ArticleWorkflow::aplicar($record, $clave, auth()->user(), $nota, $fechaPublicacion);
        } catch (DomainException $e) {
            Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()->title(ArticleWorkflow::acciones()[$clave]['label'].': hecho')->success()->send();
        $this->redirect(ArticleResource::getUrl('edit', ['record' => $record]));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['word_count'] = str_word_count(strip_tags((string) ($data['body_md'] ?? '')));

        return $data;
    }

    /** Los artículos no se borran: se descartan. */
    protected function getFormActions(): array
    {
        return $this->getRecord()->isLocked() ? [] : parent::getFormActions();
    }
}
