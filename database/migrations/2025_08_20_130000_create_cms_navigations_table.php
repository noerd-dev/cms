<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cms_navigations')) {
            Schema::create('cms_navigations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('navigation_key');
                $table->json('name')->nullable();
                $table->unsignedBigInteger('page_id')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('navigation_key');
                $table->index('page_id');

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('page_id')->references('id')->on('pages')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_navigations');
    }
};
