# Model Discovery Reference

Use `search-docs` for authoritative documentation on model discovery.

## What `discoverModel()` derives

One call — `$this->discoverModel(Order::class)` — produces the entity type, the entity set, and the
key. Specifically it:

- reads the table and maps **every column** to a typed EDM property (minus `#[ODataIgnore]`, and
  minus `$hidden` when the class says `#[ODataEntity(useHidden: true)]`);
- lets **Eloquent casts override** the database type, so an `array` cast or a date cast wins over
  what the column says;
- since odata 3.1, takes the **facets** from the column: `Nullable`, `Precision`/`Scale` from
  `decimal(p,s)`, `MaxLength` from `varchar(n)`. UI5 formats and validates input by them;
- detects the **primary key**;
- turns `HasMany`, `BelongsTo`, `HasOne` and `BelongsToMany` into **navigation properties**.
  **Polymorphic relations stay out, all of them** (`morphTo`, `morphMany`, `morphOne`,
  `morphToMany`, `morphedByMany`), and so do through-relations. A polymorphic join runs over a type
  column plus an id, which `$metadata` cannot state. To serve such an edge, model it explicitly: a
  custom entity set for one type, a virtual expand, or a plain relation on a non-polymorphic table.

## The one requirement people miss

A relation only becomes a navigation property **if the related model is also discovered**. Two
models, two `discoverModel()` calls, and `$expand=customer` works with no further code:

```php
$this->discoverModel(Order::class);
$this->discoverModel(Customer::class); // without this, Order::customer() is not expandable
```

If an `$expand` returns a 400 for an unknown navigation property, this is almost always why.

## The four attributes

All live in `LaravelUi5\OData\Service\Discovery\Attributes`.

```php
use LaravelUi5\OData\Service\Discovery\Attributes\{ODataEntity, ODataProperty, ODataNavigation, ODataIgnore};

#[ODataEntity(name: 'Order', entitySet: 'Orders', useHidden: true)]   // class; all arguments optional
class Order extends Model
{
    protected $hidden = ['internal_token'];        // with useHidden: not in $metadata, not filterable

    #[ODataProperty(name: 'netAmount', nullable: false, precision: 19, scale: 4)]   // property
    public ?string $amount {
        get => $this->getAttribute('amount');
        set(?string $value) { $this->setAttribute('amount', $value); }
    }

    #[ODataIgnore]                                    // property or method
    public ?string $internal_note {
        get => $this->getAttribute('internal_note');
        set(?string $value) { $this->setAttribute('internal_note', $value); }
    }

    #[ODataNavigation(name: 'customer')]              // method
    public function buyer() { return $this->belongsTo(Customer::class); }
}
```

### A column attribute needs a hooked property — never a plain one

Column-level attributes (`#[ODataProperty]`, `#[ODataIgnore]`, vocabulary annotations) are read from a
declared PHP property named like the column. **A plain `public $amount;` breaks the model:** it shadows
Eloquent's attribute bag, so reads return `null` and writes are lost on `save()`. Declare a PHP 8.4
property with hooks that delegate to the bag, exactly as above:

- **Write `set` as a block.** `set($v) => $this->setAttribute('col', $v)` *assigns* the returned model
  to the property, and `$order->amount = …` then throws a `TypeError`. Mass assignment
  (`create()`, `fill()`, `update()`) bypasses the hook and hides the bug.
- **Type it nullable.** The `get` hook returns whatever the bag holds, and that is `null` for a
  column a fresh model has not been given yet.
- Only annotated columns need this. Every other column keeps going through `__get()`.

### What each attribute is for

Every argument is optional and every attribute is an **override**. Discovery already derives the
type name from the class, the set name from the table, the property names from the columns, the
facets from the column types and the navigation names from the relation methods. Add an attribute
when the derived value is wrong for the wire contract, not to document what is already true.

- **`useHidden: true`** (since 3.1) is the one to reach for on any model with secrets in `$hidden`.
  Without it, a hidden column never leaves the server but is still *declared*, and a declared
  column is filterable: `$filter=startswith(password,'$2y')` interrogates it. The key always stays.
- **`#[ODataIgnore]`** keeps a single column, accessor or relation out of the schema.
- **`nullable:`, `precision:`, `scale:`** (since 3.1) override the column's facets for one model.
  An override that the type cannot carry (`scale` on a string, `scale` above `precision`) fails
  loudly when the schema is built.

## Facets that depend on the installation

Some facets are not a fact of the column. A unit price stored as `decimal(19,6)` may have to be
announced with the installation's price decimals. For that, bind a
`LaravelUi5\OData\Service\Contracts\ColumnFacetResolverInterface` (since 3.1). Discovery calls it for
every column with the model class, the column, the model's cast and the schema's facets, and uses
the facets it returns. The order is schema → resolver → attribute.

```php
final class PriceDecimals implements ColumnFacetResolverInterface
{
    public function resolve(string $modelClass, string $column, ?string $cast, TypeFacets $facets): TypeFacets
    {
        return $cast === UnitPrice::class ? $facets->withScale(config('shop.price_decimals')) : $facets;
    }
}
```

**The resolver runs when the schema is built.** `odata:cache` freezes its answer, so re-run
`odata:cache` after the installation fact changes.

## Casts and the cost that follows

Casts are honoured, and they are the expensive half of hydration — enum casts especially, roughly
doubling per-row cost. That does not matter on a keyed detail. It matters enormously on a list,
which is the reason lists belong on custom entity sets rather than on a leaner cast-free model. If
you find yourself building a cast-free read model to make a discovered list faster, the model is
not the problem: the list should not be a discovered set at all.

## Virtual expands — the last resort, and its exact scope

A virtual expand makes a computed collection appear as a navigation property on a discovered model
**without a real Eloquent relation behind it**. Implement `VirtualExpandResolverInterface`
alongside `CustomEntitySetInterface` and register with `discoverCustomEntitySet()`:

```php
use LaravelUi5\OData\Service\Contracts\{CustomEntitySetInterface, VirtualExpandResolverInterface};
use LaravelUi5\OData\Protocol\Planning\ExpandItem;

class Kpis implements CustomEntitySetInterface, VirtualExpandResolverInterface
{
    public function expandsOn(): array   // EntityType => navigation property name
    {
        return ['User' => 'kpis', 'Project' => 'kpis'];
    }

    public function resolveExpand(array $parentRow, string $parentEntityType, ExpandItem $expand): array
    {
        // $parentRow carries the parent's data; $expand carries $filter, $select, …
        return [['kpi_id' => 1, 'name' => 'Hours', 'value' => 42.0]];
    }
}
```

Registration wires the navigation properties and bindings on the parent entity types automatically.

**The scope constraint that decides most designs:** virtual expands resolve **only on discovered
(Eloquent) sets**. A custom entity set cannot carry them. So a detail or header built as a computed
custom set silently forfeits `$expand` — and the fix is never "make expands work on custom sets", it
is "stop being a custom set". Model the detail with `discoverModel()` and put the computed part in a
virtual expand.

Reserve them for the genuinely computed: a sum across tables, a catalog left-joined with overrides.
A union of two relations is not computed — it is two relations.

`$count` inside `$expand` works on real relations (since 3.1) and answers `501` on a virtual one: the
resolver decides which rows it returns, so the engine cannot know the total. If a client needs it,
return the count as a property of its own.
