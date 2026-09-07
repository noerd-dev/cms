<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cms_form_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('key');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('send_email')->default(false);
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->string('notification_email')->nullable();
            $table->string('yml_path');
            $table->timestamp('yml_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // form_requests is created before form_types, so its reference column
        // is added here — a two-step like the pages <-> collections link.
        Schema::table('cms_form_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('form_type_id')->nullable()->after('tenant_id');

            $table->index('form_type_id');
            $table->foreign('form_type_id')->references('id')->on('cms_form_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_form_requests') && Schema::hasColumn('cms_form_requests', 'form_type_id')) {
            Schema::table('cms_form_requests', function (Blueprint $table): void {
                $table->dropForeign(['form_type_id']);
                $table->dropColumn('form_type_id');
            });
        }

        Schema::dropIfExists('cms_form_types');
    }
};
