<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Email configuration
            $table->boolean('send_email')->default(false);
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->string('notification_email')->nullable();

            // YML metadata
            $table->string('yml_path');
            $table->timestamp('yml_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Add form_type_id to form_requests table
        if (Schema::hasTable('form_requests') && ! Schema::hasColumn('form_requests', 'form_type_id')) {
            Schema::table('form_requests', function (Blueprint $table) {
                $table->foreignId('form_type_id')->nullable()->after('tenant_id')->constrained('form_types')->nullOnDelete();
                $table->index('form_type_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove form_type_id from form_requests table
        if (Schema::hasTable('form_requests') && Schema::hasColumn('form_requests', 'form_type_id')) {
            Schema::table('form_requests', function (Blueprint $table) {
                $table->dropForeign(['form_type_id']);
                $table->dropColumn('form_type_id');
            });
        }

        Schema::dropIfExists('form_types');
    }
};
