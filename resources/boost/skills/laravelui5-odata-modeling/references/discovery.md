# Model Discovery Reference

Use `search-docs` for authoritative documentation on model discovery.

## What `discoverModel()` derives

One call — `$this->discoverModel(Order::class)` — produces the entity type, the entity set, and the
key. Specifically it:

- reads the table and maps **every column the model serializes** to a typed EDM property: a
  non-empty `$visible` admits only what it names, `$hidden` removes from that;
- lets **Eloquent casts override** the database type, so a date cast wins over what the column says.
  An `array`/`json`/`object`/`collection` cast is `Edm.String` and goes out as its JSON text;
- since odata 3.1, takes the **facets** from the column: `Nullable`, `Precision`/`Scale` from
  `decimal(p,s)`, `MaxLength` from `varchar(n)`. UI5 formats and validates input by them;
- detects the **primary key**;
- turns `HasMany`, `BelongsTo`, `HasOne` and `BelongsToMany` into **navigation properties**, named
  like the method, minus relations in `$hidden`.
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

## What the model never serializes is not declared

Since odata 4.0, `$hidden` and `$visible` are the schema, always:

```php
class Account extends Model
{
    protected $hidden = ['password', 'tokens'];   // a column and a relation
}
```

`password` and `tokens` are neither in `$metadata` nor on the wire; `$select`, `$filter` and
`$orderby` naming them answer `400`. **The key must stay serializable** — a key in `$hidden` or
outside `$visible` fails loudly when the schema is built. Two models resolving to the same type or set
name fail loudly too, naming both classes.

## The wire carries exactly the declared properties

A discovered row — list, single entity, expanded at any depth — carries the type's declared
properties, the expanded navigations with their `@odata.count`, and the `$compute` aliases. What
else `toArray()` holds stays behind: `$appends`, a `belongsToMany` row's `pivot`, a relation loaded
through `$with` but not expanded. **If a client needs a value, declare it** (a column, a computed
property below) or serve a custom entity set. Never expect an accessor to reach the client.

## The class attributes

All live in `LaravelUi5\OData\Service\Discovery\Attributes`, and all sit on the **class**. Nothing
OData goes on a PHP property of the model: a declared property shadows Eloquent's attribute bag, and
nothing reads one.

```php
use LaravelUi5\OData\Edm\Annotation\Path;
use LaravelUi5\OData\Edm\EdmPrimitiveType;
use LaravelUi5\OData\Service\Discovery\Attributes\{ODataAnnotate, ODataComputed, ODataEntity};
use LaravelUi5\OData\Vocabularies\Common\V1\Label;
use LaravelUi5\OData\Vocabularies\Measures\V1\{ISOCurrency, Unit};

#[ODataEntity(name: 'Item', entitySet: 'Items')]                              // names only, both optional
#[ODataComputed('currency', EdmPrimitiveType::String, nullable: false, maxLength: 3)]
#[ODataComputed('unit', EdmPrimitiveType::String, maxLength: 3)]
#[ODataAnnotate('price', new ISOCurrency(new Path('currency')))]             // a column's terms
#[ODataAnnotate('quantity', new Unit(new Path('unit')))]
#[ODataAnnotate('name', new Label('Item'))]
class ServiceItem extends Model
{
    protected $hidden = ['internal_note'];          // not declared, not filterable, not sent
}
```

- **`#[ODataEntity(name:, entitySet:)]`** names the type and the set. Add it when the derived name
  is wrong for the contract — two classes with the same short name, or an English plural that does
  not fit (`Person` → `People`) — not to restate it.
- **`#[ODataAnnotate('column', …terms)]`** puts vocabulary terms on one declared property. Repeat
  it freely. A name the type does not declare (a typo, a hidden column) fails loudly. A term written
  directly on the class annotates the entity type instead. See `annotations.md`.
- **`#[ODataComputed('name', EdmPrimitiveType::…, nullable:, maxLength:, precision:, scale:)]`**
  declares a property with no column — the code beside a code-list `…_id`, the tenant's base
  currency next to an amount. Its value is an SQL expression from the bound
  `ColumnExpressionResolverInterface`, asked with the model class and the name:

```php
final class ItemExpressions implements ColumnExpressionResolverInterface
{
    public function __construct(private readonly string $baseCurrency) {}   // e.g. the tenant's, per request

    public function expression(string $source, string $property): ?string
    {
        return $source !== ServiceItem::class ? null : match ($property) {
            'currency' => "'" . $this->baseCurrency . "'",
            'unit'     => '(select code from units where units.id = service_items.unit_id)',
            default    => null,
        };
    }
}
```

  The engine selects `(<expression>) AS name` on every read, and `$filter`, `$orderby`, `$search`
  and `$compute` use it. The name must not be a column and must be serializable; a computed property
  no resolver answers fails when queried. **Prefer this over a custom entity set** when the model is
  right and only one or two values are missing.

### Upgrading a 3.x model

`#[ODataProperty]`, `#[ODataIgnore]` and `#[ODataNavigation]` are gone, and with them the hooked
properties they needed. `useHidden:` is gone from `#[ODataEntity]`; drop the argument.

| 3.x | 4.0 |
|:---|:---|
| `#[ODataIgnore]` | the model's `$hidden` |
| `#[ODataProperty(nullable:, precision:, scale:)]` | a `ColumnFacetResolverInterface` answering for model and column |
| `#[ODataProperty(type:)]` | a cast |
| `#[ODataProperty(name:)]`, `#[ODataNavigation(name:)]` | a custom entity set |
| a vocabulary attribute on a hooked property | `#[ODataAnnotate]` on the class; delete the hooked property |

## Facets that depend on the installation

Some facets are not a fact of the column. A unit price stored as `decimal(19,6)` may have to be
announced with the installation's price decimals. For that, bind a
`LaravelUi5\OData\Service\Contracts\ColumnFacetResolverInterface` (since 3.1). Discovery calls it for
every column with the model class, the column, the model's cast and the schema's facets, and uses
the facets it returns. The order is schema → resolver. Because it receives the model class, it also
answers for one model (`$modelClass === Product::class && $column === 'unit_price'`). A facet the
type cannot carry (`scale` on a string, `scale` above `precision`) fails loudly when the schema is
built.

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
