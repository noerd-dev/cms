<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('articles', 'additional_fields')) {
            Schema::table('articles', function (Blueprint $table): void {
                $table->json('additional_fields')->nullable()->after('featured_image');
            });
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('additional_fields');
        });
    }
};
