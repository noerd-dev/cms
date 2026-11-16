<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Noerd\Cms\Services\Maintenance\FieldTypeRenameRepair;

/*
 | noerd/cms 0.6 names its field types in camelCase (`elementCollection`,
 | `homepageSelect`). Rewrites the field schemas stored in the database —
 | collection definitions and element collections — to the new names
 | (FieldTypeRenameRepair). Idempotent, data only.
 */
return new class extends Migration {
    public function up(): void
    {
        app(FieldTypeRenameRepair::class)->run();
    }

    public function down(): void
    {
        // The earlier names are not restored: 0.6 no longer reads them.
    }
};
