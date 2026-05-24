<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Plugin
{
    private FANM_Menu_Repository $repository;
    private FANM_Tree_Renderer $renderer;
    private FANM_Page_Callbacks $callbacks;
    private FANM_Assets $assets;
    private FANM_Builder_Page $builder_page;
    private FANM_Ajax_Controller $ajax_controller;
    private FANM_Admin_Menu_Controller $admin_menu_controller;
    private FANM_GitHub_Plugin_Updater $updater;

    public static function init(): void
    {
        $plugin = new self();
        $plugin->hooks();
    }

    public static function activate(): void
    {
        $repository = new FANM_Menu_Repository();
        $repository->install_defaults();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function __construct()
    {
        $this->repository = new FANM_Menu_Repository();
        $this->renderer = new FANM_Tree_Renderer($this->repository);
        $this->callbacks = new FANM_Page_Callbacks();
        $this->assets = new FANM_Assets($this->repository);
        $this->builder_page = new FANM_Builder_Page($this->repository, $this->renderer);
        $this->ajax_controller = new FANM_Ajax_Controller($this->repository, $this->renderer);
        $this->admin_menu_controller = new FANM_Admin_Menu_Controller($this->repository, $this->callbacks);
        $this->updater = new FANM_GitHub_Plugin_Updater();
    }

    private function hooks(): void
    {
        add_action('init', [$this, 'load_textdomain']);

        $this->assets->hooks();
        $this->builder_page->hooks();
        $this->ajax_controller->hooks();
        $this->admin_menu_controller->hooks();
        $this->updater->register();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'flexible-admin-nested-menu',
            false,
            dirname(plugin_basename(FANM_FILE)) . '/languages/'
        );
    }
}
