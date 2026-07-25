# Rocio Hotel — Tablet Screen Architecture

Product & UX screen inventory for the single-project, role-based tablet suite.
This is an **architecture specification only** — no design, no code, no widgets.

> Scope: enumerates every screen, dialog, bottom sheet, popup and system state
> each tablet role requires. Cross-cutting states are defined once in the
> **Global / Shared** layer and referenced by every role to guarantee a
> consistent experience.

## Legend

- **Screen** — a full, routed destination.
- **Dialog** — modal, blocking, centered (decisions/short input).
- **Bottom Sheet** — modal panel from the bottom edge (contextual actions/input).
- **Popup** — transient, non-blocking overlay (toast, banner, incoming call).
- **State screens** — Loading / Error / Offline / Empty / Success / Confirmation.

## Role coverage summary

| Role | Primary screens | Nav model |
|---|--:|---|
| Guest | ~34 | Home hub + side rail |
| Reception | ~24 | Dashboard + top tabs |
| Kitchen | ~10 | KDS board + tabs |
| Bar | ~11 | Board + tabs |
| Housekeeping | ~14 | Task board + tabs |
| Maintenance | ~14 | Work-order board + tabs |
| Security | ~16 | Dashboard + tabs |
| Cinema | ~14 | Schedule + tabs |
| Manager | ~18 | Dashboard + side rail |

---

# 0. Global / Shared layer (used by ALL roles)

### 0.1 Authentication & session
- **Screens:** Splash, Device/Kiosk provisioning, Role selection, Login (email/password), Staff PIN login, Biometric unlock, Forgot password, Reset password, Session lock, Logout.
- **Dialogs:** Session timeout, Re-authenticate, Logout confirm, Wrong PIN / lockout.
- **Popups:** Session-expiring warning banner.

### 0.2 System state screens (single source of truth)
- **Loading:** Splash loader, Full-screen loader, Section/skeleton loader, Inline button loader, Pull-to-refresh, Pagination (load-more) loader.
- **Error:** Fatal error (crash), Server error (5xx), Not found (404), Unauthorized / session expired (401), Permission denied (403), Request timeout, Form validation error, Payment failed.
- **Offline:** Full offline screen, Persistent offline banner, Reconnecting indicator, Sync-pending / queued-actions indicator.
- **Empty:** No data, No search results, Empty cart/queue, No tasks / "all caught up".
- **Success:** Generic success, Saved, Submitted, Payment successful.
- **Confirmation:** Destructive-action confirm (delete/cancel), Discard-unsaved-changes, Irreversible-action confirm.
- **Maintenance:** App under maintenance, Force-update required.

### 0.3 Shared dialogs / sheets / popups
- **Dialogs:** Confirm, Alert, Permission request (camera/mic/location/notifications), Rate/feedback prompt.
- **Bottom Sheets:** Filter, Sort, Date picker, Time picker, Date-range picker, Photo source (camera/gallery), Share, More actions/overflow.
- **Popups:** Toast / snackbar, In-app notification banner, Incoming intercom/call, New-item alert.

### 0.4 Shared Settings & Profile
- **Settings:** Account, Notifications, Language & region, Display (theme/brightness), Device & kiosk mode, Security (PIN/biometric), Sync & data, About/Legal, Sign out.
- **Profile:** View profile, Edit profile, Change PIN/password, Shift/attendance (staff roles).

---

# 1. Guest Tablet

### Main Navigation
Home hub (tile grid) + persistent side rail: **Home · Dining · Smart Room · My Stay · Services · Chat · More**. "More" reveals: My Orders, My Bill, My Tab, Visitor Pass, Intercom, Hotel Information, Settings, SOS (always-visible persistent action).

### Screens
- Home / Welcome dashboard
- Dining: Menu, Category, Dish detail, Cart, Room-service tracking
- My Orders: list, Order detail & live status
- Smart Room: Control hub, Lighting, Air Conditioner / Climate, Curtains, Television, Streaming launcher (Netflix / YouTube / Prime Video / Live TV), Scenes/modes, Do-Not-Disturb & Make-up-Room
- My Stay: Overview, Reservation details, Extend stay, Express checkout
- Billing: My Bill (folio), My Tab (running charges)
- Services: Spa (services, booking), Gym (access/info), Cinema (showtimes, seat selection, booking)
- Visitor Pass: Request, Active pass / QR
- Communication: Chat (with reception/services), Intercom (call departments)
- Hotel Information: Directory, Amenities, Local guide, Policies
- Feedback / Review, Wake-up call / Alarm, SOS Emergency

