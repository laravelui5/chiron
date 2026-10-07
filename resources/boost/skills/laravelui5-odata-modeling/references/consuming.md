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

What the engine does with them, as of odata 3.1. Each point is a behaviour clients rely on:

- **`$filter` refuses what it cannot translate.** Supported: `eq ne gt ge lt le in and or not`,
  `contains startswith endswith` (matched literally, `%` and `_` are not wildcards), `tolower` /
  `toupper` around a property or a string, `any`/`all` on discovered models. Anything else answers
  `501 unsupported_filter`. A malformed comparison (`1 eq 1`, `price gt null`) answers
  `400 invalid_filter`. A filter is never silently dropped. **`$filter` is still not an authorization
  boundary:** scope the set itself.
- **`$search` matches the term literally**, across all `Edm.String` properties. One surrounding
  pair of quotes is phrase syntax and is removed.
- **Keys are checked against their type.** `Orders(abc)` is `400 invalid_key`, not `Orders(0)`.
  String keys must be quoted, with `''` for a quote inside: `Customers('O''Brien')`. A composite key
  names every part exactly once.
- **`@odata.nextLink` repeats the request**: same path, same options, only `$skip` moved. A client
  can follow it as is.
- **`$expand=nav($count=true)`** adds `nav@odata.count`: the size under the expand's `$filter`, not
  limited by its `$top`. Not on a single-valued navigation (`400`), not on a virtual one (`501`).
- **`IEEE754Compatible=true`** in `Accept` (UI5 always sends it) writes `Edm.Decimal` and
  `Edm.Int64` as strings, so no digit is lost. Without it they are JSON numbers. Every property
  goes out as its declared type: booleans as `true`/`false`, `Edm.Binary` base64url-encoded.

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
column, a cast, an attribute, an annotation, the set of discovered models, or an installation fact a
`ColumnFacetResolverInterface` reads (the resolver's answer is frozen at cache time). Since 3.1 the
cache reproduces the whole schema, annotations and facets included.

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
