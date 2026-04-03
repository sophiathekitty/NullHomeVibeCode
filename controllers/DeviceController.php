<?php
require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/Device.php';

/**
 * DeviceController — business logic for smart home devices.
 *
 * HTTP-aware layer that delegates all data access to the Device model.
 * Returns plain associative arrays (via Device::toArray()) suitable for
 * JSON serialisation by the API handler.
 */
class DeviceController extends Controller
{
    /**
     * Constructor — bootstraps the parent controller.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Return all devices as an array of plain arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return array_map(
            static fn(Device $d) => $d->toArray(),
            Device::findAll()
        );
    }

    /**
     * Return a single device by id, or null if not found.
     *
     * @param int $id The device's primary key.
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $device = Device::findById($id);
        return $device?->toArray();
    }

    /**
     * Return all devices assigned to a given room.
     *
     * @param int $roomId The room's primary key.
     * @return array<int, array<string, mixed>>
     */
    public function getByRoom(int $roomId): array
    {
        return array_map(
            static fn(Device $d) => $d->toArray(),
            Device::findByRoom($roomId)
        );
    }

    /**
     * Return all devices of a given type.
     *
     * @param string $type One of: 'light', 'device'.
     * @return array<int, array<string, mixed>>
     */
    public function getByType(string $type): array
    {
        return array_map(
            static fn(Device $d) => $d->toArray(),
            Device::findByType($type)
        );
    }

    /**
     * Create a new device.
     *
     * @param string      $name       Human-readable device name.
     * @param string      $type       Device type: 'light' or 'device'.
     * @param string|null $subtype    Optional subtype, e.g. 'ceiling', 'fan'.
     * @param string|null $color      Optional hex colour string, e.g. '#ff8800'.
     * @param int|null    $roomId     Optional FK to rooms.id.
     * @param int|null    $brightness Optional brightness 0–100.
     * @return array<string, mixed> The newly created device as a plain array.
     * @throws \PDOException If the database query fails.
     */
    public function create(
        string $name,
        string $type = 'light',
        ?string $subtype = null,
        ?string $color = null,
        ?int $roomId = null,
        ?int $brightness = null
    ): array {
        $stub = new Device();
        $id   = $stub->insert([
            'name'       => $name,
            'type'       => $type,
            'subtype'    => $subtype,
            'color'      => $color,
            'room_id'    => $roomId,
            'brightness' => $brightness,
            'state'      => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return Device::findById($id)?->toArray() ?? [];
    }

    /**
     * Toggle a device's state (off → on, on → off).
     *
     * @param int $id The device's primary key.
     * @return array<string, mixed>|null The updated device, or null if not found.
     * @throws \PDOException If the database query fails.
     */
    public function toggle(int $id): ?array
    {
        $device = Device::findById($id);
        if ($device === null) {
            return null;
        }
        $device->setState(!$device->getState());
        return Device::findById($id)?->toArray();
    }

    /**
     * Turn a device on.
     *
     * @param int $id The device's primary key.
     * @return array<string, mixed>|null The updated device, or null if not found.
     * @throws \PDOException If the database query fails.
     */
    public function turnOn(int $id): ?array
    {
        $device = Device::findById($id);
        if ($device === null) {
            return null;
        }
        $device->setState(true);
        return Device::findById($id)?->toArray();
    }

    /**
     * Turn a device off.
     *
     * @param int $id The device's primary key.
     * @return array<string, mixed>|null The updated device, or null if not found.
     * @throws \PDOException If the database query fails.
     */
    public function turnOff(int $id): ?array
    {
        $device = Device::findById($id);
        if ($device === null) {
            return null;
        }
        $device->setState(false);
        return Device::findById($id)?->toArray();
    }

    /**
     * Set the brightness for a device.
     *
     * @param int $id         The device's primary key.
     * @param int $brightness Value between 0 and 100 inclusive.
     * @return array<string, mixed>|null The updated device, or null if not found.
     * @throws \InvalidArgumentException If brightness is outside 0–100.
     * @throws \PDOException             If the database query fails.
     */
    public function setBrightness(int $id, int $brightness): ?array
    {
        $device = Device::findById($id);
        if ($device === null) {
            return null;
        }
        $brightness = max(0, min(100, $brightness));
        $device->setBrightness($brightness);
        return Device::findById($id)?->toArray();
    }

    /**
     * Delete a device by id.
     *
     * @param int $id The device's primary key.
     * @return bool True if the device existed and was deleted, false otherwise.
     * @throws \PDOException If the database query fails.
     */
    public function delete(int $id): bool
    {
        $device = Device::findById($id);
        if ($device === null) {
            return false;
        }
        return $device->delete();
    }
}
