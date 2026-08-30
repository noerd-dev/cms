<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Created before the cms tables to resolve the circular dependency between
     * pages and collections. The page_id foreign key is added afterwards in the
     * create_cms_tables migration, once the pages table exists.
     */
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->unsignedBigInteger('element_page_id')->nullable();
            $table->string('collection_key');
            $table->string('owner_field')->nullable();
            $table->json('element_fields')->nullable();
            $table->boolean('is_element_collection')->default(false);
            $table->integer('sort')->default(0);
            $table->string('name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('page_id');
            $table->index('element_page_id');
            $table->index('collection_key');
            $table->index('is_element_collection');
            $table->index('sort');
            $table->unique(['tenant_id', 'collection_key']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('noerd_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
