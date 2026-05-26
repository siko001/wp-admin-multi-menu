<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Assets
{
    private FANM_Menu_Repository $repository;
    private FANM_Admin_Menu_Scanner $scanner;

    public function __construct(FANM_Menu_Repository $repository, FANM_Admin_Menu_Scanner $scanner)
    {
        $this->repository = $repository;
        $this->scanner = $scanner;
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

        if ($hook === 'fanm-builder_page_fanm-access' || strpos($hook, 'fanm-access') !== false) {
            $this->enqueue_builder_style();
        }
    }

    private function enqueue_builder_style(): void
    {
        wp_enqueue_style(
            'fanm-builder',
            FANM_URL . 'assets/css/builder.css',
            [],
            FANM_VERSION
        );
    }

    private function enqueue_builder_assets(): void
    {
        $menus = $this->repository->all();

        $this->enqueue_builder_style();

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
            'version' => FANM_VERSION,
            'defaultMode' => empty($menus),
            'messages' => [
                'ajaxMissing' => __('Error: WordPress AJAX object not loaded. Please refresh the page.', 'flexible-admin-nested-menu'),
                'saveSuccess' => __('Saved!', 'flexible-admin-nested-menu'),
                'saveError' => __('Error saving. Try again.', 'flexible-admin-nested-menu'),
                'restoreConfirm' => __('Restore the menu builder to the default WordPress admin sidebar? This will discard your saved menu arrangement after you confirm.', 'flexible-admin-nested-menu'),
                'exportName' => __('admin-menu-sidebar-export.json', 'flexible-admin-nested-menu'),
                'importConfirm' => __('Restore this saved sidebar export into the builder? Review it, then press Save & Apply Menus to make it live.', 'flexible-admin-nested-menu'),
                'importError' => __('Could not restore that sidebar export. Please choose a valid JSON export file.', 'flexible-admin-nested-menu'),
                'importSuccess' => __('Sidebar export restored into the builder. Press Save & Apply Menus to make it live.', 'flexible-admin-nested-menu'),
                'currentError' => __('Error restoring the default sidebar menu.', 'flexible-admin-nested-menu'),
            ],
        ]);
    }

    private function enqueue_admin_menu_assets(): void
    {
        $menus = $this->repository->all();

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
            'items' => empty($menus)
                ? []
                : $this->repository->admin_menu_map(
                    $this->repository->merge_with_snapshot(
                        $menus,
                        $this->scanner->snapshot()
                    )
                ),
        ]);
    }
}
