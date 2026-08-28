<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConsumableController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAclController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DuesController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\DutyTypeController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventFeedController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\GeolocationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Legacy\LegacyBridgeController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MyPermissionsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PluginController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReplacementController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShortcutController;
use App\Http\Controllers\SmsHistoryController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TotpController;
use App\Http\Controllers\UnavailabilityController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes are migrated here from the legacy flat-file layout incrementally.
| Each domain area (auth, personnel, events, …) gets its own route group
| once the corresponding controller and Blade views exist.
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Public health-check for uptime probes / load balancers. JSON, no auth — it
// reports service availability only, never data. (Laravel's bare /up stays.)
Route::get('/health', HealthController::class)->name('health');

// Public RSS 2.0 feed of upcoming events (legacy rss.php). No auth — it exposes
// only events explicitly flagged visible outside the organisation, never member
// data. Optional filters: ?section={S_ID} (+ descendants), ?types=FOR,INT.
Route::get('/feeds/events', EventFeedController::class)->name('feed.events');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt')->middleware('throttle:auth');

    // Compatibility for middleware redirects generated from /index.php/* requests.
    Route::get('/index.php/login', [AuthController::class, 'showLogin'])->name('login.compat');
    Route::post('/index.php/login', [AuthController::class, 'login'])->name('login.attempt.compat')->middleware('throttle:auth');

    // Legacy scripts sometimes point to login.php explicitly.
    Route::get('/index.php/login.php', fn () => redirect('/login'));
    Route::get('/login.php', fn () => redirect('/login'));

    // TOTP challenge — shown after correct password when 2FA is enabled.
    // Intentionally inside 'guest' so already-authenticated users are bounced.
    Route::get('/totp/challenge', [TotpController::class, 'showChallenge'])->name('totp.challenge');
    Route::post('/totp/challenge', [TotpController::class, 'verifyChallenge'])->name('totp.challenge.verify');

    // Self-service password reset (no auth required)
    Route::get('/password/reset', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/password/reset', [PasswordResetController::class, 'sendResetToken'])->name('password.email')->middleware('throttle:auth');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'confirmToken'])->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/layout', [DashboardController::class, 'saveLayout'])->name('dashboard.layout.save');
    Route::get('/events', [EventController::class, 'index'])->name('event.index')->middleware('permission:0');
    // List exports (static segments before the {code} wildcard).
    Route::get('/events/export/xls', [EventController::class, 'exportListXls'])->name('event.export.xls')->middleware('permission:0');
    Route::get('/events/export/csv', [EventController::class, 'exportListCsv'])->name('event.export.csv')->middleware('permission:0');
    Route::get('/events/create', [EventController::class, 'create'])->name('event.create')->middleware('permission:15');
    Route::post('/events', [EventController::class, 'store'])->name('event.store')->middleware('permission:15');
    Route::get('/events/{code}', [EventController::class, 'show'])->name('event.show')->middleware('permission:0');
    Route::get('/events/{code}/edit', [EventController::class, 'edit'])->name('event.edit')->middleware('permission:15');
    Route::put('/events/{code}', [EventController::class, 'update'])->name('event.update')->middleware('permission:15');
    Route::delete('/events/{code}', [EventController::class, 'destroy'])->name('event.destroy')->middleware('permission:19');
    Route::post('/events/{code}/duplicate', [EventController::class, 'duplicate'])->name('event.duplicate')->middleware('permission:15');
    // Participant management — inscription, fonction, équipe (permission 10 = inscrire)
    Route::post('/events/{code}/participants', [EventController::class, 'participantStore'])->name('event.participant.store')->middleware('permission:10');
    Route::patch('/events/{code}/participants/{pid}', [EventController::class, 'participantUpdate'])->name('event.participant.update')->middleware('permission:10');
    Route::patch('/events/{code}/participants/{pid}/team', [EventController::class, 'participantTeam'])->name('event.participant.team')->middleware('permission:10');
    Route::delete('/events/{code}/participants/{pid}', [EventController::class, 'participantDestroy'])->name('event.participant.destroy')->middleware('permission:10');
    // Équipes CRUD — teams within an event (permission 15 = gérer activité)
    Route::post('/events/{code}/teams', [EventController::class, 'teamStore'])->name('event.team.store')->middleware('permission:15');
    Route::put('/events/{code}/teams/{ee}', [EventController::class, 'teamUpdate'])->name('event.team.update')->middleware('permission:15');
    Route::delete('/events/{code}/teams/{ee}', [EventController::class, 'teamDestroy'])->name('event.team.destroy')->middleware('permission:15');
    Route::post('/events/{code}/teams/{ee}/participants', [EventController::class, 'teamAddParticipant'])->name('event.team.participant.add')->middleware('permission:10');
    Route::post('/events/{code}/teams/{ee}/equipment', [EventController::class, 'teamAddEquipment'])->name('event.team.equipment.add')->middleware('permission:15');
    // Renforts — attach/detach reinforcement sub-events (permission 15)
    Route::post('/events/{code}/reinforcements', [EventController::class, 'reinforcementAttach'])->name('event.reinforcement.attach')->middleware('permission:15');
    Route::delete('/events/{code}/reinforcements/{reinforcement}', [EventController::class, 'reinforcementDetach'])->name('event.reinforcement.detach')->middleware('permission:15');
    // Véhicules — attach/detach vehicles (permission 15)
    Route::post('/events/{code}/vehicles', [EventController::class, 'vehicleAttach'])->name('event.vehicle.attach')->middleware('permission:15');
    Route::delete('/events/{code}/vehicles/{vehicle}', [EventController::class, 'vehicleDetach'])->name('event.vehicle.detach')->middleware('permission:15');
    // Matériel — assign/update-qty/detach equipment (permission 15)
    Route::post('/events/{code}/equipment', [EventController::class, 'equipmentAttach'])->name('event.equipment.attach')->middleware('permission:15');
    Route::patch('/events/{code}/equipment/{ma}', [EventController::class, 'equipmentUpdateQty'])->name('event.equipment.qty')->middleware('permission:15');
    Route::delete('/events/{code}/equipment/{ma}', [EventController::class, 'equipmentDetach'])->name('event.equipment.detach')->middleware('permission:15');
    // Exports
    Route::get('/events/{code}/export/participants', [EventController::class, 'exportParticipants'])->name('event.export.participants')->middleware('permission:0');
    Route::get('/events/{code}/export/vehicles', [EventController::class, 'exportVehicles'])->name('event.export.vehicles')->middleware('permission:0');
    Route::get('/events/{code}/ical', [EventController::class, 'exportIcal'])->name('event.ical')->middleware('permission:0');
    Route::get('/events/{code}/trombinoscope', [EventController::class, 'trombinoscope'])->name('event.trombinoscope')->middleware('permission:0');
    // Event report — printable summary (replaces evenement_rapport.php); chef-or-15 gated in-controller
    Route::get('/events/{code}/report', [EventController::class, 'report'])->name('event.report')->middleware('permission:0');
    // Required positions (postes requis)
    Route::post('/events/{code}/required-positions', [EventController::class, 'storeRequiredPosition'])->name('event.required-position.store')->middleware('permission:15');
    Route::patch('/events/{code}/required-positions/{psId}', [EventController::class, 'updateRequiredPosition'])->name('event.required-position.update')->middleware('permission:15');
    Route::delete('/events/{code}/required-positions/{psId}', [EventController::class, 'destroyRequiredPosition'])->name('event.required-position.destroy')->middleware('permission:15');
    // Reinforcement request (demande de renfort)
    Route::get('/events/{code}/renfort-request', [EventController::class, 'reinforcementRequest'])->name('event.renfort-request')->middleware('permission:0');
    Route::post('/events/{code}/renfort-request', [EventController::class, 'reinforcementRequestUpdate'])->name('event.renfort-request.update')->middleware('permission:15');
    // Event options — option groups, options, dropdown choices (permission 15)
    Route::post('/events/{code}/option-groups', [EventController::class, 'optionGroupStore'])->name('event.option-group.store')->middleware('permission:15');
    Route::patch('/events/{code}/option-groups/{groupId}', [EventController::class, 'optionGroupUpdate'])->name('event.option-group.update')->middleware('permission:15');
    Route::delete('/events/{code}/option-groups/{groupId}', [EventController::class, 'optionGroupDestroy'])->name('event.option-group.destroy')->middleware('permission:15');
    Route::post('/events/{code}/options', [EventController::class, 'optionStore'])->name('event.option.store')->middleware('permission:15');
    Route::patch('/events/{code}/options/{optionId}', [EventController::class, 'optionUpdate'])->name('event.option.update')->middleware('permission:15');
    Route::delete('/events/{code}/options/{optionId}', [EventController::class, 'optionDestroy'])->name('event.option.destroy')->middleware('permission:15');
    Route::post('/events/{code}/options/{optionId}/choices', [EventController::class, 'dropdownChoiceStore'])->name('event.option.choice.store')->middleware('permission:15');
    Route::delete('/events/{code}/options/{optionId}/choices/{choiceId}', [EventController::class, 'dropdownChoiceDestroy'])->name('event.option.choice.destroy')->middleware('permission:15');
    // Participant choices (perm 15, event chef, or self — checked in controller)
    Route::post('/events/{code}/participant-choices/{pid}', [EventController::class, 'participantChoicesSave'])->name('event.participant-choices.save');
    // Main courante (incident log)
    Route::post('/events/{code}/log', [EventController::class, 'logStore'])->name('event.log.store')->middleware('permission:15');
    Route::patch('/events/{code}/log/{logId}', [EventController::class, 'logUpdate'])->name('event.log.update')->middleware('permission:15');
    Route::delete('/events/{code}/log/{logId}', [EventController::class, 'logDestroy'])->name('event.log.destroy')->middleware('permission:15');
    // Guard views — day / week / month, all reading the same astreinte data.
    // Route names stay stable; /duty redirects to the weekly (main) view.
    Route::redirect('/duty', '/duty/weekly');
    Route::get('/duty/today', [DutyController::class, 'today'])->name('duty.today')->middleware('permission:61');
    Route::get('/duty/weekly', [DutyController::class, 'index'])->name('duty.index')->middleware('permission:61');
    Route::get('/duty/monthly', [DutyController::class, 'onCall'])->name('duty.on-call')->middleware('permission:52');
    Route::get('/duty/monthly/export/xls', [DutyController::class, 'exportOnCallXls'])->name('duty.on-call.export.xls')->middleware('permission:52');
    Route::get('/duty/monthly/export/csv', [DutyController::class, 'exportOnCallCsv'])->name('duty.on-call.export.csv')->middleware('permission:52');
    Route::get('/garde/types', [DutyTypeController::class, 'index'])->name('duty.types.index')->middleware('permission:5');
    Route::post('/garde/types', [DutyTypeController::class, 'store'])->name('duty.types.store')->middleware('permission:5');
    Route::patch('/garde/types/{id}', [DutyTypeController::class, 'update'])->name('duty.types.update')->middleware('permission:5');
    Route::delete('/garde/types/{id}', [DutyTypeController::class, 'destroy'])->name('duty.types.destroy')->middleware('permission:5');
    Route::get('/unavailability', [UnavailabilityController::class, 'index'])->name('unavailability.index')->middleware('permission:11');
    Route::get('/replacements', [ReplacementController::class, 'index'])->name('replacement.index')->middleware(['permission:0', 'feature:remplacements']);
    Route::get('/replacements/export/xls', [ReplacementController::class, 'exportXls'])->name('replacement.export.xls')->middleware(['permission:0', 'feature:remplacements']);
    Route::get('/replacements/export/csv', [ReplacementController::class, 'exportCsv'])->name('replacement.export.csv')->middleware(['permission:0', 'feature:remplacements']);
    Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index')->middleware(['permission:38', 'feature:disponibilites']);
    Route::post('/availability/toggle', [AvailabilityController::class, 'toggle'])->name('availability.toggle')->middleware(['permission:38', 'feature:disponibilites']);
    Route::get('/availability/print', [AvailabilityController::class, 'print'])->name('availability.print')->middleware(['permission:38', 'feature:disponibilites']);
    Route::get('/admin/monitoring', [AdminController::class, 'monitoring'])->name('admin.monitoring')->middleware('permission:49');
    // Diagnostics — deliberately trigger an issue to verify the observability
    // pipeline (error tracking, error/performance canaux) end to end.
    Route::post('/admin/monitoring/simulate', [AdminController::class, 'simulateIssue'])->name('admin.monitoring.simulate')->middleware('permission:49');
    // Backup & restore
    Route::get('/admin/backup', [BackupController::class, 'index'])->name('admin.backup')->middleware('permission:14');
    Route::post('/admin/backup', [BackupController::class, 'store'])->name('admin.backup.store')->middleware('permission:14');
    Route::get('/admin/backup/{filename}/download', [BackupController::class, 'download'])->name('admin.backup.download')->middleware('permission:14');
    Route::delete('/admin/backup/{filename}', [BackupController::class, 'destroy'])->name('admin.backup.destroy')->middleware('permission:14');
    Route::post('/admin/backup/restore', [BackupController::class, 'restore'])->name('admin.backup.restore')->middleware('permission:14');
    Route::patch('/admin/backup/settings', [BackupController::class, 'updateSettings'])->name('admin.backup.settings')->middleware('permission:14');
    // Maintenance (upgrade.php superseded by artisan migrate)
    Route::get('/admin/maintenance', [MaintenanceController::class, 'index'])->name('admin.maintenance')->middleware('permission:14');
    Route::post('/admin/maintenance/clear-caches', [MaintenanceController::class, 'clearCaches'])->name('admin.maintenance.clear-caches')->middleware('permission:14');
    Route::post('/admin/maintenance/optimize-db', [MaintenanceController::class, 'optimizeDatabase'])->name('admin.maintenance.optimize-db')->middleware('permission:14');
    Route::post('/admin/maintenance/prune-logs', [MaintenanceController::class, 'pruneLogs'])->name('admin.maintenance.prune-logs')->middleware('permission:14');
    Route::get('/admin/security', [AdminController::class, 'security'])->name('admin.security')->middleware('permission:14');
    Route::get('/admin/security/politique/create', [AdminController::class, 'policyCreate'])->name('admin.policy.create')->middleware('permission:14');
    Route::post('/admin/security/politique', [AdminController::class, 'policyStore'])->name('admin.policy.store')->middleware('permission:14');
    Route::get('/admin/security/politique/{id}/edit', [AdminController::class, 'policyEdit'])->name('admin.policy.edit')->middleware('permission:14');
    Route::patch('/admin/security/politique/{id}', [AdminController::class, 'policyUpdate'])->name('admin.policy.update')->middleware('permission:14');
    Route::delete('/admin/security/politique/{id}', [AdminController::class, 'policyDestroy'])->name('admin.policy.destroy')->middleware('permission:14');
    Route::post('/admin/security/ldap', [AdminController::class, 'ldapStore'])->name('admin.ldap.store')->middleware('permission:14');
    Route::get('/admin/security/ldap/{id}/edit', [AdminController::class, 'ldapEdit'])->name('admin.ldap.edit')->middleware('permission:14');
    Route::patch('/admin/security/ldap/{id}', [AdminController::class, 'ldapUpdate'])->name('admin.ldap.update')->middleware('permission:14');
    Route::delete('/admin/security/ldap/{id}', [AdminController::class, 'ldapDestroy'])->name('admin.ldap.destroy')->middleware('permission:14');
    Route::post('/admin/security/ldap/{id}/test', [AdminController::class, 'ldapTest'])->name('admin.ldap.test')->middleware('permission:14');
    Route::post('/admin/security/ldap/{id}/attr', [AdminController::class, 'ldapAttrStore'])->name('admin.ldap.attr.store')->middleware('permission:14');
    Route::delete('/admin/security/ldap/{id}/attr/{attrId}', [AdminController::class, 'ldapAttrDestroy'])->name('admin.ldap.attr.destroy')->middleware('permission:14');
    Route::post('/admin/security/ldap/{id}/ou', [AdminController::class, 'ldapOuStore'])->name('admin.ldap.ou.store')->middleware('permission:14');
    Route::delete('/admin/security/ldap/{id}/ou/{ruleId}', [AdminController::class, 'ldapOuDestroy'])->name('admin.ldap.ou.destroy')->middleware('permission:14');
    Route::post('/admin/security/network/test-hibp', [AdminController::class, 'testHibp'])->name('admin.network.test-hibp')->middleware('permission:14');
    // Security hardening (Renforcement) — CSP/HSTS, auth throttling, upload safety.
    // Toggle/value rows save via the generic admin.settings.save endpoint (per-row,
    // keyed by configuration ID), exactly like the other security tabs.
    Route::post('/admin/security/hardening/test-clamav', [AdminController::class, 'testClamav'])->name('admin.security.test-clamav')->middleware('permission:14');
    Route::get('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings')->middleware('permission:14');
    Route::get('/admin/notifications', [AdminController::class, 'notifications'])->name('admin.notifications')->middleware('permission:14');
    Route::patch('/admin/settings/{id}', [AdminController::class, 'saveSetting'])->name('admin.settings.save')->middleware('permission:14');
    Route::post('/admin/settings/{id}/upload', [AdminController::class, 'uploadSetting'])->name('admin.settings.upload')->middleware('permission:14');
    Route::delete('/admin/settings/{id}/file', [AdminController::class, 'deleteSetting'])->name('admin.settings.delete-file')->middleware('permission:14');

    // ── First-run setup wizard + organisation-type management (perm 14) ────────
    Route::get('/setup', [SetupController::class, 'show'])->name('setup.show')->middleware('permission:14');
    Route::post('/setup', [SetupController::class, 'store'])->name('setup.store')->middleware('permission:14');
    Route::get('/admin/organisation-type', [SetupController::class, 'editOrgType'])->name('setup.org-type')->middleware('permission:14');
    Route::patch('/admin/organisation-type', [SetupController::class, 'updateOrgType'])->name('setup.org-type.update')->middleware('permission:14');
    Route::post('/admin/organisation-type/reset-roles', [SetupController::class, 'resetRoles'])->name('setup.org-type.reset-roles')->middleware('permission:14');
    Route::post('/admin/organisation-type/delete-custom-roles', [SetupController::class, 'deleteCustomRoles'])->name('setup.org-type.delete-custom-roles')->middleware('permission:14');

    // ── Fonctionnalités & Modules — unified feature registry (ob_feature) ──────
    Route::get('/admin/features', [FeatureController::class, 'index'])->name('admin.features')->middleware('permission:14');
    Route::patch('/admin/features/{feature}', [FeatureController::class, 'toggle'])->name('admin.features.toggle')->middleware('permission:14');

    // ── Plugins — community plugin marketplace (WIP placeholder) ──────────────
    Route::middleware('permission:14')->group(function () {
        Route::get('/admin/plugins', [PluginController::class, 'index'])->name('admin.plugins');
        Route::post('/admin/plugins/{slug}/install', [PluginController::class, 'install'])->name('admin.plugins.install')->where('slug', '[a-z0-9-]+');
        Route::post('/admin/plugins/{slug}/enable', [PluginController::class, 'enable'])->name('admin.plugins.enable')->where('slug', '[a-z0-9-]+');
        Route::post('/admin/plugins/{slug}/disable', [PluginController::class, 'disable'])->name('admin.plugins.disable')->where('slug', '[a-z0-9-]+');
        Route::delete('/admin/plugins/{slug}', [PluginController::class, 'uninstall'])->name('admin.plugins.uninstall')->where('slug', '[a-z0-9-]+');
        Route::post('/admin/plugins/registries', [PluginController::class, 'storeRegistry'])->name('admin.plugins.registries.store');
        Route::post('/admin/plugins/registries/{registry}/toggle', [PluginController::class, 'toggleRegistry'])->name('admin.plugins.registries.toggle');
        Route::delete('/admin/plugins/registries/{registry}', [PluginController::class, 'destroyRegistry'])->name('admin.plugins.registries.destroy');
    });

    // ── Paramétrage — reference table CRUD ────────────────────────────────────
    Route::get('/admin/references', [ReferenceController::class, 'index'])->name('admin.references')->middleware('permission:5');
    // Type événement
    Route::get('/admin/references/event-type', [ReferenceController::class, 'eventTypeIndex'])->name('admin.references.event-type')->middleware('permission:5');
    Route::post('/admin/references/event-type', [ReferenceController::class, 'eventTypeStore'])->name('admin.references.event-type.store')->middleware('permission:5');
    Route::patch('/admin/references/event-type/{code}', [ReferenceController::class, 'eventTypeUpdate'])->name('admin.references.event-type.update')->middleware('permission:5');
    Route::delete('/admin/references/event-type/{code}', [ReferenceController::class, 'eventTypeDestroy'])->name('admin.references.event-type.destroy')->middleware('permission:5');
    // Type participation
    Route::get('/admin/references/participation-type', [ReferenceController::class, 'participationTypeIndex'])->name('admin.references.participation-type')->middleware('permission:5');
    Route::post('/admin/references/participation-type', [ReferenceController::class, 'participationTypeStore'])->name('admin.references.participation-type.store')->middleware('permission:5');
    Route::patch('/admin/references/participation-type/{id}', [ReferenceController::class, 'participationTypeUpdate'])->name('admin.references.participation-type.update')->middleware('permission:5');
    Route::delete('/admin/references/participation-type/{id}', [ReferenceController::class, 'participationTypeDestroy'])->name('admin.references.participation-type.destroy')->middleware('permission:5');
    // Type matériel
    Route::get('/admin/references/equipment-type', [ReferenceController::class, 'equipmentTypeIndex'])->name('admin.references.equipment-type')->middleware('permission:5');
    Route::post('/admin/references/equipment-type', [ReferenceController::class, 'equipmentTypeStore'])->name('admin.references.equipment-type.store')->middleware('permission:5');
    Route::patch('/admin/references/equipment-type/{id}', [ReferenceController::class, 'equipmentTypeUpdate'])->name('admin.references.equipment-type.update')->middleware('permission:5');
    Route::delete('/admin/references/equipment-type/{id}', [ReferenceController::class, 'equipmentTypeDestroy'])->name('admin.references.equipment-type.destroy')->middleware('permission:5');
    // Catégorie matériel
    Route::get('/admin/references/equipment-category', [ReferenceController::class, 'equipmentCategoryIndex'])->name('admin.references.equipment-category')->middleware('permission:5');
    Route::post('/admin/references/equipment-category', [ReferenceController::class, 'equipmentCategoryStore'])->name('admin.references.equipment-category.store')->middleware('permission:5');
    Route::patch('/admin/references/equipment-category/{usage}', [ReferenceController::class, 'equipmentCategoryUpdate'])->name('admin.references.equipment-category.update')->middleware('permission:5');
    Route::delete('/admin/references/equipment-category/{usage}', [ReferenceController::class, 'equipmentCategoryDestroy'])->name('admin.references.equipment-category.destroy')->middleware('permission:5');
    // Type consommable
    Route::get('/admin/references/consumable-type', [ReferenceController::class, 'consumableTypeIndex'])->name('admin.references.consumable-type')->middleware('permission:5');
    Route::post('/admin/references/consumable-type', [ReferenceController::class, 'consumableTypeStore'])->name('admin.references.consumable-type.store')->middleware('permission:5');
    Route::patch('/admin/references/consumable-type/{id}', [ReferenceController::class, 'consumableTypeUpdate'])->name('admin.references.consumable-type.update')->middleware('permission:5');
    Route::delete('/admin/references/consumable-type/{id}', [ReferenceController::class, 'consumableTypeDestroy'])->name('admin.references.consumable-type.destroy')->middleware('permission:5');
    // Catégorie consommable
    Route::get('/admin/references/consumable-category', [ReferenceController::class, 'consumableCategoryIndex'])->name('admin.references.consumable-category')->middleware('permission:5');
    Route::post('/admin/references/consumable-category', [ReferenceController::class, 'consumableCategoryStore'])->name('admin.references.consumable-category.store')->middleware('permission:5');
    Route::patch('/admin/references/consumable-category/{code}', [ReferenceController::class, 'consumableCategoryUpdate'])->name('admin.references.consumable-category.update')->middleware('permission:5');
    Route::delete('/admin/references/consumable-category/{code}', [ReferenceController::class, 'consumableCategoryDestroy'])->name('admin.references.consumable-category.destroy')->middleware('permission:5');
    // Type véhicule
    Route::get('/admin/references/vehicle-type', [ReferenceController::class, 'vehicleTypeIndex'])->name('admin.references.vehicle-type')->middleware('permission:5');
    Route::post('/admin/references/vehicle-type', [ReferenceController::class, 'vehicleTypeStore'])->name('admin.references.vehicle-type.store')->middleware('permission:5');
    Route::patch('/admin/references/vehicle-type/{code}', [ReferenceController::class, 'vehicleTypeUpdate'])->name('admin.references.vehicle-type.update')->middleware('permission:5');
    Route::delete('/admin/references/vehicle-type/{code}', [ReferenceController::class, 'vehicleTypeDestroy'])->name('admin.references.vehicle-type.destroy')->middleware('permission:5');
    // Permissions — full ACL: section ceilings + group/role grants + per-user overrides
    Route::get('/admin/permissions', [PermissionController::class, 'index'])->name('admin.permissions')->middleware('permission:9');
    Route::post('/admin/permissions/grant', [PermissionController::class, 'setGrant'])->name('admin.permissions.grant.set')->middleware('permission:9');
    Route::post('/admin/permissions/override', [PermissionController::class, 'setUserGrant'])->name('admin.permissions.user.set')->middleware('permission:9');
    Route::post('/admin/permissions/ceiling', [PermissionController::class, 'toggleCeiling'])->name('admin.permissions.ceiling.toggle')->middleware('permission:9');
    Route::post('/admin/permissions/group', [PermissionController::class, 'groupStore'])->name('admin.permissions.group.store')->middleware('permission:9');
    Route::patch('/admin/permissions/group/{gpId}', [PermissionController::class, 'groupUpdate'])->name('admin.permissions.group.update')->middleware('permission:9');
    Route::delete('/admin/permissions/group/{gpId}', [PermissionController::class, 'groupDestroy'])->name('admin.permissions.group.destroy')->middleware('permission:9');
    Route::get('/admin/permissions/group/{gpId}/export', [PermissionController::class, 'exportGroup'])->name('admin.permissions.group.export')->middleware('permission:9');
    // Active section / role context switch (navbar)
    Route::get('/context/section', [ContextController::class, 'section'])->name('context.section')->middleware('permission:0');
    Route::get('/context/role', [ContextController::class, 'role'])->name('context.role')->middleware('permission:0');
    // User-facing "Mes droits" (effective permissions preview)
    Route::get('/my-permissions', [MyPermissionsController::class, 'index'])->name('my-permissions')->middleware('permission:0');
    // Vehicle function types
    Route::get('/admin/references/vehicle-function', [ReferenceController::class, 'vehicleFunctionIndex'])->name('admin.references.vehicle-function')->middleware('permission:5');
    Route::post('/admin/references/vehicle-function', [ReferenceController::class, 'vehicleFunctionStore'])->name('admin.references.vehicle-function.store')->middleware('permission:5');
    Route::patch('/admin/references/vehicle-function/{id}', [ReferenceController::class, 'vehicleFunctionUpdate'])->name('admin.references.vehicle-function.update')->middleware('permission:5');
    Route::delete('/admin/references/vehicle-function/{id}', [ReferenceController::class, 'vehicleFunctionDestroy'])->name('admin.references.vehicle-function.destroy')->middleware('permission:5');
    // Grade categories
    Route::get('/admin/references/grade-category', [ReferenceController::class, 'gradeCategoryIndex'])->name('admin.references.grade-category')->middleware('permission:5', 'feature:grades');
    Route::post('/admin/references/grade-category', [ReferenceController::class, 'gradeCategoryStore'])->name('admin.references.grade-category.store')->middleware('permission:5', 'feature:grades');
    Route::patch('/admin/references/grade-category/{code}', [ReferenceController::class, 'gradeCategoryUpdate'])->name('admin.references.grade-category.update')->middleware('permission:5', 'feature:grades');
    Route::delete('/admin/references/grade-category/{code}', [ReferenceController::class, 'gradeCategoryDestroy'])->name('admin.references.grade-category.destroy')->middleware('permission:5', 'feature:grades');
    // Grade icons
    Route::get('/admin/references/grade', [ReferenceController::class, 'gradeIndex'])->name('admin.references.grade')->middleware('permission:5', 'feature:grades');
    Route::post('/admin/references/grade', [ReferenceController::class, 'gradeStore'])->name('admin.references.grade.store')->middleware('permission:5', 'feature:grades');
    Route::post('/admin/references/grade/reorder', [ReferenceController::class, 'gradeReorder'])->name('admin.references.grade.reorder')->middleware('permission:5', 'feature:grades');
    Route::patch('/admin/references/grade/{grade}', [ReferenceController::class, 'gradeUpdate'])->name('admin.references.grade.update')->middleware('permission:5', 'feature:grades');
    Route::delete('/admin/references/grade/{grade}', [ReferenceController::class, 'gradeDestroy'])->name('admin.references.grade.destroy')->middleware('permission:5', 'feature:grades');
    Route::post('/admin/references/grade/{grade}/icon', [ReferenceController::class, 'gradeIconUpload'])->name('admin.references.grade.icon.upload')->middleware('permission:5', 'feature:grades');
    Route::delete('/admin/references/grade/{grade}/icon', [ReferenceController::class, 'gradeIconDestroy'])->name('admin.references.grade.icon.destroy')->middleware('permission:5', 'feature:grades');
    // Equipe (competence group) and Poste (position/competence definition)
    Route::get('/admin/references/team', [ReferenceController::class, 'teamIndex'])->name('admin.references.team')->middleware('permission:18');
    Route::post('/admin/references/team', [ReferenceController::class, 'teamStore'])->name('admin.references.team.store')->middleware('permission:18');
    Route::patch('/admin/references/team/{id}', [ReferenceController::class, 'teamUpdate'])->name('admin.references.team.update')->middleware('permission:18');
    Route::delete('/admin/references/team/{id}', [ReferenceController::class, 'teamDestroy'])->name('admin.references.team.destroy')->middleware('permission:18');
    Route::get('/admin/references/position', [ReferenceController::class, 'positionIndex'])->name('admin.references.position')->middleware('permission:18');
    Route::post('/admin/references/position', [ReferenceController::class, 'positionStore'])->name('admin.references.position.store')->middleware('permission:18');
    Route::patch('/admin/references/position/{id}', [ReferenceController::class, 'positionUpdate'])->name('admin.references.position.update')->middleware('permission:18');
    Route::delete('/admin/references/position/{id}', [ReferenceController::class, 'positionDestroy'])->name('admin.references.position.destroy')->middleware('permission:18');
    // Contact types (contact handles referential)
    Route::get('/admin/references/contact-type', [ReferenceController::class, 'contactTypeIndex'])->name('admin.references.contact-type')->middleware('permission:5');
    Route::post('/admin/references/contact-type', [ReferenceController::class, 'contactTypeStore'])->name('admin.references.contact-type.store')->middleware('permission:5');
    Route::patch('/admin/references/contact-type/{id}', [ReferenceController::class, 'contactTypeUpdate'])->name('admin.references.contact-type.update')->middleware('permission:5');
    Route::delete('/admin/references/contact-type/{id}', [ReferenceController::class, 'contactTypeDestroy'])->name('admin.references.contact-type.destroy')->middleware('permission:5');
    // Competence hierarchy (poste_hierarchie)
    Route::get('/admin/references/competence-hierarchy', [ReferenceController::class, 'competenceHierarchyIndex'])->name('admin.references.competence-hierarchy')->middleware('permission:18');
    Route::post('/admin/references/competence-hierarchy', [ReferenceController::class, 'competenceHierarchyStore'])->name('admin.references.competence-hierarchy.store')->middleware('permission:18');
    Route::post('/admin/references/competence-hierarchy/{code}/members', [ReferenceController::class, 'competenceHierarchyMemberAdd'])->name('admin.references.competence-hierarchy.member.add')->middleware('permission:18');
    Route::delete('/admin/references/competence-hierarchy/{code}/members/{psId}', [ReferenceController::class, 'competenceHierarchyMemberRemove'])->name('admin.references.competence-hierarchy.member.remove')->middleware('permission:18');
    Route::patch('/admin/references/competence-hierarchy/{code}', [ReferenceController::class, 'competenceHierarchyUpdate'])->name('admin.references.competence-hierarchy.update')->middleware('permission:18');
    Route::delete('/admin/references/competence-hierarchy/{code}', [ReferenceController::class, 'competenceHierarchyDestroy'])->name('admin.references.competence-hierarchy.destroy')->middleware('permission:18');
    Route::middleware('feature:cotisations')->group(function () {
        Route::get('/dues', [DuesController::class, 'index'])->name('dues.index')->middleware('permission:53');
        Route::post('/dues', [DuesController::class, 'batchSave'])->name('dues.save')->middleware('permission:53');
        Route::get('/dues/export', [DuesController::class, 'export'])->name('dues.export')->middleware('permission:53');
    });
    // Calendrier — shared calendar (activities + absences), multi-person for managers.
    Route::get('/calendar', [PlanningController::class, 'index'])->name('planning.index')->middleware('permission:0');
    Route::get('/calendar/events', [PlanningController::class, 'events'])->name('planning.events')->middleware('permission:0');
    Route::get('/calendar/print', [PlanningController::class, 'print'])->name('planning.print')->middleware('permission:0');
    Route::middleware('feature:vehicules')->group(function () {
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicle.index')->middleware('permission:42');
        // List exports (static segments before the {vehicle} wildcard).
        Route::get('/vehicles/export/xls', [VehicleController::class, 'exportXls'])->name('vehicle.export.xls')->middleware('permission:42');
        Route::get('/vehicles/export/csv', [VehicleController::class, 'exportCsv'])->name('vehicle.export.csv')->middleware('permission:42');
        Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicle.create')->middleware('permission:17');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicle.store')->middleware('permission:17');
        Route::get('/vehicles/{vehicule}', [VehicleController::class, 'show'])->name('vehicle.show')->middleware('permission:42');
        Route::get('/vehicles/{vehicule}/edit', [VehicleController::class, 'edit'])->name('vehicle.edit')->middleware('permission:17');
        Route::put('/vehicles/{vehicule}', [VehicleController::class, 'update'])->name('vehicle.update')->middleware('permission:17');
        Route::delete('/vehicles/{vehicule}', [VehicleController::class, 'destroy'])->name('vehicle.destroy')->middleware('permission:19');
        Route::post('/vehicles/{vehicule}/equipment', [VehicleController::class, 'equipmentAttach'])->name('vehicle.equipment.attach')->middleware('permission:17');
        Route::delete('/vehicles/{vehicule}/equipment/{maId}', [VehicleController::class, 'equipmentDetach'])->name('vehicle.equipment.detach')->middleware('permission:17');
    });
    Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index')->middleware(['permission:42', 'feature:materiel']);
    Route::get('/equipment/export/xls', [EquipmentController::class, 'exportXls'])->name('equipment.export.xls')->middleware(['permission:42', 'feature:materiel']);
    Route::get('/equipment/export/csv', [EquipmentController::class, 'exportCsv'])->name('equipment.export.csv')->middleware(['permission:42', 'feature:materiel']);
    Route::get('/consumables', [ConsumableController::class, 'index'])->name('consumable.index')->middleware(['permission:42', 'feature:consommables']);
    Route::get('/consumables/export/xls', [ConsumableController::class, 'exportXls'])->name('consumable.export.xls')->middleware(['permission:42', 'feature:consommables']);
    Route::get('/consumables/export/csv', [ConsumableController::class, 'exportCsv'])->name('consumable.export.csv')->middleware(['permission:42', 'feature:consommables']);
    Route::get('/documents', [DocumentController::class, 'index'])->name('document.index')->middleware('permission:44');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('document.download')->middleware('permission:44');
    Route::get('/documents/export/{format}', [DocumentController::class, 'export'])->name('document.export')->middleware('permission:44');
    // Per-object ACL ("Partager") — entry needs library access (44); the SHARE
    // right (or permission 47) is enforced in the controller.
    Route::get('/documents/acl/{type}/{id}', [DocumentAclController::class, 'show'])->name('document.acl')->middleware('permission:44')->whereIn('type', ['folder', 'document']);
    Route::post('/documents/acl/{type}/{id}', [DocumentAclController::class, 'store'])->name('document.acl.store')->middleware('permission:44')->whereIn('type', ['folder', 'document']);
    Route::delete('/documents/acl/ace/{ace}', [DocumentAclController::class, 'destroy'])->name('document.acl.destroy')->middleware('permission:44');
    // Document type & security configuration — permission 47
    Route::get('/documents/types', [DocumentTypeController::class, 'index'])->name('document.types')->middleware('permission:47');
    Route::post('/documents/types', [DocumentTypeController::class, 'store'])->name('document.types.store')->middleware('permission:47');
    Route::patch('/documents/types/{type}', [DocumentTypeController::class, 'update'])->name('document.types.update')->middleware('permission:47');
    Route::delete('/documents/types/{type}', [DocumentTypeController::class, 'destroy'])->name('document.types.destroy')->middleware('permission:47');
    // Folder management (replaces save_folder.php / upd_folder.php). Entry needs
    // library access (44); the per-object ACL (or permission 47) is enforced in
    // the controller, so an ACL grant can authorise a non-manager.
    Route::post('/documents/folders', [DocumentController::class, 'folderStore'])->name('document.folder.store')->middleware('permission:44');
    Route::patch('/documents/folders/{folder}', [DocumentController::class, 'folderUpdate'])->name('document.folder.update')->middleware('permission:44');
    Route::delete('/documents/folders/{folder}', [DocumentController::class, 'folderDestroy'])->name('document.folder.destroy')->middleware('permission:44');
    // Document upload / edit / delete (replaces upd_document.php / save_documents.php).
    Route::post('/documents', [DocumentController::class, 'store'])->name('document.store')->middleware('permission:44');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('document.update')->middleware('permission:44');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('document.destroy')->middleware('permission:44');
    Route::get('/messages', [MessageController::class, 'index'])->name('message.index')->middleware('permission:44');
    // Photo album (replaces legacy SPGM gallery). Entry: permission 44; upload/delete: 47.
    Route::get('/photos', [PhotoController::class, 'index'])->name('photo.index')->middleware('permission:44');
    Route::get('/photos/{album}', [PhotoController::class, 'albumShow'])->name('photo.album')->middleware('permission:44');
    Route::post('/photos', [PhotoController::class, 'albumStore'])->name('photo.album.store')->middleware('permission:47');
    Route::patch('/photos/{album}', [PhotoController::class, 'albumUpdate'])->name('photo.album.update')->middleware('permission:47');
    Route::delete('/photos/{album}', [PhotoController::class, 'albumDestroy'])->name('photo.album.destroy')->middleware('permission:47');
    Route::post('/photos/{album}/upload', [PhotoController::class, 'photoStore'])->name('photo.store')->middleware('permission:47');
    Route::post('/photos/auto-albums', [PhotoController::class, 'autoAlbumCreate'])->name('photo.auto-albums')->middleware('permission:47');
    Route::get('/photos/{album}/pick-docs', [PhotoController::class, 'pickDocuments'])->name('photo.pick-docs')->middleware('permission:47');
    Route::post('/photos/{album}/from-docs', [PhotoController::class, 'storeFromDocuments'])->name('photo.from-docs')->middleware('permission:47');
    Route::patch('/photos/{album}/cover', [PhotoController::class, 'setCover'])->name('photo.cover')->middleware('permission:47');
    Route::get('/photo/{photo}/file', [PhotoController::class, 'photoServe'])->name('photo.serve')->middleware('permission:44');
    Route::delete('/photos/{album}/photos', [PhotoController::class, 'photoBulkDestroy'])->name('photo.bulk-destroy')->middleware('permission:47');
    Route::patch('/photos/{album}/reorder', [PhotoController::class, 'reorder'])->name('photo.reorder')->middleware('permission:47');
    Route::get('/photo/{photo}/download', [PhotoController::class, 'photoDownload'])->name('photo.download')->middleware('permission:44');
    Route::get('/photos/{album}/download', [PhotoController::class, 'albumDownload'])->name('photo.album.download')->middleware('permission:44');
    Route::patch('/photo/{photo}', [PhotoController::class, 'photoUpdate'])->name('photo.update')->middleware('permission:47');
    Route::delete('/photo/{photo}', [PhotoController::class, 'photoDestroy'])->name('photo.destroy')->middleware('permission:47');
    Route::get('/organization', fn () => redirect()->route('organization.org-chart'))->name('organization.index');
    Route::get('/organization/org-chart', [OrganizationController::class, 'index'])->name('organization.org-chart')->middleware('permission:52');
    // Sections — native list + CRUD (replaces departement.php)
    Route::get('/organization/sections', [OrganizationController::class, 'sections'])->name('organization.sections')->middleware('permission:52');
    Route::get('/organization/sections/create', [OrganizationController::class, 'createSection'])->name('organization.sections.create')->middleware('permission:52');
    Route::post('/organization/sections', [OrganizationController::class, 'storeSection'])->name('organization.sections.store')->middleware('permission:52');
    Route::get('/organization/sections/{section}', [OrganizationController::class, 'showSection'])->name('organization.sections.show')->middleware('permission:52');
    Route::get('/organization/sections/{section}/edit', [OrganizationController::class, 'editSection'])->name('organization.sections.edit')->middleware('permission:52');
    Route::patch('/organization/sections/{section}', [OrganizationController::class, 'updateSection'])->name('organization.sections.update')->middleware('permission:52');
    Route::delete('/organization/sections/{section}', [OrganizationController::class, 'destroySection'])->name('organization.sections.destroy')->middleware('permission:52');
    Route::patch('/organization/sections/{section}/deactivate', [OrganizationController::class, 'deactivateSection'])->name('organization.sections.deactivate')->middleware('permission:52');
    Route::patch('/organization/sections/{section}/reactivate', [OrganizationController::class, 'reactivateSection'])->name('organization.sections.reactivate')->middleware('permission:52');
    Route::post('/organization/sections/{section}/interdictions', [OrganizationController::class, 'storeInterdiction'])->name('organization.sections.interdictions.store')->middleware('permission:52');
    Route::patch('/organization/sections/{section}/interdictions/{interdiction}', [OrganizationController::class, 'updateInterdiction'])->name('organization.sections.interdictions.update')->middleware('permission:52');
    Route::delete('/organization/sections/{section}/interdictions/{interdiction}', [OrganizationController::class, 'destroyInterdiction'])->name('organization.sections.interdictions.destroy')->middleware('permission:52');
    Route::patch('/organization/sections/{section}/personalisation', [OrganizationController::class, 'updatePersonalisation'])->name('organization.sections.personalisation')->middleware('permission:52');
    // PDF assets — permission:0 because any member generating a livret/carte needs them
    Route::get('/organization/sections/{section}/letterhead', [OrganizationController::class, 'sectionLetterhead'])->name('organization.sections.letterhead')->middleware('permission:0');
    Route::delete('/organization/sections/{section}/letterhead', [OrganizationController::class, 'resetLetterhead'])->name('organization.sections.letterhead.reset')->middleware('permission:52');
    Route::get('/organization/sections/{section}/badge', [OrganizationController::class, 'sectionBadge'])->name('organization.sections.badge')->middleware('permission:0');
    Route::delete('/organization/sections/{section}/badge', [OrganizationController::class, 'resetBadge'])->name('organization.sections.badge.reset')->middleware('permission:52');
    Route::patch('/organization/sections/{section}/rib', [OrganizationController::class, 'updateRib'])->name('organization.sections.rib')->middleware('permission:52');
    Route::get('/organization/sections/{section}/rib/download', [OrganizationController::class, 'downloadRib'])->name('organization.sections.rib.download')->middleware('permission:52');
    Route::put('/organization/sections/{section}/agrement/{code}', [OrganizationController::class, 'upsertAgrement'])->name('organization.sections.agrement.upsert')->middleware('permission:52');
    Route::delete('/organization/sections/{section}/agrement/{code}', [OrganizationController::class, 'destroyAgrement'])->name('organization.sections.agrement.destroy')->middleware('permission:52');
    // Cartographie — native Leaflet map (replaces jvectormap.php)
    Route::get('/organization/map', [OrganizationController::class, 'map'])->name('organization.map')->middleware(['permission:27', 'feature:carte']);
    Route::get('/statistics', fn () => redirect()->route('statistics.dashboard'))->name('statistics.index');
    Route::get('/statistics/dashboard', [StatisticsController::class, 'index'])->name('statistics.dashboard')->middleware('permission:27');
    Route::get('/statistics/annual-report', fn () => redirect()->route('statistics.annual-report.overview'))->name('statistics.annual-report');
    Route::get('/statistics/annual-report/overview', [StatisticsController::class, 'reportOverview'])->name('statistics.annual-report.overview')->middleware('permission:27');
    Route::get('/statistics/annual-report/activities', [StatisticsController::class, 'reportActivities'])->name('statistics.annual-report.activities')->middleware('permission:27');
    Route::get('/statistics/annual-report/training', [StatisticsController::class, 'reportTraining'])->name('statistics.annual-report.training')->middleware('permission:27');
    // Financial report — "Cotisations par section" (replaces report_cotisations.php)
    Route::get('/statistics/financial-report', [FinancialReportController::class, 'index'])->name('statistics.financial-report')->middleware('permission:53');
    Route::get('/statistics/financial-report/export/xls', [FinancialReportController::class, 'exportXls'])->name('statistics.financial-report.export.xls')->middleware('permission:53');
    Route::get('/statistics/financial-report/export/csv', [FinancialReportController::class, 'exportCsv'])->name('statistics.financial-report.export.csv')->middleware('permission:53');
    // SMS history — "Historique SMS" (replaces histo_sms.php)
    Route::get('/communication/sms-history', [SmsHistoryController::class, 'index'])->name('communication.sms-history')->middleware('permission:23');
    Route::get('personnel/{personnel}/photo', [PersonnelController::class, 'photo'])
        ->name('personnel.photo')
        ->middleware('permission:0');
    Route::get('personnel/grade/{grade}', [PersonnelController::class, 'gradeImage'])
        ->name('personnel.grade-image')
        ->where('grade', '[A-Z0-9]+')
        ->middleware('permission:0');
    // Personnel list exports (static segments before resource wildcard)
    Route::get('personnel/export/xls', [PersonnelController::class, 'exportXls'])
        ->name('personnel.export.xls')->middleware('permission:0');
    Route::get('personnel/export/csv', [PersonnelController::class, 'exportCsv'])
        ->name('personnel.export.csv')->middleware('permission:0');
    Route::post('personnel/export/emails', [PersonnelController::class, 'exportEmailList'])
        ->name('personnel.export.emails')->middleware('permission:0');
    Route::post('personnel/export/contacts', [PersonnelController::class, 'exportContactsCsv'])
        ->name('personnel.export.contacts')->middleware('permission:0');
    Route::get('personnel/create', [PersonnelController::class, 'create'])
        ->name('personnel.create')->middleware('permission:1');
    Route::post('personnel', [PersonnelController::class, 'store'])
        ->name('personnel.store')->middleware('permission:1');
    Route::resource('personnel', PersonnelController::class)
        ->only(['index', 'show', 'edit', 'update'])
        ->middleware('permission:0');
    // Qualifications (competences) CRUD — nested under personnel
    Route::post('personnel/{personnel}/qualifications', [PersonnelController::class, 'storeQualification'])
        ->name('personnel.qualification.store')->middleware('permission:0');
    Route::patch('personnel/{personnel}/qualifications/{psId}', [PersonnelController::class, 'updateQualification'])
        ->name('personnel.qualification.update')->middleware('permission:0');
    Route::delete('personnel/{personnel}/qualifications/{psId}', [PersonnelController::class, 'destroyQualification'])
        ->name('personnel.qualification.destroy')->middleware('permission:0');
    // Contact handles (social/communication identifiers)
    Route::post('personnel/{personnel}/contacts', [PersonnelController::class, 'updateContacts'])
        ->name('personnel.contacts.update')->middleware('permission:0');
    Route::post('personnel/{personnel}/salarie', [PersonnelController::class, 'updateSalarie'])
        ->name('personnel.salarie.update')->middleware('permission:2');
    // Duess CRUD — nested under personnel
    Route::post('personnel/{personnel}/dues', [PersonnelController::class, 'storeDues'])
        ->name('personnel.dues.store')->middleware('permission:0');
    Route::patch('personnel/{personnel}/dues/{pcId}', [PersonnelController::class, 'updateDues'])
        ->name('personnel.dues.update')->middleware('permission:0');
    Route::delete('personnel/{personnel}/dues/{pcId}', [PersonnelController::class, 'destroyDues'])
        ->name('personnel.dues.destroy')->middleware('permission:0');
    // Trainings (formations) CRUD — nested under personnel
    Route::post('personnel/{personnel}/trainings', [PersonnelController::class, 'storeTraining'])
        ->name('personnel.training.store')->middleware('permission:0');
    Route::patch('personnel/{personnel}/trainings/{pfId}', [PersonnelController::class, 'updateTraining'])
        ->name('personnel.training.update')->middleware('permission:0');
    Route::delete('personnel/{personnel}/trainings/{pfId}', [PersonnelController::class, 'destroyTraining'])
        ->name('personnel.training.destroy')->middleware('permission:0');
    // Per-member exports
    Route::get('personnel/{personnel}/export/formations', [PersonnelController::class, 'exportFormationsXls'])
        ->name('personnel.export.formations')->middleware('permission:0');
    Route::get('personnel/{personnel}/vcard', [PersonnelController::class, 'exportVcard'])
        ->name('personnel.vcard')->middleware('permission:0');
    Route::get('personnel/{personnel}/qr-code', [PersonnelController::class, 'qrCode'])
        ->name('personnel.qr-code')->middleware('permission:0');
    Route::get('personnel/{personnel}/export/meetings', [PersonnelController::class, 'exportMeetingsXls'])
        ->name('personnel.export.meetings')->middleware('permission:0');
    Route::get('personnel/{personnel}/logbook-data', [PersonnelController::class, 'logbookData'])
        ->name('personnel.logbook')->middleware('permission:0');
    Route::get('personnel/{personnel}/card-data', [PersonnelController::class, 'cardData'])
        ->name('personnel.card')->middleware('permission:0');
    // Homonym merge
    Route::get('personnel/{personnel}/merge/{doublon}', [PersonnelController::class, 'homonymMerge'])
        ->name('personnel.merge.show')->middleware('permission:2');
    Route::post('personnel/{personnel}/merge/{doublon}', [PersonnelController::class, 'doMerge'])
        ->name('personnel.merge')->middleware('permission:2');
    // Tenues / uniform dotation
    Route::get('personnel/{personnel}/tenues', [PersonnelController::class, 'tenues'])
        ->name('personnel.tenues')->middleware('permission:0');
    Route::post('personnel/{personnel}/tenues', [PersonnelController::class, 'tenuesUpdate'])
        ->name('personnel.tenues.update')->middleware('permission:0');
    Route::get('personnel/{personnel}/preferences', [PersonnelController::class, 'preferences'])
        ->name('personnel.preferences')->middleware('permission:0');
    Route::patch('personnel/{personnel}/preferences', [PersonnelController::class, 'preferencesUpdate'])
        ->name('personnel.preferences.update')->middleware('permission:0');
    // Géolocalisation
    Route::get('/geolocation', [GeolocationController::class, 'index'])
        ->name('geolocation.index')->middleware(['permission:0', 'feature:geolocalize_enabled']);
    Route::post('personnel/{personnel}/gps', [GeolocationController::class, 'updateGps'])
        ->name('personnel.gps.update')->middleware('permission:0');
    Route::get('/personnel/photos', [PersonnelController::class, 'trombinoscope'])->name('personnel.photo-directory')->middleware('permission:0');
    Route::get('/qualifications', [PersonnelController::class, 'qualifications'])->name('personnel.qualifications')->middleware(['permission:56', 'feature:competences']);
    Route::get('/qualifications/export/xls', [PersonnelController::class, 'exportQualificationsXls'])->name('personnel.qualifications.export.xls')->middleware(['permission:56', 'feature:competences']);
    Route::get('/qualifications/export/csv', [PersonnelController::class, 'exportQualificationsCsv'])->name('personnel.qualifications.export.csv')->middleware(['permission:56', 'feature:competences']);
    Route::get('/companies', [CompanyController::class, 'index'])->name('company.index')->middleware(['permission:29', 'feature:client']);
    Route::get('/companies/export/xls', [CompanyController::class, 'exportXls'])->name('company.export.xls')->middleware(['permission:29', 'feature:client']);
    Route::get('/companies/export/csv', [CompanyController::class, 'exportCsv'])->name('company.export.csv')->middleware(['permission:29', 'feature:client']);
    Route::get('/legacy', fn () => redirect()->route('dashboard'))->name('dashboard.legacy');
    Route::get('/about', function () {
        // TODO: Migrate code
        return redirect('/legacy/about.php');
    })->name('about');
    Route::post('/shortcuts/toggle', [ShortcutController::class, 'toggle'])->name('shortcuts.toggle');

    // Account — combined authentication page (password + 2FA)
    Route::get('/account/authentification', [AccountController::class, 'showAuth'])->name('account.auth');
    Route::post('/account/authentification', [AccountController::class, 'changePassword'])->name('account.password.update');
    Route::post('/account/authentification/2fa/confirm', [TotpController::class, 'confirmSetup'])->name('totp.confirm');
    Route::post('/account/authentification/2fa/codes', [TotpController::class, 'regenerateCodes'])->name('totp.codes.regenerate');
    Route::delete('/account/authentification/2fa', [TotpController::class, 'disable'])->name('totp.disable');
    // Legacy redirects
    Route::redirect('/account/password', '/account/authentification')->name('account.password');
    Route::redirect('/account/2fa', '/account/authentification?tab=2fa')->name('totp.setup');

    // Account — charter acceptance
    Route::get('/account/charter', [AccountController::class, 'showCharter'])->name('account.charter');
    Route::post('/account/charter/accept', [AccountController::class, 'acceptCharter'])->name('account.charter.accept');
    Route::post('/account/charter/reject', [AccountController::class, 'rejectCharter'])->name('account.charter.reject');
    Route::post('/account/charter/reset', [AccountController::class, 'resetCharter'])->name('account.charter.reset');

    // Admin — charter editor (permission 14), nested under security
    Route::get('/admin/security/charter', [AccountController::class, 'showEditCharter'])->name('admin.security.charter')->middleware('permission:14');
    Route::post('/admin/security/charter', [AccountController::class, 'saveCharter'])->name('admin.security.charter.save')->middleware('permission:14');

    // Connected users (permission 20 = Audit)
    Route::get('/admin/connected-users', [AccountController::class, 'connectedUsers'])->name('account.connected-users')->middleware('permission:20');

    // Send credentials — admin action, nested under personnel
    Route::get('personnel/{personnel}/send-credentials', [AccountController::class, 'showSendCredentials'])->name('personnel.send-credentials.show');
    Route::post('personnel/{personnel}/send-credentials', [AccountController::class, 'sendCredentials'])->name('personnel.send-credentials');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::match(['GET', 'POST'], '/index.php/logout', [AuthController::class, 'logout'])->name('logout.compat');

    Route::get('/{assetType}/{assetPath}', [LegacyBridgeController::class, 'asset'])
        ->where('assetType', 'css|js|images|webfonts')
        ->where('assetPath', '.*')
        ->name('legacy_bridge.asset');

    Route::get('/index.php/{assetType}/{assetPath}', [LegacyBridgeController::class, 'asset'])
        ->where('assetType', 'css|js|images|webfonts')
        ->where('assetPath', '.*')
        ->name('legacy_bridge.asset.compat.pathinfo');

    Route::get('/legacy/{assetType}/{assetPath}', [LegacyBridgeController::class, 'asset'])
        ->where('assetType', 'css|js|images|webfonts')
        ->where('assetPath', '.*')
        ->name('legacy_bridge.asset.legacy');

    // Legacy links frequently resolve as /index.php/{file}. Keep compatibility.
    Route::match(['GET', 'POST'], '/index.php/{legacyFile}', [LegacyBridgeController::class, 'show'])
        ->where('legacyFile', '.*')
        ->name('legacy_bridge.compat.pathinfo');
});

