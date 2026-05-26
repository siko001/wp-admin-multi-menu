<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Ajax_Controller
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
        add_action('wp_ajax_fanm_save_menus', [$this, 'save']);
        add_action('wp_ajax_fanm_import_current_menu', [$this, 'import_current_menu']);
    }

    public function save(): void
    {
        $this->authorize();

        if (empty($_POST['menus'])) {
            wp_send_json_error(__('No menu data provided.', 'flexible-admin-nested-menu'));
        }

        $menus = json_decode(wp_unslash($_POST['menus']), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($menus)) {
            wp_send_json_error(__('Invalid JSON data.', 'flexible-admin-nested-menu'));
        }

        $snapshot = $this->scanner->snapshot();

        if (!empty($snapshot)) {
            $menus = $this->repository->merge_with_snapshot($menus, $snapshot);
        }

        $this->repository->save($menus);
        flush_rewrite_rules();

        wp_send_json_success([
            'message' => __('Menus saved and applied!', 'flexible-admin-nested-menu'),
        ]);
    }

    public function import_current_menu(): void
    {
        $this->authorize();

        $menus = $this->scanner->snapshot();

        if (empty($menus)) {
            wp_send_json_error(__('No current admin menu items were found.', 'flexible-admin-nested-menu'));
        }

        $this->repository->save($menus);

        wp_send_json_success([
            'message' => __('Current admin menu loaded.', 'flexible-admin-nested-menu'),
        ]);
    }

    private function authorize(): void
    {
        check_ajax_referer('fanm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'flexible-admin-nested-menu'), 403);
        }
    }
}
