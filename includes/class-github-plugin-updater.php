<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_GitHub_Plugin_Updater
{
    private const OWNER = 'siko001';
    private const REPO = 'wp-admin-multi-menu';
    private const SLUG = 'wp-admin-multi-menu';
    private const ZIP_ASSET = 'wp-admin-multi-menu.zip';
    private const CACHE_KEY = 'fanm_github_release';

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }

    public function inject_update($transient)
    {
        if (!is_object($transient) || empty($transient->checked) || !isset($transient->checked[$this->plugin_basename()])) {
            return $transient;
        }

        $release = $this->latest_release(true);

        if (!$release || $release['package'] === '') {
            return $transient;
        }

        if (!version_compare($release['version'], $this->installed_version(), '>')) {
            unset($transient->response[$this->plugin_basename()]);
            $transient->no_update[$this->plugin_basename()] = $this->update_payload($release);

            return $transient;
        }

        unset($transient->no_update[$this->plugin_basename()]);
        $transient->response[$this->plugin_basename()] = $this->update_payload($release);

        return $transient;
    }

    public function plugin_info($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        $release = $this->latest_release();

        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'WP Admin Multi Menu',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => '<a href="https://github.com/' . self::OWNER . '">Neil VM</a>',
            'homepage' => $this->repo_url(),
            'requires' => '5.0',
            'requires_php' => '7.4',
            'tested' => $release['tested'],
            'download_link' => $release['package'],
            'sections' => [
                'description' => 'Visual WordPress admin menu builder with support for deeply nested admin menu flyouts.',
                'changelog' => nl2br(esc_html($release['notes'] ?: 'See the GitHub release notes.')),
            ],
        ];
    }

    private function update_payload(array $release): object
    {
        return (object) [
            'id' => $this->repo_url(),
            'slug' => self::SLUG,
            'plugin' => $this->plugin_basename(),
            'new_version' => $release['version'],
            'url' => $this->repo_url(),
            'package' => $release['package'],
            'tested' => $release['tested'],
            'requires' => '5.0',
            'requires_php' => '7.4',
        ];
    }

    private function latest_release(bool $force_refresh = false): ?array
    {
        $cached = get_site_transient(self::CACHE_KEY);

        if (!$force_refresh && is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get($this->api_url(), [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'wp-admin-multi-menu-updater',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            set_site_transient(self::CACHE_KEY, null, 5 * MINUTE_IN_SECONDS);

            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($data) || empty($data['tag_name'])) {
            set_site_transient(self::CACHE_KEY, null, 5 * MINUTE_IN_SECONDS);

            return null;
        }

        $package = $this->asset_download_url($data);
        $release = [
            'version' => ltrim((string) $data['tag_name'], 'vV'),
            'package' => $package,
            'notes' => (string) ($data['body'] ?? ''),
            'tested' => $this->tested_version(),
        ];

        set_site_transient(
            self::CACHE_KEY,
            $release,
            $package === '' ? 5 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS
        );

        return $release;
    }

    private function asset_download_url(array $release): string
    {
        $assets = is_array($release['assets'] ?? null) ? $release['assets'] : [];

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            if (($asset['name'] ?? '') === self::ZIP_ASSET && !empty($asset['browser_download_url'])) {
                return (string) $asset['browser_download_url'];
            }
        }

        return '';
    }

    private function installed_version(): string
    {
        return $this->plugin_data('Version', '0.0.0');
    }

    private function tested_version(): string
    {
        return $this->plugin_data('TestedUpTo', '6.6');
    }

    private function plugin_data(string $key, string $fallback): string
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_plugin_data(FANM_FILE, false, false);

        return (string) ($data[$key] ?? $fallback);
    }

    private function plugin_basename(): string
    {
        return plugin_basename(FANM_FILE);
    }

    private function api_url(): string
    {
        return 'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest';
    }

    private function repo_url(): string
    {
        return 'https://github.com/' . self::OWNER . '/' . self::REPO;
    }
}

