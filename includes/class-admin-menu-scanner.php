<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Admin_Menu_Scanner
{
    public function snapshot(bool $respect_capabilities = true): array
    {
        global $menu, $submenu;

        if (empty($menu)) {
            $menu_file = ABSPATH . 'wp-admin/menu.php';

            if (file_exists($menu_file)) {
                require $menu_file;
            }
        }

        if (empty($menu)) {
            do_action('admin_menu');
        }

        $items = [];

        $order = 0;

        foreach ((array) $menu as $menu_item) {
            if (!$this->is_valid_menu_item($menu_item, $respect_capabilities)) {
                continue;
            }

            $slug = $this->canonical_slug((string) $menu_item[2]);

            if ($this->is_deprecated_menu_item($slug)) {
                continue;
            }

            $id = $this->item_id($slug);
            $items[$id] = [
                'id' => $id,
                'title' => wp_strip_all_tags((string) $menu_item[0]),
                'slug' => $slug,
                'url' => $this->admin_url($slug),
                'cap' => (string) ($menu_item[1] ?? 'read'),
                'parent' => 0,
                'icon' => (string) ($menu_item[6] ?? 'dashicons-admin-generic'),
                'hidden' => false,
                'order' => $order++,
                'source' => 'existing',
            ];

            foreach ((array) ($submenu[$menu_item[2]] ?? []) as $submenu_item) {
                if (!$this->is_valid_menu_item($submenu_item, $respect_capabilities)) {
                    continue;
                }

                if ((string) $submenu_item[2] === (string) $menu_item[2]) {
                    continue;
                }

                $child_slug = $this->canonical_slug((string) $submenu_item[2]);

                if ($this->is_deprecated_menu_item($child_slug)) {
                    continue;
                }

                $child_id = $this->item_id($slug . '|' . $child_slug);
                $items[$child_id] = [
                    'id' => $child_id,
                    'title' => wp_strip_all_tags((string) $submenu_item[0]),
                    'slug' => $child_slug,
                    'url' => $this->admin_url($child_slug),
                    'cap' => (string) ($submenu_item[1] ?? 'read'),
                    'parent' => $id,
                    'icon' => 'dashicons-admin-generic',
                    'hidden' => false,
                    'order' => $order++,
                    'source' => 'existing',
                ];
            }
        }

        $collapse_id = $this->item_id('collapse-menu');
        $items[$collapse_id] = [
            'id' => $collapse_id,
            'title' => __('Collapse Menu', 'flexible-admin-nested-menu'),
            'slug' => 'collapse-menu',
            'url' => '#collapse-menu',
            'cap' => 'read',
            'parent' => 0,
            'icon' => 'dashicons-arrow-left-alt2',
            'hidden' => false,
            'order' => $order++,
            'source' => 'existing',
        ];

        return $items;
    }

    private function is_valid_menu_item($item, bool $respect_capabilities = true): bool
    {
        if (!is_array($item) || empty($item[2]) || !is_string($item[2])) {
            return false;
        }

        if (strpos($item[2], 'separator') === 0) {
            return false;
        }

        return !$respect_capabilities || !isset($item[1]) || current_user_can((string) $item[1]);
    }

    private function is_deprecated_menu_item(string $slug): bool
    {
        return $slug === 'wc-reports' ||
            $slug === 'admin.php?page=wc-reports' ||
            strpos($slug, 'admin.php?page=wc-reports&') === 0;
    }

    private function item_id(string $key): string
    {
        return 'existing_' . substr(md5($key), 0, 16);
    }

    private function canonical_slug(string $slug): string
    {
        $slug = html_entity_decode($slug, ENT_QUOTES | ENT_HTML5);

        if (strpos($slug, admin_url()) === 0) {
            $slug = substr($slug, strlen(admin_url()));
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

    private function admin_url(string $slug): string
    {
        if (strpos($slug, 'http://') === 0 || strpos($slug, 'https://') === 0) {
            return esc_url_raw($slug);
        }

        if (strpos($slug, '.php') !== false) {
            return admin_url($slug);
        }

        return admin_url('admin.php?page=' . $slug);
    }
}
