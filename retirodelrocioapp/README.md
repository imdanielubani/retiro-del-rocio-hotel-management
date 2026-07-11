# Rocio Hotel — Smart Hospitality Tablet Application

A single Flutter project serving **every tablet role** in the hotel (Guest,
Reception, Kitchen, Bar, Housekeeping, Maintenance, Security, Cinema, Manager)
through **role-based navigation** — one binary, one codebase, many faces.

> **Status: Phase 1 — Architecture scaffold only.**
> This repository currently contains the **folder structure and documentation**.
> No business logic, controllers, models, or wiring exist yet. Every folder is
> preserved in git with a `.gitkeep`. Code arrives in later phases.

---

## 1. Architectural principles

| Principle | What it means here |
|---|---|
| **Feature-first** | Code is organised by *feature/role* (`modules/`), not by technical type. Everything a feature needs lives under its own folder. |
| **Clean Architecture** | Each module is split into `domain` (pure business rules), `data` (implementations), `application` (state/use-case orchestration), and `presentation` (UI). Dependencies point **inward**. |
| **Single project, many roles** | Role is resolved at runtime (from the authenticated session) and the router exposes only that role's module tree. |
| **Dependency rule** | `domain` depends on nothing. `data` and `application` depend on `domain`. `presentation` depends on `application`. `domain` never imports Flutter. |

---

## 2. Top-level layout (`lib/`)

```
lib/
├── main.dart          # App entrypoint (replaced per-flavor in a later phase)
├── app/               # Root App widget + MaterialApp.router assembly
├── bootstrap/         # Pre-runApp initialisation (DI, env, error zones, Firebase)
├── config/            # Build-time configuration
│   └── env/           #   → per-flavor settings (dev / staging / production)
├── core/              # Cross-cutting foundation shared by ALL modules
│   ├── theme/         #   → colors, typography, spacing, ThemeData factory
│   ├── constants/     #   → app constants, API endpoint paths, asset paths, roles
│   ├── extensions/    #   → Dart/Flutter extension methods
│   ├── helpers/       #   → small stateless helper functions
│   ├── utils/         #   → utilities (logger, formatters)
│   ├── validators/    #   → reusable form validators
│   ├── error/         #   → Failure / Exception types
│   ├── network/       #   → Dio client, Result type, interceptors
│   └── usecases/      #   → base UseCase contracts
├── shared/            # Reusable UI + models shared across modules
│   ├── widgets/       #   → common widgets (buttons, states, placeholders)
│   ├── models/        #   → shared value objects / DTOs
│   └── mixins/        #   → shared Dart mixins
├── services/          # App-wide integrations (thin, framework-facing)
│   ├── storage/       #   → secure storage, prefs, Hive
│   ├── notifications/ #   → FCM + local notifications
│   ├── realtime/      #   → WebSocket connection manager
│   ├── smart_home/    #   → Tuya Smart integration surface
│   ├── locks/         #   → TTLock integration surface
│   ├── payments/      #   → Paystack + Flutterwave surfaces
│   └── analytics/     #   → analytics / telemetry
├── repositories/      # Global/base repository contracts (cross-module)
├── models/            # Global/base models (cross-module)
├── providers/         # Global Riverpod providers (DI roots, app state)
├── routes/            # go_router configuration, route names, guards
├── widgets/           # Global app-level widgets (shells, scaffolds)
├── l10n/              # Localization (ARB files + generated delegates)
└── modules/           # ← Feature-first modules (the heart of the app)
```

### Why both `modules/*/data` **and** top-level `repositories/`, `models/`, `providers/`, `services/`?

- **Inside a module** live the things *only that feature* uses.
- **Top-level** `repositories/`, `models/`, `providers/`, `services/` hold the
  **cross-cutting** implementations shared by many modules (e.g. the session
  repository, the WebSocket service, the global auth provider). Feature folders
  stay focused; shared infrastructure gets a clear home.

