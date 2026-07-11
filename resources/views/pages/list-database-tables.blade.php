<x-filament-panels::page>
    {{ \Filament\Support\Facades\FilamentView::renderHook('dbmyadmin::connection-banner') }}

    {{ $this->table }}
</x-filament-panels::page>