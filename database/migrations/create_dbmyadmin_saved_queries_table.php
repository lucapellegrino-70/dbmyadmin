<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('dbmyadmin.saved_queries_table', 'dbmyadmin_saved_queries'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('sql');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('dbmyadmin.saved_queries_table', 'dbmyadmin_saved_queries'));
    }
};
