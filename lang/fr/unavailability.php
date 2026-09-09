<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Absences / Indisponibilités
    |--------------------------------------------------------------------------
    */

    'title' => 'Indisponibilités',
    'page_title' => 'Absences / Indisponibilités',
    'breadcrumb' => 'Absences / Indisponibilités',

    // Onglets de vue
    'tab_section' => 'Ma section',
    'tab_mine' => 'Mes absences',

    // Onglets de statut
    'status_pending' => 'En attente',
    'status_accepted' => 'Acceptées',
    'status_all' => 'Toutes',

    // Actions
    'declare_absence' => 'Déclarer une absence',
    'accept' => 'Accepter',
    'reject' => 'Refuser',
    'cancel_absence' => 'Annuler',
    'confirm_cancel' => 'Annuler cette absence ?',

    // Formulaire de déclaration
    'field_person' => 'Personne',
    'field_person_self' => 'Moi-même',
    'field_type' => "Type d'absence",
    'type_needs_validation' => 'validation requise',
    'field_scope' => 'Durée',
    'scope_full' => 'Journée(s) complète(s)',
    'scope_morning' => 'Matin',
    'scope_afternoon' => 'Après-midi',
    'scope_hours' => 'Heures',
    'field_start' => 'Date de début',
    'field_end' => 'Date de fin',
    'field_end_hint' => 'Pour une période de plusieurs jours (journées complètes).',
    'field_hour_start' => 'Heure de début',
    'field_hour_end' => 'Heure de fin',
    'field_hours_hint' => 'Renseignez les heures uniquement pour une absence de type « Heures ».',
    'field_comment' => 'Commentaire',
    'error_circuit_volunteer' => 'Les absences avec circuit de validation ne sont pas disponibles pour ce statut de personnel.',

    // Statuts
    'status_rejected' => 'Refusées',

    // Messages
    'saved' => 'Absence enregistrée.',
    'accepted' => 'Absence acceptée.',
    'rejected' => 'Absence refusée.',
    'cancelled' => 'Absence annulée.',

];
