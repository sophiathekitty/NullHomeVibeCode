# NullHome API Conventions

## URL Structure

All API requests are routed through `/api/index.php` via URL rewriting. Everything after `/api/` is parsed into a resource name and an ordered list of path parameters.

```
/api/{resource}/{param1}/{param2}/...
```

### Examples

| URL | `resource` | `params` |
|-----|-----------|---------|
| `/api/lights/1/toggle` | `lights` | `["1", "toggle"]` |
| `/api/lights` | `lights` | `[]` |
| `/api/wemo/scan/reset` | `wemo` | `["scan", "reset"]` |

Each resource maps to a dedicated API handler. The handler receives the params array and is responsible for interpreting them.

---

## Standard Response Envelope

All API responses return JSON with the following top-level structure:

```json
{
    "success": true,
    "data": { },
    "error": null
}
```

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `success` | `bool` | `true` if the request completed without error |
| `data` | `object\|array\|null` | The response payload; `null` on failure |
| `error` | `string\|null` | Human-readable error message; `null` on success |

Both `error` and `data` are **always present** in every response, even when `null`. This keeps the shape predictable for consumers that destructure without checking first.

### Success response

```json
{
    "success": true,
    "data": {
        "id": 1,
        "state": "on"
    },
    "error": null
}
```

### Error response

```json
{
    "success": false,
    "data": null,
    "error": "Device not found"
}
```

---

## Debug Logging

When debug mode is active, responses may include an additional `"debug"` key containing an array of log entries. This key is **omitted entirely** in normal operation — consumers should never depend on its presence.

```json
{
    "success": true,
    "data": { },
    "error": null,
    "debug": [
        "Handler loaded: lights",
        "Query: SELECT * FROM devices WHERE id = 1",
        "Execution time: 4ms"
    ]
}
```

The debug system is not yet implemented. This key is reserved and its internal structure may evolve.

---

## Legacy Compatibility

Some endpoints exist to support older NullHub systems and intentionally deviate from the standard envelope. These are documented here rather than treated as bugs.

### `/api/info/`

Returns hub identity information in the legacy NullHub response shape. The top-level key matches the resource name (`"info"`) rather than using the standard `"data"` wrapper, and the `success`/`error` fields are absent.

```json
{
    "info": {
        "url": "192.168.86.202",
        "name": "dev",
        "type": "hub",
        "is_hub": false,
        "hub": "192.168.86.90",
        "hub_name": "null pi",
        "room": "0",
        "enabled": "1",
        "main": false,
        "dev": "dev",
        "hash": "3af17efdc976cee4105b97cbc9947908d53420ed",
        "modified": "2024-04-17 15:49:25",
        "path": "\/",
        "server": "pi3ap",
        "mac_address": "b8:27:eb:b5:b6:7c",
        "git": "https:\/\/github.com\/sophiathekitty\/NullHub",
        "setup": "complete"
    }
}
```

This shape is intentional for backward compatibility. New consumers should use the standard envelope. Any additional legacy endpoints should be documented in this section.

---

## Summary

| Convention | Decision |
|-----------|---------|
| Response envelope key for payload | Always `"data"` |
| `"error"` field on success | Always present, set to `null` |
| `"data"` field on failure | Always present, set to `null` |
| Debug logs | Reserved as `"debug"` array, omitted unless active |
| Legacy endpoint deviations | Documented explicitly in this file |