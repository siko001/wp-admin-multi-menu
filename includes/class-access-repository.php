<?php

if (!defined('ABSPATH')) {
    exit;
}

class FANM_Access_Repository
{
    public const OPTION_NAME = 'fanm_menu_access_rules';

    public function all(): array
    {
        $rules = get_option(self::OPTION_NAME, []);

        if (!is_array($rules)) {
            $rules = [];
            update_option(self::OPTION_NAME, $rules);
        }

        return $rules;
    }

    public function save(array $rules): void
    {
        update_option(self::OPTION_NAME, $this->sanitize_many($rules));
    }

    public function reset(): void
    {
        update_option(self::OPTION_NAME, []);
    }

    public function rule_for(string $role, string $item_id, array $item): array
    {
        $rules = $this->all();

        if (isset($rules[$role][$item_id])) {
            return array_merge($this->default_rule($role, $item), $rules[$role][$item_id]);
        }

        return $this->default_rule($role, $item);
    }

    public function default_rule(string $role, array $item): array
    {
        if ($role === 'administrator') {
            return [
                'visible' => true,
                'access' => true,
            ];
        }

        $role_object = get_role($role);
        $capability = (string) ($item['cap'] ?? 'read');
        $has_capability = $role_object && !empty($role_object->capabilities[$capability]);

        return [
            'visible' => $has_capability,
            'access' => $has_capability,
        ];
    }

    private function sanitize_many(array $rules): array
    {
        $sanitized = [];

        foreach ($rules as $role => $items) {
            $role = sanitize_key($role);

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item_id => $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $sanitized[$role][sanitize_key($item_id)] = [
                    'visible' => !empty($rule['visible']),
                    'access' => !empty($rule['access']),
                ];
            }
        }

        return $sanitized;
    }
}
