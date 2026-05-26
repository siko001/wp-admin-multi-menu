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

        if (!is_array($menus)) {
            $menus = [];
            update_option(self::OPTION_NAME, $menus);
        }

        return $menus;
    }

    public function save(array $menus): void
    {
        update_option(self::OPTION_NAME, $this->sanitize_many($menus));
    }

    public function repair_metadata(array $menus, array $reference): array
    {
        foreach ($menus as $id => $menu) {
            if (!is_array($menu) || !isset($reference[$id])) {
                continue;
            }

            foreach (['title', 'slug', 'url', 'cap', 'icon'] as $field) {
                if (empty($menus[$id][$field]) || ($field === 'slug' && $menus[$id][$field] === $id)) {
                    $menus[$id][$field] = $reference[$id][$field] ?? $menus[$id][$field] ?? '';
                }
            }
        }

        return $menus;
    }

    public function merge_with_snapshot(array $saved, array $snapshot): array
    {
        if (empty($snapshot)) {
            return $saved;
        }

        $merged = [];
        $seen = [];
        $snapshot_by_key = [];

        foreach ($snapshot as $snapshot_id => $snapshot_menu) {
            if (!is_array($snapshot_menu)) {
                continue;
            }

            $snapshot_key = $this->menu_key($snapshot_menu);

            if ($snapshot_key !== '') {
                $snapshot_by_key[$snapshot_key] = $snapshot_menu;
            }
        }

        foreach ($saved as $id => $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $parent = $this->normalize_parent($menu['parent'] ?? 0);
            $menu = $this->sanitize($menu, (string) $id);
            $key = $this->menu_key($menu);
            $snapshot_match = $snapshot_by_key[$key] ?? null;

            if (empty($menu['custom_title']) && is_array($snapshot_match) && (string) ($menu['title'] ?? '') !== (string) ($snapshot_match['title'] ?? '')) {
                $menu['custom_title'] = true;
            }

            if ($key !== '' && isset($seen[$key])) {
                continue;
            }

            if (isset($snapshot[$id])) {
                $merged[$id] = array_merge($snapshot[$id], $menu, [
                    'parent' => $parent !== 0 && (isset($snapshot[$parent]) || isset($saved[$parent])) ? $parent : 0,
                    'hidden' => !empty($menu['hidden']),
                    'custom_title' => !empty($menu['custom_title']) || (string) ($menu['title'] ?? '') !== (string) ($snapshot[$id]['title'] ?? ''),
                    'order' => (int) ($menu['order'] ?? 0),
                ]);
                $seen[$key] = true;
                continue;
            }

            $merged[$id] = $this->sanitize(array_merge($menu, [
                'parent' => $parent !== 0 && (isset($snapshot[$parent]) || isset($saved[$parent])) ? $parent : 0,
            ]), (string) $id);
            $seen[$key] = true;
        }

        foreach ($snapshot as $id => $menu) {
            $key = $this->menu_key($menu);

            if (!isset($merged[$id]) && ($key === '' || !isset($seen[$key]))) {
                $menu['order'] = count($merged);
                $merged[$id] = $menu;
                $seen[$key] = true;
            }
        }

        return $merged;
    }

    public function install_defaults(): void
    {
        if (!is_array(get_option(self::OPTION_NAME, null))) {
            update_option(self::OPTION_NAME, []);
        }
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

    public function admin_menu_map(array $menus): array
    {
        $items = [];
        $menus = $this->ordered($menus);

        foreach ($menus as $id => $menu) {
            $parent = $this->normalize_parent($menu['parent'] ?? 0);

            $items[] = [
                'id' => $id,
                'parent' => $parent,
                'title' => $menu['title'] ?? '',
                'slug' => $menu['slug'],
                'url' => $menu['url'] ?? '',
                'icon' => $menu['icon'] ?? 'dashicons-admin-generic',
                'hidden' => !empty($menu['hidden']),
                'source' => 'existing',
                'parentId' => $parent,
                'parentSlug' => $parent !== 0 && isset($menus[$parent]) ? $menus[$parent]['slug'] : '',
                'parentUrl' => $parent !== 0 && isset($menus[$parent]) ? ($menus[$parent]['url'] ?? '') : '',
                'parentTitle' => $parent !== 0 && isset($menus[$parent]) ? ($menus[$parent]['title'] ?? '') : '',
                'depth' => $this->depth($menus, (string) $id),
                'order' => (int) ($menu['order'] ?? 0),
            ];
        }

        return $items;
    }

    public function sanitize_many(array $menus): array
    {
        $sanitized = [];
        $seen = [];
        $fallback_order = 0;

        foreach ($menus as $id => $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $id = sanitize_key($id);
            $has_order = isset($menu['order']);
            $menu = $this->sanitize($menu, $id);

            if ($this->is_deprecated_menu($menu)) {
                continue;
            }

            $menu['order'] = $has_order ? (int) $menu['order'] : $fallback_order;
            $key = $this->menu_key($menu);

            if ($key !== '' && isset($seen[$key])) {
                if (!empty($menu['custom_title']) && isset($sanitized[$seen[$key]]) && empty($sanitized[$seen[$key]]['custom_title'])) {
                    unset($sanitized[$seen[$key]]);
                    $sanitized[$id] = $menu;
                    $seen[$key] = $id;
                }

                continue;
            }

            $sanitized[$id] = $menu;

            if ($key !== '') {
                $seen[$key] = $id;
            }

            $fallback_order++;
        }

        return $this->ordered($sanitized);
    }

    public function sanitize(array $menu, ?string $fallback_id = null): array
    {
        $id = sanitize_key($menu['id'] ?? $fallback_id ?? uniqid('menu_', false));
        $slug = $this->canonical_slug(sanitize_text_field($menu['slug'] ?? $id));
        $url = esc_url_raw($menu['url'] ?? '');

        if ($slug === 'customize.php') {
            $url = admin_url('customize.php');
        }

        return [
            'id' => $id,
            'title' => sanitize_text_field($menu['title'] ?? ''),
            'slug' => $slug ?: $id,
            'url' => $url,
            'cap' => sanitize_text_field($menu['cap'] ?? 'manage_options'),
            'parent' => $this->normalize_parent($menu['parent'] ?? 0),
            'icon' => sanitize_text_field($menu['icon'] ?? 'dashicons-admin-generic'),
            'hidden' => !empty($menu['hidden']),
            'custom_title' => !empty($menu['custom_title']),
            'order' => (int) ($menu['order'] ?? 0),
            'source' => 'existing',
        ];
    }

    public function ordered(array $menus): array
    {
        uasort($menus, static function ($a, $b): int {
            $a_order = is_array($a) ? (int) ($a['order'] ?? 0) : 0;
            $b_order = is_array($b) ? (int) ($b['order'] ?? 0) : 0;

            return $a_order <=> $b_order;
        });

        return $menus;
    }

    private function menu_key(array $menu): string
    {
        $slug = $this->canonical_slug((string) ($menu['slug'] ?? ''));
        $url_path = $this->canonical_slug($this->path_from_url((string) ($menu['url'] ?? '')));
        $woo_alias = $this->woo_alias_key($slug, $url_path);

        if ($woo_alias !== '') {
            return $woo_alias;
        }

        if ($url_path !== '') {
            if ($this->is_generic_wc_admin_path($url_path)) {
                return 'id:' . sanitize_key((string) ($menu['id'] ?? ''));
            }

            return 'path:' . $url_path;
        }

        if ($slug !== '') {
            return 'slug:' . $slug;
        }

        return '';
    }

    private function is_deprecated_menu(array $menu): bool
    {
        $slug = $this->canonical_slug((string) ($menu['slug'] ?? ''));
        $path = $this->canonical_slug($this->path_from_url((string) ($menu['url'] ?? '')));

        return $slug === 'wc-reports' ||
            $slug === 'admin.php?page=wc-reports' ||
            strpos($slug, 'admin.php?page=wc-reports&') === 0 ||
            $path === 'admin.php?page=wc-reports' ||
            strpos($path, 'admin.php?page=wc-reports&') === 0;
    }

    private function woo_alias_key(string $slug, string $path): string
    {
        $is_generic_woo_home = $path === '' || $this->is_generic_wc_admin_path($path);

        if (
            $slug === 'wc-orders' ||
            $slug === 'admin.php?page=wc-orders' ||
            $path === 'admin.php?page=wc-orders' ||
            $slug === 'edit.php?post_type=shop_order' ||
            $path === 'edit.php?post_type=shop_order'
        ) {
            return 'woo:orders';
        }

        if (
            $slug === 'woocommerce-marketing' ||
            $path === 'admin.php?page=woocommerce-marketing' ||
            $path === 'admin.php?page=wc-admin&path=/marketing' ||
            $path === 'admin.php?page=wc-admin&path=%2Fmarketing' ||
            $slug === 'wc-admin&path=/marketing' ||
            $slug === 'wc-admin&path=%2Fmarketing'
        ) {
            return 'woo:marketing';
        }

        if (
            (($slug === 'wc-admin' || $slug === 'woocommerce') && $is_generic_woo_home) ||
            $path === 'admin.php?page=wc-admin' ||
            $path === 'admin.php?page=woocommerce'
        ) {
            return 'woo:home';
        }

        return '';
    }

    private function is_generic_wc_admin_path(string $path): bool
    {
        return $path === 'admin.php?page=wc-admin' ||
            $path === 'admin.php?page=woocommerce' ||
            $path === 'admin.php?page=wc-admin&path=/' ||
            $path === 'admin.php?page=wc-admin&path=%2F' ||
            $path === 'admin.php?page=wc-admin&path=/home' ||
            $path === 'admin.php?page=wc-admin&path=%2Fhome';
    }

    private function path_from_url(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5));

        if (!is_array($parts)) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        if (strpos($path, '/wp-admin/') !== false) {
            $path = preg_replace('#^.*?/wp-admin/#', '', $path) ?: $path;
        }

        return ltrim($path . $query, '/');
    }

    private function canonical_slug(string $slug): string
    {
        $slug = html_entity_decode($slug, ENT_QUOTES | ENT_HTML5);
        $admin_url = admin_url();

        if (strpos($slug, $admin_url) === 0) {
            $slug = substr($slug, strlen($admin_url));
        }

        if (strpos($slug, '/wp-admin/') !== false) {
            $slug = preg_replace('#^.*?/wp-admin/#', '', $slug) ?: $slug;
        }

        $slug = ltrim($slug, '/');

        if (strpos($slug, 'customize.php') === 0) {
            return 'customize.php';
        }

        return $slug;
    }
}