### Dialogs
Do-Not-Disturb confirm · SOS confirm (with countdown) · Cancel order confirm · End intercom call · Extend-stay confirm · Rate service · Logout/lock.

### Bottom Sheets
Add-to-cart (quantity + modifiers) · Payment method · Dining filters · Time-slot picker (spa/dining) · Quick device control · Share item.

### Popups
Order-status toasts · Promo/announcement banner · Incoming intercom call · Notification banner (order ready, message).

### Search Screens
Dining search · Hotel-information search · Services search.

### Filter Screens
Dining filters (category, dietary, price) · Spa filters · Cinema filters (movie/time).

### Settings / Profile
Room preferences (language, brightness, volume, notifications, accessibility) · Guest profile (My Stay identity, read-mostly).

### State screens
Uses **Global 0.2**. Guest-specific success: Order placed · Booking confirmed · Payment successful · Request sent. Confirmation: Cancel order · Checkout · SOS trigger.

---

# 2. Reception Tablet

### Main Navigation
Dashboard + top tabs: **Dashboard · Reservations · Rooms · Guests · Billing · Tasks · More** (Visitor Pass, Keys, Messages, Reports, Settings).

### Screens
- Dashboard (arrivals, departures, occupancy, alerts)
- Reservations: list, detail, New/walk-in reservation, Modify, Cancel
- Check-in flow: guest details → ID scan → room assignment → key/TTLock issue → payment/deposit
- Check-out flow: folio review → charges → payment → feedback 
- Rooms: Room rack / availability grid, Room detail & status
- Guests: Guest list, Guest profile, Guest history/preferences
- Billing: Folio, Post charge, Split/transfer, Payment, Refund, Invoice/reprint
- Keys: TTLock issue/revoke, Key audit
- Visitor Pass issuing
- Messages / Chat (guest & inter-department)
- Tasks / requests, Shift handover, Lost & found

### Dialogs
Assign room · Issue/revoke key confirm · Cancel reservation · Refund confirm · Rate override · Reprint invoice · ID-scan capture.

### Bottom Sheets
Guest quick-actions · Add charge · Payment method · Date-range · Room filter.

### Popups
New online booking alert · Guest request alert · Payment result · Arrival reminder.

### Search Screens
Guest search · Reservation search · Room search.

### Filter Screens
Reservation filters (status, date, channel, VIP) · Room filters (type, status, floor, housekeeping state).

### Settings / Profile
Terminal/printer settings · Shift settings · Staff profile.

### State screens
Global 0.2. Success: Check-in complete · Check-out complete · Payment posted. Confirmation: Cancel/refund/override.

---

# 3. Kitchen Tablet (KDS)

### Main Navigation
Tabs: **Live Board · Queue · Menu Availability · History**.

### Screens
- KDS ticket board (New → Preparing → Ready)
- Ticket detail (items, modifiers, allergens, table/room, timers)
- Order queue / expo view
- Menu availability (86 / restore items)
- Prep timers & station view
- Order history / recall

### Dialogs
Mark ready confirm · Void/cancel item · 86-item confirm · Delay & notify.

### Bottom Sheets
Item modifiers detail · Assign to station · Time-bump.

### Popups
New-order sound + banner · VIP/priority flag · Recall alert.

### Search / Filter
Search: ticket/order. Filter: station, course, status, priority.

### Settings / Profile
KDS layout & sound · Station config · Staff profile.

### State screens
Global 0.2. Empty: "No active tickets / all caught up". Success: Order bumped.

---

# 4. Bar Tablet

### Main Navigation
Tabs: **Live Orders · Tabs · Menu · History**.

### Screens
- Drink order board (New → Preparing → Served)
- Order detail
- Open tabs list, Tab detail, Close/settle tab
- Menu availability
- Order history

### Dialogs
Mark served · Void item · Close-tab confirm · Age-verification prompt.

### Bottom Sheets
Modifiers · Payment/settle · Assign to bartender.

### Popups
New-order alert · VIP flag.

### Search / Filter
Search: tab/order/guest. Filter: status, type, table.

### Settings / Profile / State
Global 0.4 + 0.2. Empty: no open orders. Success: tab settled.

---

# 5. Housekeeping Tablet

### Main Navigation
Tabs: **My Tasks · Rooms · Requests · Supplies · More**.

### Screens
- Task board / assigned rooms
- Room status grid, Room detail + cleaning checklist
- Mark clean / inspected / out-of-order
- Guest requests (towels, amenities, DND, make-up room)
- Linen & minibar restock, Supplies request
- Inspection screen, Lost & found report, Maintenance handoff
- Schedule / shift

