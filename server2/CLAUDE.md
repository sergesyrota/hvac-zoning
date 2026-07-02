# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A PHP cron daemon that controls physical HVAC vents per-room ("zone") based on what a master
thermostat is doing. It reads current state from thermostat adapters (Nest, Homebridge, or a
generic CLI adapter), decides which zones should be open/closed, and drives vent adapters
(currently RS485v1 hardware over a Gearman job queue) to move them. It does not schedule
temperatures or make comfort decisions itself — it only reacts to calls already made by
thermostats and enforces zone/airflow logic.

**Production runs on the Homebridge adapter** (`zones/*.hb.json`), invoked via cron every
minute. Nest support exists in the code and in `zones/bedrooms.json` but is not the live
deployment — see the Nest cache reads that are commented out/disabled in `nagios_check.php`.

## Commands

Install dependencies (pulls `sergesyrota/syrota-automation` from a private VCS repo declared in
`composer.json`):

```
composer install
```

Run the app once (production cron schedule is every minute):

```
env - $(cat ./.env) php ./cron.php
```

Run the airflow logic tests (plain `assert()` based, not PHPUnit — requires zend assertions
enabled, which is the PHP CLI default):

```
php tests/airflow.php
```

Run the PHPUnit adapter tests:

```
vendor/bin/phpunit tests/nestAdapter.php
```

There is no single "run all tests" command wiring the two together — run them separately.

## Configuration model

Two files drive a running instance, both referenced via environment variables (see
`.env.example`):

- **`TSTATS_JSON`** — the zone/equipment config (e.g. `zones/bedrooms.json` or the
  `zones/*.hb.json` Homebridge variant of the same rooms). Has a `parameters` object (system-wide
  settings like `min_airflow`, `state_expiration`, `override_activate_time`,
  `check_vent_errors`) and a `zones` object keyed by numeric zone ID. Exactly one zone must have
  `"master": true` — that's the zone whose thermostat is wired to the actual heating/cooling
  equipment; all other zones are "slaves" that only have vents.
- **`STATE_FILE`** — a JSON blob of runtime state (`last_update`, `init_time`,
  `override_present`, `master_checksum`, `override_set_time`, `vent_error_timestamp`,
  `homebridge_unique_id`) persisted between cron runs. It self-invalidates and resets to empty if
  stale beyond `state_expiration` seconds. Never hand-edit the schema without updating both
  `App::getEmptyState()` and every reader of `$this->state`. `App::run()` calls `initState()`
  before `initEquipment()` specifically so this cache is available when thermostat adapters are
  constructed — don't reorder those calls.

Thermostat/vent connection configs reference **environment variable names as strings**, not
literal secrets (e.g. `"bearer_token": "NEST_TOKEN"` means "read the actual token from
`getenv('NEST_TOKEN')`"). This indirection is intentional so zone JSON files can be committed
without leaking credentials.

## Architecture

Entry point chain: `cron.php` → `bootstrap.php` → `app.php` (class `App`).

`cron.php` does PID-file-based re-run protection (skips silently if a previous run's PID file is
younger than 300s) before constructing `App` and calling `run()`.

`bootstrap.php` is the composer-autoload substitute for the `adapters/vent/*` classes — they are
**not** in composer's classmap (only `adapters/thermostat` is, via `composer.json`
`autoload.classmap`), so they're required manually here.

**Adapter pattern** — two independent adapter families, each with an interface + factory:

