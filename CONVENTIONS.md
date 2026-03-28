# Project Conventions

This document is the authoritative reference for how this project is structured and how code must be written and organized. All contributors and automated agents must follow these rules.

---

## 1. Directory Structure

```
/api          → API endpoint handlers. Return JSON only. Never include view files.
/app          → Front-end app entry point (index.php). Thin shell only — no logic.
/controllers  → HTTP-aware controllers. Coordinate requests and delegate to models/modules.
/install      → Install wizard entry point (index.php). Thin shell only — no logic.
/models       → Database-backed model classes. One class per table.
/modules      → HTTP-unaware backend logic. Callable from controllers, API handlers, tests, and cron jobs.
  /db         → Database class, query builder, and model validation (e.g. DatabaseValidationService).
  /devices    → Device drivers: WemoDriver.php, TuyaDriver.php, LightGroupDispatcher.php
  /weather    → External weather API clients: OpenWeatherMap.php, NullHubWeather.php
  /install    → Installer.php — handles config.php and config.test.php file creation
  /hub        → FailoverManager.php, Heartbeat.php
/views        → PHP template partials. Receive variables only — no logic, no DB calls.
/services     → Cron job and bash script entry points (e.g. every_minute.php, reboot.php). Thin shells only — delegate all logic to modules.
/tests        → PHPUnit test classes.
/public       → Publicly served static assets (CSS, JS, images).
```

---

## 2. Separation of Concerns

- **PHP and HTML must be separated.** View files in `/views` may use PHP only for outputting variables (e.g. `<?= $title ?>`). No logic, no database calls, no conditionals beyond simple display toggles.
- **Controllers and API handlers are the only entry points for HTTP requests.** They delegate logic to models and modules; they do not implement logic themselves.
- **Modules have no knowledge of HTTP.** They must not reference `$_POST`, `$_GET`, `$_SESSION`, or any superglobal. All inputs are passed as arguments.
- **API endpoints return JSON only.** They call `json_encode()` and `exit`. They never include or require a view file.
- **Device-specific logic is isolated to `/modules/devices`.** Controllers, API handlers, and the LightGroup model never call Wemo or Tuya APIs directly. All device communication goes through `WemoDriver` or `TuyaDriver`.
- **CSS must live in `/public/css`.** No inline styles. No `<style>` blocks in view or template files.
- **JavaScript must live in `/public/js`.** No inline scripts. No `<script>` blocks with logic in view or template files. Script tags in templates may only reference external `.js` files.

---

## 3. Naming Conventions

- **PHP classes:** `PascalCase`. One class per file. Filename matches class name exactly (e.g. `LightGroup.php`).
- **PHP methods and variables:** `camelCase`.
- **Database columns:** `snake_case`.
- **View/template files:** `kebab-case.php` (e.g. `device-list.php`).
- **CSS files:** `kebab-case.css`.
- **JavaScript files:** `kebab-case.js`.
- **Module subdirectories:** lowercase, no hyphens (e.g. `/modules/devices`, `/modules/weather`).
- **Constants:** `UPPER_SNAKE_CASE` (as used in `config.php`).

---

## 4. PHPDoc Commenting Standards

Every PHP class and every public and protected method must have a PHPDoc block. Private methods should also be documented. Constructors must be documented.

Required tags:
- `@param` for every parameter, including type and description
- `@return` for every method that returns a value (use `void` if nothing is returned)
- `@throws` if the method can throw an exception

Use the most specific type available. Prefer `array<string, mixed>` over `array` for associative arrays. Use PHP union types where applicable (e.g. `int|null`). Nullable parameters must be `?Type`.

For query builder chainable methods, document the return type as `static`.

**Example:**

```php
/**
 * Calculates a weighted average incorporating a new sample into an existing average.
 *
 * @param float $existingAvg The currently stored average value.
 * @param int $sampleCount The number of samples already incorporated.
 * @param float $newAvg The new sample value to incorporate.
 * @return float The updated weighted average.
 */
public function weightedAverage(float $existingAvg, int $sampleCount, float $newAvg): float
```

---

## 5. JSDoc Commenting Standards

Every JavaScript function must have a JSDoc block.

Required tags:
- `@param {type} name` for every parameter
- `@returns {type}` for every function that returns a value
- `@async` for async functions (in addition to `@returns {Promise<type>}`)

**Example:**

```javascript
/**
 * Sends a state change command to the lights API.
 *
 * @async
 * @param {string} groupId - The light group identifier.
 * @param {boolean} state - True to turn on, false to turn off.
 * @returns {Promise<Object>} The JSON response from the API.
 */
async function setLightGroupState(groupId, state) {
```

---

## 6. MVC Layering Summary

| Layer | Location | HTTP-aware? | DB access? | Calls modules? |
|---|---|---|---|---|
| API handler | `/api` | Yes | Via models | Yes |
| Controller | `/controllers` | Yes | Via models | Yes |
| Model | `/models` | No | Yes | No |
| Module | `/modules` | No | Via models | N/A |
| View | `/views` | No | No | No |
