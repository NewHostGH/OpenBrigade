<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The disponibilités module is now natively implemented (shared calendar +
 * availability grid with self-declaration), so drop its WIP marker and give it
 * a clearer description.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ob_feature')->where('key', 'disponibilites')->update([
            'name' => 'Disponibilités',
            'status' => 'native',
            'description' => 'Calendrier partagé et déclaration des créneaux de disponibilité du personnel.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('ob_feature')->where('key', 'disponibilites')->update([
            'name' => 'Disponibilité',
            'status' => 'wip',
            'description' => 'activer gestion des disponibilités',
            'updated_at' => now(),
        ]);
    }
};
