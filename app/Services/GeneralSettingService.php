<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Typed reader for the general-purpose settings of the legacy `configuration`
 * table (Administration ▸ Configuration) that are consumed across the app:
 * timezone, currency, phone formatting, mandatory profile photo, maintenance
 * mode, telemetry opt-in and automatic database optimisation.
 *
 * Unlike the security/observability settings these rows ship with the install
 * SQL, so there is nothing to seed. Reads are memoised per request and fall
 * back to typed defaults; some consumers run at boot (timezone) or on every
 * request (maintenance mode), so a missing table must never break a page.
 */
class GeneralSettingService
{
    /** @var array<string,int|string> */
    private const DEFAULTS = [
        'version' => '',
        'application_title' => '',
        'cisurl' => '',
        'timezone' => 'Europe/Paris',
        'default_money' => 'Euro',
        'default_money_symbol' => '€',
        'phone_prefix' => '+33',
        'min_numbers_in_phone' => 10,
        'photo_obligatoire' => 0,
        'maintenance_mode' => 0,
        'maintenance_text' => '',
        'ameliorations' => 0,
        'auto_optimize' => 0,
        'dispo_periodes' => 4,
    ];

    /** @var array<string,string>|null */
    private ?array $cache = null;

    /**
     * Installed application version (configuration row 1 — the SSOT, stamped
     * by the release migrations; '' → keep the APP_VERSION default).
     */
    public function appVersion(): string
    {
        return $this->string('version');
    }

    /** Stored application name ('' → keep the APP_NAME default). */
    public function appName(): string
    {
        return $this->string('application_title');
    }

    /** Stored public site URL ('' → keep the APP_URL default). */
    public function siteUrl(): string
    {
        return $this->string('cisurl');
    }

    public function timezone(): string
    {
        return $this->string('timezone');
    }

    public function currencyName(): string
    {
        return $this->string('default_money');
    }

    public function currencySymbol(): string
    {
        return $this->string('default_money_symbol');
    }

    public function phonePrefix(): string
    {
        return $this->string('phone_prefix');
    }

    public function phoneMinDigits(): int
    {
        return $this->int('min_numbers_in_phone');
    }

    public function photoRequired(): bool
    {
        return $this->bool('photo_obligatoire');
    }

    public function maintenanceEnabled(): bool
    {
        return $this->bool('maintenance_mode');
    }

    public function maintenanceText(): string
    {
        return $this->string('maintenance_text');
    }

    public function telemetryEnabled(): bool
    {
        return $this->bool('ameliorations');
    }

    public function autoOptimizeEnabled(): bool
    {
        return $this->bool('auto_optimize');
    }

    /** Raw stored value, or the default when no row exists. */
    public function get(string $name): string
    {
        $map = $this->map();

        return $map[$name] ?? (string) (self::DEFAULTS[$name] ?? '');
    }

    public function bool(string $name): bool
    {
        return $this->get($name) === '1';
    }

    public function int(string $name): int
    {
        return (int) $this->get($name);
    }

    public function string(string $name): string
    {
        return $this->get($name);
    }

    /** @return array<string,string> */
    private function map(): array
    {
        if ($this->cache === null) {
            try {
                $this->cache = DB::table('configuration')
                    ->whereIn('NAME', array_keys(self::DEFAULTS))
                    ->pluck('VALUE', 'NAME')
                    ->map(fn ($v) => (string) $v)
                    ->all();
            } catch (Throwable) {
                // Consulted at boot (timezone) and on every request (maintenance
                // mode); a missing table or transient DB problem must fall back
                // to the defaults, never 500.
                $this->cache = [];
            }
        }

        return $this->cache;
    }
}
