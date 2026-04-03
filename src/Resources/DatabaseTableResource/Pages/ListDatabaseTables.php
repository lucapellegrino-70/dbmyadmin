<?php

namespace LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use LucaPellegrino\DbMyAdmin\Models\DatabaseTable;
use LucaPellegrino\DbMyAdmin\Resources\DatabaseTableResource;

class ListDatabaseTables extends ListRecords
{
    protected static string $resource = DatabaseTableResource::class;

    protected static ?string $title = 'Gestione Tabelle Database';

    /**
     * Returns a collection-backed Builder mock so Filament can filter, sort,
     * and paginate without executing real SQL queries against a DB table.
     */
    protected function getTableQuery(): ?Builder
    {
        $models = DatabaseTable::getAllModels();

        return new class($models) extends Builder {
            protected $collection;
            public $orders  = [];
            public $wheres  = [];
            public $limit   = null;
            public $offset  = null;

            public function __construct($collection)
            {
                $this->collection = $collection;
            }

            public function getModel()
            {
                return new DatabaseTable();
            }

            public function get($columns = ['*'])
            {
                return $this->collection;
            }

            public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
            {
                $page    = $page ?: (int) request()->get($pageName, 1);
                $perPage = $perPage ?: 15;
                $total   = $total ?? (int) $this->collection->count();
                $items   = $this->collection->forPage($page, $perPage)->values();

                return new \Illuminate\Pagination\LengthAwarePaginator(
                    $items,
                    $total,
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'pageName' => $pageName]
                );
            }

            public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
            {
                return $this->paginate($perPage, $columns, $pageName, $page);
            }

            public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
            {
                return $this->paginate($perPage, $columns, 'page', 1);
            }

            public function count()
            {
                return (int) $this->collection->count();
            }

            public function getCountForPagination($columns = ['*'])
            {
                return (int) $this->collection->count();
            }

            public function max($column)
            {
                return $this->collection->max($column);
            }

            public function min($column)
            {
                return $this->collection->min($column);
            }

            public function avg($column)
            {
                return $this->collection->avg($column);
            }

            public function sum($column)
            {
                return $this->collection->sum($column);
            }

            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if (func_num_args() === 2) {
                    $value    = $operator;
                    $operator = '=';
                }

                if (is_string($column)) {
                    $column         = str_replace(['`', 'database_tables.'], '', $column);
                    $this->wheres[] = compact('column', 'operator', 'value', 'boolean');

                    $this->collection = $this->collection->filter(function ($model) use ($column, $value, $operator) {
                        $modelValue = $model->$column ?? null;
                        return match ($operator) {
                            '=', '==' => $modelValue == $value,
                            '!=', '<>' => $modelValue != $value,
                            'like' => str_contains(
                                strtolower((string) $modelValue),
                                strtolower(str_replace('%', '', (string) $value))
                            ),
                            default => $modelValue == $value,
                        };
                    });
                }

                return $this;
            }

            public function whereIn($column, $values, $boolean = 'and', $not = false)
            {
                if (is_string($column)) {
                    $column           = str_replace(['`', 'database_tables.'], '', $column);
                    $this->collection = $this->collection->filter(
                        fn ($model) => in_array($model->$column ?? null, $values)
                    );
                }

                return $this;
            }

            public function orderBy($column, $direction = 'asc')
            {
                if (is_string($column)) {
                    $column         = str_replace(['`', 'database_tables.'], '', $column);
                    $this->orders[] = ['column' => $column, 'direction' => $direction];
                    $this->collection = $this->collection
                        ->sortBy($column, SORT_REGULAR, $direction === 'desc')
                        ->values();
                }

                return $this;
            }

            public function limit($value)
            {
                $this->limit      = $value;
                $this->collection = $this->collection->take($value);
                return $this;
            }

            public function offset($value)
            {
                $this->offset     = $value;
                $this->collection = $this->collection->skip($value);
                return $this;
            }

            public function find($id, $columns = ['*'])
            {
                return $this->collection->firstWhere('name', $id);
            }

            public function first($columns = ['*'])
            {
                return $this->collection->first();
            }

            public function firstOrFail($columns = ['*'])
            {
                return $this->first($columns)
                    ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }

            public function tap($callback)
            {
                return $this;
            }

            public function clone()
            {
                $cloned             = new static($this->collection);
                $cloned->orders     = $this->orders;
                $cloned->wheres     = $this->wheres;
                $cloned->limit      = $this->limit;
                $cloned->offset     = $this->offset;
                return $cloned;
            }

            public function newQuery()
            {
                return new static(DatabaseTable::getAllModels());
            }

            public function toBase()
            {
                return $this;
            }

            public function __call($method, $parameters)
            {
                if ($method === 'getConnection') {
                    return null;
                }
                return $this;
            }
        };
    }
}
