# Consuming the Service Reference

Use `search-docs` for authoritative documentation on query options and clients.

## The URL surface

Declaring a service creates the endpoints. There is nothing to route by hand.

```
GET /odata/Orders?$filter=total gt 100&$orderby=placed_at desc&$top=20&$select=id,total
GET /odata/Orders(42)?$expand=customer($select=name)
GET /odata/Orders/$count?$filter=status eq 'open'
GET /odata/$metadata
GET /odata/
```

Supported: `$filter`, `$select`, `$expand` (nested), `$orderby`, `$top`, `$skip`, `$count`,
`$search`, `$compute`, and `$batch` with partial failure. Functions and singletons are available on
the schema.

**Out of scope by design:** writes of any kind, OData actions, ETags, and `$apply`. If a task needs
one of those, it is not a task for this engine.

## Read authorization

Authorization is a bindable seam, and its failure behaviour is deliberate and worth knowing:

- a denied **root** entity set answers `403`;
- a denied **`$expand`** is **pruned with a warning**, not failed.

So a partially authorized read succeeds and returns less, rather than returning nothing. Client code
must therefore treat an absent expanded property as normal, not as an error.

## Caching the schema

`php artisan odata:cache` pre-compiles the EDM to PHP classes so no discovery runs at request time.
Run it during deployment. Re-run it after changing anything discovery reads: a discovered model, a
column, a cast, an attribute, an annotation, or the set of discovered models.

Stale cache is the usual explanation for "I added the property and `$metadata` does not show it".

## Multiple services

An application can carry several services, each on its own route with its own namespace. A service
that serves a different client, a different bounded context, or a different stability contract is a
legitimate reason to declare a second one rather than widening the first.

## Clients

The API is URLs, so the smallest client is `fetch`:

```js
const res = await fetch(
  "/odata/Orders?$filter=status eq 'open'&$orderby=total desc&$top=10",
  { headers: { Accept: "application/json" } },
);
const { value: orders } = await res.json();
```

Beyond that:

- **UI5** binds tables and forms directly with `sap.ui.model.odata.v4.ODataModel`. The engine's
  `$metadata` is what its SmartControls and value helps read.
- **Excel and Power BI** consume a service as an OData feed with no adapter — *Get Data → From
  OData feed*.
- **react-admin** has `ra-data-odata-server`; npm also carries typed clients such as
  `@odata/client`.

Nothing about the service is client-specific. If a client needs a materially different shape, that
is a signal to declare a second service, not to bend the first.
