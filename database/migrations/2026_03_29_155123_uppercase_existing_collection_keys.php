<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('collections')
            ->whereRaw('collection_key != UPPER(collection_key)')
            ->update(['collection_key' => DB::raw('UPPER(collection_key)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse — original casing is unknown
    }
};
