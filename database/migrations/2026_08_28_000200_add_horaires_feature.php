<?php

// project: OpenBrigade

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Registers the salaried-staff timesheet ("Horaires de travail") as a native,
 * gateable feature so the nav item and routes can be toggled from
 * Admin → Fonctionnalités. Enabled by default. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ob_feature')->updateOrInsert(
            ['key' => 'horaires'],
            [
                'name' => 'Horaires de travail',
                'description' => 'Feuille de temps hebdomadaire du personnel salarié : '
                    .'saisie des pointages, heures supplémentaires et circuit de validation.',
                'group' => 'planning',
                'status' => 'native',
                'icon' => 'clock',
                'enabled' => 1,
                'ordering' => 120,
            ],
        );
    }

    public function down(): void
    {
        DB::table('ob_feature')->where('key', 'horaires')->delete();
    }
};