### Dialogs
Mark clean confirm · Report issue to maintenance · Request supplies · DND override.

### Bottom Sheets
Status change · Photo capture · Reassign room.

### Popups
New/priority request alert · Rush-room alert.

### Search / Filter
Search: room. Filter: floor, status, priority, credit (checkout/stayover).

### Settings / Profile / State
Global. Success: room marked clean/inspected. Empty: no assigned rooms.

---

# 6. Maintenance Tablet

### Main Navigation
Tabs: **Work Orders · Assets · Requests · Schedule · More**.

### Screens
- Work-order board (New → Accepted → In-progress → Done)
- Work-order detail, Create work order, Accept/assign
- Asset list, Asset detail & service history
- Preventive-maintenance schedule
- Parts / inventory, Report fault (photo/video), Complete order
- Room/device status

### Dialogs
Accept/complete confirm · Escalate · Parts request · Close order.

### Bottom Sheets
Status update · Attachment/photo · Assign technician.

### Popups
New/urgent work order · SLA-breach alert.

### Search / Filter
Search: work order / asset. Filter: priority, status, category, location.

### Settings / Profile / State
Global. Success: order completed. Confirmation: close/escalate.

---

# 7. Security Tablet

### Main Navigation
Tabs: **Dashboard · Incidents · Access & Visitors · Patrols · CCTV · More**.

### Screens
- Security dashboard (alerts, SOS feed, status)
- Incidents: list, detail, Report incident
- Visitor log, Visitor-pass verify (QR scan), Watchlist
- Access control / door status, Lock audit (TTLock)
- Patrol checkpoints & log
- CCTV / camera grid
- Emergency protocols / SOP

### Dialogs
Acknowledge alert · Escalate · Grant/deny access · Close incident.

### Bottom Sheets
Quick incident log · Evidence (photo/video) · Assign officer.

### Popups
SOS / panic alert (priority) · Alarm · Unauthorized-access alert.

### Search / Filter
Search: visitor / incident / vehicle. Filter: incident type, severity, zone, date.

### Settings / Profile / State
Global. Success: incident logged/closed. Confirmation: access grant, escalate.

---

# 8. Cinema Tablet

### Main Navigation
Tabs: **Showtimes · Bookings · Seat Map · Snacks · Check-in**.

### Screens
- Showtime schedule, Movie detail
- Seat map / availability
- Bookings list, Booking detail, Create/walk-in booking
- Ticket check-in / QR scan
- Snacks: menu, order, payment
- Session management, Occupancy view

### Dialogs
Confirm booking · Cancel/refund · Release held seats · Check-in confirm.

### Bottom Sheets
Seat selection · Add snack · Payment method.

### Popups
Show-starting-soon · Sold-out / seat-taken.

### Search / Filter
Search: booking / guest / reference. Filter: movie, time, status.

### Settings / Profile / State
Global. Success: booking created, checked in. Empty: no bookings for session.

---

# 9. Manager Tablet

### Main Navigation
Dashboard + side rail: **Dashboard · Operations · Reports · Staff · Approvals · More** (Inventory, Feedback, Announcements, Audit, Settings).

### Screens
- Executive dashboard (KPIs: occupancy, revenue, ADR/RevPAR, alerts)
- Operations overview (live status across all departments)
- Reports: catalog, Report detail, Export (financial, F&B, housekeeping, security)
- Staff: roster, attendance, performance
- Approvals: queue + detail (refunds, discounts, comps, rate overrides)
- Guest feedback / reviews, Complaints
- Inventory overview
- Alerts center, Announcements / broadcast, Audit log

### Dialogs
Approve/reject confirm · Broadcast message · Override confirm.

### Bottom Sheets
Date-range · Export options · Metric/department filter.

### Popups
Critical alert · Approval-needed banner.

### Search / Filter
Global search (guests, staff, orders, rooms). Filter: date, department, metric, status.

### Settings / Profile / State
Global. Success: approved/broadcast sent. Confirmation: reject, override, broadcast.

---

## Appendix — cross-role reuse

These are defined **once** and reused everywhere (do not re-implement per role):
Authentication (0.1) · All system states (0.2) · Shared dialogs/sheets/popups
(0.3) · Settings & Profile shells (0.4) · Chat · Intercom · Notifications ·
Visitor Pass · Payments/Checkout · SOS.

Role modules compose these shared surfaces; only role-specific *content* differs.
