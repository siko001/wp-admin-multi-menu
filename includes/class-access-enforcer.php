<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Access_Enforcer
{
    private FANM_Access_Repository $access_repository;
    private FANM_Admin_Menu_Scanner $scanner;

    public function __construct(FANM_Access_Repository $access_repository, FANM_Admin_Menu_Scanner $scanner)
    {
        $this->access_repository = $access_repository;
        $this->scanner = $scanner;
    }

    public function hooks(): void
    {
        add_filter('user_has_cap', [$this, 'filter_caps'], 20, 4);
        add_action('admin_menu', [$this, 'filter_admin_menu'], 9999);
    }

    public function filter_caps(array $allcaps, array $caps, array $args, WP_User $user): array
    {
        if (!is_admin() || $this->is_administrator($user)) {
            return $allcaps;
        }

        $role = $this->primary_role($user);
        $requested_cap = isset($args[0]) ? (string) $args[0] : '';

        if ($role === '' || $requested_cap === '') {
            return $allcaps;
        }

        foreach ($this->scanner->snapshot(false) as $item_id => $item) {
            if ((string) ($item['cap'] ?? '') !== $requested_cap || !$this->request_matches_item($item)) {
                continue;
            }

            $rule = $this->access_repository->rule_for($role, (string) $item_id, $item);
            $allcaps[$requested_cap] = !empty($rule['access']);
            break;
        }

        return $allcaps;
    }

    public function filter_admin_menu(): void
    {
        $user = wp_get_current_user();

        if (!$user || $this->is_administrator($user)) {
            return;
        }

        $role = $this->primary_role($user);

        if ($role === '') {
            return;
        }

        foreach ($this->scanner->snapshot(false) as $item_id => $item) {
            $rule = $this->access_repository->rule_for($role, (string) $item_id, $item);

            if (!empty($rule['visible']) && !empty($rule['access'])) {
                continue;
            }

            $slug = (string) ($item['slug'] ?? '');
            $parent_id = (string) ($item['parent'] ?? '0');

            if ($parent_id !== '0') {
                $parent = $this->scanner->snapshot(false)[$parent_id] ?? null;

                if (is_array($parent)) {
                    remove_submenu_page((string) ($parent['slug'] ?? ''), $slug);
                }

                continue;
            }

            remove_menu_page($slug);
        }
    }

    private function request_matches_item(array $item): bool
    {
        $request_path = $this->request_path();
        $item_path = $this->canonical_path((string) ($item['url'] ?? ''));
        $slug = $this->canonical_path((string) ($item['slug'] ?? ''));

        if ($item_path !== '' && $request_path === $item_path) {
            return true;
        }

        if ($slug !== '' && $request_path === $slug) {
            return true;
        }

        if ($slug !== '' && strpos($slug, '.php') === false && $request_path === 'admin.php?page=' . $slug) {
            return true;
        }

        return false;
    }

    private function request_path(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        return $this->canonical_path($uri);
    }

    private function canonical_path(string $path): string
    {
        $path = html_entity_decode($path, ENT_QUOTES | ENT_HTML5);

        if ($path === '') {
            return '';
        }

        if (strpos($path, admin_url()) === 0) {
            $path = substr($path, strlen(admin_url()));
        }

        if (strpos($path, '/wp-admin/') !== false) {
            $path = preg_replace('#^.*?/wp-admin/#', '', $path) ?: $path;
        }

        return ltrim($path, '/');
    }

    private function primary_role(WP_User $user): string
    {
        return isset($user->roles[0]) ? (string) $user->roles[0] : '';
    }

    private function is_administrator(WP_User $user): bool
    {
        return in_array('administrator', (array) $user->roles, true);
    }
}
