<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('element_page')) {
            Schema::create('element_page', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('page_id');
                $table->unsignedBigInteger('element_id');
                $table->unsignedInteger('sort')->default(0);
                $table->json('data')->nullable();
                $table->timestamps();

                $table->index('page_id');
                $table->index('element_id');

                $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
                $table->foreign('element_id')->references('id')->on('elements');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('element_page');
    }
};
