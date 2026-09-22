---
name: laravelui5-odata-modeling
description: >-
  Model a read surface on laravelui5/odata — the OData v4 engine for Laravel. Use this when adding
  or reshaping anything a client reads: a list, a keyed detail, a related sub-list, a count, a
  filterable master, an export. The core is a five-step decision tree — native column → real
  Eloquent relation (free $expand) → scope ($filter) → relation plus shaping → virtual expand (only
  for the genuinely computed) — gated by a performance rule that overrides it: small-N detail uses
  discoverModel(), large-N lists stay raw-SQL custom entity sets, because the engine is fast by
  streaming rows rather than hydrating models. Triggers: "add an entity set", "expose this model
  over OData", "OData read", "master/detail", "$expand", "$filter", "$metadata", "discoverModel",
  "custom entity set", "AbstractEntitySet", "virtual expand", "the OData list is slow", "how do I
  filter/sort/page this", "expose an enum over OData", "odata:cache".
license: MIT
metadata:
  author: laravelui5
---

# Modeling a read surface on laravelui5/odata

## When to use this skill

Any time data has to leave the application for a client to read — a UI5 table, Excel, Power BI,
react-admin, a `fetch` call. Do **not** use it for writes: the engine is read-only, and writes stay
in ordinary Laravel controllers, form requests and jobs.

## The rule that frames everything

**One Eloquent model per table. Relations become `$expand`s, scopes become `$filter`s, and the
exotic tools are for the genuinely computed.**

A list and a detail are views of the *same* table, so they are one discovered set: the list reads
it with `$select`, the detail reads `Set(id)?$expand=…`. Most "aspects" of a subject turn out to be
relationships nobody has written yet. Reach for a custom entity set or a virtual expand **last**.

## The decision tree — take the FIRST that fits

1. **A column on the table** → native property. Free with `discoverModel()`.
2. **A real Eloquent relation** → free `$expand`. Write the relation, discover **both** models.
3. **A filter over the list** → an Eloquent scope, surfaced through `$filter`.
4. **A relation that needs reshaping** (union of two directions, dedup, a catalog join) → the
   relation `$expand` plus light shaping in the client.
5. **Genuinely computed, with no relation to reflect** (a cross-table sum, a catalog×override
   left join) → a virtual expand. **Only this case earns one.**

If you are writing a custom entity set and the honest answer to *"is this just a relation or a
scope?"* is yes — stop and go back to the model.

## The performance gate — row count overrides the tree

The engine is fast because it **streams optimized SQL rows and does not hydrate Eloquent models**.
Hydration costs roughly **30–75× per row** (measured, see `references/custom-entity-sets.md`).

- **Small, bounded N** — a keyed detail with a few `$expand`s, a related sub-list → `discoverModel()`.
  Use the application's own model, casts and all; at one row the overhead is noise.
- **Large or unbounded N** — a master list, an export, an aggregate → a **raw-SQL custom entity
  set**, registered with `discoverCustomEntitySet()`.

**A custom entity set on a big list is not legacy debt.** Never convert one to `discoverModel()`
for consistency — that trades away the reason the engine exists. `$expand` is eager-loaded, so N+1
is not the risk; per-row hydration is.

## The shape of a service

```php
use LaravelUi5\OData\ODataService;
use LaravelUi5\OData\Service\Contracts\EdmBuilderInterface;

class SalesService extends ODataService
{
    public function serviceUri(): string { return ''; }
    public function namespace(): string  { return 'App.Sales'; }

    protected function configure(EdmBuilderInterface $builder): EdmBuilderInterface
    {
        $this->discoverModel(Order::class);      // small-N detail, relations for free
        $this->discoverModel(Customer::class);   // discover the RELATED model too
        $this->discoverCustomEntitySet(OrderList::class); // large-N list, raw SQL

        return $builder->namespace($this->namespace());
    }
}
```

The detail reads `Orders(42)?$expand=customer`; the list reads `OrderList?$filter=…`. Never list the
discovered `Orders` *collection* on a large table — that is what `OrderList` is for.

## Which reference to read before implementing

- **`references/discovery.md`** — what `discoverModel()` derives, the four attributes, casts,
  relations as navigation properties, and virtual expands (contract and scope).
- **`references/custom-entity-sets.md`** — when a custom set is justified, the measured numbers,
  the `columns()` contract, backed enums as `Edm.EnumType`, streaming.
- **`references/consuming.md`** — the URL surface, `$batch`, read authorization behaviour, caching,
  and what the common clients expect.

## Three things not to do

- **Do not write a controller, API resource or `?include=` convention** for data an OData service
  already serves. Declaring the service is the endpoint.
- **Do not hand-write or edit CSDL.** `$metadata` is generated from the model and the annotations.
- **Do not add `#[ODataEntity]` or `#[ODataNavigation]` for their own sake.** They exist to
  override what discovery already derives correctly.
