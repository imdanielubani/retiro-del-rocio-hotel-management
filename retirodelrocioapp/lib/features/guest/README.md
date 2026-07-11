# Guest Feature

Part of the Rocio Hotel Smart Hospitality tablet application.

> Status: Phase 2 — architecture scaffold. Folders and documentation only; no code.

## Responsibility

The Guest Tablet — the in-room experience. `guest/` is a **parent module**: its
own `data/domain/presentation` layers cover the Home shell and guest identity,
and every Guest-Tablet feature is nested underneath as a self-contained
sub-module.

## Parent-level structure (Feature-First Clean Architecture)

- **data/** — implementation layer
  - **datasources/** — remote (REST API) and local (cache) data sources
  - **models/** — DTOs; JSON serialisation (Freezed + json_serializable)
  - **repositories/** — repository implementations fulfilling domain contracts
- **domain/** — pure business layer (no Flutter, no JSON)
  - **entities/** — business objects
  - **repositories/** — abstract repository contracts
  - **usecases/** — single-purpose business actions
- **presentation/** — UI layer (`controllers/ screens/ widgets/ dialogs/ bottom_sheets/ popups/ search/ filters/`)
- **routes/** — go_router route definitions for the guest shell
- **bindings/** — dependency wiring / provider registration

Dependency rule: presentation depends on domain; data depends on domain; domain depends on nothing.

## Nested sub-features (each a full Clean-Architecture module)

| Sub-feature | Guest Tablet surface |
|---|---|
| `dining/` | Dining menu & room service |
| `orders/` | My Orders + order tracking |
| `my_stay/` | My Stay (reservation, itinerary) |
| `billing/` | My Bill (folio) |
| `my_tab/` | My Tab (running charges) |
| `spa/` | Spa services & booking |
| `gym/` | Gym access & info |
| `cinema/` | Cinema showtimes & booking |
| `smart_room/` | Smart Room controls (lighting, climate, media, privacy) |
| `visitor_pass/` | Visitor pass request & QR |
| `chat/` | Guest ↔ staff chat |
| `intercom/` | Room ↔ department intercom |
| `sos/` | SOS emergency |
| `hotel_information/` | Hotel directory, amenities, policies |

> Note: several of these surfaces (`chat`, `intercom`, `visitor_pass`, `sos`,
> `cinema`, `smart_room`) are also consumed by other role tablets. Per the
> chosen structure they live here under `guest/`, and other roles reference them
> from this location.
