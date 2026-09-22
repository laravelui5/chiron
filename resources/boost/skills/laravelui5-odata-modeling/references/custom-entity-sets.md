# Custom Entity Sets Reference

Use `search-docs` for authoritative documentation on resolvers and custom entity sets.

## When a custom entity set is the right answer

Two independent reasons, and either alone is sufficient:

1. **Row count.** The surface returns a large or unbounded number of rows — a master list, an
   export, an aggregate.
2. **No model behind it.** The rows come from a SQL view, a join across tables, an external API or
   a directory of files. The key is the only hard requirement.

It is **not** the right answer for a keyed detail, a header, or a related sub-list. Those are small
and belong on `discoverModel()`, where relations expand for free.

## The measurement that sets the boundary

180 rows, dev SQLite, 50 iterations:

| Path | Time | vs. raw |
|:---|---:|---:|
| Raw `stdClass` streaming — custom set | **0.82 ms** | 1× |
| Eloquent, cast-free, `toArray` | 27.97 ms | ~34× |
| Eloquent, enum + date casts, `toArray` | 60.77 ms | ~74× |
| Eloquent, paged 50 rows | 13.79 ms | ~60× |
| Eloquent with eager `$expand` | 71.71 ms | 3 SQL queries — eager, **not** N+1 |

Two facts fall out. **Casts roughly double hydration cost**, enum casts being the expensive part.
And **`$expand` is eager-loaded**, so the risk on a wide read is per-row hydration, never N+1.

At 10,000 rows the hydrated path is seconds and the streamed path is milliseconds. That gap is the
engine's reason to exist — do not close it for stylistic consistency.

## The shape

A custom entity set declares its columns and hands back a query. The engine runs the standard OData
pipeline — `$filter`, `$orderby`, `$top`, `$skip`, `$count`, projection — on top of what it gets,
and streams the result rather than buffering it.

`query()` is the **first** call in the pipeline, which makes it the seam for anything that has to
narrow the row set before OData touches it. Override it, return a Builder, and the engine never asks
why the rows are what they are.

## Backed enums become `Edm.EnumType`

The columnar contract accepts an **int-backed enum class-string** as a column type, not just an EDM
primitive:

```php
public function columns(): array
{
    return [
        'id'    => EdmPrimitiveType::Int64,
        'tier'  => LicenseTier::class,   // int-backed PHP enum
    ];
}
```

What happens then:

- `$metadata` carries a real `Edm.EnumType` whose members are the **PHP case names**, with
  `UnderlyingType` fixed at `Edm.Int32`. String and pure enums are rejected.
- The wire carries the **symbolic member name** — `tier: 1` is emitted as `tier: "Single"`.
- Coercion happens at the HTTP layer, so the underlying query still selects the raw integer;
  `->query()->get()` in a test returns ints, not names.
- **Unknown integers fall through raw**, which keeps the surface drift-safe when the database holds
  a value the enum does not know yet.

**Labels are not the engine's business.** It emits the member name; a human-readable label is a
client concern, mapped from the member name to a translation key. Never put display text in the
schema.

## What does not exist yet

There is no lean, hydration-free path for `discoverModel()` reads that carry no `$expand` and no
meaningful casts. It is a plausible optimisation and it is filed on the package roadmap — it is
**not** implemented. Do not write code that assumes a discovered set will approach raw speed, and do
not "optimise" a list onto `discoverModel()` in anticipation of it. Until it lands, large lists stay
on custom entity sets.
