<x-filament-panels::page>

@php
$types = [
    'Numeri'   => ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT','FLOAT','DOUBLE','DECIMAL','NUMERIC','BIT','BOOLEAN'],
    'Stringhe' => ['VARCHAR','CHAR','TINYTEXT','TEXT','MEDIUMTEXT','LONGTEXT','BINARY','VARBINARY'],
    'Date/Ora' => ['DATE','DATETIME','TIMESTAMP','TIME','YEAR'],
    'Altri'    => ['JSON','ENUM','SET','BLOB','MEDIUMBLOB','LONGBLOB','TINYBLOB'],
];
$engines    = ['InnoDB','MyISAM','MEMORY','ARCHIVE','CSV','BLACKHOLE'];
$charsets   = ['utf8mb4','utf8','latin1','ascii'];
$collations = [
    'utf8mb4' => ['utf8mb4_unicode_ci','utf8mb4_general_ci','utf8mb4_bin','utf8mb4_0900_ai_ci'],
    'utf8'    => ['utf8_general_ci','utf8_unicode_ci','utf8_bin'],
    'latin1'  => ['latin1_swedish_ci','latin1_general_ci','latin1_bin'],
    'ascii'   => ['ascii_general_ci','ascii_bin'],
];
$fkActions  = ['RESTRICT','CASCADE','SET NULL','SET DEFAULT','NO ACTION'];
@endphp

