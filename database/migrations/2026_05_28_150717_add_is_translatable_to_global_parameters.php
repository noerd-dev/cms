<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('global_parameters', function (Blueprint $table) {
            $table->boolean('is_translatable')->default(false)->after('value');
        });

        DB::table('global_parameters')->orderBy('id')->each(function ($parameter): void {
            $decoded = json_decode((string) $parameter->value, true);

            if (is_array($decoded)) {
                DB::table('global_parameters')
                    ->where('id', $parameter->id)
                    ->update(['is_translatable' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('global_parameters', function (Blueprint $table) {
            $table->dropColumn('is_translatable');
        });
    }
};
