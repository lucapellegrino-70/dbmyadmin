<?php

namespace LucaPellegrino\DbMyAdmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A concrete Eloquent model whose table name is set at construction time.
 * Used by BrowseTableRecords to query any table dynamically
 * without code generation or runtime class evaluation.
 */
class DynamicTableModel extends Model
{
    public $timestamps = false;

    public function __construct(string $tableName = '', string $primaryKey = 'id', array $attributes = [])
    {
        parent::__construct($attributes);
        if ($tableName !== '') {
            $this->table = $tableName;
        }
        $this->primaryKey   = $primaryKey;
        $this->incrementing = true;
    }
}
