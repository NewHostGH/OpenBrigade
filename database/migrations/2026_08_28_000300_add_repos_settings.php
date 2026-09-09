<?php

// project: OpenBrigade

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuration rows powering the Repos auto-suggestion engine (Admin ▸ Options,
 * Planning tab). Each factor of the suggestion can be toggled/tuned here.
 * Idempotent (keyed by ID); down() removes them.
 */
return new class extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private const ROWS = [
        ['ID' => 144, 'NAME' => 'repos_auto_enable', 'VALUE' => '1', 'YESNO' => 1, 'ORDERING' => 122,
            'DISPLAY_NAME' => 'Repos : activer la suggestion automatique',
            'DESCRIPTION' => 'Affiche le bouton « Suggérer » sur l\'écran Repos.'],
        ['ID' => 145, 'NAME' => 'repos_postgarde', 'VALUE' => '1', 'YESNO' => 1, 'ORDERING' => 123,
            'DISPLAY_NAME' => 'Repos : repos de sécurité après une garde',
            'DESCRIPTION' => 'Suggère un repos le lendemain de chaque garde (24h).'],
        ['ID' => 146, 'NAME' => 'repos_postgarde_scope', 'VALUE' => 'full', 'YESNO' => 0, 'ORDERING' => 124,
            'DISPLAY_NAME' => 'Repos : durée du repos post-garde',
            'DESCRIPTION' => 'Journée complète, ou seulement la demi-journée Jour ou Nuit.'],
        ['ID' => 147, 'NAME' => 'repos_halfday_shift', 'VALUE' => '0', 'YESNO' => 1, 'ORDERING' => 125,
            'DISPLAY_NAME' => 'Repos : demi-journée selon le poste',
            'DESCRIPTION' => 'Garde de nuit → repos Jour ; garde de jour → repos Nuit (prioritaire sur la durée ci-dessus).'],
        ['ID' => 148, 'NAME' => 'repos_timesheet_threshold_min', 'VALUE' => '0', 'YESNO' => 0, 'ORDERING' => 126,
            'DISPLAY_NAME' => 'Repos : seuil d\'heures travaillées par jour (minutes, 0 = désactivé)',
            'DESCRIPTION' => 'Au-delà de ce nombre de minutes travaillées un jour, un repos est suggéré le lendemain.'],
        ['ID' => 149, 'NAME' => 'repos_min_rest_days', 'VALUE' => '0', 'YESNO' => 0, 'ORDERING' => 127,
            'DISPLAY_NAME' => 'Repos : nombre minimum de jours de repos par mois (0 = désactivé)',
            'DESCRIPTION' => 'Complète les suggestions pour atteindre ce minimum de jours de repos par mois.'],
        ['ID' => 150, 'NAME' => 'repos_min_rest_days_week', 'VALUE' => '0', 'YESNO' => 0, 'ORDERING' => 128,
            'DISPLAY_NAME' => 'Repos : nombre minimum de jours de repos par semaine (0 = désactivé)',
            'DESCRIPTION' => 'Complète les suggestions pour atteindre ce minimum de jours de repos par semaine.'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $row) {
            DB::table('configuration')->updateOrInsert(
                ['ID' => $row['ID']],
                $row + ['TAB' => 1, 'HIDDEN' => 0, 'IS_FILE' => 0, 'CARD_NAME' => ''],
            );
        }
    }

    public function down(): void
    {
        DB::table('configuration')->whereIn('ID', array_column(self::ROWS, 'ID'))->delete();
    }
};
