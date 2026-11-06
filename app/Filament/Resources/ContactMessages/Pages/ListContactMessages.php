<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    protected static ?string $title = 'Mensajes del formulario de contacto';

    public function getTabs(): array
    {
        // El parámetro debe llamarse $query: Filament lo inyecta por nombre.
        $tab = fn (string $label, ?string $estado) => Tab::make($label)
            ->modifyQueryUsing(fn (Builder $query) => $estado ? $query->where('estado', $estado) : $query)
            ->badge(ContactMessage::query()->when($estado, fn ($q) => $q->where('estado', $estado))->count());

        return [
            'nuevo' => $tab('Nuevos', 'nuevo'),
            'leído' => $tab('Leídos', 'leído'),
            'respondido' => $tab('Respondidos', 'respondido'),
            'archivado' => $tab('Archivados', 'archivado'),
            'spam' => $tab('Spam', 'spam'),
            'todos' => $tab('Todos', null),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'nuevo';
    }
}
