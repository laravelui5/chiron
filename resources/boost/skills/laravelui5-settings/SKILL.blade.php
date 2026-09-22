---
name: laravelui5-settings
description: >-
  Make something configurable in LaravelUi5 — and pick the right one of the three mechanisms that
  look alike. A Setting answers "how is this configured" (a threshold, a limit, a switch) and is
  declared on an artifact; a Slot answers "in what context" (currency, locale, time zone) and is
  declared on a module; Customizing is a code-owned reference catalog (role codes, document types)
  projected into tables by ui5:sync. Covers #[Setting], #[Slot], EditLevel, the five-scope
  precedence ladder, who may write what, and Customizing vs Tailoring. Triggers: "make this
  configurable", "#[Setting]", "#[Slot]", "a threshold", "default currency", "per-tenant value",
  "settings app", "EditLevel", "scope override", "customizing", "reference table", "dropdown
  values", "the setting always returns the default".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Settings in Core: the default, and nothing else

`#[Setting]` and `#[Slot]` are **Core** attributes and they work here — but understand what Core
does with them:

> Core resolves **the declared default and nothing else**. There is no storage, no per-user and no
> per-tenant value. Core does not even cast the default — you get back exactly the literal you
> declared.

So a setting in a Core-only host is a **documented, typed constant with one declaration site**. That
is genuinely useful: it keeps magic numbers out of the code and names them. It is not configuration.

```php
#[Setting(key: 'pageSize', type: ValueType::Int, default: 10, note: 'Rows per page.')]
```

Declare it on the **artifact class**. `EditLevel` is accepted and carries no meaning here — Core is
auth-blind and enforces nothing.

**Storage, the five-scope override ladder, the Settings app and the write gates are
`laravelui5/sdk`** (laravelui5.com/sdk/settings/). If you need a value that differs per tenant, site
or user, that is the package that provides it.
@else
# Making something configurable

## Three mechanisms, and choosing between them

This is where most of the confusion in the stack lives. Ask what *kind* of thing you are declaring:

| | **Setting** | **Slot** | **Customizing** |
|:---|:---|:---|:---|
| Answers | *how is this configured* — a threshold, a limit, a switch | *in what context* — currency, locale, time zone | *what vocabulary exists* — role codes, document types, tax codes |
| Declared on | an **artifact** class | a **module** class | an attribute, per catalog row |
| Belongs to | the configuration | **the person** | your code |
| Changed in | the Settings app | the **Partners** app (Parameters tab) | nowhere — it is redeployed |

**The short test: if a value belongs to the person rather than to the configuration, declare a
slot.** Core ships currency, locale and time zone as slots for exactly that reason.

## `#[Setting]` — declare it on the artifact

```php
#[Setting(key: 'pageSize', type: ValueType::Int, default: 10, note: 'Rows per page.',
          level: EditLevel::Organization)]
class InvoicingApp extends AbstractUi5App { /* … */ }
```

> **The declaration site is the artifact class — an app, an action — and that is the only one there
> is.** You may see older docblocks suggesting a handler, a resource or a data provider. Do not
> follow them: a setting declared there never reaches the Settings app and can never be configured.

`ui5:sync` writes each declared setting as the `Platform` row of that artifact, after which it
appears in the Settings app under the artifact's title.

**Reports are the exception**: a report provider gets no settings injected. Parameterise a report
with slots instead.

## `#[Slot]` — declare it on the module

Repeatable, and it mirrors the `#[Role]` convention. The registry harvests slots at boot and
**auto-expands each into a synthetic setting keyed `slot.{name}`**.

- **On an artifact class it fails at boot.** PHP cannot narrow the attribute's target to modules, so
  the registry checks it instead.
- **`note` is mandatory.** An empty string fails validation at boot; `'TBD'` is a permitted
  placeholder.
- **`editable` is a governance hint and no more.** Core is auth-blind and enforces nothing; its one
  reader is the slot-to-setting expansion, which carries the level into the synthetic setting.

A slot's `slot.*` setting is **refused by the Settings app before any other check** — the parameter
chain never reads an override of it, so a value stored there would go unseen. **Slots are overridden
per person, in the Partners console.**

## Reading — and never writing

```php
$context->setting('pageSize');     // the artifact's own, scope-resolved
$context->appSetting('theme');     // the same for the artifact's app
```

Both are resolved **once, when the context is built**, and held as a flat map. **Application code
never writes a setting.**

## The five scopes

Core's `Scope` enum, least to most specific. **The highest scope present wins.**

| Scope | Meaning |
|:---|:---|
| `Platform` | the default declared in code, seeded by `ui5:sync` |
| `Installation` | shared override, operator admin |
| `Tenant` | shared override, tenant admin or above |
| `Site` | shared override, local admin or above |
| `User` | one person's value |

## Who may change one — five checks, in order

Opening the Settings app needs the `settings-admin` ability; saving and resetting need `setSetting`
and `resetSetting`. Beyond that every change passes:

1. **Not the default.** The `Platform` row is never written by hand — only `ui5:sync` writes it.
2. **Declared.** A key with no `Platform` row cannot be overridden.
3. **Edit level.** The partner's edit level must reach the setting's `level`.
4. **Scope.** A partner writes at their own scope and at more specific ones.
5. **On behalf.** Setting another partner's `User` value needs `LocalAdmin` or higher.

Checks 3 to 5 are decided by the partner's **system level** (`sdk_partners.system_level`):
`User` → `LocalAdmin` → `TenantAdmin` → `OperatorAdmin` → `PlatformOwner`, each reaching one scope
further down the ladder. **The system level grants nothing by itself** — without the `local_admin`
role a partner cannot open the Settings app at any level.

A change that fails a check, or a value that does not fit the declared type, is refused with its
reason and nothing is stored.

## Customizing is the third thing

Controlled vocabularies — partner roles, relationship types, document types — declared in code and
reconciled into tables by `ui5:sync`. See `references/customizing.md`; the distinction that matters
is **Customizing (the vocabulary, owned by your code) vs Tailoring (the assignments, owned by the
running system)**. They never share a table.

## Do not

- **Do not declare a setting on a handler or provider.** The artifact class is the only site.
- **Do not write a setting from application code.**
- **Do not use a setting for something that belongs to the person.** That is a slot.
- **Do not use a setting for a business relationship.** A list of ids stored as a setting is a weak
  reference with no foreign key; if the configuration expresses a domain rule, model it properly.
- **Do not expect a new setting to appear before `ui5:sync` has run.**
@endif
