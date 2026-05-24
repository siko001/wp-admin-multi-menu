<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Menu_Repository
{
    public const OPTION_NAME = 'fanm_nested_menus';

    public function all(): array
    {
        $menus = get_option(self::OPTION_NAME, []);

        if (!is_array($menus) || empty($menus)) {
            $menus = $this->defaults();
            $this->save($menus);
        }

        return $menus;
    }

    public function save(array $menus): void
    {
        update_option(self::OPTION_NAME, $this->sanitize_many($menus));
    }

    public function install_defaults(): void
    {
        update_option(self::OPTION_NAME, $this->defaults());
    }

    public function defaults(): array
    {
        return [
            'menu_1' => [
                'id' => 'menu_1',
                'title' => 'Dashboard',
                'slug' => 'fanm-dashboard',
                'cap' => 'manage_options',
                'callback' => 'fanm_dashboard_cb',
                'parent' => 0,
                'icon' => 'dashicons-dashboard',
            ],
            'menu_2' => [
                'id' => 'menu_2',
                'title' => 'Settings',
                'slug' => 'fanm-settings',
                'cap' => 'manage_options',
                'callback' => 'fanm_settings_cb',
                'parent' => 'menu_1',
                'icon' => 'dashicons-admin-settings',
            ],
            'menu_3' => [
                'id' => 'menu_3',
                'title' => 'Advanced',
                'slug' => 'fanm-advanced',
                'cap' => 'manage_options',
                'callback' => 'fanm_advanced_cb',
                'parent' => 'menu_2',
                'icon' => 'dashicons-admin-tools',
            ],
        ];
    }

    public function demo(): array
    {
        return [
            'dashboard' => [
                'id' => 'dashboard',
                'title' => 'Dashboard',
                'slug' => 'fanm-dashboard',
                'cap' => 'read',
                'callback' => 'fanm_dashboard_cb',
                'parent' => 0,
                'icon' => 'dashicons-dashboard',
            ],
            'analytics' => [
                'id' => 'analytics',
                'title' => 'Analytics',
                'slug' => 'fanm-analytics',
                'cap' => 'manage_options',
                'callback' => 'fanm_analytics_cb',
                'parent' => 'dashboard',
                'icon' => 'dashicons-chart-bar',
            ],
            'reports' => [
                'id' => 'reports',
                'title' => 'Reports',
                'slug' => 'fanm-reports',
                'cap' => 'manage_options',
                'callback' => 'fanm_reports_cb',
                'parent' => 'analytics',
                'icon' => 'dashicons-media-spreadsheet',
            ],
            'users' => [
                'id' => 'users',
                'title' => 'User Management',
                'slug' => 'fanm-users',
                'cap' => 'manage_options',
                'callback' => 'fanm_users_cb',
                'parent' => 0,
                'icon' => 'dashicons-users',
            ],
            'settings' => [
                'id' => 'settings',
                'title' => 'Settings',
                'slug' => 'fanm-settings',
                'cap' => 'manage_options',
                'callback' => 'fanm_settings_cb',
                'parent' => 0,
                'icon' => 'dashicons-admin-settings',
            ],
        ];
    }

    public function create(array $overrides = []): array
    {
        $id = 'menu_' . uniqid('', false) . '_' . wp_rand(1000, 9999);

        return $this->sanitize(array_merge([
            'id' => $id,
            'title' => 'New Menu',
            'slug' => 'new-menu-' . time(),
            'cap' => 'manage_options',
            'callback' => '__return_empty_string',
            'parent' => 0,
            'icon' => 'dashicons-admin-generic',
        ], $overrides), $id);
    }

    public function normalize_parent($parent)
    {
        if ($parent === 0 || $parent === '0' || $parent === '' || $parent === null) {
            return 0;
        }

        return sanitize_key($parent);
    }

    public function depth(array $menus, string $id): int
    {
        $depth = 0;
        $seen = [];

        while (isset($menus[$id]) && !empty($menus[$id]['parent']) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = (string) $menus[$id]['parent'];
            $depth++;
        }

        return $depth;
    }

    public function top_ancestor_slug(array $menus, string $id): string
    {
        if (!isset($menus[$id])) {
            return 'admin.php';
        }

        $current = $menus[$id];
        $seen = [];

        while (!empty($current['parent']) && isset($menus[$current['parent']]) && !isset($seen[$current['id']])) {
            $seen[$current['id']] = true;
            $current = $menus[$current['parent']];
        }

        return $current['slug'] ?? 'admin.php';
    }

    public function nested_admin_map(array $menus): array
    {
        $items = [];

        foreach ($menus as $id => $menu) {
            $parent = $this->normalize_parent($menu['parent'] ?? 0);

            if ($parent === 0 || !isset($menus[$parent])) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'parent' => $parent,
                'slug' => $menu['slug'],
                'parentSlug' => $menus[$parent]['slug'],
                'depth' => $this->depth($menus, (string) $id),
            ];
        }

        return $items;
    }

    public function sanitize_many(array $menus): array
    {
        $sanitized = [];

        foreach ($menus as $id => $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $id = sanitize_key($id);
            $sanitized[$id] = $this->sanitize($menu, $id);
        }

        return $sanitized;
    }

    public function sanitize(array $menu, ?string $fallback_id = null): array
    {
        $id = sanitize_key($menu['id'] ?? $fallback_id ?? uniqid('menu_', false));
        $slug = sanitize_title($menu['slug'] ?? $id);

        return [
            'id' => $id,
            'title' => sanitize_text_field($menu['title'] ?? ''),
            'slug' => $slug ?: $id,
            'cap' => sanitize_text_field($menu['cap'] ?? 'manage_options'),
            'callback' => sanitize_text_field($menu['callback'] ?? '__return_empty_string'),
            'parent' => $this->normalize_parent($menu['parent'] ?? 0),
            'icon' => sanitize_text_field($menu['icon'] ?? 'dashicons-admin-generic'),
        ];
    }

    public function descendants(array $menus, string $menu_id): array
    {
        $ids = [$menu_id];

        do {
            $found_child = false;

            foreach ($menus as $id => $menu) {
                if (in_array($menu['parent'] ?? 0, $ids, true) && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $found_child = true;
                }
            }
        } while ($found_child);

        return $ids;
    }
}
