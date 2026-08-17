# Smart Room / Tuya — System Overview

## Why this doc set exists

We're adding in-room IoT control (lights, AC, curtains, TV, scenes) to the guest
tablet, backed by Tuya Cloud. This is **not** a greenfield feature — a forensic
pass over the codebase (2026-08-17) found that most of the surrounding
infrastructure already exists and was explicitly built to be extended this way:

- `devices` table / `Device` model — provisioned tablets & TVs, already scoped
  to a `room_unit_id`, already Sanctum-authenticated, migration docblock
  literally reserves the design for "future TTLock / IoT / Tuya" devices.
- `DeviceProvisioningService` — QR-based tablet pairing. Not reused directly
  by Tuya (Tuya devices don't run our app / scan a QR), but the "server owns
  the identity, never hands secrets to the client" pattern carries over.
- `RoomUnit` — physical room, already the join point between `Booking`
  (occupancy) and `Device` (tablets). `RoomUnit::booted()` already broadcasts
  `RoomStatusChanged` on a `rooms.{id}` channel with a documented
  "realtime is an accelerator, never a dependency" fallback-to-poll principle.
  Smart Room reuses this exact channel and this exact principle.
- `TTLockService` — a vendor-cloud client for a different piece of hardware
  (door locks), but structurally exactly what `TuyaService` needs: config-only
  credentials, cached/refreshed OAuth token, `Http::` client with retry,
  provider-specific error-code handling, secrets never leave the server.
- `guest/smart_room/` — Flutter screens for Lights / Curtains / AC / TV /
  Scenes already scaffolded and routed from the guest home screen, each
  explicitly commented "blank shell, awaiting the Tuya integration."

So the job is narrower than a generic "build smart room from scratch" brief:
**extend `RoomUnit` with Tuya-backed devices, build the Tuya service layer,
expose a Smart Room API, and fill in the five already-scaffolded Flutter
screens.** We do not rebuild tablet provisioning, staff auth, or the
Bar/Kitchen tablets — those are unrelated, finished systems.

## Actors

- **Guest tablet** — Sanctum `Device` token, `mode = guest`, bound to one
  `room_unit_id`. Can only ever see/command devices in its own room, derived
  server-side from the token — never from a client-supplied room id.
- **Admin (Livewire dashboard)** — discovers Tuya devices, assigns them to a
  `RoomUnit`, configures scenes. Holds the only UI that talks to Tuya
  discovery endpoints.
- **Tuya Cloud** — external vendor. Laravel is the only thing that ever
  authenticates to it; credentials never reach Flutter or the browser.

## High-level flow

```
Guest tablet (Sanctum device token)
    -> Laravel: GET /api/v1/guest/room/devices
    -> derive room_unit_id from the authenticated Device
    -> SmartDevice::where('room_unit_id', ...)->get()
    -> return capability-shaped JSON (no Tuya ids/secrets)

Guest taps "Main Light ON"
    -> POST /api/v1/guest/room/devices/{smartDevice}/command
    -> authorize: $smartDevice->room_unit_id === $device->room_unit_id
    -> TuyaCommandService::send($smartDevice, $command)
    -> Tuya Cloud -> physical device
    -> SmartDeviceStatusChanged broadcast on rooms.{room_unit_id}
    -> tablet updates instantly, or on its next poll if the socket is down
```

## Document index

- `02-smart-room-architecture.md` — room/device data model, guest API,
  Flutter wiring, scenes, security, realtime.
- `03-tuya-architecture.md` — Tuya Cloud integration: auth, discovery,
  capability mapping, command/status flow, error handling, rate limits.

Tablet provisioning, staff auth, and realtime-channel conventions are not
re-documented here — see `app/Services/DeviceProvisioningService.php`,
`app/Http/Controllers/Api/V1/TabletController.php`, and
`app/Events/RoomStatusChanged.php` for the existing, unchanged systems this
work builds on top of.
