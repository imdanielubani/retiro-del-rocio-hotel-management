# Features

Feature-First Clean Architecture home for the Rocio Hotel tablet application.
Each feature is self-contained and follows the identical layered structure —
see any feature's own `README.md` for its responsibility.

> Status: Phase 2 — architecture scaffold. Folders and documentation only; no code.

## Standard feature structure

```
<feature>/
├── README.md
├── data/
│   ├── datasources/
│   ├── models/
│   └── repositories/
├── domain/
│   ├── entities/
│   ├── repositories/
│   └── usecases/
├── presentation/
│   ├── controllers/
│   ├── screens/
│   └── widgets/
├── routes/
└── bindings/
```

**Dependency rule:** `presentation → domain ← data`. `domain` imports nothing outward.

## Feature catalog

Guest-Tablet features are **nested under `guest/`** as sub-modules; all other
features are top-level. `_shared/` is the global cross-cutting UI layer (not a
business feature).

### Top-level features (24)

| Feature | Folder |
|---|---|
| Authentication | `authentication/` |
| Dashboard | `dashboard/` |
| Guest (parent module) | `guest/` |
| Reception | `reception/` |
| Property | `property/` |
| Restaurant | `restaurant/` |
| Kitchen | `kitchen/` |
| Bar | `bar/` |
| Inventory | `inventory/` |
| Vehicle Pickup | `vehicle_pickup/` |
| Membership | `membership/` |
| Housekeeping | `housekeeping/` |
| Maintenance | `maintenance/` |
| Security | `security/` |
| Task Center | `task_center/` |
| Payments | `payments/` |
| Reports | `reports/` |
| Notifications | `notifications/` |
| Tuya | `tuya/` |
| TTLock | `ttlock/` |
| Devices | `devices/` |
| Manager | `manager/` |
| Settings | `settings/` |
| Checkout | `checkout/` |

### Nested under `guest/` (14)

| Sub-feature | Folder |
|---|---|
| Dining | `guest/dining/` |
| Orders | `guest/orders/` |
| My Stay | `guest/my_stay/` |
| My Bill | `guest/billing/` |
| My Tab | `guest/my_tab/` |
| Spa | `guest/spa/` |
| Gym | `guest/gym/` |
| Cinema | `guest/cinema/` |
| Smart Room | `guest/smart_room/` |
| Visitor Pass | `guest/visitor_pass/` |
| Chat | `guest/chat/` |
| Intercom | `guest/intercom/` |
| SOS | `guest/sos/` |
| Hotel Information | `guest/hotel_information/` |

> Note: `lib/modules/` (Phase 1) is intentionally left untouched. This
> `lib/features/` tree is the canonical feature home going forward.
