<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cms_redirects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            // 191 keeps the composite unique index inside MySQL's key length
            // limit on utf8mb4; real redirect sources are far shorter.
            $table->string('source_path', 191);
            $table->unsignedBigInteger('target_page_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('target_page_id');
            $table->unique(['tenant_id', 'source_path'], 'cms_redirects_tenant_source_unique');

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('target_page_id')->references('id')->on('cms_pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_redirects');
    }
};
