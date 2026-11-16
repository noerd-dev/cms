<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Up to 0.5 a form type with `send_email` sent ONE mail — to the form
 | recipients and, with the same subject and body, to whatever address the
 | visitor typed into `email`. 0.6 separates the two:
 |
 | - `send_email` / `email_subject` / `email_body` is the notification of the
 |   recipients (CMS settings, else `notification_email`) — never the visitor
 | - `send_confirmation` / `confirmation_subject` / `confirmation_body` is the
 |   confirmation of the visitor, sent to the field `confirmation_field`
 |   (null = `email`)
 |
 | Existing rows keep their behaviour as closely as is safe: a type with
 | `send_email` switched on gets the confirmation switched on with a COPY of
 | its subject and body, so visitors keep receiving a mail — the recipients'
 | copy now only goes to the recipients. `is_active` marks a type whose form
 | YAML disappeared (FormTypeSyncService); every existing type starts active.
 | Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cms_form_types')) {
            return;
        }

        $addConfirmation = ! Schema::hasColumn('cms_form_types', 'send_confirmation');

        Schema::table('cms_form_types', function (Blueprint $table) use ($addConfirmation): void {
            if ($addConfirmation) {
                $table->boolean('send_confirmation')->default(false)->after('notification_email');
                $table->string('confirmation_subject')->nullable()->after('send_confirmation');
                $table->text('confirmation_body')->nullable()->after('confirmation_subject');
                $table->string('confirmation_field')->nullable()->after('confirmation_body');
            }

            if (! Schema::hasColumn('cms_form_types', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('title');
            }
        });

        // Only when the columns were added just now: a second run must not
        // switch on a confirmation an admin switched off meanwhile.
        if ($addConfirmation) {
            DB::table('cms_form_types')
                ->where('send_email', true)
                ->update([
                    'send_confirmation' => true,
                    'confirmation_subject' => DB::raw('email_subject'),
                    'confirmation_body' => DB::raw('email_body'),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_form_types')) {
            return;
        }

        Schema::table('cms_form_types', function (Blueprint $table): void {
            foreach (['send_confirmation', 'confirmation_subject', 'confirmation_body', 'confirmation_field', 'is_active'] as $column) {
                if (Schema::hasColumn('cms_form_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
