<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Noerd\Cms\Services\Maintenance\SelectOptionValueRepair;

/*
 | The document editor stored a select with a plain list of options as the
 | option's array index; from noerd/cms 0.6 it stores the option itself, like
 | every other form. Rewrites the stored index values (see
 | SelectOptionValueRepair for the exact rule). Idempotent, data only.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('content_elements')) {
            return;
        }

        app(SelectOptionValueRepair::class)->run();
    }

    public function down(): void
    {
        // The index values are not restored: they were the bug.
    }
};
