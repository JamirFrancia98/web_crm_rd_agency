<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\ContactMessageLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    protected static string $resource = ContactMessageResource::class;

    public function getTitle(): string
    {
        return 'Mensaje de '.$this->getRecord()->nombre;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var ContactMessage $m */
        $m = $this->getRecord();
        // Abrirlo lo marca como leído y queda registrado quién lo vio.
        ContactMessageLog::anotar($m->id, 'vio', self::quien());
        if ($m->estado === 'nuevo') {
            $this->cambiar('leído', null, false);
        }
    }

    private static function quien(): string
    {
        return auth()->user()->name.' <'.auth()->user()->email.'>';
    }

    private function cambiar(string $estado, ?string $nota, bool $avisar = true): void
    {
        $m = $this->getRecord();
        $anterior = $m->estado;
        $m->forceFill(['estado' => $estado])->save();
        ContactMessageLog::anotar($m->id, "estado: {$anterior} → {$estado}", self::quien(), $nota);
        if ($avisar) {
            Notification::make()->title('Marcado como '.ContactMessage::ESTADOS[$estado])->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $estado = fn (string $clave, string $label, string $color) => Action::make('marcar_'.$clave)
            ->label($label)->color($color)
            ->visible(fn () => $this->getRecord()->estado !== $clave)
            ->action(fn () => $this->cambiar($clave, null));

        return [
            $estado('respondido', 'Marcar como respondido', 'success'),
            $estado('archivado', 'Archivar', 'gray'),
            $estado('spam', 'Es spam', 'warning'),
            $estado('leído', 'Volver a «leído»', 'gray'),
            Action::make('nota')->label('Añadir nota')->color('gray')
                ->schema([Textarea::make('notas')->label('Notas internas')->rows(4)->maxLength(2000)->default(fn () => $this->getRecord()->notas)])
                ->action(function (array $data) {
                    $this->getRecord()->update(['notas' => $data['notas']]);
                    ContactMessageLog::anotar($this->getRecord()->id, 'nota actualizada', self::quien());
                    $this->redirect(ContactMessageResource::getUrl('view', ['record' => $this->getRecord()]));
                }),
            // Atender una solicitud de borrado: elimina el mensaje; el registro conserva solo que se borró, sin datos personales.
            Action::make('eliminar')->label('Eliminar definitivamente')->color('danger')->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Eliminar este mensaje')
                ->modalDescription('Se borra para siempre, también de las copias de este panel. Úsalo para atender una solicitud de borrado. Queda anotado que se eliminó, sin datos personales.')
                ->schema([Textarea::make('motivo')->label('Motivo')->required()->rows(2)->maxLength(300)])
                ->action(function (array $data) {
                    $id = $this->getRecord()->id;
                    $this->getRecord()->delete();
                    ContactMessageLog::anotar($id, 'eliminado', self::quien(), $data['motivo']);
                    Notification::make()->title('Mensaje eliminado')->success()->send();
                    $this->redirect(ContactMessageResource::getUrl('index'));
                }),
        ];
    }
}
