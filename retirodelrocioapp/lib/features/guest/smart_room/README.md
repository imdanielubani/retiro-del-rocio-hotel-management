# Smart Room Feature

Part of the Rocio Hotel Smart Hospitality tablet application.

> Status: Phase 2 — architecture scaffold. Folders and documentation only; no code.

## Responsibility

In-room smart controls surface (lighting, climate, media, privacy).

## Structure (Feature-First Clean Architecture)

- **data/** — implementation layer
  - **datasources/** — remote (REST API) and local (cache) data sources
  - **models/** — DTOs; JSON serialisation (Freezed + json_serializable)
  - **repositories/** — repository implementations fulfilling domain contracts
- **domain/** — pure business layer (no Flutter, no JSON)
  - **entities/** — business objects
  - **repositories/** — abstract repository contracts
  - **usecases/** — single-purpose business actions
- **presentation/** — UI layer
  - **controllers/** — Riverpod controllers bridging UI and domain
  - **screens/** — routed full-page screens
  - **widgets/** — feature-local widgets
- **routes/** — go_router route definitions for this feature
- **bindings/** — dependency wiring / provider registration for this feature

Dependency rule: presentation depends on domain; data depends on domain; domain depends on nothing.
