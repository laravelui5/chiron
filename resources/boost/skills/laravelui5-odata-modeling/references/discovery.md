# Model Discovery Reference

Use `search-docs` for authoritative documentation on model discovery.

## What `discoverModel()` derives

One call — `$this->discoverModel(Order::class)` — produces the entity type, the entity set, and the
key. Specifically it:

- reads the table and maps **every column** to a typed EDM property (minus `#[ODataIgnore]`);
- lets **Eloquent casts override** the database type, so an `array` cast or a date cast wins over
  what the column says;
- detects the **primary key**;
- turns `HasMany`, `BelongsTo`, `HasOne` and `BelongsToMany` into **navigation properties**.

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

#[ODataEntity(name: 'Order', entitySet: 'Orders')]   // class; both arguments optional
class Order extends Model
{
    #[ODataProperty(name: 'netAmount', type: 'Edm.Decimal', nullable: false)]  // property
    public $amount;

    #[ODataIgnore]                                    // property or method
    public $internalNote;

    #[ODataNavigation(name: 'customer')]              // method
    public function buyer() { return $this->belongsTo(Customer::class); }
}
```

Every argument is optional and every attribute is an **override**. Discovery already derives the
type name from the class, the set name from the table, the property names from the columns and the
navigation names from the relation methods. Add an attribute when the derived name is wrong for the
wire contract, not to document what is already true.

`#[ODataIgnore]` is the one you will reach for legitimately: it keeps a column, an accessor or an
appended attribute out of the schema.

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
