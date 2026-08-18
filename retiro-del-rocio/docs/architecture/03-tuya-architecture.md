# Tuya Architecture

Sources checked against current official docs (2026-08-17):
[Get Token](https://developer.tuya.com/en/docs/cloud/6c1636a9bd?id=Ka7kjumkoa53v),
[Authentication Method](https://developer.tuya.com/en/docs/iot/authentication-method?id=Ka49gbaxjygox),
[Device Control](https://developer.tuya.com/en/docs/cloud/device-control?id=K95zu01ksols7).
Endpoints below are what those pages currently document — **do not invent
endpoints or command codes beyond what's confirmed here or re-verified
against the live docs when implementing.**

## 1. Project setup (manual, one-time, in the Tuya IoT Platform — not code)

Create a Cloud Project → subscribe the **IoT Core** and **Authorization**
(and, if the hotel uses Tuya's Smart Home PaaS to link guest-owned Tuya
accounts rather than devices we provision ourselves, **Smart Home PaaS**)
APIs → link the physical devices via the Tuya Smart Life / Smart app during
device install → note the project's `Access ID` (client id) and
`Access Secret` (client secret) and the account region.

This step happens outside this codebase; the architecture below assumes it's
done and the resulting credentials are dropped into `.env`.

## 2. Credentials (server-only, `config/services.php`)

```php
'tuya' => [
    'base_url' => env('TUYA_BASE_URL', 'https://openapi.tuyaeu.com'), // region-specific: tuyaus/tuyaeu/tuyacn/tuyain
    'client_id' => env('TUYA_CLIENT_ID'),
    'client_secret' => env('TUYA_CLIENT_SECRET'),
    'timeout' => (int) env('TUYA_TIMEOUT', 15),
    'retries' => (int) env('TUYA_RETRIES', 2),
],
```

**Confirmed 2026-08-17 against the live account**: this hotel's Tuya Cloud
Project is **Smart Home PaaS mode** — devices are linked via an associated
Tuya app account, not added directly as project assets. Discovery therefore
uses `GET /v1.0/iot-01/associated-users/devices`
(`TUYA_DISCOVERY_ENDPOINT` in `.env`), verified live: a signed request
returns a real `{"devices": [], "has_more": false, "total": 0}` (empty
because no physical device has been linked to the app account yet, not
because the endpoint is wrong — `/v1.0/iot-03/devices` by contrast rejected
the same account with `device_ids param is illegal`, confirming it is not
the listing endpoint for this project). An Industry/Custom project uses a
different endpoint — re-verify rather than assuming this value if the
project is ever recreated under a different mode.

## 3. Authorization model

Same shape as `TTLockService`, adapted to Tuya's actual signing:

- **Token request** (`GET /v1.0/token?grant_type=1`): sign string is
  `client_id + t + nonce`, HMAC-SHA256 keyed on `client_secret`, uppercase
  hex, sent as header `sign` alongside `t` (13-digit ms timestamp),
  `sign_method: HMAC-SHA256`, `client_id`. No `access_token` header on this
  call. Response: `access_token`, `refresh_token`, `expire_time` (seconds,
  currently 7200 per docs).
- **Business API requests**: sign string is
  `client_id + access_token + t + nonce + stringToSign`, where
  `stringToSign = HTTPMethod\nContentSHA256\nHeaders\nURL` (URL includes the
  query string). Same HMAC-SHA256/hex/uppercase, plus header `access_token`.
- Cache the token bundle in `Cache::put('tuya.token.*', ..., ttl)`, same
  pattern as `TTLockService::TOKEN_CACHE_KEY` — refresh via `refresh_token`
  before expiry or on a token-invalid error code from a call.
- `TuyaService::isConfigured()` mirrors `TTLockService::isConfigured()` —
  guards every call site so a missing/misconfigured project fails fast with
  a clear message instead of a cryptic HTTP error.

## 4. Service layer (`app/Services/IoT/Tuya/`)

Mirrors `TTLockService`'s shape, split by responsibility per the master
prompt rather than one god-class:

- **`TuyaClient.php`** — low-level signed HTTP client: builds the
  `stringToSign`, signs, sends, unwraps `{success, result, code, msg}`,
  throws `TuyaException` on `success: false` (Tuya's API returns HTTP 200
  even on logical errors, same convention as TTLock's `errcode`).
- **`TuyaAuthService.php`** — token acquisition/refresh/cache, as above.
- **`TuyaDeviceService.php`** — discovery (`GET` device-list — endpoint
  finalized per §2), device detail (`GET /v1.0/iot-03/devices/{device_id}`),
  and specification/capabilities
  (`GET /v1.0/iot-03/devices/{device_id}/functions`) — the response this
  normalizes into `smart_devices.capabilities` (see
  `02-smart-room-architecture.md`).
- **`TuyaCommandService.php`** — `POST /v1.0/iot-03/devices/{device_id}/commands`
  with body `{"commands": [{"code": ..., "value": ...}]}`. Takes a
  `SmartDevice` + normalized `{capability, value}` pair, looks up the
  capability's Tuya `code` from the device's stored `capabilities` map,
  validates the value against the stored type/range/enum, and only then
  builds the Tuya payload — Flutter and the guest API never see a raw Tuya
  `code`.
- **`TuyaStatusService.php`** — `GET /v1.0/iot-03/devices/{device_id}/status`,
  used both synchronously (right after a command, to confirm state) and on a
  scheduled sync (`app/Console/Kernel` — every 60–120s per device believed
  online, budget-permitting under Tuya's rate limits) to catch state changes
  made outside our system (a guest using the Tuya/Smart Life app directly,
  or a physical switch).

## 5. Provider abstraction

```php
interface SmartDeviceProviderInterface {
    public function discover(): array;
    public function status(SmartDevice $device): array;
    public function sendCommand(SmartDevice $device, string $capability, mixed $value): void;
}

class TuyaProvider implements SmartDeviceProviderInterface { /* delegates to the Tuya services above */ }
```

`SmartDevice.provider` (default `tuya`) selects the bound implementation via
a small container binding (`app/Providers/AppServiceProvider.php`, resolved
by provider string). This exists purely so a second vendor (e.g. a
non-Tuya smart-TV API) doesn't require touching `SmartRoomController` or the
`smart_devices` schema — not because a second provider is planned now.

## 6. Command flow (ties into `02-smart-room-architecture.md` §API)

```
SmartRoomController::command($smartDevice, Request $request)
  -> validate {capability: string, value: mixed} against $smartDevice->capabilities
  -> authorize room match (see 02, §Security)
  -> app(SmartDeviceProviderInterface::class, ['provider' => $smartDevice->provider])
       ->sendCommand($smartDevice, $capability, $value)
  -> on success: update smart_devices.last_state, log activity, broadcast SmartDeviceStatusChanged
  -> on TuyaException: log activity as command_failed, return a friendly 502
     ("Air conditioner is currently unavailable.") — never the raw vendor error
```

## 7. Error handling

| condition | handling |
|---|---|
| Tuya unconfigured (`isConfigured() === false`) | 503, admin-facing message only; guest UI shows "Smart Room is temporarily unavailable" |
| device offline (`status !== online`) | reject command client-side (UI disables the control) and server-side (re-check before sending) |
| Tuya token invalid/expired mid-call | one transparent refresh + retry, same as `TTLockService::call()`'s `TOKEN_ERROR_CODES` handling — exact Tuya code TBD against live account, stubbed behind a constant to fill in during implementation |
| Tuya rate-limited | back off per `Retry-After`/documented limit, queue the sync job rather than hammering |
| malformed/out-of-range command value | 422 before any Tuya call is made |
| network timeout | `Http::timeout()/retry()` as configured; surfaced as the same friendly "unavailable" message |

## 8. Rate limits & production considerations

Tuya's per-project rate limits are plan-dependent and must be read from the
live project's console at implementation time, not assumed. Practical
mitigations built regardless of the exact number: batch the scheduled status
sync (not one job per device), debounce rapid repeat commands from a single
tablet tap, and treat `TuyaStatusService`'s scheduled sync as best-effort
(skip a cycle rather than queue up backlog if Tuya is slow).

## 9. What's explicitly deferred (no Tuya project credentials yet)

Per master-prompt rule 21: the service boundary, config keys, and signing
logic are implemented and unit-testable (sign generation, payload shaping)
without a live account. `TuyaDeviceService::discover()`'s exact endpoint and
`TuyaAuthService`'s exact token-invalid error code are **stubbed with a
config-driven endpoint and a documented TODO** pending one real call against
the hotel's actual Tuya project — do not mark Tuya discovery/sync as
"working" until verified against a live response.
