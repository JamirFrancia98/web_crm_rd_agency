<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Services\Social\SocialWorkflow;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSocialPost extends EditRecord
{
    protected static string $resource = SocialPostResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->ref.' · '.$this->getRecord()->titulo;
    }

    protected function getHeaderActions(): array
    {
        $color = ['aprobar' => 'success', 'publicar' => 'info', 'programar' => 'info', 'descartar' => 'danger', 'devolver_a_borrador' => 'gray', 'retirar_aprobacion' => 'warning', 'enviar_a_revision' => 'primary', 'empezar_borrador' => 'primary', 'reabrir' => 'gray'];
        $actions = [];

        foreach (SocialWorkflow::acciones() as $clave => $def) {
            $accion = Action::make($clave)
                ->label($def['label'])
                ->color($color[$clave] ?? 'gray')
                ->visible(fn () => SocialWorkflow::puede($this->getRecord(), $clave, auth()->user()))
                ->requiresConfirmation();

            if ($def['nota']) {
                $accion->schema([Textarea::make('nota')->label('Motivo (queda en el historial)')->required()->rows(3)->maxLength(500)]);
            }
            if ($clave === 'aprobar') {
                $accion->modalDescription('Aprueba solo el TEXTO de esta pieza (pieza por pieza). No la publica ni la programa en LinkedIn.');
            }
            if (in_array($clave, ['programar', 'publicar'], true)) {
                $accion->modalDescription('Solo registra lo que ya hiciste a mano en LinkedIn: el CRM no publica nada.');
            }

            $actions[] = $accion->action(function (array $data) use ($clave) {
                $this->aplicar($clave, $data['nota'] ?? null);
            });
        }

        return $actions;
    }

    private function aplicar(string $clave, ?string $nota): void
    {
        $record = $this->getRecord();
        try {
            if (! $record->isLocked()) {
                $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                $record = $record->fresh();
            }
            SocialWorkflow::aplicar($record, $clave, auth()->user(), $nota);
        } catch (DomainException $e) {
            Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()->title(SocialWorkflow::acciones()[$clave]['label'].': hecho')->success()->send();
        $this->redirect(SocialPostResource::getUrl('edit', ['record' => $record]));
    }
}