if (file_exists(__DIR__.'/web_legacy_bridge.php')) {
    require __DIR__.'/web_legacy_bridge.php';
}

/*
|--------------------------------------------------------------------------
| Error-page preview (local only)
|--------------------------------------------------------------------------
|
| Renders the custom error/status views directly so they can be inspected
| during development without forcing a real failure. Guarded to the local
| non-production environments so it never ships to production.
|
| Visit /_preview/error/404, /403, /401, /419, /429, /500, /503.
|
*/
if (! app()->isProduction()) {
    Route::get('/_preview/error/{code}', function (string $code) {
        abort_unless(view()->exists("errors.$code"), 404);

        return response()->view("errors.$code", [
            // 503 reads $exception->getMessage(); supply a dummy for the others too.
            'exception' => new HttpException((int) $code),
        ], (int) $code);
    })->whereNumber('code')->name('preview.error');
}

/*
|--------------------------------------------------------------------------
| Fallback (unmatched URLs)
|--------------------------------------------------------------------------
|
| Routing a non-existent URL throws the 404 before the 'web' middleware
| group runs, so the session/auth context is missing and the error page
| can't tell a logged-in user from a guest. Catching it here pushes the
| request through the web stack first, so the 404 view keeps the app shell
| for authenticated users.
|
*/
Route::fallback(fn () => abort(404));
