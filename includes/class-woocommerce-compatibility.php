<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_WooCommerce_Compatibility
{
    public function hooks(): void
    {
        add_action('admin_init', [$this, 'repair_administrator_caps'], 5);
        add_action('admin_menu', [$this, 'hide_deprecated_reports'], 9999);
    }

    public function repair_administrator_caps(): void
    {
        if (!$this->is_woocommerce_active() || !current_user_can('manage_options')) {
            return;
        }

        $role = get_role('administrator');

        if (!$role) {
            return;
        }

        foreach ($this->administrator_caps() as $cap) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }
    }

    public function hide_deprecated_reports(): void
    {
        if (!$this->is_woocommerce_active()) {
            return;
        }

        remove_menu_page('wc-reports');
    }

    private function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce') || defined('WC_PLUGIN_FILE');
    }

    /**
     * @return string[]
     */
    private function administrator_caps(): array
    {
        return [
            'manage_woocommerce',
            'view_woocommerce_reports',
            'edit_product',
            'read_product',
            'delete_product',
            'edit_products',
            'edit_others_products',
            'publish_products',
            'read_private_products',
            'delete_products',
            'delete_private_products',
            'delete_published_products',
            'delete_others_products',
            'edit_private_products',
            'edit_published_products',
            'manage_product_terms',
            'edit_product_terms',
            'delete_product_terms',
            'assign_product_terms',
            'edit_shop_orders',
            'read_shop_order',
            'delete_shop_order',
            'edit_shop_coupons',
            'read_shop_coupon',
            'delete_shop_coupon',
        ];
    }
}