<div class="space-y-6">

    {{-- OPZIONI TABELLA --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Opzioni tabella</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 p-4 md:grid-cols-4">

            <div class="col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nome tabella <span class="text-red-500">*</span></label>
                <input type="text" wire:model.live="tableName" placeholder="es. orders"
                    class="w-full rounded-lg border px-3 py-2 text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary-500
                        {{ isset($validationErrors['tableName']) ? 'border-red-400 focus:border-red-400' : 'border-gray-300 focus:border-primary-500' }}
                        dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"/>
                @if(isset($validationErrors['tableName']))
                    <p class="mt-1 text-xs text-red-500">{{ $validationErrors['tableName'] }}</p>
                @endif
            </div>

            <div class="col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Commento</label>
                <input type="text" wire:model.live="tableComment" placeholder="Descrizione opzionale"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"/>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Engine</label>
                <select wire:model.live="engine" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    @foreach($engines as $e)<option value="{{ $e }}">{{ $e }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Charset</label>
                <select wire:model.live="charset" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    @foreach($charsets as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Collation</label>
                <select wire:model.live="collation" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    @foreach($collations[$charset] ?? [] as $col)<option value="{{ $col }}">{{ $col }}</option>@endforeach
                </select>
            </div>

        </div>
    </div>

    {{-- STRUTTURA COLONNE --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Struttura colonne</h2>
            <button wire:click="addColumn"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                <x-heroicon-o-plus style="width:14px;height:14px" /> Aggiungi colonna
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-6">#</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-32">Nome <span class="text-red-400">*</span></th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-36">Tipo</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-20">Lunghezza</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 w-20">Default</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-12">NULL</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-14">UNSIGNED</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-10">A_I</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-10">PK</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-10">UQ</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-500 w-10">IDX</th>
                        <th class="px-2 py-2 text-left font-semibold text-gray-500 min-w-24">Commento</th>
                        <th class="px-2 py-2 w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($columns as $i => $col)
                    @php $noLen = in_array($col['type'] ?? '', ['TEXT','MEDIUMTEXT','LONGTEXT','TINYTEXT','DATE','DATETIME','TIMESTAMP','TIME','YEAR','JSON','BLOB','MEDIUMBLOB','LONGBLOB','TINYBLOB','BOOLEAN']); @endphp
                    <tr @class(['bg-white dark:bg-gray-900' => $i % 2 === 0, 'bg-gray-50/60 dark:bg-gray-800/30' => $i % 2 !== 0])>

                        <td class="px-2 py-1.5">
                            <div class="flex flex-col items-center gap-0.5">
                                <button wire:click="moveColumnUp({{ $i }})" class="text-gray-300 hover:text-gray-500" title="Su">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3l-7 7h14l-7-7z"/></svg>
                                </button>
                                <span class="text-gray-400 font-mono">{{ $i + 1 }}</span>
                                <button wire:click="moveColumnDown({{ $i }})" class="text-gray-300 hover:text-gray-500" title="Giu">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 17l7-7H3l7 7z"/></svg>
                                </button>
                            </div>
                        </td>

                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="columns.{{ $i }}.name" placeholder="nome_colonna"
                                @class(['w-full rounded border px-2 py-1 font-mono text-xs focus:outline-none dark:bg-gray-900 dark:text-gray-200',
                                    'border-red-400' => isset($validationErrors["col_{$i}"]),
                                    'border-gray-200 dark:border-gray-700 focus:border-primary-400' => !isset($validationErrors["col_{$i}"])])/>
                            @if(isset($validationErrors["col_{$i}"]))
                                <p class="text-red-500 text-[10px] mt-0.5">{{ $validationErrors["col_{$i}"] }}</p>
                            @endif
                        </td>

                        <td class="px-2 py-1.5">
                            <select wire:model.live="columns.{{ $i }}.type"
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
                                <input type="text" wire:model.live="columns.{{ $i }}.length"
                                    class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                            @endif
                        </td>

                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="columns.{{ $i }}.default" placeholder="NULL"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            <input type="checkbox" wire:model.live="columns.{{ $i }}.nullable"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            @if(in_array($col['type'] ?? '', ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT','FLOAT','DOUBLE','DECIMAL','NUMERIC']))
                                <input type="checkbox" wire:model.live="columns.{{ $i }}.unsigned"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600"/>
                            @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                            @endif
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            @if(in_array($col['type'] ?? '', ['TINYINT','SMALLINT','MEDIUMINT','INT','BIGINT']))
                                <input type="checkbox" wire:model.live="columns.{{ $i }}.auto_increment"
                                    class="h-4 w-4 rounded border-gray-300 text-primary-600"/>
                            @else
                                <span class="text-gray-200 dark:text-gray-700">—</span>
                            @endif
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            <input type="checkbox" wire:model.live="columns.{{ $i }}.primary"
                                class="h-4 w-4 rounded border-gray-300 text-amber-500"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            <input type="checkbox" wire:model.live="columns.{{ $i }}.unique"
                                class="h-4 w-4 rounded border-gray-300 text-blue-500"/>
                        </td>

                        <td class="px-2 py-1.5 text-center">
                            <input type="checkbox" wire:model.live="columns.{{ $i }}.index"
                                class="h-4 w-4 rounded border-gray-300 text-green-500"/>
                        </td>

                        <td class="px-2 py-1.5">
                            <input type="text" wire:model.live="columns.{{ $i }}.comment" placeholder="opzionale"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                        </td>

                        <td class="px-2 py-1.5">
                            <button wire:click="removeColumn({{ $i }})"
                                class="inline-flex items-center rounded px-1.5 py-1 text-red-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/20">
                                <x-heroicon-o-trash style="width:14px;height:14px" />
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(isset($validationErrors['columns']))
            <div class="px-4 pb-3"><p class="text-xs text-red-500">{{ $validationErrors['columns'] }}</p></div>
        @endif

        <div class="border-t border-gray-100 px-4 py-2.5 dark:border-gray-800">
            <button wire:click="addColumn"
                class="inline-flex items-center gap-1.5 text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400">
                <x-heroicon-o-plus-circle style="width:16px;height:16px" /> Aggiungi colonna
            </button>
        </div>
    </div>

    {{-- FOREIGN KEYS --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <div>
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Foreign Keys</h2>
                <p class="text-xs text-gray-400 mt-0.5">Solo InnoDB. L'INDEX sulla colonna locale viene aggiunto automaticamente.</p>
            </div>
            <button wire:click="addForeignKey"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-white" style="background-color: #f59e0b;">
                <x-heroicon-o-plus style="width:14px;height:14px" /> Aggiungi FK
            </button>
        </div>

        @if(empty($foreignKeys))
        <div class="flex items-center justify-center gap-2 py-8 text-xs text-gray-400 dark:text-gray-600">
            <x-heroicon-o-link style="width:16px;height:16px" />
            Nessuna foreign key. Clicca "Aggiungi FK" per iniziare.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 w-6">#</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 min-w-36">Colonna locale <span class="text-red-400">*</span></th>
                        <th class="px-3 py-2 text-center font-semibold text-gray-500 w-6">→</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 min-w-40">Tabella riferita <span class="text-red-400">*</span></th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 min-w-32">Colonna riferita <span class="text-red-400">*</span></th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 min-w-32">ON DELETE</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 min-w-32">ON UPDATE</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-500 min-w-40">Nome constraint <span class="font-normal text-gray-400">(auto)</span></th>
                        <th class="px-3 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($foreignKeys as $i => $fk)
                    <tr @class(['bg-white dark:bg-gray-900', 'bg-red-50/40' => isset($validationErrors["fk_{$i}_col"]) || isset($validationErrors["fk_{$i}_table"])])>

                        <td class="px-3 py-2 text-center font-mono text-gray-400">{{ $i + 1 }}</td>

                        <td class="px-3 py-2">
                            <select wire:model.live="foreignKeys.{{ $i }}.column"
                                @class(['w-full rounded border px-2 py-1 text-xs focus:outline-none dark:bg-gray-900 dark:text-gray-200',
                                    'border-red-400' => isset($validationErrors["fk_{$i}_col"]),
                                    'border-gray-200 dark:border-gray-700 focus:border-amber-400' => !isset($validationErrors["fk_{$i}_col"])])>
                                <option value="">— seleziona —</option>
                                @foreach($columns as $col)
                                    @if(trim($col['name'] ?? '') !== '')
                                    <option value="{{ $col['name'] }}" @selected(($fk['column'] ?? '') === $col['name'])>
                                        {{ $col['name'] }} ({{ $col['type'] ?? '' }})
                                    </option>
                                    @endif
                                @endforeach
                            </select>
                            @if(isset($validationErrors["fk_{$i}_col"]))
                                <p class="text-red-500 text-[10px] mt-0.5">{{ $validationErrors["fk_{$i}_col"] }}</p>
                            @endif
                        </td>

                        <td class="px-3 py-2 text-center text-gray-300 font-bold">→</td>

                        <td class="px-3 py-2">
                            <select wire:model.live="foreignKeys.{{ $i }}.ref_table"
                                @class(['w-full rounded border px-2 py-1 text-xs focus:outline-none dark:bg-gray-900 dark:text-gray-200',
                                    'border-red-400' => isset($validationErrors["fk_{$i}_table"]),
                                    'border-gray-200 dark:border-gray-700 focus:border-amber-400' => !isset($validationErrors["fk_{$i}_table"])])>
                                <option value="">— seleziona —</option>
                                @foreach($this->getAvailableTables() as $tbl)
                                <option value="{{ $tbl }}" @selected(($fk['ref_table'] ?? '') === $tbl)>{{ $tbl }}</option>
                                @endforeach
                            </select>
                            @if(isset($validationErrors["fk_{$i}_table"]))
                                <p class="text-red-500 text-[10px] mt-0.5">{{ $validationErrors["fk_{$i}_table"] }}</p>
                            @endif
                        </td>

                        <td class="px-3 py-2">
                            <select wire:model.live="foreignKeys.{{ $i }}.ref_column"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-amber-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                @foreach($this->getColumnsForTable($fk['ref_table'] ?? '') as $refCol)
                                <option value="{{ $refCol }}" @selected(($fk['ref_column'] ?? 'id') === $refCol)>{{ $refCol }}</option>
                                @endforeach
                                @if(empty($this->getColumnsForTable($fk['ref_table'] ?? '')))
                                <option value="id">id</option>
                                @endif
                            </select>
                        </td>

                        <td class="px-3 py-2">
                            <select wire:model.live="foreignKeys.{{ $i }}.on_delete"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-amber-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                @foreach($fkActions as $action)
                                <option value="{{ $action }}" @selected(($fk['on_delete'] ?? 'RESTRICT') === $action)>{{ $action }}</option>
                                @endforeach
                            </select>
                        </td>

                        <td class="px-3 py-2">
                            <select wire:model.live="foreignKeys.{{ $i }}.on_update"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs focus:border-amber-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                                @foreach($fkActions as $action)
                                <option value="{{ $action }}" @selected(($fk['on_update'] ?? 'RESTRICT') === $action)>{{ $action }}</option>
                                @endforeach
                            </select>
                        </td>

                        <td class="px-3 py-2">
                            <input type="text" wire:model.live="foreignKeys.{{ $i }}.constraint_name"
                                placeholder="auto"
                                class="w-full rounded border border-gray-200 px-2 py-1 text-xs font-mono focus:border-amber-400 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"/>
                        </td>

                        <td class="px-3 py-2">
                            <button wire:click="removeForeignKey({{ $i }})"
                                class="inline-flex items-center rounded px-1.5 py-1 text-red-400 hover:bg-red-50 hover:text-red-600">
                                <x-heroicon-o-trash style="width:14px;height:14px" />
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-4 py-2.5 dark:border-gray-800">
            <div class="grid grid-cols-3 gap-x-6 gap-y-1 text-[10px] text-gray-400">
                <span><strong class="text-gray-500">RESTRICT</strong> — blocca se esistono riferimenti</span>
                <span><strong class="text-gray-500">CASCADE</strong> — propaga l'operazione</span>
                <span><strong class="text-gray-500">SET NULL</strong> — imposta NULL (colonna nullable)</span>
                <span><strong class="text-gray-500">SET DEFAULT</strong> — imposta il valore default</span>
                <span><strong class="text-gray-500">NO ACTION</strong> — come RESTRICT (controllo differito)</span>
            </div>
        </div>
        @endif
    </div>

    {{-- ANTEPRIMA DDL --}}
    @if($generatedDdl && !str_starts_with($generatedDdl, '--'))
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Anteprima DDL</h2>
            <span class="text-xs text-gray-400">Generato automaticamente</span>
        </div>
        <pre class="overflow-x-auto p-4 font-mono text-xs text-gray-700 dark:text-gray-300 leading-relaxed">{{ $generatedDdl }}</pre>
    </div>
    @endif

    @if(isset($validationErrors['sql']))
    <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-950/30 dark:text-red-400">
        <strong>Errore SQL:</strong> {{ $validationErrors['sql'] }}
    </div>
    @endif

    {{-- AZIONI --}}
    <div class="flex items-center justify-between">
        <a href="{{ \LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource::getUrl() }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            &larr; Annulla
        </a>
        <button wire:click="createTable" wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-2 text-sm font-semibold text-white shadow hover:bg-primary-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="createTable">
                <x-heroicon-o-table-cells class="inline -mt-0.5" style="width:16px;height:16px" /> Crea tabella
            </span>
            <span wire:loading wire:target="createTable" class="flex items-center gap-2">
                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Creazione in corso...
            </span>
        </button>
    </div>

</div>

</x-filament-panels::page>