<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chaînes du tableau de bord
    |--------------------------------------------------------------------------
    |
    | Toutes les chaînes visibles du tableau de bord et de ses widgets.
    | Organisées par widget (snake_case). Les mots génériques déjà présents
    | dans common.php ne sont pas dupliqués ici.
    |
    */

    // ── Page index ─────────────────────────────────────────────────────────

    'index' => [
        'title' => 'Tableau de bord',
        'password_expired' => 'Votre mot de passe a',
        'password_expired_tag' => 'expiré',
        'password_expiry_soon' => 'Votre mot de passe expire dans',
        'password_days' => ':count jours',
        'password_on' => '(le :date)',
        'password_change_now' => 'Changer maintenant',
        'photo_required' => 'Une photo de profil est obligatoire pour s\'inscrire aux activités.',
        'photo_add_now' => 'Ajouter ma photo',
        'competence_expiry' => 'Expiration prochaine de vos compétences :',
        'competence_days' => 'expire dans :count jours',
        'competence_see' => 'Voir le détail',
        'drop_hint' => 'Glissez un widget ici',
        'hidden_tray_title' => 'Widgets masqués',
        'tray_all_visible' => 'Tous les widgets sont visibles.',
        'btn_show_widget' => 'Afficher',
        'btn_customize' => 'Personnaliser',
    ],

    // ── Widget : À propos ───────────────────────────────────────────────────

    'agenda' => [
        'title' => 'Agenda',
        'open_calendar' => 'Ouvrir le calendrier',
        'empty' => 'Aucun événement à venir.',
    ],

    'about' => [
        'title' => 'À propos',
        'doc_online' => 'Documentation en ligne',
        'community' => 'Communauté eBrigade',
        'support' => 'Support – :email',
        'version' => 'version',
    ],

    // ── Widget : Anniversaires / Ma section ────────────────────────────────

    'birthdays' => [
        'title' => 'Ma section',
        'section_title' => 'Anniversaires à souhaiter',
        'none' => 'Aucun anniversaire dans les 3 prochains jours.',
        'whatsapp_title' => 'Mes groupes WhatsApp',
        'whatsapp_join' => 'Rejoindre le groupe WhatsApp',
    ],

    // ── Widget : Consommables ───────────────────────────────────────────────

    'consumables' => [
        'title' => 'Consommables',
    ],

    // ── Widget : Congés à valider (CP) ─────────────────────────────────────

    'cp' => [
        'title' => 'Congés à valider',
        'to_valdiate' => 'À valider',
        'date_range' => '— :debut au :fin',
    ],

    // ── Widget : Service / Astreinte ───────────────────────────────────────

    'duty' => [
        'title' => 'Service / Astreinte',
        'empty' => 'Aucun personnel de service.',
    ],

    // ── Widget : Activités à venir ─────────────────────────────────────────

    'events' => [
        'title' => 'Activités à venir',
        'closed_title' => 'Inscriptions fermées',
        'open_title' => 'Inscriptions ouvertes',
        'session_prefix' => '– session n°',
        'empty' => 'Aucune activité prévue.',
    ],

    // ── Widget : Notes de frais ─────────────────────────────────────────────

    'expenses' => [
        'title' => 'Notes de frais',
        'empty' => 'Aucune note de frais à traiter.',
        'note_ref' => 'Note #:id',
        // Statuts
        'status_waiting' => 'En attente',
        'status_approved' => 'Validé',
        'status_approved1' => 'Validé N1',
        'status_approved2' => 'Validé N2',
    ],

    // ── Widget : Horaires à valider ─────────────────────────────────────────

    'horaires' => [
        'title' => 'Horaires à valider',
        'week_label' => 'Semaine :week – :year',
        'to_validate' => 'À valider',
    ],

    // ── Widget : Consignes / Actualités / Informations ──────────────────────

    'infos' => [
        'consignes_title' => 'Consignes opérationnelles',
        'actualites_title' => 'Actualités',
        'fallback_title' => 'Informations',
        'empty' => 'Aucune information en cours.',
    ],

    // ── Widget : Mains courantes ────────────────────────────────────────────

    'mc' => [
        'title' => 'Mains courantes',
        'empty' => 'Aucune main courante en cours.',
    ],

    // ── Widget : Mes activités ──────────────────────────────────────────────

    'my_activities' => [
        'title' => 'Mes activités',
        'astreinte_title' => 'Astreinte',
        'closed_title' => 'Inscriptions fermées',
        'registered_title' => 'Inscrit',
        'session_prefix' => '– session n°',
        'empty' => 'Aucune participation prévue.',
    ],

    // ── Widget : Remplacements ──────────────────────────────────────────────

    'remplacements' => [
        'title' => 'Remplacements',
        'guard_label' => 'Remplacements de garde',
    ],

    // ── Widget : Demande de remplaçant ──────────────────────────────────────

    'replacement_requests' => [
        'title' => 'Demande de remplaçant',
        'search_label' => 'Recherche de remplaçant',
        'in_progress' => 'En cours',
        'date_range' => '– du :debut au :fin',
    ],

    // ── Widget : Statistiques manquantes ────────────────────────────────────

    'stats_missing' => [
        'title' => 'Statistiques manquantes',
    ],

    // ── Widget : Stats KPI bar ───────────────────────────────────────────────

    'stats' => [
        'participations_title' => 'Mes participations',
        'total' => 'Total',
        'upcoming' => 'À venir',
        'activities_title' => 'Activités',
        'this_month' => 'Ce mois',
        'quarter' => 'Trimestre',
        'new_members_title' => 'Nouveaux membres',
        'tasks_title' => 'Tâches',
        'my_alarms' => 'Mes alarmes',
    ],

    // ── Widget : Formations ──────────────────────────────────────────────────

    'training' => [
        'title' => 'Formations :year',
        'trainee_since' => 'Suivies depuis le 1er janvier :year',
        'trainer_since' => 'Données depuis le 1er janvier :year',
        'total' => 'TOTAL',
        'total_trainer' => 'TOTAL formateur',
        'other_code' => 'Autres',
    ],

    // ── Widget : Activité non réglée ─────────────────────────────────────────

    'unpaid' => [
        'title' => 'Activité non réglée',
        'badge_relance' => 'Relancé',
        'badge_facture' => 'Facturé',
        'badge_to_bill' => 'À facturer',
    ],

    // ── Widget : Véhicules ───────────────────────────────────────────────────

    'vehicles' => [
        'title' => 'Véhicules',
    ],

    // ── Widget : Mon profil (welcome) ────────────────────────────────────────

    'welcome' => [
        'title' => 'Mon profil',
        'number_prefix' => 'Nº :id',
        'week_prefix' => 'Semaine :week',
        'incomplete_title' => 'Fiche incomplète',
        'complete_link' => 'Compléter ma fiche &rarr;',
    ],

];
