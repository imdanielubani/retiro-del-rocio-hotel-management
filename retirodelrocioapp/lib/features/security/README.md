# Security Feature

Part of the Rocio Hotel Smart Hospitality tablet application.

> Status: Home dashboard implemented (Figma 204:3089 / 257:1133). Visitors Today
> and Visitor Pass Requests render their empty states until the Visitor Pass
> feature lands and the backend populates them.

## Responsibility

Access monitoring, incident logging and patrols.

## Implemented — Security Home Dashboard

Reached after a `security`-role officer signs in on the security station:
`WelcomeScreen` (Sign In) → `StaffLoginScreen` → `StaffDashboardScreen`, which
routes `role == 'security'` to `SecurityDashboardScreen`.

- **domain/** — `SecurityOverview`, `SecurityIncident`, `SecurityVisitor`,
  `VisitorPassRequest`.
- **data/** — `SecurityRepository`: `GET /security/overview`, and
  `POST /security/incidents/{id}/respond|resolve`, authed by the officer's JWT.
- **application/** — `securityOverviewProvider` (12s poll),
  `securityRealtimeProvider` (the `sos` Reverb channel, `SosChannel`) and
  `SecurityActions` (respond / resolve).
- **presentation/** — `SecurityDashboardScreen` + the rail, top bar, stat cards,
  incident card (Respond → acknowledge, Resolve) and visitor/pass widgets.

Incidents are live SOS alerts: Respond acknowledges (the guest tablet flips to
"Security is on their way"); Resolve closes the incident. The dashboard follows
the realtime `sos` channel and re-polls every 12 seconds, so it never depends on
the socket alone.

## Implemented — Incident Response (SOS Alert Logs)

Reached from the rail's **Incident Response** item (`IncidentResponseScreen`,
Figma 222:8280 + detail 225:10446).

- **data/** — `SecurityRepository.incidents(token, {status})` →
  `GET /security/incidents` (every alert, newest first, full timeline + case
  number `SOS-YYMM-NNN`).
- **application/** — `incidentLogsProvider` (15s poll, invalidated by the same
  `sos` realtime channel and by respond/resolve).
- **presentation/** — the log list (`IncidentLogRow`, status-tinted:
  red / amber / green with Acknowledge / Resolve / Closed + View), a status
  Filter, and the slide-in `IncidentDetailPanel` (emergency card, a
  Triggered → Acknowledged → Resolved timeline, case details, and the primary
  action + Call Room). Acknowledge / Resolve run through the shared
  `SecurityActions`, so the dashboard, the logs and the guest tablet all update
  together.

Call Room is present but surfaces a "coming soon" notice — no telephony backend
yet.

## Implemented — SOS Alert Trigger (priority overlay)

The realtime interrupt (Figma 258:1471 red / 258:1791 green),
`presentation/dialogs/sos_alert_overlay.dart`.

- Driven off `securityOverviewProvider`, which the `sos` Reverb channel refreshes
  — so a new emergency pops within a socket round-trip (12s poll is the backstop).
- Presented from `SecurityDashboardScreen` (the root, always-mounted security
  screen) via the **root navigator**, so it interrupts wherever the officer is,
  including Incident Response. Guards (`_announced`, `_presenting`) mean each
  alert surfaces exactly once and never stacks.
- Red **PRIORITY ALERT** → **Acknowledge & Dispatch** runs the shared
  `SecurityActions.respond` (the guest tablet flips to "Security is on their way"
  off the same broadcast) → the overlay switches to the green **Alert
  Acknowledged** confirmation with officer + ETA. **Dismiss** leaves it open on
  the dashboard; **Call Room** is a "coming soon" notice.

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
