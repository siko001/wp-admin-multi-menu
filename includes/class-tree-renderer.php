<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Tree_Renderer
{
    private FANM_Menu_Repository $repository;

    public function __construct(FANM_Menu_Repository $repository)
    {
        $this->repository = $repository;
    }

    public function tree(array $menus, $parent_id = 0, int $level = 0): string
    {
        $items = '';
        $menus = $this->repository->ordered($menus);

        foreach ($menus as $id => $menu) {
            if ($this->repository->normalize_parent($menu['parent'] ?? 0) !== $this->repository->normalize_parent($parent_id)) {
                continue;
            }

            $items .= $this->item($menu, (string) $id, $level);
            $items .= $this->tree($menus, $id, $level + 1);
            $items .= '</li>';
        }

        $class = esc_attr('fanm-level-' . $level);

        return <<<HTML
<ul class="{$class}">
    {$items}
</ul>
HTML;
    }

    public function item(array $menu, string $id, int $level = 0): string
    {
        $parent = esc_attr((string) ($menu['parent'] ?? 0));
        $item_id = esc_attr($id);
        $item_level = esc_attr((string) $level);
        $title = esc_html($menu['title'] ?? '');
        $title_attr = esc_attr($menu['title'] ?? '');
        $slug = esc_attr($menu['slug'] ?? '');
        $cap = esc_attr($menu['cap'] ?? 'manage_options');
        $icon = esc_attr($menu['icon'] ?? 'dashicons-admin-generic');
        $url = esc_url($menu['url'] ?? '');
        $hidden = !empty($menu['hidden']) ? '1' : '0';
        $hidden_checked = !empty($menu['hidden']) ? ' checked' : '';
        $custom_title = !empty($menu['custom_title']) ? '1' : '0';
        $move_label = esc_attr__('Move or expand menu item', 'flexible-admin-nested-menu');
        $hide_label = esc_attr__('Hide this menu item', 'flexible-admin-nested-menu');

        return <<<HTML
<li data-id="{$item_id}" data-level="{$item_level}" data-parent="{$parent}" data-hidden="{$hidden}">
    <div class="fanm-item-row" role="button" tabindex="0" aria-label="{$move_label}">
        <span class="fanm-handle dashicons dashicons-menu" aria-hidden="true"></span>
        <div class="fanm-fields">
            <span class="fanm-menu-title" tabindex="0" role="button">{$title}</span>
            <span class="fanm-menu-slug">{$slug}</span>
            <input type="hidden" class="title" value="{$title_attr}">
            <input type="hidden" class="slug" value="{$slug}">
            <input type="hidden" class="cap" value="{$cap}">
            <input type="hidden" class="icon" value="{$icon}">
            <input type="hidden" class="source" value="existing">
            <input type="hidden" class="url" value="{$url}">
            <input type="hidden" class="hidden" value="{$hidden}">
            <input type="hidden" class="custom_title" value="{$custom_title}">
        </div>
        <div class="fanm-item-actions" aria-label="Menu item hierarchy actions">
            <label class="fanm-visibility-toggle" title="{$hide_label}">
                <input class="fanm-hidden-toggle" type="checkbox"{$hidden_checked}>
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            </label>
            <button class="button-link fanm-sort-item" type="button">
                <span class="dashicons dashicons-sort" aria-hidden="true"></span>
            </button>
            <button class="button-link fanm-outdent-item" type="button">
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
            </button>
            <button class="button-link fanm-indent-item" type="button">
                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
            </button>
        </div>
    </div>
HTML;
    }
}