- Thermostats (`Thermostat\iThermostat`, `Thermostat\Factory::get()`): `cli`, `nest`,
  `homebridge`. Every thermostat exposes `getMode()` (auto/heat/cool/off) and `getCall()`
  (heat/cool/off — what it's actually doing right now). Nest and Homebridge additionally support
  `setOverride()`/`removeOverride()`/`getChecksum()`, used only for the master zone. Real API
  access is delegated to a `Connector\*` class per adapter (`adapters/thermostat/connector/`);
  the adapter class itself only translates connector data into `iThermostat` semantics.
  `Thermostat\Factory::get()` takes the app's `$state` as a second argument so the homebridge
  branch can hand its connector a live reference into `$state->homebridge_unique_id`.
- **Homebridge accessory resolution**: Homebridge's own `uniqueId` is *not* stable across
  Homebridge restarts (it can be regenerated for many accessories at once). Zone config
  (`connection.serial_number`, an env var name like Nest's `bearer_token`) instead points at the
  accessory's `accessoryInformation.SerialNumber`, which is stable. The connector
  (`adapters/thermostat/connector/homebridge.php`) resolves serial number → `uniqueId` by
  fetching the full `/api/accessories` list only when needed (no cached value, or a "not found"
  400 from Homebridge on a direct-by-`uniqueId` call), caches the result in
  `$state->homebridge_unique_id` (keyed by serial number, mutated in place since PHP passes
  `stdClass` by handle), and retries the failed request exactly once with the freshly-resolved
  id. There is no `device_id`/uniqueId field left in zone config — it only ever lives in the
  cache.
- Vents (`Vent\iVent`, `VentFactory::get()`): currently only `rs485v1`, which talks to hardware
  through `SyrotaAutomation\Gearman` (from the private `syrota-automation` package) rather than
  a direct connection. `setOpen($percent)` converts percent-open into a rotation angle via
  `asin()`. Vent adapters also implement self-healing (`errorPresent()` /
  `selfHeal()` — reverses, recalibrates, and re-checks).

**`App::run()` decision flow** (in `app.php`):
1. Read the master zone's mode; if `MODE_AUTO`, substitute its current `getCall()` so the rest of
   the logic only ever deals with heat/cool/off.
2. If master mode is `off`: close the master zone's vents, open everything else, and return early
   — no override logic applies when the master isn't calling for anything.
3. Otherwise, for every non-master zone, open it only if its `getCall()` matches the master's
   mode; else close it.
4. **Master override**: if other zones want to run but the master thermostat isn't calling
   (e.g. master room already satisfied), and no override is already active, and the system has
   been in a stable state for at least `override_activate_time` seconds, nudge the master's
   target temperature via `setOverride()` so it starts calling too (sacrificing master-zone
   comfort to keep airflow to other zones). The override is tracked by a `getChecksum()` snapshot
   so that if a human changes the thermostat externally, the code won't try to "helpfully" revert
   a change it didn't make.
   - Auto mode never gets an override (ambiguous whether heat or cool would collide).
5. Once no zones need the override anymore, `removeOverride()` is called — but only if the
   checksum still matches what was set, otherwise the temperature is left alone and only the
   `override_present` flag is cleared.
6. `enforceMinAirflow()` (backed by `lib/airflow.php`'s `Airflow` class) adjusts the final vent
   percentages so total open airflow never drops below `min_airflow`: it first opens up non-master
   zones proportionally, and only opens the master zone if non-master zones alone can't reach the
   minimum.
7. `executeVentMoves()` sorts zones from most-open to most-closed and applies moves with a
   `$delay` (default 2s) between each vent command — likely to avoid overloading whatever bus/host
   the Gearman worker sits on. Optionally checks `errorPresent()` on each vent first (gated by
   `check_vent_errors` config) and triggers self-heal. If any vent move throws, the whole zone set
   is reset to each zone's configured `defaultOpen` percentage as a fail-safe, then the exception
   is re-thrown.

`lib/airflow.php`'s `Airflow` class is pure calculation with no side effects — it's the one part
of the system with real unit tests (`tests/airflow.php`), useful as a spec for expected behavior
when changing min-airflow logic.

## Standalone scripts (not part of the cron flow)

- `bedroomAcState.php` and `nagios_check.php` are separate, ad-hoc entry points (a status
  webpage and a Nagios health check) that read `zones/.env.bedrooms` directly via a hand-rolled
  `getCustomEnv()` line parser — not real dotenv, and not routed through `bootstrap.php`/`App`.
  They read `STATE_FILE` and (historically) a Nest response cache file directly; treat them as
  independent read-only tools, not something `App` depends on.

## Gotchas specific to this codebase

- Zone IDs are strings from JSON keys (`"1"`, `"2"`, ...) but are also used as PHP array/object
  keys throughout — comparisons like `$this->masterZoneId == $id` rely on loose `==` between
  string and possibly-int forms. Be careful introducing strict `===` comparisons here.
- `getMode()` vs `getCall()` are not interchangeable: mode is what the thermostat is *set* to,
  call is what it's *currently doing*. Auto mode has no direct "call" equivalent from the zone
  config's perspective — `App::run()` resolves auto by substituting the master's `getCall()`.
- Two zone config files exist for the same physical rooms (`zones/bedrooms.json` for Nest,
  `zones/bedrooms.hb.json` for Homebridge) — **production uses the Homebridge (`.hb.json`)
  variant**; the Nest one is not currently live. Confirm via `.env` / `TSTATS_JSON` before
  assuming Nest-specific behavior applies.
