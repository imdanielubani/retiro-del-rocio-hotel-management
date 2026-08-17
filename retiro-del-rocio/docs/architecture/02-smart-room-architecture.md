# Smart Room Architecture

## Database

Three new tables. `RoomUnit` (existing) becomes the parent of Tuya devices,
mirroring how it's already the parent of `Device` (tablets).

### `smart_devices`

One row per physical Tuya device, assigned to exactly one room.

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| room_unit_id | FK `room_units`, nullable | null = discovered from Tuya but not yet assigned to a room |
| name | string | admin-editable label, e.g. "Bedside Left" |
| type | string, indexed | `light`\|`ac`\|`curtain`\|`tv` — open string, not enum-locked, so new types don't need a migration |
| provider | string, default `tuya` | future-proofing per `SmartDeviceProviderInterface` below |
| provider_device_id | string, unique | Tuya's `device_id` |
| provider_product_id | string, nullable | Tuya `product_id` — used to re-fetch spec/capabilities |
| capabilities | json | normalized capability map, see below — cached from Tuya's device-specification response, refreshed on sync |
| last_state | json, nullable | last known DP values, cached for instant UI paint before a live status fetch resolves |
| status | string, default `unknown` | `online`\|`offline`\|`unknown` — from Tuya's device status, refreshed on sync/heartbeat, not guest-triggered |
| last_synced_at | timestamp, nullable | |
| is_active | boolean, default true | admin can disable a device (e.g. removed from room) without losing history |
| sort_order | unsigned int, default 0 | admin-controlled display order within a room |
| timestamps | | |

Indexes: `room_unit_id`, `provider_device_id` (unique), `type`.

### `smart_scenes`

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| room_id | FK `rooms`, nullable | category-level template (e.g. every Deluxe Room gets a "Welcome" scene) |
| room_unit_id | FK `room_units`, nullable | room-specific override/addition; a room-level scene takes precedence over a same-slug category template |
| name | string | |
| slug | string | e.g. `welcome`, `relax`, `sleep`, `checkout` — not globally unique, unique per (room_id, room_unit_id) |
| icon | string, nullable | icon key for the Flutter UI |
| sort_order | unsigned int, default 0 | |
| is_active | boolean, default true | |
| timestamps | | |

Exactly one of `room_id` / `room_unit_id` is set per row (app-level constraint,
enforced in the model's `creating` hook — not worth a DB CHECK given SQLite is
the local/test driver).

### `smart_scene_actions`

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| smart_scene_id | FK `smart_scenes`, cascade delete | |
| smart_device_id | FK `smart_devices`, cascade delete | |
| command | json | e.g. `{"switch": true, "bright": 80}` — same shape a direct device command takes |
| sort_order | unsigned int, default 0 | scenes fire actions in order, small delay between groups if Tuya rate limits require it |

### `smart_device_activity_logs`

Structural copy of the existing `device_activity_logs` table (audit trail
convention already used for tablets and maintenance work orders), scoped to
`smart_device_id` instead of `device_id`. Logs: `assigned`, `unassigned`,
`renamed`, `command_sent`, `command_failed`, `scene_activated`, `synced`.
Never logs Tuya secrets — only device id, command payload, and outcome.

## Capability model

Tuya devices expose wildly different DP (data-point) codes per product. We
don't want Flutter switching on Tuya-specific codes, so `capabilities` on
`smart_devices` is a **normalized** map, translated by `TuyaDeviceService`
from Tuya's raw `functions` response at discovery/sync time:

```json
{
  "power": { "code": "switch_led", "type": "bool" },
  "brightness": { "code": "bright_value_v2", "type": "int", "min": 10, "max": 1000 },
  "color_temperature": { "code": "temp_value_v2", "type": "int", "min": 0, "max": 1000 }
}
```

```json
// AC
{
  "power": { "code": "switch", "type": "bool" },
  "temperature": { "code": "temp_set", "type": "int", "min": 16, "max": 30 },
  "mode": { "code": "mode", "type": "enum", "values": ["cold", "hot", "wind", "auto"] },
  "fan_speed": { "code": "fan_speed_enum", "type": "enum", "values": ["low", "mid", "high"] }
}
```

```json
// Curtain
{ "control": { "code": "control", "type": "enum", "values": ["open", "stop", "close"] },
  "position": { "code": "percent_control", "type": "int", "min": 0, "max": 100 } }
```

The **keys** (`power`, `brightness`, `temperature`, ...) are our vocabulary
and are what Flutter renders against — a device simply omits a key it doesn't
support. The **codes** are Tuya's, kept server-side only for building the
outbound command; Flutter never sends a raw Tuya DP code, only
`{"capability": "power", "value": true}`, which `TuyaCommandService`
translates using the device's stored `capabilities` map. This is what makes
"don't hard-code device capabilities" (master-prompt rule 8) and "the tablet
never talks to Tuya directly" (rule 4/9) hold simultaneously.

