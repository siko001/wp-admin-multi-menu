<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Ajax_Controller
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
        add_action('wp_ajax_fanm_save_menus', [$this, 'save']);
        add_action('wp_ajax_fanm_add_menu', [$this, 'add']);
        add_action('wp_ajax_fanm_delete_menu', [$this, 'delete']);
        add_action('wp_ajax_fanm_import_demo', [$this, 'import_demo']);
        add_action('wp_ajax_fanm_export_json', [$this, 'export_json']);
        add_action('wp_ajax_fanm_import_json', [$this, 'import_json']);
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

        $this->repository->save($menus);
        flush_rewrite_rules();

        wp_send_json_success([
            'message' => __('Menus saved and applied!', 'flexible-admin-nested-menu'),
        ]);
    }

    public function add(): void
    {
        $this->authorize();

        $parent = isset($_POST['parent'])
            ? $this->repository->normalize_parent(wp_unslash($_POST['parent']))
            : 0;

        $menus = $this->repository->all();

        if ($parent !== 0 && !isset($menus[$parent])) {
            wp_send_json_error(__('Invalid parent menu.', 'flexible-admin-nested-menu'));
        }

        $menu = $this->repository->create(['parent' => $parent]);
        $menus[$menu['id']] = $menu;
        $this->repository->save($menus);

        wp_send_json_success([
            'html' => $this->renderer->item($menu, $menu['id']) . '</li>',
        ]);
    }

    public function delete(): void
    {
        $this->authorize();

        $menu_id = isset($_POST['menu_id']) ? sanitize_key(wp_unslash($_POST['menu_id'])) : '';

        if ($menu_id === '') {
            wp_send_json_error(__('Missing menu ID.', 'flexible-admin-nested-menu'));
        }

        $menus = $this->repository->all();

        foreach ($this->repository->descendants($menus, $menu_id) as $id) {
            unset($menus[$id]);
        }

        $this->repository->save($menus);

        wp_send_json_success([
            'message' => __('Menu deleted successfully!', 'flexible-admin-nested-menu'),
        ]);
    }

    public function import_demo(): void
    {
        $this->authorize();
        $this->repository->save($this->repository->demo());

        wp_send_json_success([
            'message' => __('Demo menus imported successfully!', 'flexible-admin-nested-menu'),
        ]);
    }

    public function export_json(): void
    {
        $this->authorize();

        wp_send_json_success([
            'data' => wp_json_encode($this->repository->all(), JSON_PRETTY_PRINT),
        ]);
    }

    public function import_json(): void
    {
        $this->authorize();

        $json_data = isset($_POST['json_data']) ? wp_unslash($_POST['json_data']) : '';

        if ($json_data === '') {
            wp_send_json_error(__('No data provided.', 'flexible-admin-nested-menu'));
        }

        $menus = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($menus)) {
            wp_send_json_error(__('Invalid JSON data.', 'flexible-admin-nested-menu'));
        }

        $this->repository->save($menus);

        wp_send_json_success([
            'message' => __('Menus imported successfully!', 'flexible-admin-nested-menu'),
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

