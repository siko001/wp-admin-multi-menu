<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="wrap fanm-builder">
    <h1><?php esc_html_e('Flexible Admin Nested Menu Builder', 'flexible-admin-nested-menu'); ?></h1>
    <p class="description">
        <?php esc_html_e('Drag to nest or reorder items. Save to apply the custom structure to the WordPress admin sidebar.', 'flexible-admin-nested-menu'); ?>
    </p>

    <div class="fanm-toolbar">
        <button id="fanm-add-root" class="button button-primary" type="button">
            <?php esc_html_e('Add Top-Level Menu', 'flexible-admin-nested-menu'); ?>
        </button>
        <button id="fanm-import-demo" class="button" type="button">
            <?php esc_html_e('Import Demo', 'flexible-admin-nested-menu'); ?>
        </button>
        <button id="fanm-import-json" class="button" type="button">
            <?php esc_html_e('Import JSON', 'flexible-admin-nested-menu'); ?>
        </button>
        <button id="fanm-export-json" class="button" type="button">
            <?php esc_html_e('Export JSON', 'flexible-admin-nested-menu'); ?>
        </button>
        <button id="fanm-save-all" class="button button-large button-primary" type="button">
            <?php esc_html_e('Save & Apply Menus', 'flexible-admin-nested-menu'); ?>
        </button>
    </div>

    <div id="fanm-menu-tree" class="fanm-tree-root">
        <?php echo $tree_html; ?>
    </div>

    <div class="fanm-preview">
        <h2><?php esc_html_e('Live Preview', 'flexible-admin-nested-menu'); ?></h2>
        <iframe src="<?php echo esc_url($admin_url); ?>" title="<?php esc_attr_e('WordPress admin menu preview', 'flexible-admin-nested-menu'); ?>"></iframe>
    </div>

    <template id="fanm-import-modal-template">
        <div class="fanm-modal" id="fanm-import-modal">
            <div class="fanm-modal-content">
                <div class="fanm-modal-header">
                    <h2><?php esc_html_e('Import Menus from JSON', 'flexible-admin-nested-menu'); ?></h2>
                    <button class="fanm-modal-close" type="button" aria-label="<?php esc_attr_e('Close modal', 'flexible-admin-nested-menu'); ?>">&times;</button>
                </div>
                <div class="fanm-modal-body">
                    <p><?php esc_html_e('Paste your JSON menu configuration below:', 'flexible-admin-nested-menu'); ?></p>
                    <textarea id="fanm-import-textarea" placeholder='{"menu_id": {"title": "Menu Title"}}'></textarea>
                </div>
                <div class="fanm-modal-footer">
                    <button class="button" id="fanm-cancel-import" type="button">
                        <?php esc_html_e('Cancel', 'flexible-admin-nested-menu'); ?>
                    </button>
                    <button class="button button-primary" id="fanm-do-import" type="button">
                        <?php esc_html_e('Import', 'flexible-admin-nested-menu'); ?>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
