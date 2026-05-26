<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Access_Page
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
        add_action('admin_init', [$this, 'handle_post']);
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function register_page(): void
    {
        add_submenu_page(
            'fanm-builder',
            __('Menu Access', 'flexible-admin-nested-menu'),
            __('Menu Access', 'flexible-admin-nested-menu'),
            'manage_options',
            'fanm-access',
            [$this, 'render']
        );
    }

    public function handle_post(): void
    {
        if (!is_admin() || ($_GET['page'] ?? '') !== 'fanm-access' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'flexible-admin-nested-menu'));
        }

        check_admin_referer('fanm_access_settings');

        if (isset($_POST['fanm_reset_access'])) {
            $this->access_repository->reset();
            $this->redirect('reset');
        }

        $submitted = isset($_POST['fanm_access']) && is_array($_POST['fanm_access'])
            ? wp_unslash($_POST['fanm_access'])
            : [];
        $rules = [];

        foreach ($this->editable_roles() as $role_key => $role) {
            if ($role_key === 'administrator') {
                continue;
            }

            foreach ($this->scanner->snapshot(false) as $item_id => $item) {
                $rules[$role_key][$item_id] = [
                    'visible' => !empty($submitted[$role_key][$item_id]['visible']),
                    'access' => !empty($submitted[$role_key][$item_id]['access']),
                ];
            }
        }

        $this->access_repository->save($rules);
        $this->redirect('saved');
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'flexible-admin-nested-menu'));
        }

        $roles = $this->editable_roles();
        $menus = $this->scanner->snapshot(false);
        $rules = $this->access_repository->all();

        include FANM_PATH . 'templates/access-page.php';
    }

    public function notices(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'fanm-access') {
            return;
        }

        if (isset($_GET['saved'])) {
            $message = esc_html__('Menu access settings saved.', 'flexible-admin-nested-menu');
        } elseif (isset($_GET['reset'])) {
            $message = esc_html__('Menu access settings restored to role defaults.', 'flexible-admin-nested-menu');
        } else {
            return;
        }

        echo <<<HTML
<div class="notice notice-success is-dismissible"><p>{$message}</p></div>
HTML;
    }

    private function editable_roles(): array
    {
        return function_exists('get_editable_roles') ? get_editable_roles() : [];
    }

    private function redirect(string $flag): void
    {
        wp_safe_redirect(add_query_arg([
            'page' => 'fanm-access',
            $flag => '1',
        ], admin_url('admin.php')));
        exit;
    }
}