## API (guest, Sanctum device token — `retiro-del-rocio/routes/api.php`)

Added inside the existing `Route::middleware(['auth:sanctum', TouchLastSeen::class])->group()`
block (same group `devices`/`tablets/{code}` already live in):

```
GET  guest/room/devices                       SmartRoomController::devices
GET  guest/room/devices/{smartDevice}         SmartRoomController::deviceShow
POST guest/room/devices/{smartDevice}/command SmartRoomController::command
GET  guest/room/scenes                        SmartRoomController::scenes
POST guest/room/scenes/{scene}/activate       SmartRoomController::activateScene
```

Every method starts by resolving the requesting `Device` from
`$request->user()` (the Sanctum principal — already how `DeviceController`
and the guest-facing dining/SOS endpoints work) and deriving
`room_unit_id` from it. `{smartDevice}`/`{scene}` route-model-bind, then a
guard (`abort_unless($smartDevice->room_unit_id === $device->room_unit_id, 403)`)
rejects any cross-room access — mirrors the exact discipline
`Device::currentBooking()` already applies for booking data. A guest token
never carries or supplies a room id; it is always looked up server-side.

## API (admin, Livewire — no new HTTP endpoints, direct model calls)

New module `app/Livewire/Admin/SmartRoom/` alongside the existing `Devices`
and `Ttlock` modules:

- `Dashboard.php` — device/scene counts, per-room coverage.
- `ManagesSmartDevices.php` — list, search, filter by room/type/status;
  rename; assign/unassign room; toggle active; "test" (fetch live status).
- `SyncDevices.php` — calls `TuyaDeviceService::discover()`, upserts
  `smart_devices` rows keyed on `provider_device_id`, leaves `room_unit_id`
  null for anything newly discovered (admin assigns it afterward — devices
  are never auto-assigned to a room).
- `Scenes.php` — CRUD scenes/actions for a room or room category template.

## Flutter

`retirodelrocioapp/lib/features/guest/smart_room/`:

- `domain/smart_device.dart` — mirrors the normalized capability JSON;
  `domain/smart_scene.dart`.
- `data/smart_room_repository.dart` — Dio, `device.token` bearer header,
  same `DioException` → typed-exception mapping convention as
  `bar_repository.dart`/`maintenance_repository.dart`.
- `application/smart_room_providers.dart` — `FutureProvider.family<List<SmartDevice>, String>` keyed on device token, 20s poll-backstop
  `Timer` (matching `RoomUnit`'s stated realtime-is-an-accelerator principle),
  plus a realtime listener on the room's existing `rooms.{id}` channel
  (reusing the channel `ProvisionedDevice.roomUnitId` already resolves,
  not a new one) that just invalidates the provider on any
  `smart_device.status_changed` event — no payload trust, always refetch.
- `presentation/screens/*.dart` — the five existing blank shells
  (`lights_screen.dart`, `curtains_screen.dart`, `air_conditioning_screen.dart`,
  `television_screen.dart`, `room_scenes_screen.dart`) get their body filled
  in via `smart_room_control_page.dart`, extended to accept a `deviceType`
  and render controls **generated from each device's `capabilities` map** —
  a toggle for `power`, a slider for `brightness`/`temperature`/`position`, a
  segmented control for `mode`/`fan_speed`/`control` enums. No per-device-type
  hard-coded widget tree beyond which capability keys map to which control
  shape.
- Optimistic UI: on tap, flip local state immediately, send the command,
  and reconcile with the next status fetch/broadcast — never show "success"
  before the command call actually returns 2xx (master-prompt rule 19).

## Security

- Tuya credentials live only in `.env` / `config/services.php`, read only by
  `TuyaService`. Never logged (activity logs record command payload +
  outcome, not credentials), never serialized into any API response.
- Every guest-side device/scene access re-derives `room_unit_id` from the
  Sanctum token server-side; `{smartDevice}` route binding plus the
  room-match guard is mandatory on every method, not just the list endpoint.
- Admin-side discovery/assignment requires the existing admin auth guard
  (whatever `Devices`/`Ttlock` Livewire modules already require — no new
  permission system invented).
- Command endpoint validates the capability key exists on that specific
  device's stored `capabilities` map and the value is in-range/in-enum
  before calling Tuya — malformed/out-of-range values are rejected with a
  422, never forwarded to the vendor API.

## Realtime

`SmartDeviceStatusChanged` event, structural copy of `RoomStatusChanged`:
broadcasts on `rooms.{room_unit_id}` (existing channel, existing private-channel
auth already in place for tablets), wrapped in try/catch + `report()` so a
broadcaster outage never fails the guest's command request — the command
still executes against Tuya and the tablet just relies on its poll fallback.
Fired after `TuyaCommandService::send()` succeeds and after
`TuyaStatusService` syncs on a schedule (see `03-tuya-architecture.md`).
