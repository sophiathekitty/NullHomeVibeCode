<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/LightsModel.php';

/**
 * LightsController — business logic for smart lights.
 */
class LightsController extends Controller {
    private LightsModel $model;

    public function __construct() {
        parent::__construct();
        $this->model = new LightsModel();
    }

    /** Return all lights. */
    public function getAll(): array {
        return $this->model->all();
    }

    /** Return a single light by id, or null if not found. */
    public function getById(int $id): ?array {
        return $this->model->find($id);
    }

    /**
     * Toggle a light's state (0 → 1, 1 → 0).
     * Returns the updated light row, or null if not found.
     */
    public function toggle(int $id): ?array {
        $light = $this->model->find($id);
        if ($light === null) {
            return null;
        }
        $newState = $light['state'] ? 0 : 1;
        $this->model->update($id, [
            'state'             => $newState,
            'last_state_change' => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        return $this->model->find($id);
    }

    /**
     * Turn a light on.
     * Returns the updated light row, or null if not found.
     */
    public function turnOn(int $id): ?array {
        $light = $this->model->find($id);
        if ($light === null) {
            return null;
        }
        $this->model->update($id, [
            'state'             => 1,
            'last_state_change' => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        return $this->model->find($id);
    }

    /**
     * Turn a light off.
     * Returns the updated light row, or null if not found.
     */
    public function turnOff(int $id): ?array {
        $light = $this->model->find($id);
        if ($light === null) {
            return null;
        }
        $this->model->update($id, [
            'state'             => 0,
            'last_state_change' => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        return $this->model->find($id);
    }

    /**
     * Set brightness (0–100) for a light.
     * Returns the updated light row, or null if not found.
     */
    public function setBrightness(int $id, int $brightness): ?array {
        $light = $this->model->find($id);
        if ($light === null) {
            return null;
        }
        $brightness = max(0, min(100, $brightness));
        $this->model->update($id, [
            'brightness' => $brightness,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->model->find($id);
    }

    /**
     * Create a new light entry.
     *
     * @param  string      $name    The light's name.
     * @param  string|null $type    Device type (default 'light').
     * @param  string|null $subtype Role within a room: lamp, ambient, mood, etc.
     * @param  int|null    $roomId  FK to rooms.id.
     * @return array  The newly created light row.
     */
    public function create(string $name, ?string $type = 'light', ?string $subtype = null, ?int $roomId = null): array {
        $id = $this->model->insert([
            'name'              => $name,
            'type'              => $type,
            'subtype'           => $subtype,
            'room_id'           => $roomId,
            'state'             => 0,
            'brightness'        => 100,
            'last_state_change' => null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        return $this->model->find($id);
    }

    /** Delete a light by id. Returns true if it existed, false otherwise. */
    public function delete(int $id): bool {
        if ($this->model->find($id) === null) {
            return false;
        }
        $this->model->delete($id);
        return true;
    }
}
