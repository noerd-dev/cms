<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (! Schema::hasColumn('collections', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('name')->constrained('noerd_users')->nullOnDelete();
            } else {
                $table->foreign('created_by')->references('id')->on('noerd_users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
