<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Admin_Menu_Controller
{
    private FANM_Menu_Repository $repository;
    private FANM_Page_Callbacks $callbacks;

    public function __construct(FANM_Menu_Repository $repository, FANM_Page_Callbacks $callbacks)
    {
        $this->repository = $repository;
        $this->callbacks = $callbacks;
    }

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'register_custom_menus'], 9999);
    }

    public function register_custom_menus(): void
    {
        $menus = $this->repository->all();

        uasort($menus, function ($a, $b) use ($menus) {
            return $this->repository->depth($menus, $a['id']) <=> $this->repository->depth($menus, $b['id']);
        });

        foreach ($menus as $menu) {
            if ($this->repository->normalize_parent($menu['parent'] ?? 0) !== 0) {
                continue;
            }

            add_menu_page(
                $menu['title'],
                $menu['title'],
                $menu['cap'],
                $menu['slug'],
                fn() => $this->callbacks->render($menu),
                $menu['icon'],
                10
            );
        }

        foreach ($menus as $id => $menu) {
            if ($this->repository->normalize_parent($menu['parent'] ?? 0) === 0) {
                continue;
            }

            add_submenu_page(
                $this->repository->top_ancestor_slug($menus, (string) $id),
                $menu['title'],
                $menu['title'],
                $menu['cap'],
                $menu['slug'],
                fn() => $this->callbacks->render($menu)
            );
        }
    }
}

