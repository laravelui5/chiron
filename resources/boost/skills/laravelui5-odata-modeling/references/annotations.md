# Annotations Reference

Use `search-docs` for authoritative documentation on annotations and vocabulary terms.

Everything here is **odata 4.0**.

## Vocabulary terms are attributes

The generated vocabulary classes live under `LaravelUi5\OData\Vocabularies\{Vocabulary}\V1`. Everything
goes on the **model class**:

- a term written directly on the class annotates the **entity type** (`#[SelectionFields([...])]`);
- a column's terms go in **`#[ODataAnnotate('column', …terms)]`**, as objects:

```php
use LaravelUi5\OData\Service\Discovery\Attributes\ODataAnnotate;

#[ODataAnnotate('name', new Label('Product name'), new Description('Shown in lists'))]
#[ODataAnnotate('name', new Label('Short', qualifier: 'Tile'))]
class Product extends Model {}
```

The column must be one the type declares (or an `#[ODataComputed]` property), else discovery fails
loudly. Never put a term on a PHP property of the model — nothing reads it since odata 4.0. Discovery
turns the terms into `$metadata` annotations. Never hand-write CSDL.

A **package** that annotates columns across models (the SDK marks translated fields
`Core.IsLanguageDependent`) binds a `ColumnAnnotationResolverInterface`, asked once per declared column
with the model class and the column. On the same term and qualifier the model's `#[ODataAnnotate]`
wins. It runs at schema build, so `odata:cache` freezes its answer.

## A value read per row is a `Path`

Some terms do not carry a fixed value; they point at another property of the same entity. The
currency of an amount is the typical case. Pass a `Path` instead of a constant:

```php
use LaravelUi5\OData\Edm\Annotation\Path;
use LaravelUi5\OData\Vocabularies\Measures\V1\ISOCurrency;

#[ODataAnnotate('amount', new ISOCurrency(new Path('currency')))]
class Order extends Model {}
```

The path may point at a column or at an `#[ODataComputed]` property (the tenant's base currency
when the row stores none). That writes `<Annotation Term="Org.OData.Measures.V1.ISOCurrency" Path="currency"/>`. A `Path` is
accepted by `Measures` (`ISOCurrency`, `Unit`, `Scale`, …), `CodeList.StandardCode`, `Common.Text`,
`Common.UnitSpecificScale` and `Common.UnitSpecificPrecision`. Other vocabularies do not take one yet.
If you need it there, build the annotation programmatically with
`new ConstantAnnotationValue('Path', 'column')`, and don't edit the generated class.

## Service-wide terms go on the container

Terms that apply to the whole service target the entity container. Call `annotateContainer()` in
`configure()`:

```php
protected function configure(EdmBuilderInterface $builder): EdmBuilderInterface
{
    $this->annotateContainer(
        new CurrencyCodes(url: '../codelists@1.0.0/$metadata', collectionPath: 'Currencies'),
    );

    return $builder->namespace($this->namespace());
}
```

`annotateContainer()` is on `ODataService`. Do not look for it on `EdmBuilderInterface`, where it
arrives with the next major.

## Currencies and units: code lists

UI5's `sap.ui.model.odata.type.Currency` and `Unit` format each row with the decimals of its own
currency or unit (EUR 2, JPY 0, KGM 3). They read those decimals from a **code list** the service
announces. Three pieces, all required:

1. **The business property points at its code:** `#[ODataAnnotate('amount', new ISOCurrency(new Path('currency')))]`,
   `#[ODataAnnotate('quantity', new Unit(new Path('unit')))]`. A code that is not a column (the
   code beside a `unit_id`) is an `#[ODataComputed]` property.
2. **The container points at the code list:** `CodeList\V1\CurrencyCodes` / `UnitsOfMeasure` with
   `url` (relative to the service URL; a static value, no resolver) and `collectionPath` (the set).
3. **The code-list set annotates its single key** with `Common.UnitSpecificScale`, `Common.Text`
   and optionally `CodeList.StandardCode`, each as a `Path` to a column, in one
   `#[ODataAnnotate('code', …)]` on the code-list model.

Rules that cost a debugging session when broken:

- **`UnitSpecificScale` must never be null.** UI5 drops that entry, and every amount in that
  currency renders **empty**.
- **The code-list set must not be paged.** UI5 reads it without `$top` and takes the first page
  only. Leave `odata.pagination.default` unset on a service that serves code lists.
- **The texts follow `Accept-Language`.** UI5 sends no `sap-language` unless the app puts one into
  its service URL.
- **Input is not checked against the code's decimals by default.** `Currency`/`Unit` default to
  `preserveDecimals: true`. Bind with `formatOptions: {preserveDecimals: false}` if too many decimals
  must be rejected. A fixed `Scale` on a plain `Edm.Decimal` is enforced without that.

## `odata:cache` keeps all of it

Container, property, type, set and function annotations all survive `odata:cache` since 3.1. If an
annotation shows up in a fresh `$metadata` but not in production, the cache is stale. Re-run
`odata:cache`.
