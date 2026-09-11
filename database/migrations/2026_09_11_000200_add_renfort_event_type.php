<?php

// project: OpenBrigade

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dedicated "Renfort" activity type. An activity of this type is a
 * reinforcement sent to another section's event: while it isn't attached yet,
 * the activity form lets its author pick the main event to attach it to.
 * Idempotent; down() removes it only when no activity uses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('type_evenement')->where('TE_CODE', 'REN')->exists()) {
            return;
        }

        DB::table('type_evenement')->insert([
            'TE_CODE' => 'REN',
            'TE_LIBELLE' => 'Renfort',
            'CEV_CODE' => 'C_OPE',
            'TE_ICON' => 'REN.png',
        ]);
    }

    public function down(): void
    {
        if (DB::table('evenement')->where('TE_CODE', 'REN')->exists()) {
            return;
        }

        DB::table('type_evenement')->where('TE_CODE', 'REN')->delete();
    }
};