---

## 3. Module anatomy (`lib/modules/<feature>/`)

Every module follows the **same four-layer Clean Architecture shape**:

```
modules/<feature>/
├── application/            # State orchestration: controllers/notifiers, use-case coordination
├── data/
│   ├── datasources/        # Remote (API) + local (cache) data sources
│   ├── models/             # DTOs — JSON (de)serialisation (Freezed + json_serializable)
│   └── repositories/       # Repository IMPLEMENTATIONS (fulfil domain contracts)
├── domain/
│   ├── entities/           # Pure business objects (no Flutter, no JSON)
│   ├── repositories/       # Repository CONTRACTS (abstract interfaces)
│   └── usecases/           # Single-purpose business actions
└── presentation/
    ├── controllers/        # Riverpod controllers bridging UI ↔ application
    ├── screens/            # Full-page routed screens
    └── widgets/            # Feature-local widgets
```

**Dependency direction:** `presentation → application → domain ← data`.

### Modules

| Module | Purpose |
|---|---|
| `auth` | Login, session, device registration, role resolution |
| `guest` | Guest Tablet shell + guest-only features (below) |
| `reception` | Reception Tablet |
| `kitchen` | Kitchen Tablet (KDS) |
| `bar` | Bar Tablet |
| `housekeeping` | Housekeeping Tablet |
| `maintenance` | Maintenance Tablet |
| `security` | Security Tablet |
| `cinema` | Cinema Tablet |
| `manager` | Manager Tablet (oversight, reports) |
| `smart_room` | Tuya-driven room controls (lighting, AC, curtains, TV, streaming, DND, make-up room) |
| `payments` | Paystack + Flutterwave flows |
| `membership` | Guest membership / loyalty |
| `visitor_pass` | Visitor pass issuing + QR |
| `notifications` | FCM + in-app notification centre |
| `chat` | Guest ↔ staff messaging |
| `intercom` | Room ↔ department intercom |
| `device_management` | Device registration, Tuya / TTLock device binding |

### Guest Tablet features

Under `modules/guest/presentation/screens/features/`:
Home · Dining · My Orders · My Stay · My Bill · Spa · Gym · SOS Emergency ·
My Tab · Hotel Information.
(Smart Room, Cinema, Chat, Intercom and Visitor Pass are their **own modules**,
reused by the Guest shell rather than duplicated.)

### Smart Room controls

Under `modules/smart_room/presentation/widgets/`:
Lighting · Air Conditioner · Curtains · Television · Netflix · YouTube ·
Prime Video · Live TV · Do Not Disturb · Make-up Room — bound to the
**Tuya Smart** integration via `services/smart_home` and `device_management`.

---

## 4. Supporting folders (project root)

```
assets/
├── images/   icons/   animations/   svgs/
├── videos/   fonts/   lottie/       splash/
test/
├── unit/     widget/  integration/  helpers/
```

- **`assets/`** — one folder per media type (declared in `pubspec.yaml` in Phase 2).
- **`test/`** — mirrors the app's layers: `unit` (domain/use-cases),
  `widget` (presentation), `integration` (end-to-end flows), `helpers`
  (fixtures/utilities).

---

## 5. Planned integrations (later phases)

Laravel REST API · Laravel Sanctum · WebSockets · Firebase Cloud Messaging ·
TTLock API · Tuya Smart API · Flutterwave · Paystack.

Each integration enters through `services/` and is consumed by modules through
`domain` repository contracts — so swapping a provider never touches feature code.

---

## 6. Phase roadmap

1. **Phase 1 — Architecture scaffold (this repo state).** Folders + docs only.
2. **Phase 2 — Configuration.** Dependencies, `analysis_options.yaml`, theming,
   go_router, Riverpod, Dio, Freezed / json_serializable, storage, localization.
3. **Phase 3+ — Feature implementation**, module by module, domain-first.
