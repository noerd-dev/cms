<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'pages',
        'articles',
        'authors',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'additional_fields') && ! Schema::hasColumn($table, 'custom_attributes')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->renameColumn('additional_fields', 'custom_attributes');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'custom_attributes') && ! Schema::hasColumn($table, 'additional_fields')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->renameColumn('custom_attributes', 'additional_fields');
                });
            }
        }
    }
};
