<x-filament-panels::page>

<div
    x-data="{
        query: @entangle('query'),
        schemaMap: @js($schemaMap),
        showSaveModal: @entangle('showSaveModal'),

        // Autocomplete
        acItems: [], acIndex: -1, acVisible: false, acX: 0, acY: 0,

        // Sidebar tabs: 'schema' | 'saved'
        sidebarTab: 'schema',
        sidebarOpen: true,

        // Schema
        expandedTables: {},

        insertAtCursor(text) {
            const ta = this.$refs.editor;
            const s = ta.selectionStart, e = ta.selectionEnd;
            const before = this.query.substring(0, s);
            const after  = this.query.substring(e);
            this.query = before + text + after;
            this.$nextTick(() => {
                ta.selectionStart = ta.selectionEnd = s + text.length;
                ta.focus();
            });
        },

        handleKeydown(ev) {
            if (this.acVisible) {
                if (ev.key === 'ArrowDown')  { ev.preventDefault(); this.acIndex = Math.min(this.acIndex + 1, this.acItems.length - 1); return; }
                if (ev.key === 'ArrowUp')    { ev.preventDefault(); this.acIndex = Math.max(this.acIndex - 1, 0); return; }
                if ((ev.key === 'Enter' || ev.key === 'Tab') && this.acIndex >= 0) { ev.preventDefault(); this.applyAutocomplete(this.acItems[this.acIndex]); return; }
                if (ev.key === 'Escape') { this.acVisible = false; return; }
            }
            if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') { ev.preventDefault(); $wire.runQuery(); }
        },

        handleInput() {
            const ta = this.$refs.editor, pos = ta.selectionStart;
            const text = this.query.substring(0, pos);
            const match = text.match(/[\w.]+$/);
            const word = match ? match[0] : '';
            if (word.length < 2) { this.acVisible = false; return; }
            const suggestions = [], wordLow = word.toLowerCase();
            if (word.includes('.')) {
                const [tbl, partial] = word.split('.');
                (this.schemaMap[tbl] ?? []).filter(c => c.toLowerCase().startsWith(partial.toLowerCase())).slice(0,12).forEach(c => suggestions.push({ label: c, insert: tbl+'.'+c, type: 'column' }));
            } else {
                Object.keys(this.schemaMap).filter(t => t.toLowerCase().startsWith(wordLow)).slice(0,8).forEach(t => suggestions.push({ label: t, insert: t, type: 'table' }));
                const seen = new Set();
                Object.entries(this.schemaMap).forEach(([tbl, cols]) => { cols.filter(c => c.toLowerCase().startsWith(wordLow) && !seen.has(c)).slice(0,4).forEach(c => { seen.add(c); suggestions.push({ label: c, insert: c, type: 'column' }); }); });
                ['SELECT','FROM','WHERE','JOIN','LEFT JOIN','INNER JOIN','ON','GROUP BY','ORDER BY','HAVING','LIMIT','OFFSET','INSERT INTO','UPDATE','SET','DELETE FROM','AND','OR','NOT','NULL','IS NULL','IS NOT NULL','LIKE','IN','BETWEEN','COUNT','SUM','AVG','MAX','MIN','DISTINCT','AS','DESC','ASC'].filter(k => k.toLowerCase().startsWith(wordLow)).slice(0,5).forEach(k => suggestions.push({ label: k, insert: k, type: 'keyword' }));
            }
            if (!suggestions.length) { this.acVisible = false; return; }
            const coords = this.getCaretCoords(ta, pos);
            this.acX = coords.left; this.acY = coords.top + 44;
            this.acItems = suggestions.slice(0,15); this.acIndex = 0; this.acVisible = true;
        },

        applyAutocomplete(item) {
            const ta = this.$refs.editor, pos = ta.selectionStart;
            const text = this.query.substring(0, pos);
            const match = text.match(/[\w.]+$/);
            const word = match ? match[0] : '';
            const start = pos - word.length;
            this.query = this.query.substring(0, start) + item.insert + this.query.substring(pos);
            this.acVisible = false;
            this.$nextTick(() => { ta.selectionStart = ta.selectionEnd = start + item.insert.length; ta.focus(); });
        },

        getCaretCoords(ta, pos) {
            const div = document.createElement('div');
            const style = window.getComputedStyle(ta);
            ['fontFamily','fontSize','fontWeight','lineHeight','padding','border','boxSizing','width','whiteSpace','overflowWrap','wordBreak'].forEach(p => { div.style[p] = style[p]; });
            div.style.position = 'absolute'; div.style.visibility = 'hidden'; div.style.whiteSpace = 'pre-wrap';
            document.body.appendChild(div);
            div.textContent = ta.value.substring(0, pos);
            const span = document.createElement('span'); span.textContent = '|'; div.appendChild(span);
            const spanRect = span.getBoundingClientRect(), divRect = div.getBoundingClientRect();
            document.body.removeChild(div);
            return { left: spanRect.left - divRect.left, top: span.offsetTop - ta.scrollTop };
        },

        toggleTable(tbl) { this.expandedTables[tbl] = !this.expandedTables[tbl]; }
    }"
    class="flex gap-4 h-full"
    @click.away="acVisible = false"
