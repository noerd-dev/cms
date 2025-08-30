<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('element_page', function (Blueprint $table): void {
            // Drop the foreign key constraint and index for element_id
            $table->dropForeign(['element_id']);
            $table->dropIndex(['element_id']);
            
            // Drop the element_id column
            $table->dropColumn('element_id');
            
            // Add element_key column
            $table->string('element_key')->after('page_id');
            
            // Add index for element_key
            $table->index('element_key');
        });
    }

    public function down(): void
    {
        Schema::table('element_page', function (Blueprint $table): void {
            // Drop element_key column and its index
            $table->dropIndex(['element_key']);
            $table->dropColumn('element_key');
            
            // Add back element_id column
            $table->unsignedBigInteger('element_id')->after('page_id');
            
            // Recreate index and foreign key for element_id
            $table->index('element_id');
            $table->foreign('element_id')->references('id')->on('elements');
        });
    }
};
