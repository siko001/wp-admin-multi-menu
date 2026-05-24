<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Page_Callbacks
{
    public function render(array $menu): void
    {
        $callback = $menu['callback'] ?? '';

        if ($callback && function_exists($callback)) {
            call_user_func($callback, $menu);
            return;
        }

        $this->default($menu);
    }

    public function dashboard(array $menu): void
    {
        $title = esc_html($menu['title'] ?? __('Dashboard', 'flexible-admin-nested-menu'));
        $heading = esc_html__('Welcome to Dashboard', 'flexible-admin-nested-menu');
        $body = esc_html__('This is your custom dashboard page. You can customize this content by editing the callback function.', 'flexible-admin-nested-menu');

        echo <<<HTML
                <div class="wrap">
                    <h1>{$title}</h1>
                    <div class="dashboard-widgets-wrap">
                        <div class="metabox-holder">
                            <div class="postbox">
                                <h2 class="hndle">{$heading}</h2>
                                <div class="inside"><p>{$body}</p></div>
                            </div>
                        </div>
                    </div>
                </div>
                HTML;
    }

    public function analytics(array $menu): void
    {
        $title = esc_html($menu['title'] ?? __('Analytics', 'flexible-admin-nested-menu'));
        $notice = esc_html__('Analytics dashboard - integrate with Google Analytics or another tracking service.', 'flexible-admin-nested-menu');
        $heading = esc_html__('Site Statistics', 'flexible-admin-nested-menu');
        $body = esc_html__('Visitors: 1,234 | Page Views: 5,678 | Bounce Rate: 45%', 'flexible-admin-nested-menu');

        echo <<<HTML
                <div class="wrap">
                    <h1>{$title}</h1>
                    <div class="notice notice-info"><p>{$notice}</p></div>
                    <div class="postbox">
                        <h2>{$heading}</h2>
                        <div class="inside"><p>{$body}</p></div>
                    </div>
                </div>
                HTML;
    }

    public function reports(array $menu): void
    {
        $title = esc_html($menu['title'] ?? __('Reports', 'flexible-admin-nested-menu'));
        $heading = esc_html__('Generated Reports', 'flexible-admin-nested-menu');
        $body = esc_html__('No reports available yet. Configure your reporting settings.', 'flexible-admin-nested-menu');

        echo <<<HTML
                <div class="wrap">
                    <h1>{$title}</h1>
                    <div class="postbox">
                        <h2>{$heading}</h2>
                        <div class="inside"><p>{$body}</p></div>
                    </div>
                </div>
                HTML;
    }

    public function users(array $menu): void
    {
        $title = esc_html($menu['title'] ?? __('User Management', 'flexible-admin-nested-menu'));
        $heading = esc_html__('User Management', 'flexible-admin-nested-menu');
        $body = esc_html__('Manage user roles, permissions, and profiles from this central location.', 'flexible-admin-nested-menu');

        echo <<<HTML
                <div class="wrap">
                    <h1>{$title}</h1>
                    <div class="postbox">
                        <h2>{$heading}</h2>
                        <div class="inside"><p>{$body}</p></div>
                    </div>
                </div>
                HTML;
    }

    public function default(array $menu): void
    {
        $title = esc_html($menu['title'] ?? __('Menu Page', 'flexible-admin-nested-menu'));
        $body = esc_html__('Default page. Edit callback in builder for custom content.', 'flexible-admin-nested-menu');

        echo <<<HTML
                <div class="wrap">
                    <h1>{$title}</h1>
                    <p>{$body}</p>
                </div>
                HTML;

        settings_errors();
    }
}

function fanm_dashboard_cb($menu): void
{
    (new FANM_Page_Callbacks())->dashboard((array) $menu);
}

function fanm_analytics_cb($menu): void
{
    (new FANM_Page_Callbacks())->analytics((array) $menu);
}

function fanm_reports_cb($menu): void
{
    (new FANM_Page_Callbacks())->reports((array) $menu);
}

function fanm_users_cb($menu): void
{
    (new FANM_Page_Callbacks())->users((array) $menu);
}

function fanm_settings_cb($menu): void
{
    (new FANM_Page_Callbacks())->default((array) $menu);
}

function fanm_advanced_cb($menu): void
{
    (new FANM_Page_Callbacks())->default((array) $menu);
}

