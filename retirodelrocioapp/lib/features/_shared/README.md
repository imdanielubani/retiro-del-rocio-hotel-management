# _shared — Global / Cross-cutting UI layer

Single source of truth for the UI surfaces that **every role reuses**, per
`docs/screen_architecture.md` §0.2 (system states) and §0.3 (shared surfaces).

> Status: architecture scaffold — folders only, no code.

The leading underscore marks this as **not a business feature** — it holds no
`data/` or `domain/`, only shared presentation surfaces. Sorting first in the
folder list keeps it visually separated from real features.

## Structure

```
_shared/presentation/
├── states/            # Defined ONCE, reused by all features — never duplicated
│   ├── loading/       #   spinners, skeletons, section/pagination loaders
│   ├── error/         #   fatal, 5xx, 404, 401/expired, 403, timeout, validation
│   ├── offline/       #   full offline, banner, reconnecting, sync-pending
│   ├── empty/         #   no data / no results / empty cart / no tasks
│   ├── success/       #   generic success, saved, submitted, payment success
│   ├── confirmation/  #   destructive-action, discard-changes, irreversible
│   └── maintenance/   #   app under maintenance, force update
├── dialogs/           # Global confirm/alert/permission/feedback dialogs
├── bottom_sheets/     # Global filter/sort/date/photo/share/more sheets
└── popups/            # Toast/snackbar, notification banner, incoming intercom
```

## Rule

Features must **consume** these shared surfaces rather than re-implement them.
A feature's own `presentation/dialogs|bottom_sheets|popups|search|filters`
folders hold only surfaces unique to that feature.
