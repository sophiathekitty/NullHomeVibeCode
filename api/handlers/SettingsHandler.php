<?php
require_once __DIR__ . '/ApiHandler.php';
require_once __DIR__ . '/../../models/SettingsModel.php';
require_once __DIR__ . '/../../modules/db/DB.php';

/**
 * SettingsHandler — handles /api/settings/… requests.
 *
 * Routes:
 *   GET    /api/settings          → list all settings
 *   GET    /api/settings/{key}    → get a single setting value
 *   POST   /api/settings          → set a setting (body: { key, value, label? })
 *   DELETE /api/settings/{key}    → delete a setting
 */
class SettingsHandler extends ApiHandler {
    private SettingsModel $model;

    public function __construct() {
        $this->model = new SettingsModel();
    }

    public function handle(array $params, string $method, array $body): void {
        $key = $params[0] ?? null;

        if ($key === null) {
            if ($method === 'GET') {
                $this->ok($this->model->all());
                return;
            }
            if ($method === 'POST') {
                $k = trim($body['key'] ?? '');
                $v = $body['value'] ?? '';
                if ($k === '') {
                    $this->error('key is required');
                    return;
                }
                $this->model->set($k, (string) $v);
                $this->ok(['key' => $k, 'value' => $v]);
                return;
            }
            $this->methodNotAllowed();
            return;
        }

        if ($method === 'GET') {
            $value = $this->model->get($key);
            if ($value === null) {
                $this->notFound("Setting '$key' not found");
                return;
            }
            $this->ok(['key' => $key, 'value' => $value]);
            return;
        }

        if ($method === 'DELETE') {
            DB::sync($this->model);
            $row = DB::query(
                'SELECT id FROM `settings` WHERE `key` = ? LIMIT 1',
                [$key]
            )->fetch();
            if (!$row) {
                $this->notFound("Setting '$key' not found");
                return;
            }
            $this->model->delete((int) $row['id']);
            $this->ok();
            return;
        }

        $this->methodNotAllowed();
    }
}
