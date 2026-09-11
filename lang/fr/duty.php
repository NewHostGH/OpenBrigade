<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Garde / Astreintes
    |--------------------------------------------------------------------------
    */

    // Titres / breadcrumb
    'breadcrumb_duty' => 'Garde',
    'title_schedule' => 'Garde de la semaine',
    'title_today' => 'Garde du jour',
    'title_on_call' => 'Garde du mois',
    'title_types' => 'Types de garde',

    // Period switcher (day / week / month — all read the same astreinte data)
    'period_day' => 'Jour',
    'period_week' => 'Semaine',
    'period_month' => 'Mois',
    'period_calendar' => 'Calendrier',

    // Calendrier des gardes
    'title_calendar' => 'Calendrier des gardes',
    'calendar_all_sections' => 'Toutes mes sections',
    'calendar_empty' => 'Aucune astreinte sur cette période.',

    // Garde du jour
    'today_heading' => 'Garde du jour',
    'today_for_date' => 'Personnel de garde le :date',
    'today_empty' => 'Personne de garde aujourd\'hui.',
    'today_no_role' => 'Sans rôle',
    'today_from' => 'de',
    'today_to' => 'à',
    'today_count' => ':count personne(s) de garde',

    // Actions
    'new_on_call' => 'Nouvelle astreinte',
    'guard_types_btn' => 'Types de garde',
    'new_type' => 'Nouveau type',
    'export_xls_title' => 'Exporter le mois en XLS',
    'export_csv_title' => 'Exporter le mois en CSV',
    'export_pdf_title' => 'Version imprimable / PDF du mois',

    // On-call printable roster
    'print_heading' => 'Astreintes',
    'print_btn' => 'Imprimer / PDF',
    'print_col_debut' => 'Début',
    'print_col_fin' => 'Fin',
    'print_col_personnel' => 'Personnel',
    'print_col_role' => 'Rôle',
    'print_empty' => 'Aucune astreinte sur ce mois.',
    'print_count' => ':count astreinte(s)',

    // Week navigation
    'prev_week' => 'Semaine précédente',
    'next_week' => 'Semaine suivante',
    'current_week' => 'Semaine courante',
    'week_label' => 'Semaine du :from au :to',

    // Day card
    'today_badge' => 'Aujourd\'hui',
    'no_on_call_day' => 'Aucune astreinte',
    'no_on_call_week' => 'Aucune astreinte pour cette semaine.',

    // Types table headers
    'col_order' => '#',
    'col_name' => 'Nom',
    'col_day' => 'Jour',
    'col_night' => 'Nuit',
    'col_persons_jn' => 'Personnes J/N',
    'col_vehicles' => 'Véhicules',
    'col_default' => 'Défaut',

    // Types icons/titles
    'active_day' => 'Actif le jour',
    'active_night' => 'Actif la nuit',
    'default_type' => 'Type par défaut',
    'empty_types' => 'Aucun type de garde configuré pour votre section.',

    // Types modal titles
    'modal_create_title' => 'Nouveau type de garde',
    'modal_edit_title' => 'Modifier — :name',
    'confirm_delete_type' => 'Supprimer le type « :name » ?',

    // Type form labels
    'form_name' => 'Nom',
    'form_order' => 'Ordre d\'affichage',
    'form_location' => 'Lieu',
    'form_location_ph' => 'ex. Caserne centrale',
    'form_address' => 'Adresse',
    'form_active_day' => 'Actif le jour',
    'form_start_day' => 'Début (jour)',
    'form_end_day' => 'Fin (jour)',
    'form_persons_day' => 'Personnes requis (jour)',
    'form_active_night' => 'Actif la nuit',
    'form_start_night' => 'Début (nuit)',
    'form_end_night' => 'Fin (nuit)',
    'form_persons_night' => 'Personnes requis (nuit)',
    'form_vehicles_default' => 'Véhicules affichés par défaut',
    'form_is_default' => 'Type de garde par défaut',

];
