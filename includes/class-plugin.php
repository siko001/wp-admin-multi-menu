<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Plugin
{
    private FANM_Menu_Repository $repository;
    private FANM_Access_Repository $access_repository;
    private FANM_Admin_Menu_Scanner $scanner;
    private FANM_Tree_Renderer $renderer;
    private FANM_Assets $assets;
    private FANM_WooCommerce_Compatibility $woocommerce_compatibility;
    private FANM_Builder_Page $builder_page;
    private FANM_Access_Page $access_page;
    private FANM_Access_Enforcer $access_enforcer;
    private FANM_Ajax_Controller $ajax_controller;
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
        $access_repository = new FANM_Access_Repository();
        $access_repository->reset();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function __construct()
    {
        $this->repository = new FANM_Menu_Repository();
        $this->access_repository = new FANM_Access_Repository();
        $this->scanner = new FANM_Admin_Menu_Scanner();
        $this->renderer = new FANM_Tree_Renderer($this->repository);
        $this->assets = new FANM_Assets($this->repository, $this->scanner);
        $this->woocommerce_compatibility = new FANM_WooCommerce_Compatibility();
        $this->builder_page = new FANM_Builder_Page($this->repository, $this->renderer, $this->scanner);
        $this->access_page = new FANM_Access_Page($this->access_repository, $this->scanner);
        $this->access_enforcer = new FANM_Access_Enforcer($this->access_repository, $this->scanner);
        $this->ajax_controller = new FANM_Ajax_Controller($this->repository, $this->scanner);
        $this->updater = new FANM_GitHub_Plugin_Updater();
    }

    private function hooks(): void
    {
        add_action('init', [$this, 'load_textdomain']);

        $this->assets->hooks();
        $this->woocommerce_compatibility->hooks();
        $this->builder_page->hooks();
        $this->access_page->hooks();
        $this->access_enforcer->hooks();
        $this->ajax_controller->hooks();
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
