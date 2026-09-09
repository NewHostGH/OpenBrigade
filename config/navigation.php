<?php

/*
|--------------------------------------------------------------------------
| Application Navigation
|--------------------------------------------------------------------------
|
| Defines the sidebar navigation tree. Each group and item can carry an
| optional 'permission' key — the integer feature ID checked via
| User::hasPermission(). Items without a permission are visible to any
| authenticated user.
|
| Keys, codes and URLs are English (CONVENTIONS §11); labels stay French
| (user-facing copy). Legacy URLs use the /legacy/ prefix so they route
| through the LegacyBridgeController. 'feature' keys mirror ob_feature.key
| and stay as legacy data. null entries inside 'items' render as dividers.
|
*/

return [
    'top' => [

        // ── Personnel ──────────────────────────────────────────────────────
        [
            'code' => 'personnel',
            'label' => 'Personnel',
            'icon' => 'users',
            'items' => [
                ['key' => 'personnel.list',        'label' => 'Liste',           'url' => '/personnel',      'icon' => 'list'],
                ['key' => 'personnel.skills',      'label' => 'Compétences',     'url' => '/qualifications', 'icon' => 'certificate',      'permission' => 56, 'feature' => 'competences'],
                ['key' => 'personnel.dues',        'label' => 'Cotisations',     'url' => '/dues',           'icon' => 'receipt',          'permission' => 53, 'feature' => 'cotisations'],
                ['key' => 'personnel.geolocation', 'label' => 'Géolocalisation', 'url' => '/geolocation',    'icon' => 'map-marker-alt',   'permission' => 76, 'feature' => 'geolocalize_enabled'],
            ],
        ],

        // ── Activités ──────────────────────────────────────────────────────
        [
            'code' => 'activities',
            'label' => 'Activités',
            'icon' => 'calendar-alt',
            'items' => [
                ['key' => 'activities.list',     'label' => 'Liste',            'url' => '/events',                                        'icon' => 'list-ul',    'permission' => 41],
                ['key' => 'activities.mc',       'label' => 'Main courante',    'url' => '/events?type=MC',                                'icon' => 'ambulance',  'permission' => 52, 'feature' => 'main_courante'],
                ['key' => 'activities.news',     'label' => 'Actualités',       'url' => '/messages?category=amicale',                     'icon' => 'newspaper',  'permission' => 44],
                ['key' => 'activities.geomap',   'label' => 'Géolocalisation',  'url' => '/legacy/gmaps_event.php',                        'icon' => 'map',        'permission' => 76],
            ],
        ],

        // ── Garde ──────────────────────────────────────────────────────────
        [
            'code' => 'duty',
            'label' => 'Garde',
            'icon' => 'clipboard-list',
            'items' => [
                ['key' => 'duty.today',   'label' => 'Garde du jour',       'url' => '/duty/today',   'icon' => 'calendar-day',   'permission' => 61],
                ['key' => 'duty.board',   'label' => 'Garde de la semaine', 'url' => '/duty/weekly',   'icon' => 'calendar-week',  'permission' => 61],
                ['key' => 'duty.on-call', 'label' => 'Garde du mois',       'url' => '/duty/monthly', 'icon' => 'calendar-alt',   'permission' => 52],
            ],
        ],

        // ── Planning ───────────────────────────────────────────────────────
        [
            'code' => 'planning',
            'label' => 'Planning',
            'icon' => 'calendar-check',
            'items' => [
                ['key' => 'planning.calendar',     'label' => 'Calendrier',      'url' => '/calendar',         'icon' => 'calendar',                           'feature_any' => ['disponibilites', 'activités']],
                ['key' => 'planning.availability', 'label' => 'Disponibilités',  'url' => '/availability',     'icon' => 'check-square',   'permission' => 38, 'feature' => 'disponibilites'],
                ['key' => 'planning.timesheet',    'label' => 'Horaires',        'url' => '/timesheet',        'icon' => 'clock',                              'feature' => 'horaires'],
                ['key' => 'planning.absences',     'label' => 'Absences',        'url' => '/unavailability',   'icon' => 'user-times',     'permission' => 11, 'feature' => 'disponibilites'],
                ['key' => 'planning.rest',         'label' => 'Repos',           'url' => '/rest',             'icon' => 'bed',            'permission' => 11, 'feature' => 'disponibilites'],
                ['key' => 'planning.replacement',  'label' => 'Remplacements',   'url' => '/replacements',     'icon' => 'exchange-alt',   'permission' => 41, 'feature' => 'remplacements'],
            ],
        ],

        // ── Clients ────────────────────────────────────────────────────────
        [
            'code' => 'companies',
            'label' => 'Clients',
            'icon' => 'user-circle',
            'permission' => 29,
            'feature' => 'client',
            'items' => [
                ['key' => 'companies.list', 'label' => 'Liste', 'url' => '/companies', 'icon' => 'list', 'permission' => 29, 'feature' => 'client'],
            ],
        ],

        // ── Logistique ─────────────────────────────────────────────────────
        [
            'code' => 'logistics',
            'label' => 'Logistique',
            'icon' => 'truck',
            'items' => [
                ['key' => 'logistics.vehicles',   'label' => 'Véhicules',    'url' => '/vehicles',    'icon' => 'truck',    'permission' => 42, 'feature' => 'vehicules'],
                ['key' => 'logistics.equipment',  'label' => 'Matériels',    'url' => '/equipment',   'icon' => 'toolbox',  'permission' => 42, 'feature' => 'materiel'],
                ['key' => 'logistics.consumables', 'label' => 'Consommables', 'url' => '/consumables', 'icon' => 'boxes',    'permission' => 42, 'feature' => 'consommables'],
            ],
        ],

        // ── Communication ──────────────────────────────────────────────────
        [
            'code' => 'comm',
            'label' => 'Communication',
            'icon' => 'envelope',
            'items' => [
                ['key' => 'comm.chat',    'label' => 'Chat',    'url' => '/legacy/chat.php',           'icon' => 'comments',  'permission' => 51],
                ['key' => 'comm.alert',   'label' => 'Alerte',  'url' => '/legacy/alerte_create.php',  'icon' => 'bell',      'permission' => 43],
                ['key' => 'comm.message', 'label' => 'Message', 'url' => '/legacy/mail_create.php',    'icon' => 'paper-plane', 'permission' => 43],
                ['key' => 'comm.sms_history', 'label' => 'Historique SMS', 'url' => '/communication/sms-history', 'icon' => 'sms', 'permission' => 23],
            ],
        ],

        // ── Documents ──────────────────────────────────────────────────────
        [
            'code' => 'docs',
            'label' => 'Documents',
            'icon' => 'folder-open',
            'permission' => 44,
            'items' => [
                ['key' => 'docs.library', 'label' => 'Bibliothèque', 'url' => '/documents', 'icon' => 'book', 'permission' => 44],
                ['key' => 'docs.photos',  'label' => 'Album photos',  'url' => '/photos',  'icon' => 'images', 'permission' => 44],
            ],
        ],

        // ── Organisation ───────────────────────────────────────────────────
        [
            'code' => 'org',
            'label' => 'Organisation',
            'icon' => 'sitemap',
            'permission' => 52,
            'items' => [
                ['key' => 'org.sections',  'label' => 'Sections',      'url' => '/organization/sections',    'icon' => 'layer-group',  'permission' => 52, 'feature' => 'multi_site'],
                ['key' => 'org.org-chart', 'label' => 'Organigramme',  'url' => '/organization/org-chart',   'icon' => 'project-diagram', 'permission' => 52, 'feature' => 'multi_site'],
                ['key' => 'org.map',       'label' => 'Cartographie',  'url' => '/organization/map',         'icon' => 'map',          'permission' => 27, 'feature' => 'multi_site'],
            ],
        ],

        // ── Statistiques ───────────────────────────────────────────────────
        [
            'code' => 'stats',
            'label' => 'Statistiques',
            'icon' => 'chart-bar',
            'permission' => 27,
            'items' => [
                ['key' => 'stats.dashboard', 'label' => 'Tableau de bord',      'url' => '/statistics/dashboard',          'icon' => 'chart-line',  'permission' => 27],
                ['key' => 'stats.report',    'label' => 'Bilan annuel',          'url' => '/statistics/annual-report',       'icon' => 'chart-pie',   'permission' => 27],
                ['key' => 'stats.reporting', 'label' => 'Reporting',             'url' => '/legacy/export.php',               'icon' => 'file-export', 'permission' => 27],
                ['key' => 'stats.dues',      'label' => 'Cotisations',           'url' => '/statistics/financial-report',    'icon' => 'coins',       'permission' => 53],
            ],
        ],

        // ── Administration ─────────────────────────────────────────────────
        [
            'code' => 'admin',
            'label' => 'Administration',
            'icon' => 'cog',
            'permission' => 5,
            'items' => [
                ['key' => 'admin.configuration', 'label' => 'Options',         'url' => '/admin/settings',     'icon' => 'sliders-h',    'permission' => 14],
                ['key' => 'admin.features',      'label' => 'Fonctionnalités', 'url' => '/admin/features',     'icon' => 'toggle-on',    'permission' => 14],
                ['key' => 'admin.references',    'label' => 'Paramétrage',     'url' => '/admin/references',   'icon' => 'wrench',       'permission' => 5],
                ['key' => 'admin.notifications', 'label' => 'Notifications',   'url' => '/admin/notifications', 'icon' => 'bell',        'permission' => 14],
                null,
                ['key' => 'admin.security',    'label' => 'Sécurité',    'url' => '/admin/security',    'icon' => 'shield-alt', 'permission' => 14],
                ['key' => 'admin.permissions', 'label' => 'Permissions', 'url' => '/admin/permissions', 'icon' => 'id-badge',   'permission' => 9],
                ['key' => 'admin.monitoring',  'label' => 'Monitoring',  'url' => '/admin/monitoring',  'icon' => 'history',    'permission' => 49],
                ['key' => 'account.connected-users', 'label' => 'Connexions', 'url' => '/admin/connected-users',    'icon' => 'users',        'permission' => 20],
                null,
                ['key' => 'admin.backup',      'label' => 'Sauvegarde',      'url' => '/admin/backup',      'icon' => 'database',     'permission' => 14],
                ['key' => 'admin.maintenance', 'label' => 'Maintenance',     'url' => '/admin/maintenance', 'icon' => 'tools',        'permission' => 14],
                null,
                ['key' => 'admin.plugins',     'label' => 'Plugins',         'url' => '/admin/plugins',     'icon' => 'puzzle-piece', 'permission' => 14],
            ],
        ],

    ],
];
