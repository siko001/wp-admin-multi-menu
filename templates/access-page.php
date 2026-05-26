<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap fanm-builder fanm-access-page">
    <h1><?php esc_html_e('Admin Menu Builder', 'flexible-admin-nested-menu'); ?></h1>

    <nav class="nav-tab-wrapper fanm-tabs" aria-label="<?php esc_attr_e('Menu builder sections', 'flexible-admin-nested-menu'); ?>">
        <a class="nav-tab" href="<?php echo esc_url(admin_url('admin.php?page=fanm-builder')); ?>"><?php esc_html_e('Menu Layout', 'flexible-admin-nested-menu'); ?></a>
        <a class="nav-tab nav-tab-active" href="<?php echo esc_url(admin_url('admin.php?page=fanm-access')); ?>"><?php esc_html_e('Menu Access', 'flexible-admin-nested-menu'); ?></a>
    </nav>

    <form method="post">
        <?php wp_nonce_field('fanm_access_settings'); ?>

        <div class="fanm-toolbar">
            <div class="fanm-toolbar-left">
                <button class="button" type="submit" name="fanm_reset_access" value="1">
                    <?php esc_html_e('Reset to Defaults', 'flexible-admin-nested-menu'); ?>
                </button>
            </div>
            <div class="fanm-toolbar-right">
                <button class="button button-large button-primary" type="submit">
                    <?php esc_html_e('Save Access Rules', 'flexible-admin-nested-menu'); ?>
                </button>
            </div>
        </div>

        <section class="fanm-builder-panel fanm-access-panel">
            <h2><?php esc_html_e('Menu Visibility & Access', 'flexible-admin-nested-menu'); ?></h2>
            <div class="fanm-access-table-wrap">
                <table class="widefat striped fanm-access-table">
                    <thead>
                        <tr>
                            <th class="fanm-access-menu-col"><?php esc_html_e('Menu Item', 'flexible-admin-nested-menu'); ?></th>
                            <th><?php esc_html_e('Capability', 'flexible-admin-nested-menu'); ?></th>
                            <?php foreach ($roles as $role_key => $role) : ?>
                                <th><?php echo esc_html(translate_user_role($role['name'])); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menus as $item_id => $item) : ?>
                            <?php $depth = $item['parent'] ? 1 : 0; ?>
                            <tr>
                                <td class="fanm-access-menu-col">
                                    <span class="fanm-access-title fanm-depth-<?php echo esc_attr((string) $depth); ?>">
                                        <?php echo esc_html($item['title'] ?? ''); ?>
                                    </span>
                                    <code><?php echo esc_html($item['slug'] ?? ''); ?></code>
                                </td>
                                <td><code><?php echo esc_html($item['cap'] ?? 'read'); ?></code></td>
                                <?php foreach ($roles as $role_key => $role) : ?>
                                    <?php
                                    $default_rule = $this->access_repository->default_rule((string) $role_key, $item);
                                    $rule = isset($rules[$role_key][$item_id])
                                        ? array_merge($default_rule, $rules[$role_key][$item_id])
                                        : $default_rule;
                                    $locked = $role_key === 'administrator';
                                    ?>
                                    <td class="fanm-access-role-cell">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="fanm_access[<?php echo esc_attr((string) $role_key); ?>][<?php echo esc_attr((string) $item_id); ?>][visible]"
                                                value="1"
                                                <?php checked(!empty($rule['visible'])); ?>
                                                <?php disabled($locked); ?>
                                            >
                                            <?php esc_html_e('Show', 'flexible-admin-nested-menu'); ?>
                                        </label>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="fanm_access[<?php echo esc_attr((string) $role_key); ?>][<?php echo esc_attr((string) $item_id); ?>][access]"
                                                value="1"
                                                <?php checked(!empty($rule['access'])); ?>
                                                <?php disabled($locked); ?>
                                            >
                                            <?php esc_html_e('Access', 'flexible-admin-nested-menu'); ?>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </form>
</div>