>

{{-- ═══════════════════════════════════════════════
     MAIN: Editor + Results
════════════════════════════════════════════════ --}}
<div class="flex flex-1 flex-col gap-3 min-w-0">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-2">

        <button @click="sidebarOpen = !sidebarOpen"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            <x-heroicon-o-bars-3 class="h-3.5 w-3.5"/> Schema
        </button>

        <div class="flex flex-wrap gap-1.5">
            @foreach(['select_all'=>'SELECT *','select_where'=>'WHERE','count'=>'COUNT','group_by'=>'GROUP BY','join'=>'JOIN','show_tables'=>'SHOW TABLES','show_columns'=>'DESCRIBE','insert'=>'INSERT','update'=>'UPDATE','delete'=>'DELETE'] as $key => $label)
            <button wire:click="loadTemplate('{{ $key }}')"
                class="inline-flex items-center rounded border border-dashed border-gray-300 bg-white px-2 py-1 text-xs text-gray-500 hover:border-primary-400 hover:text-primary-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
                {{ $label }}
            </button>
            @endforeach

            @foreach(['FROM'=>'FROM table_name','LEFT JOIN'=>'LEFT JOIN table_name ON a.id = table_name.a_id','RIGHT JOIN'=>'RIGHT JOIN table_name ON a.id = table_name.a_id','ORDER BY'=>'ORDER BY column_name ASC'] as $label => $snippet)
            <button @click="insertAtCursor('\n{{ $snippet }}')"
                class="inline-flex items-center rounded border border-dashed border-blue-300 bg-white px-2 py-1 text-xs text-blue-500 hover:border-blue-400 hover:text-blue-600 dark:border-blue-800 dark:bg-gray-900 dark:text-blue-400">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                Limite:
                <select wire:model="limitRows" class="rounded border border-gray-200 bg-white px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <option value="100">100</option>
                    <option value="250">250</option>
                    <option value="500">500</option>
                    <option value="1000">1000</option>
                </select>
            </label>

            {{-- Salva query --}}
            <button wire:click="openSaveModal"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                <x-heroicon-o-bookmark class="h-3.5 w-3.5"/> Salva
            </button>

            <button wire:click="clearQuery"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                <x-heroicon-o-x-mark class="h-3.5 w-3.5"/> Pulisci
            </button>

            <button wire:click="runQuery" wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="runQuery">
                    <x-heroicon-o-play class="h-3.5 w-3.5 inline -mt-0.5"/> Esegui <span class="opacity-60 font-normal ml-1">Ctrl+↵</span>
                </span>
                <span wire:loading wire:target="runQuery" class="flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Esecuzione…
                </span>
            </button>
        </div>
    </div>

    {{-- Editor --}}
    <div class="relative">
        <textarea x-ref="editor" x-model="query"
            @keydown="handleKeydown($event)" @input="handleInput()" @scroll="acVisible = false"
            rows="8" placeholder="-- Scrivi qui la tua query SQL&#10;-- Ctrl+Enter per eseguire"
            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm leading-relaxed text-gray-800 shadow-inner focus:border-primary-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:focus:border-primary-600 dark:focus:bg-gray-900 resize-y"
            spellcheck="false" autocomplete="off"></textarea>

        {{-- Autocomplete dropdown --}}
        <div x-show="acVisible"
            x-transition:enter="transition ease-out duration-75"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            :style="`left: ${acX}px; top: ${acY}px`"
            class="absolute z-50 min-w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-xl dark:border-gray-700 dark:bg-gray-800"
            style="display:none; max-height:220px; overflow-y:auto;">
            <template x-for="(item, i) in acItems" :key="i">
                <button @mousedown.prevent="applyAutocomplete(item)"
                    :class="i === acIndex ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'text-gray-700 dark:text-gray-300'"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <span :class="{'text-blue-500':item.type==='table','text-green-500':item.type==='column','text-orange-400':item.type==='keyword'}"
                        class="w-4 shrink-0 text-center font-bold"
                        x-text="item.type==='table'?'T':(item.type==='column'?'C':'K')"></span>
                    <span class="font-mono" x-text="item.label"></span>
                </button>
            </template>
        </div>
    </div>

    {{-- Barra stato --}}
    @if($hasRun)
    <div @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-xs',
        'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-400' => $errorMessage,
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' => !$errorMessage])>
        @if($errorMessage)
            <x-heroicon-o-exclamation-circle class="h-4 w-4 shrink-0"/>
            <span class="font-mono">{{ $errorMessage }}</span>
        @else
            <x-heroicon-o-check-circle class="h-4 w-4 shrink-0"/>
            <span>
                @if($hasResults)
                    <strong>{{ number_format($totalRows) }}</strong> righe restituite
                    @if($totalRows > $limitRows)
                        <span class="text-amber-600 dark:text-amber-400">(visualizzate prime {{ $limitRows }})</span>
                    @endif
                @else
                    <strong>{{ number_format($totalRows) }}</strong> righe interessate
                @endif
                &nbsp;·&nbsp; {{ $execTimeMs }} ms
            </span>
        @endif
    </div>
    @endif

    {{-- Tabella risultati --}}
    @if($hasResults && !$errorMessage && count($results) > 0)
    <div class="flex-1 overflow-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700" style="max-height:50vh;">
        <table class="w-full min-w-max text-xs">
            <thead class="sticky top-0 z-10 bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="border-b border-r border-gray-200 px-2 py-1.5 text-center font-mono text-gray-400 dark:border-gray-700 w-10">#</th>
                    @foreach($columns as $col)
                    <th class="border-b border-r border-gray-200 px-3 py-1.5 text-left font-semibold text-gray-600 last:border-r-0 dark:border-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($results as $i => $row)
                <tr @class(['hover:bg-gray-50 dark:hover:bg-gray-800/50', 'bg-gray-50/50 dark:bg-gray-900/30' => $i % 2 !== 0])>
                    <td class="border-r border-gray-100 px-2 py-1 text-center font-mono text-gray-300 dark:border-gray-800 select-none">{{ $i + 1 }}</td>
                    @foreach($columns as $col)
                    <td class="border-r border-gray-100 px-3 py-1 last:border-r-0 dark:border-gray-800 max-w-xs">
                        @if($row[$col] === 'NULL')
                            <span class="italic text-gray-300 dark:text-gray-600">NULL</span>
                        @else
                            <span class="truncate block font-mono" title="{{ $row[$col] }}">{{ $row[$col] }}</span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @elseif($hasResults && !$errorMessage && count($results) === 0)
    <div class="flex items-center justify-center rounded-xl border border-dashed border-gray-200 py-12 text-sm text-gray-400 dark:border-gray-700">
        <x-heroicon-o-inbox class="mr-2 h-5 w-5"/> Nessun risultato
    </div>
    @endif

