<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // 1) Rename existing collections table to collection_rows
        if (Schema::hasTable('collections') && !Schema::hasTable('collection_rows')) {
            Schema::rename('collections', 'collection_rows');
        }

        // 2) Create new parent collections table
        if (!Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('collection_key');
                $table->string('name')->nullable();
                $table->timestamps();
                $table->index('tenant_id');
                $table->unique(['tenant_id', 'collection_key']);
                $table->foreign('tenant_id', 'collections_new_tenant_id_foreign')->references('id')->on('tenants')->onDelete('cascade');
            });
        }

        // 3) Add collection_id to collection_rows and migrate data
        if (!Schema::hasColumn('collection_rows', 'collection_id')) {
            Schema::table('collection_rows', function (Blueprint $table): void {
                $table->unsignedBigInteger('collection_id')->nullable()->after('tenant_id');
                $table->foreign('collection_id')->references('id')->on('collections')->onDelete('cascade');
            });
        }

        // Populate collections and link rows
        $rows = DB::table('collection_rows')->select('tenant_id', 'collection_key')->distinct()->get();
        foreach ($rows as $row) {
            $existing = DB::table('collections')
                ->where('tenant_id', $row->tenant_id)
                ->where('collection_key', $row->collection_key)
                ->first();

            if (!$existing) {
                $collectionId = DB::table('collections')->insertGetId([
                    'tenant_id' => $row->tenant_id,
                    'collection_key' => $row->collection_key,
                    'name' => $row->collection_key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $collectionId = $existing->id;
            }

            DB::table('collection_rows')
                ->where('tenant_id', $row->tenant_id)
                ->where('collection_key', $row->collection_key)
                ->update(['collection_id' => $collectionId]);
        }

        // 4) Drop collection_key from rows once migrated
        if (Schema::hasColumn('collection_rows', 'collection_key')) {
            Schema::table('collection_rows', function (Blueprint $table): void {
                $table->dropColumn('collection_key');
            });
        }
    }

    public function down(): void
    {
        // Re-add collection_key to rows
        if (!Schema::hasColumn('collection_rows', 'collection_key')) {
            Schema::table('collection_rows', function (Blueprint $table): void {
                $table->string('collection_key')->nullable()->after('tenant_id');
                $table->index('collection_key');
            });
        }

        // Fill back collection_key from parent relation
        $rows = DB::table('collection_rows')->select('id', 'collection_id')->get();
        foreach ($rows as $row) {
            $parent = DB::table('collections')->where('id', $row->collection_id)->first();
            if ($parent) {
                DB::table('collection_rows')->where('id', $row->id)->update([
                    'collection_key' => $parent->collection_key,
                ]);
            }
        }

        // Drop foreign and column
        if (Schema::hasColumn('collection_rows', 'collection_id')) {
            Schema::table('collection_rows', function (Blueprint $table): void {
                $table->dropForeign(['collection_id']);
                $table->dropColumn('collection_id');
            });
        }

        // Drop new collections table and rename back
        Schema::dropIfExists('collections');
        if (Schema::hasTable('collection_rows') && !Schema::hasTable('collections')) {
            Schema::rename('collection_rows', 'collections');
        }
    }
};


