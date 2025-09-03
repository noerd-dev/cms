<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('element_page', function (Blueprint $table): void {
            // Only drop foreign key if it exists
            if ($this->foreignKeyExists('element_page', 'element_page_element_id_foreign')) {
                $table->dropForeign(['element_id']);
            }
            
            // Only drop index if it exists  
            if ($this->indexExists('element_page', 'element_page_element_id_index')) {
                $table->dropIndex(['element_id']);
            }
            
            // Only drop column if it exists
            if (Schema::hasColumn('element_page', 'element_id')) {
                $table->dropColumn('element_id');
            }
            
            // Only add element_key column if it doesn't exist
            if (!Schema::hasColumn('element_page', 'element_key')) {
                $table->string('element_key')->after('page_id');
                $table->index('element_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('element_page', function (Blueprint $table): void {
            // Only drop element_key index if it exists
            if ($this->indexExists('element_page', 'element_page_element_key_index')) {
                $table->dropIndex(['element_key']);
            }
            
            // Only drop element_key column if it exists
            if (Schema::hasColumn('element_page', 'element_key')) {
                $table->dropColumn('element_key');
            }
            
            // Only add back element_id column if it doesn't exist
            if (!Schema::hasColumn('element_page', 'element_id')) {
                $table->unsignedBigInteger('element_id')->after('page_id');
                $table->index('element_id');
                $table->foreign('element_id')->references('id')->on('elements');
            }
        });
    }
    
    /**
     * Check if a foreign key exists on a table
     */
    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $databaseName = config('database.connections.mysql.database');
        
        $result = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND CONSTRAINT_NAME = ?
        ", [$databaseName, $table, $foreignKey]);
        
        return $result[0]->count > 0;
    }
    
    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        $databaseName = config('database.connections.mysql.database');
        
        $result = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND INDEX_NAME = ?
        ", [$databaseName, $table, $index]);
        
        return $result[0]->count > 0;
    }
};
