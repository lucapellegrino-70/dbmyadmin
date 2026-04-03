<?php

namespace LucaPellegrino\DbMyAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class SavedQuery extends Model
{
    protected $fillable = ['name', 'description', 'sql', 'created_by'];

    public function getTable(): string
    {
        return config('dbmyadmin.saved_queries_table', 'dbmyadmin_saved_queries');
    }

    public function getSqlPreviewAttribute(): string
    {
        if (strlen($this->sql) <= 80) {
            return $this->sql;
        }

        return substr($this->sql, 0, 77) . '...';
    }
}
