# NullHome

A lightweight, self-hosted home automation platform written in PHP, designed to
run on a **Raspberry Pi**. No frameworks — just a clean custom MVC-style
architecture on top of PHP 8+ and MySQL.

---

## Folder Structure

```
/
├── index.php                  Root entry point (serves the SPA)
├── config.php                 Database connection constants
├── .htaccess                  Apache rewrite rules (clean URLs)
│
├── app/                       Frontend single-page application
│   ├── index.php              PHP-rendered HTML shell
│   ├── css/
│   │   └── style.css          Minimal dark-theme styles
│   └── js/
│       └── app.js             jQuery-based SPA logic
│
├── api/                       RESTful JSON API
│   ├── index.php              Single entry point — parses URL path, dispatches to handlers
│   └── handlers/
│       ├── ApiHandler.php     Abstract base handler (JSON response envelope)
│       ├── LightsHandler.php  Handles /api/lights/… requests
│       └── SettingsHandler.php Handles /api/settings/… requests
│
├── models/                    Model classes (define DB table structure)
│   ├── Model.php              Abstract base model (CRUD helpers)
│   ├── LightsModel.php        lights table
│   └── SettingsModel.php      settings key/value table
│
├── controllers/               Business logic classes
│   ├── Controller.php         Abstract base controller
│   └── LightsController.php   Lights state management
│
├── db/
│   └── DB.php                 PDO wrapper + automatic schema sync
│
└── services/                  Cron / startup entry points
    ├── reboot.php             Runs on boot — syncs DB schema, seeds defaults
    ├── every_minute.php       Runs every minute
    ├── every_10_minutes.php   Runs every 10 minutes
    └── every_hour.php         Runs every hour
```

---

## Conventions

### PHP
- **PHP 8+** required; no Composer dependencies unless essential.
- All files use `require_once` for class loading — no autoloader.
- `config.php` defines `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
  as constants.

### Models

Each model extends `Model` and defines:
- `getTable(): string` — MySQL table name.
- `getFields(): array` — ordered list of field definition arrays.

**Field definition array keys:**

| Key        | Type         | Description                                      |
|------------|--------------|--------------------------------------------------|
| `name`     | `string`     | Column name                                      |
| `type`     | `string`     | MySQL type (`VARCHAR`, `INT`, `TINYINT`, `TEXT`, `TIMESTAMP`, …) |
| `length`   | `int\|null`  | Column length / precision, or `null`             |
| `nullable` | `bool`       | Whether the column allows `NULL` (default `true`) |
| `default`  | `mixed\|null`| Default value, or `null`                         |

`DB::sync($model)` is called automatically by Model helpers and compares the
field definitions against the live MySQL schema — creating the table or adding /
modifying columns as needed. An `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
column is always created automatically.

### Database (DB class)

`DB::connection()` — returns the shared PDO instance (lazy init).  
`DB::query($sql, $params)` — prepare + execute a parameterised query, returns `PDOStatement`.  
`DB::sync($model)` — create or sync the table for a model.

### API

All requests are routed through `api/index.php`.  
URL path segments are parsed into a parameter array, e.g.:

```
/api/lights/1/toggle  →  resource="lights", params=["1","toggle"]
/api/lights           →  resource="lights", params=[]
```

**Response envelope** (always JSON):

```json
{ "success": true,  "data": { … }, "error": null }
{ "success": false, "data": null,  "error": "message" }
```

#### Lights API (`/api/lights`)

| Method   | Path                          | Description                          |
|----------|-------------------------------|--------------------------------------|
| `GET`    | `/api/lights`                 | List all lights                      |
| `GET`    | `/api/lights/{id}`            | Get a single light                   |
| `POST`   | `/api/lights`                 | Create a light `{name, location?}`   |
| `DELETE` | `/api/lights/{id}`            | Delete a light                       |
| `POST`   | `/api/lights/{id}/toggle`     | Toggle on/off                        |
| `POST`   | `/api/lights/{id}/on`         | Turn on                              |
| `POST`   | `/api/lights/{id}/off`        | Turn off                             |
| `POST`   | `/api/lights/{id}/brightness` | Set brightness `{value: 0–100}`      |

#### Settings API (`/api/settings`)

| Method   | Path                       | Description                             |
|----------|----------------------------|-----------------------------------------|
| `GET`    | `/api/settings`            | List all settings                       |
| `GET`    | `/api/settings/{key}`      | Get a single setting value              |
| `POST`   | `/api/settings`            | Set a setting `{key, value}`            |
| `DELETE` | `/api/settings/{key}`      | Delete a setting                        |

### Controllers

Controllers extend `Controller`, hold a model instance, and implement business
rules. API handlers instantiate controllers; service scripts may also use them.

### Services (cron)

Add these to `/etc/crontab` or the Pi's `crontab -e`:

```cron
@reboot          php /var/www/html/services/reboot.php           >> /var/log/nullhome.log 2>&1
* * * * *        php /var/www/html/services/every_minute.php     >> /var/log/nullhome.log 2>&1
*/10 * * * *     php /var/www/html/services/every_10_minutes.php >> /var/log/nullhome.log 2>&1
0 * * * *        php /var/www/html/services/every_hour.php       >> /var/log/nullhome.log 2>&1
```

### JavaScript

- jQuery-based; no build step.
- All API calls use `$.ajax` with `Content-Type: application/json`.
- Avoid unnecessary complexity — keep it readable and functional.

---

## Setup (Raspberry Pi)

```bash
# 1. Install dependencies
sudo apt-get install apache2 php php-mysql mysql-server

# 2. Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# 3. Create the database
mysql -u root -p
  CREATE DATABASE nullhome CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'nullhome'@'localhost' IDENTIFIED BY 'changeme';
  GRANT ALL ON nullhome.* TO 'nullhome'@'localhost';
  FLUSH PRIVILEGES;

# 4. Deploy files to web root
sudo cp -r . /var/www/html/

# 5. Copy and edit config
sudo nano /var/www/html/config.php   # set DB_PASS etc.

# 6. Run the boot script to create all tables
php /var/www/html/services/reboot.php

# 7. Install cron jobs
crontab -e
# (add the cron lines from above)
```

Visit `http://<raspberry-pi-ip>/` to open the NullHome dashboard.

---

## Running Tests

Tests use PHPUnit and run against a real MySQL database (`homehub_test`). They
never touch your production database.

```bash
# 1. Copy the example config to the test config and edit with your local test DB credentials
cp config.php.example config.test.php
# edit config.test.php — set DB_NAME to 'homehub_test' and use a local MySQL user

# 2. Install PHPUnit (dev dependencies only)
composer install

# 3. Run the test suite
./vendor/bin/phpunit --testdox
```

> **Important:** `config.test.php` is gitignored. Never run tests against your
> real database — always use a dedicated test database (e.g. `homehub_test`).
