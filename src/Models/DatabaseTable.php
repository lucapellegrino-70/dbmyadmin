<?php

namespace LucaPellegrino\DbMyAdmin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LucaPellegrino\DbMyAdmin\Contracts\DatabaseDriver;

class DatabaseTable extends Model
{
    protected $connection   = null;
    protected $primaryKey   = 'name';
    protected $keyType      = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ['name', 'rows', 'data_length', 'index_length', 'engine', 'collation'];

    protected $casts = [
        'rows'         => 'integer',
        'data_length'  => 'integer',
        'index_length' => 'integer',
    ];

    protected static ?Collection $cachedModels = null;

    public static function getAllModels(): Collection
    {
        if (static::$cachedModels !== null) {
            return static::$cachedModels;
        }

        /** @var DatabaseDriver $driver */
        $driver   = app(DatabaseDriver::class);
        $excluded = config('dbmyadmin.excluded_tables', []);

        static::$cachedModels = $driver->getTables()
            ->reject(fn ($table) => in_array($table['name'], $excluded))
            ->map(function ($table) {
                $model = new static($table);
                $total = ($table['data_length'] ?? 0) + ($table['index_length'] ?? 0);
                $model->setAttribute('total_size', $total ?: null);
                return $model;
            });

        return static::$cachedModels;
    }

    public static function clearCache(): void
    {
        static::$cachedModels = null;
    }

    public static function find($key): ?static
    {
        return static::getAllModels()->firstWhere('name', $key);
    }

    public static function findOrFail($key): static
    {
        return static::find($key) ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
            "Table [{$key}] not found."
        );
    }

    public static function all($columns = ['*']): Collection
    {
        return static::getAllModels();
    }

    // Prevent actual DB writes
    public function save(array $options = []): bool { return true; }
    public function delete(): ?bool { return true; }
    public function refresh(): static { return $this; }

    public function getConnection(): \Illuminate\Database\Connection
    {
        return DB::connection();
    }
}
