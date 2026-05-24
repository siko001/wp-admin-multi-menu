<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Assets
{
    private FANM_Menu_Repository $repository;

    public function __construct(FANM_Menu_Repository $repository)
    {
        $this->repository = $repository;
    }

    public function hooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        $this->enqueue_admin_menu_assets();

        if ($hook === 'toplevel_page_fanm-builder') {
            $this->enqueue_builder_assets();
        }
    }

    private function enqueue_builder_assets(): void
    {
        wp_enqueue_style(
            'fanm-builder',
            FANM_URL . 'assets/css/builder.css',
            [],
            FANM_VERSION
        );

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'fanm-builder',
            FANM_URL . 'assets/js/builder.js',
            ['jquery', 'jquery-ui-sortable'],
            FANM_VERSION,
            true
        );

        wp_localize_script('fanm-builder', 'fanmBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fanm_nonce'),
            'messages' => [
                'ajaxMissing' => __('Error: WordPress AJAX object not loaded. Please refresh the page.', 'flexible-admin-nested-menu'),
                'saveSuccess' => __('Saved!', 'flexible-admin-nested-menu'),
                'saveError' => __('Error saving. Try again.', 'flexible-admin-nested-menu'),
                'addError' => __('Error adding menu.', 'flexible-admin-nested-menu'),
                'deleteConfirm' => __('Are you sure you want to delete this menu and all its children?', 'flexible-admin-nested-menu'),
                'deleteError' => __('Error deleting menu.', 'flexible-admin-nested-menu'),
                'demoConfirm' => __('This will replace all current menus with demo data. Continue?', 'flexible-admin-nested-menu'),
                'demoError' => __('Error importing demo.', 'flexible-admin-nested-menu'),
                'exportError' => __('Error exporting menus.', 'flexible-admin-nested-menu'),
                'jsonRequired' => __('Please enter JSON data.', 'flexible-admin-nested-menu'),
                'importError' => __('Error importing:', 'flexible-admin-nested-menu'),
            ],
        ]);
    }

    private function enqueue_admin_menu_assets(): void
    {
        wp_enqueue_style(
            'fanm-admin-menu',
            FANM_URL . 'assets/css/admin-menu.css',
            [],
            FANM_VERSION
        );

        wp_enqueue_script(
            'fanm-admin-menu',
            FANM_URL . 'assets/js/admin-menu.js',
            ['jquery'],
            FANM_VERSION,
            true
        );

        wp_localize_script('fanm-admin-menu', 'fanmAdminMenu', [
            'items' => $this->repository->nested_admin_map($this->repository->all()),
        ]);
    }
}

