---
name: laravelui5-security
description: >-
  Authorize a LaravelUi5 surface — apps, actions, OData entity sets and controls — with the SDK's
  time-aware RBAC. Use this whenever adding or changing who may open, run or read something, when
  deciding how to cut an app, or when a grant does not take effect. Covers the four ability types
  (#[Access], #[Act], See, #[Read]), the role-first declaration rule, the capability mini-app cut,
  scoped roles and the morph map, and the law Authz = f(App, Actor, Time). Triggers: "add a
  permission", "who may", "#[Access]", "#[Act]", "#[Read]", "#[Role]", "403", "hide a button",
  "restrict this entity set", "role scope", "the grant does not work", "authorization", "RBAC",
  "AbilitySet", "SdkContext::abilities".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Authorization is an SDK capability

This installation has `laravelui5/core` without `laravelui5/sdk`. **Core is stateless about the
organisation**: it knows artifacts, routes, manifests and the execution pipeline, and nothing about
users, tenants, partners or roles. There is no `#[Access]`, no `#[Act]`, no `#[Read]` and no ability
system here — that is a deliberate refusal, not a gap.

**Authorize with ordinary Laravel instead**: the `ui5` route middleware stops anonymous calls, and a
policy or gate — best called from a `FormRequest::authorize()` — decides the rest. See the
`laravelui5-actions` skill.

Time-aware, partner-scoped RBAC lives in `laravelui5/sdk` (**laravelui5.com/sdk/security/**).
@else
# Authorizing with the SDK

## The law

**`Authz = f(App, Actor, Time)`.** Every answer depends on three coordinates, and time is one of
them: every grant carries the window in which it holds. The engine resolves an actor's effective
abilities in **one** SQL query — no N+1, no in-PHP filtering, no cached partial state — and the
result is an `AbilitySet`, reached through `SdkContext::abilities()`.

> **Security is opt-in, not opt-out. If you do not put a gate on your code, everybody can open the
> door.**

That sentence is from the framework's own source, and it is the single most important thing on this
page. An ungated artifact is public to every signed-in partner.

## The four ability types

| Type | Answers | Declared on | Enforced |
|:---|:---|:---|:---|
| **Access** | May this actor **open** it? | an artifact class — app, dialog, value help, card, dashboard, group, report, resource, tile, chart | per request (403); it also disappears from navigation, search and the Launchpad |
| **Act** | May this actor **run** it? | an action class | when the action is dispatched (403) |
| **See** | May this actor **see this control**? | the app manifest (`sap.ui.viewModifications`) | when the manifest is served — the control is hidden |
| **Read** | May this actor **read this entity set**? | the entity set's source class | at the OData boundary (403, or the `$expand` is dropped) |

**Access, Act and Read are enforced on the server. `See` only shapes the screen** — never treat it
as protection. A hidden button is a nicer UI, not a closed door.

## The trap that costs the most: `#[Access]` does not gate OData

An app's `#[Access]` gates **opening the app**, not its OData endpoint.

> **An entity set without `#[Read]` can be read by every signed-in partner.**

Gate every set that is not meant for everyone. See `references/read-gate.md`.

## Declaring an ability

```php
#[Role(AcmeRole::Accounting, 'Books invoices and closes periods.')]
class AccountingModule extends AbstractUi5Module { /* … */ }

#[Access(ability: 'openInvoices', role: AcmeRole::Accounting, note: 'Open the invoicing app.')]
class InvoicingApp extends AbstractUi5App { /* … */ }
```

All three ability attributes share one shape — `(string $ability, string|BackedEnum $role, string
$note)` — and three rules hold:

1. **The role comes first.** Every ability belongs to exactly one role, declared on the **module**
   with `#[Role]`. **The registry refuses to load an ability whose role was never declared.**
2. **Names are unique per app, per type.** Two abilities of the same type with the same name in one
   app are refused.
3. **Changing an ability name is a breaking security change.** Existing grants reference the name;
   renaming silently revokes them.

Every ability needs i18n keys for its UI labels:
`abilities.access.<ability>.title` / `.description`, and the same under `act` and `read`.

## How big is an app? The capability cut

Coming from Laravel the instinct is one app with a router and a guard per route. **That is the wrong
cut here.**

> **An app is a capability. If not everyone who may open the app may do this, it is its own app.**

So not one timesheet app with three views, but three apps, three modules, three `#[Access]`
abilities — each with its own router, views and registration. You would arrive at this rule yourself
around the third or fourth app; taking it early saves the refactor.

## Nothing takes effect before `ui5:sync`

Abilities and roles are projected into the database by `ui5:sync`, and the deploy order
**`migrate → sync → cache`** is a hard contract. Until sync has run, **a new `#[Read]` does not lock
anyone out and does not protect anything either.**

So when a grant "does not work", run `ui5:sync` before debugging anything else.

## Never cache a grant

The ability **definition** is identity and may be cached. The **grant** — who holds it, and when —
is never cached; it stays request-time in the `AbilitySet`. Cache an id, never an answer.

And read the actor from the context: **`$context->actor()`, never `Auth::user()`**. Under
impersonation `actor()` and `principal()` differ, and using the wrong one is a silent privilege bug.

## References

- **`references/read-gate.md`** — `#[Read]` in full: where to declare it, what a denied read, a
  denied `$expand` and a `$batch` actually return, and the five conditions under which a read is let
  through anyway.
- **`references/scoping.md`** — which *rows*, as opposed to which *capability*: scoped roles, the
  morph-map footgun that silently breaks grants, and `#[ScopedByRole]`.

## Do not

- **Do not leave an entity set ungated** and assume the app's `#[Access]` covers it.
- **Do not use `See` as protection.** It hides; it does not deny.
- **Do not rename an ability** without treating it as a breaking change.
- **Do not cache an authorization answer.**
- **Do not filter a list in PHP to "secure" it.** Gate the set with `#[Read]` and scope the rows
  with the `#[Scoped*]` family.
@endif
