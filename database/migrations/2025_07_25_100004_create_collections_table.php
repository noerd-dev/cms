<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('page_id')->nullable();
                $table->unsignedBigInteger('tenant_id');
                $table->string('collection_key');
                $table->integer('sort')->default(0);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('collection_key');
                $table->index('page_id');
                $table->index('sort');

                $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
