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
        Schema::table('cms_navigations', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('navigation_key')
                ->constrained('cms_navigations')->cascadeOnDelete();
            $table->integer('sort_order')->default(0)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('cms_navigations', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'sort_order']);
        });
    }
};
