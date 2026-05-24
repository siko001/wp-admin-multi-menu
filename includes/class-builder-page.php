<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Builder_Page
{
    private FANM_Menu_Repository $repository;
    private FANM_Tree_Renderer $renderer;

    public function __construct(FANM_Menu_Repository $repository, FANM_Tree_Renderer $renderer)
    {
        $this->repository = $repository;
        $this->renderer = $renderer;
    }

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_notices', [$this, 'notices']);
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
        $tree_html = $this->renderer->tree($menus);
        $admin_url = admin_url();

        include FANM_PATH . 'templates/builder-page.php';
    }

    public function notices(): void
    {
        if (!isset($_GET['page'], $_GET['saved']) || $_GET['page'] !== 'fanm-builder') {
            return;
        }

        $message = esc_html__('Menus updated successfully!', 'flexible-admin-nested-menu');

        echo <<<HTML
<div class="notice notice-success is-dismissible"><p>{$message}</p></div>
HTML;
    }
}

