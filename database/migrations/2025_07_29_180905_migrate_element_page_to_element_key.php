<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, migrate existing data to use element_key instead of element_id
        $elementPages = DB::table('element_page')
            ->join('elements', 'element_page.element_id', '=', 'elements.id')
            ->select('element_page.id', 'elements.element_key')
            ->get();

        // Add element_key column if it doesn't exist
        if (!Schema::hasColumn('element_page', 'element_key')) {
            Schema::table('element_page', function (Blueprint $table): void {
                $table->string('element_key')->nullable()->after('page_id');
            });
        }

        // Update existing records with element_key
        foreach ($elementPages as $elementPage) {
            DB::table('element_page')
                ->where('id', $elementPage->id)
                ->update(['element_key' => $elementPage->element_key]);
        }

        // Remove foreign key constraint if it exists and drop element_id column
        try {
            Schema::table('element_page', function (Blueprint $table): void {
                $table->dropForeign('element_page_element_id_foreign');
            });
        } catch (\Exception $e) {
            // Foreign key constraint might not exist
        }

        Schema::table('element_page', function (Blueprint $table): void {
            $table->dropColumn('element_id');
        });

        // Make element_key not nullable now that all records are updated
        Schema::table('element_page', function (Blueprint $table): void {
            $table->string('element_key')->nullable(false)->change();
            $table->index('element_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add element_id column
        Schema::table('element_page', function (Blueprint $table): void {
            $table->unsignedBigInteger('element_id')->nullable()->after('page_id');
        });

        // Migrate data back to element_id (assuming elements table still exists)
        $elementPages = DB::table('element_page')
            ->leftJoin('elements', 'element_page.element_key', '=', 'elements.element_key')
            ->select('element_page.id', 'elements.id as element_id')
            ->get();

        foreach ($elementPages as $elementPage) {
            if ($elementPage->element_id) {
                DB::table('element_page')
                    ->where('id', $elementPage->id)
                    ->update(['element_id' => $elementPage->element_id]);
            }
        }

        // Re-add foreign key constraint and make element_id not nullable
        Schema::table('element_page', function (Blueprint $table): void {
            $table->unsignedBigInteger('element_id')->nullable(false)->change();
            $table->foreign('element_id')->references('id')->on('elements');
        });

        // Drop element_key column
        Schema::table('element_page', function (Blueprint $table): void {
            $table->dropColumn('element_key');
        });
    }
};
