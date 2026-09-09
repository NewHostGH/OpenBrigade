<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserShortcut;
use Illuminate\Support\Facades\Request;

class NavigationService
{
    public function __construct(private readonly FeatureService $features) {}

    /**
     * Full navigation tree for the sidebar.
     * Each item includes `key`, `pinned` (bool), and `active` (bool).
     */
    public function getNavGroups(?User $user): array
    {
        $currentPath = '/'.ltrim(Request::path(), '/');
        $pinnedKeys = $user ? UserShortcut::keysForUser($user->P_ID) : [];
        $pinnedSet = array_flip($pinnedKeys);
        $groups = [];

        // The single "active" item is the one whose path is the LONGEST prefix
        // of the current path — so on /duty/on-call only "Astreintes" lights up,
        // not the parent /duty items in another group.
        $activePath = $this->activePath($currentPath);

        foreach (config('navigation.top', []) as $group) {
            if (isset($group['permission']) && ! $this->can($user, $group['permission'])) {
                continue;
            }

            if (! $this->featureOk($group)) {
                continue;
            }

            $items = $this->resolveItems($group['items'], $user, $currentPath, $pinnedSet, $activePath);

            if (empty($items)) {
                continue;
            }

            $active = collect($items)
                ->filter(fn ($item) => $item !== null)
                ->contains(fn ($item) => $item['active']);

            $groups[] = [
                'code' => $group['code'],
                'label' => $group['label'],
                'icon' => $group['icon'],
                'active' => $active,
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * Ordered list of pinned shortcuts for the navbar siglet strip.
     */
    public function getPinnedShortcuts(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $pinnedKeys = UserShortcut::keysForUser($user->P_ID);
        if (empty($pinnedKeys)) {
            return [];
        }

        // Build a flat map of all items by key
        $itemMap = $this->buildItemMap($user);

        $shortcuts = [];
        foreach ($pinnedKeys as $key) {
            if (isset($itemMap[$key])) {
                $shortcuts[] = $itemMap[$key];
            }
        }

        return $shortcuts;
    }

    /**
     * Toggle a shortcut pin for a user. Returns the new pinned state.
     */
    public function toggleShortcut(User $user, string $key): bool
    {
        $itemMap = $this->buildItemMap($user);
        if (! isset($itemMap[$key])) {
            return false;
        }

        $existing = UserShortcut::where('user_id', $user->P_ID)
            ->where('item_key', $key)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $nextOrder = UserShortcut::where('user_id', $user->P_ID)->max('sort_order') + 1;
        UserShortcut::create([
            'user_id' => $user->P_ID,
            'item_key' => $key,
            'sort_order' => $nextOrder,
        ]);

        return true;
    }

    private function buildItemMap(?User $user): array
    {
        $map = [];
        foreach (config('navigation.top', []) as $group) {
            if (isset($group['permission']) && ! $this->can($user, $group['permission'])) {
                continue;
            }
            foreach ($group['items'] as $item) {
                if ($item === null) {
                    continue;
                }
                if (isset($item['permission']) && ! $this->can($user, $item['permission'])) {
                    continue;
                }
                if (! $this->featureOk($item)) {
                    continue;
                }
                $map[$item['key']] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'url' => $item['url'],
                    'icon' => $item['icon'] ?? '',
                ];
            }
        }

        return $map;
    }

    /**
     * The longest nav item path that is a boundary-aware prefix of the current
     * path. Only the item(s) carrying exactly this path are marked active, so a
     * parent path (e.g. /duty) never lights up while on a child (/duty/on-call).
     * Returns '' when nothing matches.
     */
    private function activePath(string $currentPath): string
    {
        $best = '';

        foreach (config('navigation.top', []) as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if ($item === null || ! isset($item['url'])) {
                    continue;
                }

                $itemPath = parse_url($item['url'], PHP_URL_PATH) ?? '';
                if ($this->pathMatches($currentPath, $itemPath) && strlen($itemPath) > strlen($best)) {
                    $best = $itemPath;
                }
            }
        }

        return $best;
    }

    /** True when $itemPath is $currentPath itself or a path-segment ancestor of it. */
    private function pathMatches(string $currentPath, string $itemPath): bool
    {
        if ($itemPath === '') {
            return false;
        }

        return $currentPath === $itemPath
            || str_starts_with($currentPath, rtrim($itemPath, '/').'/');
    }

    private function resolveItems(array $rawItems, ?User $user, string $currentPath, array $pinnedSet, string $activePath): array
    {
        $resolved = [];

        foreach ($rawItems as $item) {
            if ($item === null) {
                $resolved[] = null;

                continue;
            }

            if (isset($item['permission']) && ! $this->can($user, $item['permission'])) {
                continue;
            }

            if (! $this->featureOk($item)) {
                continue;
            }

            $itemPath = parse_url($item['url'], PHP_URL_PATH) ?? '';
            $active = $itemPath !== '' && $itemPath === $activePath;

            $resolved[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'] ?? '',
                'active' => $active,
                'pinned' => isset($pinnedSet[$item['key']]),
            ];
        }

        return $this->stripOrphanedDividers($resolved);
    }

    private function stripOrphanedDividers(array $items): array
    {
        $result = [];
        $prevWasDivider = true;

        foreach ($items as $item) {
            if ($item === null) {
                if (! $prevWasDivider) {
                    $result[] = null;
                    $prevWasDivider = true;
                }
            } else {
                $result[] = $item;
                $prevWasDivider = false;
            }
        }

        if (! empty($result) && end($result) === null) {
            array_pop($result);
        }

        return $result;
    }

    private function can(?User $user, int $permission): bool
    {
        return $user !== null && $user->hasPermission($permission);
    }

    /**
     * Feature gate for a nav node. `feature` requires that flag (AND); optional
     * `feature_any` requires at least one of a list of flags (OR). Both apply
     * when both are present.
     *
     * @param  array<string, mixed>  $node
     */
    private function featureOk(array $node): bool
    {
        if (isset($node['feature']) && ! $this->features->isEnabled($node['feature'])) {
            return false;
        }

        if (isset($node['feature_any'])) {
            foreach ((array) $node['feature_any'] as $flag) {
                if ($this->features->isEnabled($flag)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }
}
