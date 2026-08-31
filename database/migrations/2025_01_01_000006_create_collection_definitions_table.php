<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collection_definitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('filename');
            $table->string('key');
            $table->string('title');
            $table->string('title_list');
            $table->text('description')->nullable();
            $table->boolean('has_page')->default(false);
            $table->json('fields');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'filename']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('noerd_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_definitions');
    }
};
