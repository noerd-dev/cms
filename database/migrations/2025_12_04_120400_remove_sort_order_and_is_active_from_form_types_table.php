<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table): void {
            $table->dropIndex('form_types_tenant_id_is_active_index');
            $table->dropColumn(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('description');
            $table->integer('sort_order')->default(0)->after('is_active');
            $table->index(['tenant_id', 'is_active']);
        });
    }
};
