# Resources — the last resort, and how to avoid needing one

**Read this before writing one.** Core has four read mechanisms and `Ui5Resource` is the last.
Work down the list and stop at the first match:

| What you need | Use | What it gives you |
|:---|:---|:---|
| A **collection of like-typed rows** — list, table, master view | an **OData entity set** | `bindList`, `$filter`, `$orderby`, paging, `$batch`, all free |
| One **object with related data** — a detail view, an object-page header | `bindElement` + `$expand` | one round trip, cached by the V4 model |
| A **computed scalar or bespoke complex value** — a total, a projection | an **OData function** (`bindFunctions()` on the app) | typed, batchable, discoverable in `$metadata` |
| **Static, user-invariant client facts** — route handles, capability flags | an **infrastructure contribution** | injected into every manifest, cacheable |
| None of the above | **Resource** | |

That last column is what you give up. A Resource returns a raw array, so batching, caching, relative
bindings, filtering, paging and — on an SDK host — the `#[Read]` authorization layer are things you
then rebuild by hand, per resource.

## The honest state of this artifact

`Ui5Resource` ships and is covered by tests, but it is **the one Core surface that never found a
second consumer**: every `getResources()` in the vendor's own applications returns `[]`, and the only
implementations are test fixtures. That is a statement about fit, not a defect — each time they
reached for it, the OData path turned out to be the better answer.

**The object-page header is no longer a reason.** Historically this artifact existed to assemble a
complex page header, because expressing that in OData v2 was painful. Under v4 it is not: a header is
`bindElement` with `$expand`, and a computed read-out is a navigation projection or a function.

### The two cases that survive

- **Foreign payloads.** Proxying a third-party system whose schema you do not own and do not want to
  model. An entity type would be a fiction.
- **A one-off blob for exactly one view.** Genuinely ad hoc, no second consumer will ever see it, and
  schema discipline would be pure overhead.

Outside those two, the table above has the answer.

## If you do write one

```php
class HeaderResource extends AbstractUi5Resource
{
    public const string NAMESPACE = 'com.acme.offers.resources.header';
    public const string VERSION   = '1.0.0';

    public function getProvider(): string { return HeaderProvider::class; }
}
```

The provider implements `DataProviderInterface` — the **same empty marker** that backs cards and
reports, so the convention is worth knowing once:

- `provide(): array` — JSON-serializable, **side-effect free**, normalized (scalars and nested
  arrays, never raw models). `ExecutableInvoker` calls it and throws
  `MissingExecutableMethodException` if it is absent.
- **Services, repositories, gateways → the constructor** (autowired through the class-string).
- **Per-request inputs → `provide()`'s parameters** — route-resolved models, `#[Parameter]` values,
  the `Ui5ContextInterface`.

## Consuming it

There is no binding. You fetch and place the result in a `JSONModel`:

```js
const model = new JSONModel();
model.loadData("/ui5/resource/com/acme/offers/resources/header@1.0.0");
this.getView().setModel(model, "header");
```

Compare that with the OData path, where the same data arrives through `bindElement` and the model
handles caching, batching and refresh for you.

And note what the `JSONModel` is doing there: holding **view state**. That is a fine pattern, and it
composes with OData just as well — loading OData results into a `JSONModel` for client-side filtering
is normal. **A `JSONModel` is not a reason to choose a Resource.**

## Do not

- **Do not skip the table at the top.** This artifact has no schema, so every shortcut becomes a
  contract you maintain by hand.
- **Do not return models.** Normalize to scalars and arrays.
- **Do not cause side effects.** `GET` means `GET`.
- **Do not change the payload shape in place.** There is no `$metadata`, so a consumer cannot
  discover that it changed. **Version the namespace, not the shape.**
- **Do not take identity from a query parameter.** Core carries no actor; on an SDK host the actor
  comes from the SDK context.
