---
name: laravelui5-partners
description: >-
  Work with the LaravelUi5 partner model — the one identity the SDK authorizes. Use this when
  connecting your User model, when asking "who is the actor", when modelling companies, people,
  departments, contacts or supplier relationships, or when the word "role" has become ambiguous.
  Covers the users.partner_id bridge and HasPartnerInterface, the four different things called
  "role" and how to tell them apart, PartnerType, org partners vs tenant, the system actor for
  non-human writes, and the #[PartnerRole] / #[RelationshipType] catalogs. Triggers: "partner",
  "who is the current user", "users.partner_id", "HasPartner", "customer", "supplier",
  "employed_by", "contact person", "system actor", "system_level", "org partner", "which role do I
  mean", "sdk_partners".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# There are no partners in Core

`laravelui5/core` is **stateless about the organisation**: it knows artifacts, routes, manifests and
the execution pipeline, and nothing about users, tenants, partners or roles. That is a deliberate
refusal — a kernel that assumes an identity model cannot be used by an application that has a
different one.

So in a Core-only host, identity is **entirely yours**: your `users` table, your policies, your
gates. Core's `Ui5ContextInterface` carries only the resolved `artifact()` and the request
`locale()`.

The partner model — one table for organisations, people and departments, with time-bound roles,
memberships and delegations — is `laravelui5/sdk`
(**laravelui5.com/sdk/partners/**).
@else
# The partner model

## One identity, and a login is not it

**Everything the SDK authorizes is a partner**: an organisation, a person or a department
(`PartnerType::Organization` / `Person` / `Department`). Customers' companies, their employees, your
own organisation and the platform owner all live in **one table, `sdk_partners`**.

**A login is not a partner.** Your `users` table stays yours and points at a partner. A login object
is optional, and a partner has at most one. Three consequences worth internalising:

- **Partners who never sign in are first-class** — a supplier, a department, a customer's company all
  have memberships and roles of their own.
- **The sign-in mechanism is yours.** The SDK owns no `users` table and no sign-in.
- **Ending access does not erase the person.** Removing access deletes a login object; the partner
  and its history stay.

## The bridge

```php
$table->unsignedInteger('partner_id')->nullable()->after('id');
$table->foreign('partner_id')->references('id')->on('sdk_partners')->nullOnDelete();
```

```php
class User extends Authenticatable implements HasPartnerInterface
{
    use HasPartner;
}
```

`HasPartnerInterface` asks for exactly one method — `partner(): ?Partner` — and the trait answers it
with a `belongsTo` on `partner_id`. **That method is the whole contract.** If your mapping is
different (by email, through another column, from an external directory), implement it yourself.

## Four different things are called "role"

Mixing these up costs an afternoon. Ask which question you are answering:

| Concept | Answers | Lives in | Grants access? |
|:---|:---|:---|:---|
| **System level** | where does this partner stand in the installation | `sdk_partners.system_level` | **no** |
| **Role** | what may this partner **do** | `sdk_role_assignments` | **yes** — with abilities and groups |
| **Membership** | whom does this partner **belong to** | `sdk_partner_relationships`, e.g. `employed_by` | no, but it **scopes data** |
| **Partner role** | what **function** does this partner have in the business | `sdk_partner_role_assignments` | no |

And the fourth splits again, by the catalog row's `PartnerRoleScope`:

- **`Structural`** — the business **classification** axis: `customer`, `supplier`. Drives the master
  filter, deduped by code, **current-window only** so expired classifications drop off.
- **`Functional`** — the partner **functions**: `primary_contact`, `bill_to`. This is the Contacts
  card — a person in a function for an organisation.

**Neither is security.** Security is `sdk_role_assignments`, the Authorization tab. A `customer`
classification grants nothing.

## The actor, the org, and the tenant are three things

```php
$context->actor();                    // the partner this request acts as
$context->principal();                // who is really signed in (differs under impersonation)
$context->primaryOrgPartner($at);     // the org the actor is a primary employee of, at that moment
$context->orgPartners($at);           // all of them
$context->tenant();                   // the owner of the site — the SaaS account boundary
```

**Org partners are actor-related** — the business the signed-in person acts on behalf of. **The
tenant is site-related** — the account boundary. They are not the same question and they never
collide.

Both org accessors take an explicit `Carbon $at`, because employment is time-bound, and both are
**fail-closed**: no active primary employment at that moment returns an empty result, never
"everything".

## The system actor — who wrote, when nobody did

When a write happens that no person made — `ui5:sync` recording defaults, a scheduled job, a webhook
— the **system actor** is recorded as its author. It is the platform owner: an ordinary partner at
the top of the system-level ladder.

The SDK reads `config('ui5.system_actor_id')` (default `env('UI5_SYSTEM_ACTOR_ID', 1)`), and
`SystemActorResolverInterface` resolves it. **The default resolver fails loudly** —
`MissingSystemActorException` if no partner has that id, `SystemActorEditLevelException` if it is not
at platform-owner level. `ui5:sync` resolves it before writing, so a missing platform owner stops the
first sync.

**It records who wrote. It is not an account that signs in, and it grants nothing.**

## The two catalogs

Partner roles and relationship types are **Customizing** — declared in code as repeatable
attributes, reconciled into their tables by `ui5:sync`:

```php
#[PartnerRole(code: 'customer', scope: PartnerRoleScope::Structural, name: 'Customer', icon: '…')]
#[RelationshipType(code: 'employed_by', name: '…', directionalName: '…', reverseName: '…', scope: …)]
```

They are the **vocabulary**, not the assignments. See the `laravelui5-settings` skill,
`references/customizing.md` — and never write to those tables at runtime, because the next sync
deletes undeclared rows.

## Do not

- **Do not read the actor from `Auth::user()`.** Use `$context->actor()`.
- **Do not confuse `actor()` with `principal()`.** Under impersonation they differ, and using the
  wrong one is a silent privilege bug.
- **Do not confuse an org partner with the tenant.**
- **Do not treat a `customer` or `supplier` classification as a permission.**
- **Do not call `orgPartners()` without the time** — employment windows are why it takes one.
- **Do not model a login as a partner**, or delete a partner to remove access. Delete the login
  object.
@endif
