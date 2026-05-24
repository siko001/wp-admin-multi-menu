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
        $icon_options = $this->icon_options($menu['icon'] ?? 'dashicons-admin-generic');
        $title_placeholder = esc_attr__('Menu Title', 'flexible-admin-nested-menu');
        $parent = esc_attr((string) ($menu['parent'] ?? 0));
        $item_id = esc_attr($id);
        $item_level = esc_attr((string) $level);
        $title = esc_attr($menu['title'] ?? '');
        $slug = esc_attr($menu['slug'] ?? '');
        $cap = esc_attr($menu['cap'] ?? 'manage_options');
        $callback = esc_attr($menu['callback'] ?? '__return_empty_string');
        $move_label = esc_attr__('Move or expand menu item', 'flexible-admin-nested-menu');
        $child_label = esc_html__('Child', 'flexible-admin-nested-menu');
        $delete_label = esc_html__('Delete', 'flexible-admin-nested-menu');

        return <<<HTML
<li data-id="{$item_id}" data-level="{$item_level}" data-parent="{$parent}">
    <button type="button" class="fanm-handle" aria-label="{$move_label}">☰</button>
    <div class="fanm-fields">
        <input type="text" class="title" value="{$title}" placeholder="{$title_placeholder}">
        <input type="text" class="slug" value="{$slug}" placeholder="my-page-slug">
        <input type="text" class="cap" value="{$cap}" placeholder="manage_options">
        <input type="text" class="callback" value="{$callback}" placeholder="__return_empty_string">
        <select class="icon">{$icon_options}</select>
    </div>
    <div class="fanm-item-actions">
        <button type="button" class="add-child button">+ {$child_label}</button>
        <button type="button" class="delete-item button">{$delete_label}</button>
    </div>
HTML;
    }

    private function icon_options(string $current_icon): string
    {
        $options = '';

        foreach ($this->icons() as $icon) {
            $selected = selected($icon, $current_icon, false);
            $label = esc_html($icon);
            $value = esc_attr($icon);
            $options .= "<option value=\"{$value}\" {$selected}>{$label}</option>";
        }

        return $options;
    }

    private function icons(): array
    {
        return [
            'dashicons-admin-home',
            'dashicons-dashboard',
            'dashicons-admin-post',
            'dashicons-admin-media',
            'dashicons-admin-links',
            'dashicons-admin-page',
            'dashicons-admin-comments',
            'dashicons-admin-appearance',
            'dashicons-admin-plugins',
            'dashicons-admin-users',
            'dashicons-admin-tools',
            'dashicons-admin-settings',
            'dashicons-admin-network',
            'dashicons-admin-generic',
            'dashicons-admin-collapse',
            'dashicons-filter',
            'dashicons-admin-customizer',
            'dashicons-admin-multisite',
            'dashicons-admin-site',
            'dashicons-admin-site-alt',
            'dashicons-admin-site-alt2',
            'dashicons-admin-site-alt3',
            'dashicons-admin-user',
            'dashicons-admin-background',
            'dashicons-admin-counter',
            'dashicons-admin-categories',
            'dashicons-admin-menu',
            'dashicons-admin-options',
            'dashicons-admin-overview',
            'dashicons-admin-colors',
            'dashicons-admin-themes',
            'dashicons-admin-widgets',
            'dashicons-admin-menus',
            'dashicons-visibility',
            'dashicons-visibility-alt',
            'dashicons-hidden',
            'dashicons-post-status',
            'dashicons-edit',
            'dashicons-post-trash',
            'dashicons-trash',
            'dashicons-edit-page',
            'dashicons-edit-pages',
            'dashicons-edit-comments',
            'dashicons-edit-large',
            'dashicons-edit-alt',
            'dashicons-welcome-write-blog',
            'dashicons-welcome-add-page',
            'dashicons-welcome-view-site',
            'dashicons-welcome-widgets-menus',
            'dashicons-welcome-comments',
            'dashicons-welcome-learn-more',
            'dashicons-format-image',
            'dashicons-format-gallery',
            'dashicons-format-audio',
            'dashicons-format-video',
            'dashicons-format-status',
            'dashicons-format-aside',
            'dashicons-format-quote',
            'dashicons-format-chat',
            'dashicons-format-standard',
            'dashicons-format-links',
            'dashicons-format-link',
            'dashicons-chart-bar',
            'dashicons-media-spreadsheet',
            'dashicons-users',
        ];
    }
}
