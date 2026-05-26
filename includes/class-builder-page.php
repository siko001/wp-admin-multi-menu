<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Builder_Page
{
    private FANM_Menu_Repository $repository;
    private FANM_Tree_Renderer $renderer;
    private FANM_Admin_Menu_Scanner $scanner;

    public function __construct(FANM_Menu_Repository $repository, FANM_Tree_Renderer $renderer, FANM_Admin_Menu_Scanner $scanner)
    {
        $this->repository = $repository;
        $this->renderer = $renderer;
        $this->scanner = $scanner;
    }

    public function hooks(): void
    {
        add_action('admin_init', [$this, 'maybe_restore_defaults']);
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function maybe_restore_defaults(): void
    {
        if (!is_admin() || ($_GET['page'] ?? '') !== 'fanm-builder' || ($_GET['fanm_action'] ?? '') !== 'restore_defaults') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'flexible-admin-nested-menu'));
        }

        check_admin_referer('fanm_restore_defaults');

        $this->repository->save([]);

        wp_safe_redirect(add_query_arg([
            'page' => 'fanm-builder',
            'restored' => '1',
        ], admin_url('admin.php')));
        exit;
    }

    public function register_page(): void
    {
        add_menu_page(
            __('Nested Menu Builder', 'flexible-admin-nested-menu'),
            __('Menu Builder', 'flexible-admin-nested-menu'),
            'manage_options',
            'fanm-builder',
            [$this, 'render'],
            'dashicons-menu',
            3
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'flexible-admin-nested-menu'));
        }

        $menus = $this->repository->all();
        $snapshot = $this->scanner->snapshot();

        if (empty($menus)) {
            $menus = $snapshot;
        } elseif (!empty($snapshot)) {
            $menus = $this->repository->merge_with_snapshot($menus, $snapshot);
            $this->repository->save($menus);
        }

        $tree_html = $this->renderer->tree($menus);
        $restore_defaults_url = wp_nonce_url(
            add_query_arg([
                'page' => 'fanm-builder',
                'fanm_action' => 'restore_defaults',
            ], admin_url('admin.php')),
            'fanm_restore_defaults'
        );

        include FANM_PATH . 'templates/builder-page.php';
    }

    public function notices(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'fanm-builder') {
            return;
        }

        if (isset($_GET['restored'])) {
            $message = esc_html__('Menu builder restored to the default WordPress admin sidebar.', 'flexible-admin-nested-menu');
        } elseif (isset($_GET['saved'])) {
            $message = esc_html__('Menus updated successfully!', 'flexible-admin-nested-menu');
        } else {
            return;
        }

        echo <<<HTML
<div class="notice notice-success is-dismissible"><p>{$message}</p></div>
HTML;
    }
}
