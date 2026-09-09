<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mon planning
    |--------------------------------------------------------------------------
    */

    'title' => 'Planning',
    'breadcrumb' => 'Planning',

    // Navigation mensuelle
    'this_month' => 'Ce mois-ci',

    // Personnel filter (managers — permission 56)
    'people_title' => 'Personnel',
    'select_all' => 'Tout cocher',
    'select_none' => 'Tout décocher',
    'all_sections' => 'Toutes mes sections',
    'legend_note' => 'Couleur = personne · motif = type :',

    // Jours (abréviations)
    'day_mon' => 'Lun',
    'day_tue' => 'Mar',
    'day_wed' => 'Mer',
    'day_thu' => 'Jeu',
    'day_fri' => 'Ven',
    'day_sat' => 'Sam',
    'day_sun' => 'Dim',

    // Légende
    'legend_event' => 'Activité',
    'legend_abs_ok' => 'Absence acceptée',
    'legend_abs_pending' => 'Absence en attente',

    // Fallback / tooltips
    'absence_default' => 'Absence',
    'pending' => 'En attente',

    // Export imprimable / PDF
    'export_xls_title' => 'Exporter le planning du mois (Excel)',
    'export_csv_title' => 'Exporter le planning du mois (CSV)',
    'export_col_lastname' => 'Nom',
    'export_col_firstname' => 'Prénom',
    'export_col_section' => 'Section',
    'export_pdf_title' => 'Version imprimable / PDF du mois',
    'print_btn' => 'Imprimer / PDF',
    'print_heading' => 'Mon planning',
    'print_for' => 'Planning de :name — :month',
    'print_section_events' => 'Activités',
    'print_section_absences' => 'Absences',
    'print_events_empty' => 'Aucune activité ce mois-ci.',
    'print_absences_empty' => 'Aucune absence ce mois-ci.',
    'print_col_date' => 'Date',
    'print_col_time' => 'Heure',
    'print_col_activity' => 'Activité',
    'print_col_type' => 'Type',
    'print_col_status' => 'Statut',
    'print_col_period' => 'Période',
    'print_col_comment' => 'Commentaire',
    'status_closed' => 'Clôturée',
    'status_open' => 'Ouverte',
    'status_accepted' => 'Acceptée',

];
