<x-filament-panels::page>

@php
$types = [
    'Numeri'   => ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT','FLOAT','DOUBLE','DECIMAL','NUMERIC','BIT','BOOLEAN'],
    'Stringhe' => ['VARCHAR','CHAR','TINYTEXT','TEXT','MEDIUMTEXT','LONGTEXT','BINARY','VARBINARY'],
    'Date/Ora' => ['DATE','DATETIME','TIMESTAMP','TIME','YEAR'],
    'Altri'    => ['JSON','ENUM','SET','BLOB','MEDIUMBLOB','LONGBLOB','TINYBLOB'],
];
@endphp

<div class="space-y-6">

    {{-- COLONNE ESISTENTI --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Colonne esistenti</h2>
            <p class="text-xs text-gray-400 mt-0.5">
                Modifica i campi per applicare un MODIFY COLUMN. Rinomina il campo "Nome" per un CHANGE COLUMN. Segna come "Elimina" per un DROP COLUMN.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-6">#</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-28">Nome</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-36">Tipo</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-20">Lunghezza</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-24">Default</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-12">NULL</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-14">UNSIGNED</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-10">A_I</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-24">Commento</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-20">Stato</th>
                        <th class="px-2 py-2 w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($columns as $i => $col)
                    @php
                        $isDrop = $col['action'] === 'drop';
                        $isModified = $col['action'] === 'modify';
                        $noLen = in_array($col['type'] ?? '', ['TEXT','MEDIUMTEXT','LONGTEXT','TINYTEXT','DATE','DATETIME','TIMESTAMP','TIME','YEAR','JSON','BLOB','MEDIUMBLOB','LONGBLOB','TINYBLOB','BOOLEAN']);
                    @endphp
                    <tr @class([
                        'opacity-50 bg-red-50 dark:bg-red-950/20 line-through' => $isDrop,
                        'bg-amber-50/50 dark:bg-amber-950/10' => $isModified && !$isDrop,
                        'bg-white dark:bg-gray-900' => !$isDrop && !$isModified,
                    ])>
                        <td class="px-2 py-1.5 text-center font-mono text-gray-400">{{ $i + 1 }}</td>

                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="columns.{{ $i }}.name"
                                @disabled($isDrop)
                                class="w-full rounded border border-gray-200 px-2 py-1 font-mono text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 disabled:opacity-40"/>
                            @if($col['name'] !== $col['original_name'])
                            <p class="text-[10px] text-amber-600 mt-0.5">CHANGE: {{ $col['original_name'] }} &rarr; {{ $col['name'] }}</p>
                            @endif
                        </td>

                        <td class="px-2 py-1.5">
                            <select wire:model.live="columns.{{ $i }}.type"
                                @disabled($isDrop)
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 disabled:opacity-40">
                                @foreach($types as $group => $typeList)
                                <optgroup label="{{ $group }}">
                                    @foreach($typeList as $t)
                                    <option value="{{ $t }}" @selected(($col['type'] ?? 'VARCHAR') === $t)>{{ $t }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </td>

                        <td class="px-2 py-1.5">
                            @if($noLen)
                                <span class="text-gray-300 px-2">—</span>
                            @else
                                <input type="text" wire:model.live="columns.{{ $i }}.length"
                                    @disabled($isDrop)
                                    class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 disabled:opacity-40"/>
                            @endif
                        </td>

                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="columns.{{ $i }}.default" placeholder="NULL"
                                @disabled($isDrop)
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 disabled:opacity-40"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            <input type="checkbox" wire:model.live="columns.{{ $i }}.nullable"
                                @disabled($isDrop)
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 disabled:opacity-40"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            @if(in_array($col['type'] ?? '', ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT','FLOAT','DOUBLE','DECIMAL','NUMERIC']))
                                <input type="checkbox" wire:model.live="columns.{{ $i }}.unsigned"
                                    @disabled($isDrop)
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 disabled:opacity-40"/>
                            @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                            @endif
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            @if(in_array($col['type'] ?? '', ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT']))
                                <input type="checkbox" wire:model.live="columns.{{ $i }}.auto_increment"
                                    @disabled($isDrop)
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600 disabled:opacity-40"/>
                            @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                            @endif
                        </td>

                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="columns.{{ $i }}.comment" placeholder=""
                                @disabled($isDrop)
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 disabled:opacity-40"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            @if($isDrop)
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700 dark:bg-red-950/40 dark:text-red-400">DROP</span>
                            @elseif($isModified)
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">MODIFY</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-400 dark:bg-gray-800">—</span>
                            @endif
                        </td>

                        <td class="px-2 py-1.5">
                            <button wire:click="toggleDrop({{ $i }})"
                                @class([
                                    'inline-flex items-center rounded px-2 py-1 text-xs font-medium',
                                    'bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-950/30 dark:text-red-400' => !$isDrop,
                                    'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400' => $isDrop,
                                ])
                                title="{{ $isDrop ? 'Annulla eliminazione' : 'Segna per eliminazione' }}">
                                @if($isDrop)
                                    <x-heroicon-o-arrow-uturn-left class="h-3.5 w-3.5"/>
                                @else
                                    <x-heroicon-o-trash class="h-3.5 w-3.5"/>
                                @endif
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- NUOVE COLONNE --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <div>
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Aggiungi colonne</h2>
                <p class="text-xs text-gray-400 mt-0.5">Queste colonne verranno aggiunte con ADD COLUMN.</p>
            </div>
            <button wire:click="addNewColumn"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                <x-heroicon-o-plus class="h-3.5 w-3.5"/> Aggiungi colonna
            </button>
        </div>

        @if(empty($newColumns))
        <div class="flex items-center justify-center gap-2 py-8 text-xs text-gray-400 dark:text-gray-600">
            <x-heroicon-o-plus-circle class="h-4 w-4"/>
            Nessuna nuova colonna. Clicca "Aggiungi colonna" per iniziare.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-28">Nome <span class="text-red-400">*</span></th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-36">Tipo</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-20">Lunghezza</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-24">Default</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-12">NULL</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-14">UNSIGNED</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-10">A_I</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-32">Posizione (AFTER)</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-24">Commento</th>
                        <th class="px-2 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-emerald-50/30 dark:bg-emerald-950/10">
                    @foreach($newColumns as $i => $col)
                    @php $noLen = in_array($col['type'] ?? '', ['TEXT','MEDIUMTEXT','LONGTEXT','TINYTEXT','DATE','DATETIME','TIMESTAMP','TIME','YEAR','JSON','BLOB','MEDIUMBLOB','LONGBLOB','TINYBLOB','BOOLEAN']); @endphp
                    <tr>
                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="newColumns.{{ $i }}.name" placeholder="nome_colonna"
                                @class(['w-full rounded border px-2 py-1 font-mono text-xs focus:outline-none dark:bg-gray-900 dark:text-gray-200',
                                    'border-red-400' => isset($validationErrors["new_col_{$i}"]),
                                    'border-gray-200 dark:border-gray-700 focus:border-primary-400' => !isset($validationErrors["new_col_{$i}"])])/>
                            @if(isset($validationErrors["new_col_{$i}"]))
                                <p class="text-red-500 text-[10px] mt-0.5">{{ $validationErrors["new_col_{$i}"] }}</p>
                            @endif
                        </td>
                        <td class="px-2 py-1.5">
                            <select wire:model.live="newColumns.{{ $i }}.type"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                @foreach($types as $group => $typeList)
                                <optgroup label="{{ $group }}">
                                    @foreach($typeList as $t)
                                    <option value="{{ $t }}" @selected(($col['type'] ?? 'VARCHAR') === $t)>{{ $t }}</option>
                                    @endforeach
                                </optgroup>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-1.5">
                            @if($noLen)
                                <span class="text-gray-300 px-2">—</span>
                            @else
                                <input type="text" wire:model.live="newColumns.{{ $i }}.length"
                                    class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                            @endif
                        </td>
                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="newColumns.{{ $i }}.default" placeholder="NULL"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                        </td>
                        <td class="px-2 py-1.5 text-center">
                            <input type="checkbox" wire:model.live="newColumns.{{ $i }}.nullable"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600"/>
                        </td>
                        <td class="px-2 py-1.5 text-center">
                            @if(in_array($col['type'] ?? '', ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT','FLOAT','DOUBLE','DECIMAL','NUMERIC']))
                                <input type="checkbox" wire:model.live="newColumns.{{ $i }}.unsigned"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600"/>
                            @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5 text-center">
                            @if(in_array($col['type'] ?? '', ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT']))
                                <input type="checkbox" wire:model.live="newColumns.{{ $i }}.auto_increment"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600"/>
                            @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                            @endif
                        </td>
                        <td class="px-2 py-1.5">
                            <select wire:model.live="newColumns.{{ $i }}.after"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                <option value="__last__">— In fondo —</option>
                                <option value="">FIRST (prima di tutto)</option>
                                @foreach($this->getAvailableColumns() as $existingCol)
                                <option value="{{ $existingCol }}" @selected(($col['after'] ?? '') === $existingCol)>AFTER {{ $existingCol }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="newColumns.{{ $i }}.comment" placeholder=""
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                        </td>
                        <td class="px-2 py-1.5">
                            <button wire:click="removeNewColumn({{ $i }})"
                                class="inline-flex items-center rounded px-1.5 py-1 text-red-400 hover:bg-red-50 hover:text-red-600">
                                <x-heroicon-o-trash class="h-3.5 w-3.5"/>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ANTEPRIMA DDL --}}
    @if($generatedDdl && !str_starts_with($generatedDdl, '--'))
    <div class="rounded-xl border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-800 dark:bg-amber-950/20">
        <div class="flex items-center justify-between border-b border-amber-200 px-4 py-3 dark:border-amber-800">
            <div class="flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-amber-500"/>
                <h2 class="text-sm font-semibold text-amber-700 dark:text-amber-400">Anteprima ALTER TABLE</h2>
            </div>
            <span class="text-xs text-amber-500">Verifica prima di applicare</span>
        </div>
        <pre class="overflow-x-auto p-4 font-mono text-xs text-amber-800 dark:text-amber-300 leading-relaxed">{{ $generatedDdl }}</pre>
    </div>
    @endif

    @if(isset($validationErrors['sql']))
    <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400">
        <strong>Errore SQL:</strong> {{ $validationErrors['sql'] }}
    </div>
    @endif

    @if(isset($validationErrors['general']))
    <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-400">
        {{ $validationErrors['general'] }}
    </div>
    @endif

    {{-- AZIONI --}}
    <div class="flex items-center justify-between">
        <a href="{{ \App\Filament\Resources\DatabaseTableResource::getUrl('browse', ['record' => $tableName]) }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            &larr; Torna ai record
        </a>

        <button wire:click="applyChanges" wire:loading.attr="disabled"
            @class([
                'inline-flex items-center gap-2 rounded-lg px-6 py-2 text-sm font-semibold text-white shadow disabled:opacity-60',
                'bg-amber-500 hover:bg-amber-600' => $generatedDdl && !str_starts_with($generatedDdl, '--'),
                'bg-gray-300 cursor-not-allowed dark:bg-gray-700' => !$generatedDdl || str_starts_with($generatedDdl, '--'),
            ])>
            <span wire:loading.remove wire:target="applyChanges">
                <x-heroicon-o-check class="h-4 w-4 inline -mt-0.5"/> Applica modifiche
            </span>
            <span wire:loading wire:target="applyChanges" class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Applicazione...
            </span>
        </button>
    </div>

</div>

</x-filament-panels::page>