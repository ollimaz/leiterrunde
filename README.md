# Leiterrunde

Internal administration app for a leader team ("Leiterrunde"): people management,
roles & permissions, document storage, notifications, audit log and dashboard —
plus an extension API used by the companion module apps.

| Companion app | Purpose |
|---|---|
| [leiterrunde_schulungen](https://github.com/ollimaz/leiterrunde_schulungen) | Training records and certificates of conduct |
| [leiterrunde_finanzen](https://github.com/ollimaz/leiterrunde_finanzen) | Bank accounts, payments and expenses |
| [leiterrunde_veranstaltungen](https://github.com/ollimaz/leiterrunde_veranstaltungen) | Events and registrations with website sync |

## Features

- **People** — person records with addresses, birthdays, CSV import/export and a per-record audit log
- **Roles & permissions** — fixed roles (`leiter`, `vorstand`, `kasse`, `schulung`, `fz`, `admin`) mapped to Nextcloud groups; capability matrix editable in the admin settings. `fz.read`/`fz.write` are locked to the `fz` role — not even Nextcloud admins can read certificates of conduct; the separate `fz.expiry.read` capability (module apps) exposes only the status and expiry date.
- **Document storage** — configurable storage user and path templates per document type (`nachweis`, `beleg`); pdf/jpg/png/heic up to 20 MB
- **Notifications** — role-based recipients for approval and reminder notifications
- **Dashboard widgets** — birthdays, training status, pending certificates, expenses and upcoming events (provided together with the module apps)
- **Signed sync endpoint** — `POST /apps/leiterrunde/api/sync`, verified with HMAC-SHA256 and delegated to sync handlers registered by module apps
- **Module system** — `IModule` contract, provider interfaces and `ModuleRegistry` let companion apps register capabilities, document types and overview tiles

## Requirements

- Nextcloud 29 – 36
- PHP 8.1+

## Installation

Clone into your Nextcloud `apps` (or `apps-extra`) directory and enable:

```bash
cd /path/to/nextcloud/apps
git clone https://github.com/ollimaz/leiterrunde.git
occ app:enable leiterrunde
```

The role groups are created by a repair step on enable. Enable this core app
**before** enabling any module app; module apps show a notice and their
background jobs become no-ops when the core app is missing.

## Extension contracts (`lib/Module/`)

Modules register via `RegisterModulesEvent` (listener in the module's
`Application::register()`, referencing the event class as a string):
`registerModule(IModule)` and `registerProvider(interface, impl)`.

- `IModule`: `getId`, `getLabel`, `getNavUrl`, `getCapabilities(): CapabilityDefinition[]`, `getDocumentTypes(): DocumentType[]`, `getOverviewTiles(IUser): OverviewTile[]`
- Provider interfaces implemented by modules: `ITrainingCheckProvider::betreuerCheck`, `IObligationProvider::{ensure, cancelIfUnpaid, status}`, `ISyncHandler::handle`
- `ModuleRegistry`: `modules()`, `provider($interface)` (null when a module is inactive → features degrade gracefully), `capabilities()`, `documentTypes()`

To add a new module: implement `IModule` + an event listener + optional
`registerProvider` calls, declare capabilities as `CapabilityDefinition`
(role-based, `lockedToRoles` for non-editable rights), a `DocumentType` for the
document storage, and mount the UI via `mountApp` from `js/lr-common.mjs`.

## Roles & permissions

Fixed roles: `leiter`, `vorstand`, `kasse`, `schulung`, `fz`, `admin` plus the
pseudo-roles `any` (member of any role) and `nca` (only `app.access` +
`settings.manage`). Assignment lives in `role_groups` (role → groups) and
`role_caps` (capability → roles) in the app config; defaults come from
`CapabilityDefinition.defaultRoles`.

Admin settings (Administration → Leiterrunde): "Roles & permissions" (matrix with
warnings: role without group, `fz` with more than one member, `fz` member is a
Nextcloud admin), "Document storage", "Notifications" (role-based recipients
`notify_roles_<type>`, `fz` fixed to the `fz` role), plus one page per module.

## API

Authentication: session + `requesttoken`, or HTTP Basic (app password) +
`OCS-APIRequest: true`. Errors are returned as `{"error":"…"}`.

Base path `/apps/leiterrunde/api`:

| Verb | Paths |
|---|---|
| GET | `/me`, `/overview`, `/persons`, `/persons/birthdays`, `/persons/options`, `/persons/export`, `/persons/{id}`, `/persons/{id}/audit`, `/settings`, `/settings/roles`, `/settings/storage`, `/settings/storage/user-check`, `/groups`, `/users`, `/audit` |
| POST | `/persons`, `/persons/import`, `/settings/secret/regenerate`, `/sync` |
| PUT | `/persons/{id}`, `/settings`, `/settings/roles`, `/settings/storage` |
| DELETE | `/persons/{id}` |

## Sync protocol

`POST /apps/leiterrunde/api/sync` — the core app verifies
`hash_hmac('sha256', "<raw-ts>\n<raw-body>", shared_secret)` (headers
`X-LR-Signature`, `X-LR-Timestamp`; 300 s clock skew, `hash_equals`,
brute-force throttling) and delegates to the registered `ISyncHandler`, or
replies `503 {"error":"Veranstaltungen-Modul nicht aktiv"}` when no sync-capable
module is active.

Inbound payload: `{action, registration}`; `registration.id` is required (400),
`status` ∈ {active,cancelled}, `payment_state` ∈ {open,paid,waived}; 404 for
unknown event/registration, 400 for invalid JSON/action. Obligation rules are
delegated to `IObligationProvider` (reference format
`EVT<local event_id>-REG<remote_reg_id>`).

Signature example:

```bash
BODY='{"action":"registration","registration":{"id":"42","event_id":"wp-123","name":"A","status":"active"}}'
TS=$(date +%s); SIG=$(php -r 'echo hash_hmac("sha256",$argv[1]."\n".$argv[2],$argv[3]);' "$TS" "$BODY" '<secret>')
curl -X POST -H "X-LR-Timestamp: $TS" -H "X-LR-Signature: $SIG" -d "$BODY" \
  https://cloud.example.com/index.php/apps/leiterrunde/api/sync
```

## Localization

Source strings are English; the maintained translation is German (informal
"du", also used for `de_DE`). Other languages fall back to English. Only
`l10n/de.json` is maintained — `de.js`, `de_DE.json` and `de_DE.js` are
generated by `php l10n-tools/build.php <app-id> [...]` (see
[l10n-tools](l10n-tools/)).

Dates, numbers and money amounts follow the Nextcloud user's locale. Status
codes stored in the database, CSV column headers, sync payloads and storage
path placeholders are deliberately not translated.

## Releasing

Releases follow the [app store developer
guide](https://nextcloudappstore.readthedocs.io/en/latest/developer.html) and
are cut from `main` in every suite repo:

```bash
# 1. Version bump + tag
sed -i 's|<version>OLD</version>|<version>NEW</version>|' appinfo/info.xml
git commit -am 'chore: release vNEW'
git tag -a vNEW -m vNEW && git push origin main --tags
gh release create vNEW --title NEW --notes "…"
```

GitHub's auto-generated archives have the wrong top-level folder, so the
app-store tarball is built and signed manually. Requires the app certificate
(`<appid>.key` + `<appid>.crt` in `~/.nextcloud/certificates/`) and the
`master-nextcloud-1` dev container for `occ`:

```bash
# 2. Stage the tagged code and sign inside the container (occ as www-data!)
STAGE=/tmp/pkg && rm -rf $STAGE && mkdir -p $STAGE
git archive --prefix=<appid>/ vNEW | tar -x -C $STAGE
docker cp ~/.nextcloud/certificates/. master-nextcloud-1:/tmp/certs/
docker cp $STAGE/. master-nextcloud-1:/tmp/pkg/
docker exec master-nextcloud-1 bash -c 'chmod 644 /tmp/certs/* && chmod -R 777 /tmp/pkg &&
  su -s /bin/bash www-data -c "php occ integrity:sign-app \
    --privateKey=/tmp/certs/<appid>.key --certificate=/tmp/certs/<appid>.crt \
    --path=/tmp/pkg/<appid>"'
docker cp master-nextcloud-1:/tmp/pkg/<appid>/appinfo/signature.json \
  $STAGE/<appid>/appinfo/signature.json

# 3. Package, generate the store signature, attach to the GitHub release
tar -czf ~/projects/dist/<appid>-NEW.tar.gz -C $STAGE <appid>
openssl dgst -sha512 -sign ~/.nextcloud/certificates/<appid>.key \
  ~/projects/dist/<appid>-NEW.tar.gz | openssl base64 -A
gh release upload vNEW ~/projects/dist/<appid>-NEW.tar.gz -R ollimaz/<appid>
docker exec master-nextcloud-1 rm -rf /tmp/certs /tmp/pkg && rm -rf $STAGE
```

The signed `signature.json` must be inside the archive. Upload the release at
<https://apps.nextcloud.com/developer/apps/releases/new> with the tarball's
download URL and the base64 signature from step 3. (First-time registration
additionally needs `echo -n <appid> | openssl dgst -sha512 -sign
~/.nextcloud/certificates/<appid>.key | openssl base64`.)

## Development

Each app ships self-contained unit tests (OCP stubs, no Nextcloud bootstrap
required):

```bash
cd tests && phpunit -c phpunit.xml
```

`tests/unit/L10nTest.php` verifies that every source string has a German
translation, no orphaned entries exist, generated files are in sync and no
German literals appear in the code.

## License

[AGPL-3.0-or-later](LICENSE)
