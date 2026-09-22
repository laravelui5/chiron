# Scoping — which *rows*, as opposed to which *capability*

Two different axes, and they never share a name:

| Axis | Question | Mechanism |
|:---|:---|:---|
| **Capability** | *what* may the actor do | `AbilitySet`, `#[Access]` / `#[Act]` / `#[Read]` |
| **Data scope** | *which rows* may the actor see | `ScopedAbstractEntitySet` + one `#[Scoped*]` attribute |

Passing the read gate says the actor may read the set. It says nothing about which rows come back.

## `ScopedAbstractEntitySet`

It narrows your source query **before** OData applies `$filter / $orderby / $skip / $top`.

1. Extend `ScopedAbstractEntitySet` and implement `baseQuery(): Builder` — the **unscoped** query.
2. Declare **at most one** scope attribute on the class. Two families exist; declaring two, or
   mixing the families, throws a `LogicException`.

> **Fail-closed by design.** With no actor context bound, or an actor that resolves to an empty set,
> the query returns **no rows** (`1 = 0`) — never "everything". A scoped entity set cannot leak rows
> by accident.

## Family 1 — partner scope

The attribute names a resolver and a **key column** (default `partner_id`); the applier emits
`whereIn(keyColumn, ids)`.

- `#[ScopedToActor]` → rows whose column equals the actor's own `partner_id`.
- `#[ScopedToOrgActors]` → rows whose column matches an org the actor is a primary employee of.

**Column contract:** `baseQuery()` **must expose the key column** — `partner_id` by default, or
whatever you pass (`#[ScopedToOrgActors('org_id')]`).

A new bridge is one resolver (`ScopeResolverInterface`) plus one sugar attribute
(`PartnerScopeAttributeInterface`) — never a change to the applier.

## Family 2 — role scope

`#[ScopedByRole(role, contextType)]` shows the rows the actor holds `role` on, within `contextType`.
The applier emits `whereIn("{contextType}_id", contextIds)`.

```php
#[ScopedByRole('key_account', 'partner')]
final readonly class ProspectsEntitySet extends ScopedAbstractEntitySet
{
    protected function baseQuery(): Builder
    {
        return DB::table('prospects_view');   // exposes partner_id
    }
}
```

**Column contract:** `baseQuery()` must expose `"{contextType}_id"` — `partner` → `partner_id`,
`territory` → `territory_id`.

## Scoped roles and the morph map — the silent footgun

A `#[Role]` can be scoped to a model: *"key account manager **for this customer**"*.

```php
#[Role(role: 'key_account', note: 'Key account manager for a customer.', scope: Customer::class)]
class SalesModule implements Ui5ModuleInterface { /* … */ }
```

The scope is written as a **class name** at declaration time and stored as a **morph key** at sync
time: `RolesWorker` resolves FQCN → key through Laravel's `Relation::morphMap()`.

**If the model is not in the morph map, the worker stores the raw FQCN instead.** That works — and
silently couples your persisted role scopes to a class path. The day the class moves namespace, the
stored keys stop matching and **grants quietly stop resolving**. No error, no log line.

> **Register the morph map before running `ui5:sync`.** Every model used as a `#[Role]` scope or a
> `#[Scoped*]` context needs a stable morph alias.

```php
// AppServiceProvider::boot()
Relation::morphMap([
    'customer'  => \App\Models\Customer::class,
    'territory' => \App\Models\Territory::class,
]);
```

The morph key is the shared vocabulary across the whole chain: `RolesWorker` writes it,
`RoleAssignmentQuery` filters on it, and `RoleScopeApplier` matches it against `contextType`. Keep
the map stable and the chain holds.

## Do not

- **Do not filter rows in PHP after the query** to "secure" a list. Scope it at the source, where it
  is fail-closed.
- **Do not declare two scope attributes** on one set, or mix the two families — it throws.
- **Do not forget the column.** The applier assumes a column the base query must actually expose.
- **Do not rely on a scope for capability.** A scoped set still needs `#[Read]` if not everyone may
  read it at all.
