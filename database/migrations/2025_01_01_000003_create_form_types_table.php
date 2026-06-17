<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('form_types')) {
            Schema::create('form_types', function (Blueprint $table): void {
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
        }

        if (Schema::hasTable('form_requests') && ! Schema::hasColumn('form_requests', 'form_type_id')) {
            Schema::table('form_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('form_type_id')->nullable()->after('tenant_id');

                $table->index('form_type_id');
                $table->foreign('form_type_id')->references('id')->on('form_types')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('form_requests') && Schema::hasColumn('form_requests', 'form_type_id')) {
            Schema::table('form_requests', function (Blueprint $table): void {
                $table->dropForeign(['form_type_id']);
                $table->dropColumn('form_type_id');
            });
        }

        Schema::dropIfExists('form_types');
    }
};
