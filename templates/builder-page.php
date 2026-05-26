<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap fanm-builder">
    <h1><?php esc_html_e('Admin Menu Builder', 'flexible-admin-nested-menu'); ?></h1>

    <nav class="nav-tab-wrapper fanm-tabs" aria-label="<?php esc_attr_e('Menu builder sections', 'flexible-admin-nested-menu'); ?>">
        <a class="nav-tab nav-tab-active" href="<?php echo esc_url(admin_url('admin.php?page=fanm-builder')); ?>"><?php esc_html_e('Menu Layout', 'flexible-admin-nested-menu'); ?></a>
        <a class="nav-tab" href="<?php echo esc_url(admin_url('admin.php?page=fanm-access')); ?>"><?php esc_html_e('Menu Access', 'flexible-admin-nested-menu'); ?></a>
    </nav>
    <div class="fanm-toolbar">
        <div class="fanm-toolbar-left">
            <a id="fanm-restore-defaults" class="button" href="<?php echo esc_url($restore_defaults_url); ?>">
                <?php esc_html_e('Restore Defaults', 'flexible-admin-nested-menu'); ?>
            </a>
            <button id="fanm-export-sidebar" class="button" type="button">
                <?php esc_html_e('Save Current Sidebar', 'flexible-admin-nested-menu'); ?>
            </button>
            <button id="fanm-restore-sidebar" class="button" type="button">
                <?php esc_html_e('Restore Saved Sidebar', 'flexible-admin-nested-menu'); ?>
            </button>
            <input id="fanm-restore-sidebar-file" class="fanm-screen-reader-file" type="file" accept="application/json,.json">
            <button id="fanm-expand-all" class="button" type="button">
                <?php esc_html_e('Expand All', 'flexible-admin-nested-menu'); ?>
            </button>
            <button id="fanm-collapse-all" class="button" type="button">
                <?php esc_html_e('Collapse All', 'flexible-admin-nested-menu'); ?>
            </button>
        </div>
        <div class="fanm-toolbar-right">
            <button id="fanm-save-all" class="button button-large button-primary" type="button">
                <?php esc_html_e('Save & Apply Menus', 'flexible-admin-nested-menu'); ?>
            </button>
        </div>
    </div>

    <div class="fanm-builder-grid" id="fanm-builder-grid">
        <section class="fanm-builder-panel">
            <h2><?php esc_html_e('Menu Layout', 'flexible-admin-nested-menu'); ?></h2>
            <div id="fanm-menu-tree" class="fanm-tree-root">
                <?php echo $tree_html; ?>
            </div>
        </section>
        <section class="fanm-builder-panel fanm-preview-panel" id="fanm-preview-panel">
            <div class="fanm-panel-heading">
                <h2><?php esc_html_e('Preview', 'flexible-admin-nested-menu'); ?></h2>
                <button id="fanm-close-preview" class="button-link fanm-panel-icon-button" type="button" aria-label="<?php esc_attr_e('Close preview', 'flexible-admin-nested-menu'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </div>
            <div id="fanm-admin-preview" class="fanm-admin-preview" aria-label="<?php esc_attr_e('WordPress admin menu preview', 'flexible-admin-nested-menu'); ?>"></div>
        </section>
        <button id="fanm-open-preview" class="fanm-open-preview" type="button" hidden aria-label="<?php esc_attr_e('Open preview', 'flexible-admin-nested-menu'); ?>" title="<?php esc_attr_e('Open preview', 'flexible-admin-nested-menu'); ?>">
            <span class="dashicons dashicons-menu" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e('Open preview', 'flexible-admin-nested-menu'); ?></span>
        </button>
    </div>
</div>