</div>{{-- /main --}}

{{-- ═══════════════════════════════════════════════
     SIDEBAR destra
════════════════════════════════════════════════ --}}
<div x-show="sidebarOpen"
    class="w-72 shrink-0 flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
    style="height: calc(100vh - 12rem); overflow: hidden;">

    {{-- Tab switcher --}}
    <div class="flex border-b border-gray-200 dark:border-gray-700">
        <button @click="sidebarTab = 'schema'"
            :class="sidebarTab === 'schema' ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-medium transition-colors">
            <x-heroicon-o-circle-stack class="h-3.5 w-3.5"/> Schema
        </button>
        <button @click="sidebarTab = 'saved'"
            :class="sidebarTab === 'saved' ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
            class="flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-medium transition-colors">
            <x-heroicon-o-bookmark class="h-3.5 w-3.5"/>
            Query salvate
            @if(count($savedQueries) > 0)
            <span class="rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-400">
                {{ count($savedQueries) }}
            </span>
            @endif
        </button>
    </div>

    {{-- TAB: Schema --}}
    <div x-show="sidebarTab === 'schema'" class="flex flex-1 flex-col overflow-hidden">
        <div class="border-b border-gray-100 px-3 py-1.5 dark:border-gray-800">
            <p class="text-[10px] text-gray-400">{{ count($schemaMap) }} tabelle &nbsp;·&nbsp; clic sul nome = inserisci</p>
        </div>
        <div class="flex-1 overflow-y-auto p-1.5 space-y-0.5">
            @foreach($schemaMap as $table => $cols)
            <div>
                <div class="flex w-full items-center rounded text-xs hover:bg-gray-100 dark:hover:bg-gray-800">
                    <button @click="toggleTable('{{ $table }}')"
                        class="flex shrink-0 items-center justify-center h-6 w-6 text-gray-400 hover:text-gray-600">
                        <svg x-show="!expandedTables['{{ $table }}']" class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"/></svg>
                        <svg x-show="expandedTables['{{ $table }}']" class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                    </button>
                    <button @click="insertAtCursor('{{ $table }}')"
                        class="flex flex-1 items-center gap-1.5 overflow-hidden py-1 pr-2 text-left">
                        <x-heroicon-o-table-cells class="h-3.5 w-3.5 shrink-0 text-primary-500"/>
                        <span class="truncate font-medium text-gray-700 dark:text-gray-300">{{ $table }}</span>
                        <span class="ml-auto shrink-0 rounded bg-gray-100 px-1 text-[10px] text-gray-400 dark:bg-gray-800">{{ count($cols) }}</span>
                    </button>
                </div>
                <div x-show="expandedTables['{{ $table }}']"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="ml-6 mt-0.5 mb-1 space-y-0.5 rounded-lg border border-gray-100 bg-gray-50/80 p-1 dark:border-gray-800 dark:bg-gray-900/50">
                    @foreach($cols as $col)
                    <button @click="insertAtCursor('{{ $col }}')"
                        class="flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-xs text-gray-600 hover:bg-primary-50 hover:text-primary-700 dark:text-gray-400 dark:hover:bg-primary-900/20">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                        <span class="truncate font-mono">{{ $col }}</span>
                    </button>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- TAB: Query salvate --}}
    <div x-show="sidebarTab === 'saved'" class="flex flex-1 flex-col overflow-hidden">

        {{-- Ricerca --}}
        <div class="border-b border-gray-100 p-2 dark:border-gray-800">
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"/>
                <input type="text" wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Cerca per nome, descrizione, SQL…"
                    class="w-full rounded-lg border border-gray-200 bg-gray-50 py-1.5 pl-7 pr-3 text-xs focus:border-primary-400 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"/>
            </div>
        </div>

        {{-- Lista --}}
        <div class="flex-1 overflow-y-auto">
            @if(empty($savedQueries))
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-xs text-gray-400 dark:text-gray-600">
                <x-heroicon-o-bookmark class="h-6 w-6"/>
                @if(!empty($searchQuery))
                    Nessuna query trovata
                @else
                    Nessuna query salvata.<br>Usa il pulsante "Salva" in toolbar.
                @endif
            </div>
            @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($savedQueries as $sq)
                <div x-data="{ preview: false }" class="group px-3 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    {{-- Riga principale: nome + pulsanti sempre visibili --}}
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-xs font-semibold text-gray-700 dark:text-gray-300">
                            {{ $sq['name'] }}
                        </span>
                        <div class="flex shrink-0 items-center gap-0.5">
                            {{-- Anteprima SQL --}}
                            <button @click="preview = !preview"
                                :title="preview ? 'Nascondi SQL' : 'Anteprima SQL'"
                                :class="preview ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'"
                                class="rounded p-1 transition-colors">
                                <x-heroicon-o-eye class="h-3.5 w-3.5"/>
                            </button>
                            {{-- Copia nell'editor --}}
                            <button wire:click="useSavedQuery({{ $sq['id'] }})"
                                title="Copia nell'editor"
                                class="rounded p-1 text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-950/20">
                                <x-heroicon-o-clipboard-document class="h-3.5 w-3.5"/>
                            </button>
                            {{-- Modifica --}}
                            <button wire:click="openEditModal({{ $sq['id'] }})"
                                title="Modifica"
                                class="rounded p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <x-heroicon-o-pencil class="h-3.5 w-3.5"/>
                            </button>
                            {{-- Elimina --}}
                            <button wire:click="deleteSavedQuery({{ $sq['id'] }})"
                                wire:confirm="Eliminare la query '{{ addslashes($sq['name']) }}'?"
                                title="Elimina"
                                class="rounded p-1 text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20">
                                <x-heroicon-o-trash class="h-3.5 w-3.5"/>
                            </button>
                        </div>
                    </div>

                    {{-- Data creazione --}}
                    <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-600">{{ $sq['updated_at'] }}</p>

                    {{-- Anteprima SQL espandibile --}}
                    <div x-show="preview" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        class="mt-2 rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
                        <pre class="whitespace-pre-wrap break-all font-mono text-[10px] text-gray-600 dark:text-gray-400 max-h-32 overflow-y-auto">{{ $sq['sql'] }}</pre>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Footer: bottone aggiungi query corrente --}}
        <div class="border-t border-gray-100 p-2 dark:border-gray-800">
            <button wire:click="openSaveModal"
                class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-primary-300 py-2 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:border-primary-700 dark:text-primary-400 dark:hover:bg-primary-950/20">
                <x-heroicon-o-plus class="h-3.5 w-3.5"/> Salva query corrente
            </button>
        </div>
    </div>

</div>{{-- /sidebar --}}

</div>{{-- /x-data --}}

{{-- ═══════════════════════════════════════════════
     MODALE: Salva / Modifica query
════════════════════════════════════════════════ --}}
@if($showSaveModal)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
        @click.stop>

        {{-- Header modale --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                {{ $editingQueryId ? 'Modifica query salvata' : 'Salva query' }}
            </h3>
            <button wire:click="closeSaveModal"
                class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                <x-heroicon-o-x-mark class="h-4 w-4"/>
            </button>
        </div>

        {{-- Body modale --}}
        <div class="space-y-5 px-6 py-6">

            {{-- Anteprima SQL --}}
            <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-gray-800">
                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Query da salvare</p>
                <p class="font-mono text-xs text-gray-600 dark:text-gray-400 line-clamp-3 whitespace-pre-wrap break-all">{{ Str::limit(trim($query), 200) }}</p>
            </div>

            {{-- Nome --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                    Nome <span class="text-red-500">*</span>
                </label>
                <input type="text" wire:model.live="saveName"
                    placeholder="es. Clienti attivi per regione"
                    class="w-full rounded-lg border px-4 py-2.5 text-sm focus:outline-none focus:ring-2
                        {{ !empty($saveError) && empty($saveName) ? 'border-red-400 focus:ring-red-300' : 'border-gray-300 focus:border-primary-500 focus:ring-primary-200' }}
                        dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    autofocus/>
            </div>

            {{-- Descrizione --}}
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Descrizione <span class="text-gray-400 font-normal">(opzionale)</span></label>
                <textarea wire:model.live="saveDescription" rows="3"
                    placeholder="Breve descrizione dello scopo della query…"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 resize-none"></textarea>
            </div>

            {{-- Errore --}}
            @if(!empty($saveError))
            <p class="text-xs text-red-500">{{ $saveError }}</p>
            @endif
        </div>

        {{-- Footer modale --}}
        <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4 dark:border-gray-800">
            <button wire:click="closeSaveModal"
                class="rounded-lg border border-gray-200 bg-white px-8 py-3 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                Annulla
            </button>
            <button wire:click="saveQuery"
                class="rounded-lg px-8 py-3 text-sm font-semibold text-white shadow"
                style="background-color: var(--color-primary-600, #6366f1);">
                {{ $editingQueryId ? 'Aggiorna' : 'Salva' }}
            </button>
        </div>
    </div>
</div>
@endif

</x-filament-panels::page>