## The LaravelUi5 stack (LUX)

Three layered Composer packages for building enterprise applications on Laravel:

- **`laravelui5/odata`** (MIT) — a standards-compliant, **read-only** OData v4 engine. Point it at an
  Eloquent model and any OData client can filter, sort, page, project and traverse the data.
- **`laravelui5/core`** (BSL 1.1, production use granted) — the metadata engine. Artifacts are plain
  PHP objects with attributes; Core derives their routes, manifests, OData services and addresses,
  and brackets a UI5 app together with its backend into **one versioned Composer package**.
- **`laravelui5/sdk`** (commercial) — the production runtime: time-aware authorization, scoped
  settings, one partner model for customers and suppliers, and a lightweight enterprise shell.

**Two rules that prevent most of the damage.** They hold across the whole stack, and an agent that
does not know them writes code that looks plausible and is wrong:

1. **OData is read-only. Every write is a `Ui5Action`** — an addressable, versioned artifact with its
   own handler, its own `FormRequest` and its own authorization gate. There is no `POST` to an entity
   set, no OData action, no `$apply`.
2. **Core is stateless about the organisation.** It knows artifacts, routes, manifests and the
   execution pipeline — never users, tenants, partners or roles. Anything that needs stored state is
   the SDK's half. A missing feature in Core is usually a refusal, not an oversight.

Full documentation: **laravelui5.com**. Use the `search-docs` tool before guessing at an API.

@if (class_exists(\LaravelUi5\OData\ODataServiceProvider::class))
## laravelui5/odata — the read surface

`$metadata` is generated from the same model that serves the rows, so the schema cannot drift from
the API.

### Never do these

- **Never write a controller, API resource, serializer or `?include=` convention for data this engine
  already serves.** Declaring the service *is* the endpoint. Reaching for `make:controller` to expose
  a model that sits in an OData service is the wrong move.
- **Never attempt writes through OData.** Queries in, JSON out. Writes are `Ui5Action`s (Core) or
  ordinary Laravel controllers, form requests and jobs in a plain app.
- **Never hand-write or edit CSDL XML.** Change the model, the attributes or the annotations — never
  the generated document.
- **Never convert a large-list custom entity set to `discoverModel()` for consistency.** See the
  performance gate below; that trade is the one expensive mistake.

### Declaring a service

@verbatim
<code-snippet name="A minimal OData service" lang="php">
use LaravelUi5\OData\ODataService;
use LaravelUi5\OData\Service\Contracts\EdmBuilderInterface;

class ProductService extends ODataService
{
    public function serviceUri(): string { return ''; }
    public function namespace(): string  { return 'App.Products'; }

    protected function configure(EdmBuilderInterface $builder): EdmBuilderInterface
    {
        $this->discoverModel(Product::class);
        $this->discoverModel(Supplier::class); // discover the RELATED model too,
                                               // or its relation is not a nav property

        return $builder->namespace($this->namespace());
    }
}
</code-snippet>
@endverbatim

`discoverModel()` reads the table, maps columns to typed properties (Eloquent casts override DB
types), detects the key, and turns `HasMany` / `BelongsTo` / `HasOne` / `BelongsToMany` into
navigation properties — **only when the related model is also discovered**. So `$expand` on a real
relation is free. Do not build one by hand.

### The performance gate — row count decides the tool

The engine is fast because it **streams raw SQL rows and does not hydrate models**.

- **Small, bounded N** (a keyed detail with a few `$expand`s, a related sub-list) → `discoverModel()`.
- **Large or unbounded N** (a master list, an export, an aggregate) → a **raw-SQL custom entity set**
  via `discoverCustomEntitySet()`. Here `discoverModel()` costs roughly **30–75× per row**.

A custom entity set on a big list is not legacy debt to modernize — it is the design working.

### Operational

`php artisan odata:cache` pre-compiles the EDM so no discovery happens at request time. Run it on
deploy and re-run it after changing a discovered model, an attribute or an annotation.

**For anything beyond this — the five-step modelling decision tree, discovery attributes, custom
entity sets, virtual expands, consuming the service — use the `laravelui5-odata-modeling` skill.**
@endif

@if (class_exists(\LaravelUi5\Core\CoreLibrary::class))
## laravelui5/core — artifacts, and the bracket around them

**A LaravelUi5 module is a Composer package that contains a UI5 app *and* its backend.** Not a folder
in the application plus a folder in a frontend repo that have to be deployed in step — one package,
one name, one version, one `require` line. That is why every URL carries an `@{version}` coordinate:
the address names a *release* of the bracket.

### Never do these

- **Never register a route** for an artifact. Every artifact type has one fixed route shape, derived
  from the registry: `app/{ns}@{ver}/index.html`, `card/{ns}@{ver}/manifest.json`,
  `resource/{ns}@{ver}`, `api/{ns}@{ver}/{uri?}` for actions. A dotted namespace becomes slashes in
  the URL — `com.acme.invoicing` is addressed as `com/acme/invoicing`.
- **Never hand-edit a module's `resources/` directory.** It is build output. `ui5:app --refresh`
  overwrites `manifest.json`; edit the UI5 source project instead.
- **Never edit `dataSources` or `models` in a served manifest.** Core assembles the `laravel.ui5`
  block — action and resource addresses, settings, contributed infrastructure — from the registry.
- **Never create an artifact by hand.** Scaffold it (below), then fill it in. The generators emit the
  current contract; a hand-written class reproduces whatever pattern the agent remembers.
- **Never ship a UI5 app whose component does not call `LaravelUi5.init(this)` before routing.** That
  call arms the facade — the OData base URL, the CSRF token, the action and resource addresses, the
  declared settings. Without it bindings have no base URL and `LaravelUi5.call()` throws. It is
  Core's contract, required with or without the SDK.

### Artifacts are declarations

An artifact is a plain PHP object. Identity lives in class constants, behaviour in a handler or
provider it points at, and everything the framework needs is stated in attributes. **Attributes
declare; classes do.**

@verbatim
<code-snippet name="An app artifact" lang="php">
class InvoicingApp extends AbstractUi5App
{
    public const string NAMESPACE = 'com.acme.invoicing';
    public const string VERSION   = '1.0.0';
}
</code-snippet>
@endverbatim

**Registration is two steps and there is no third:** the artifact is returned from its module
(`getCards()`, `getActions()`, `getReports()`, …), and the module class is listed in `config/ui5.php`
under `modules`. Nothing is wired in a route file, a service provider or a frontend config.

### Always scaffold

`ui5:app` · `ui5:action` · `ui5:card` · `ui5:tile` · `ui5:chart` · `ui5:dashboard` · `ui5:group` ·
`ui5:report` · `ui5:resource` · `ui5:slot` · `ui5:wire` · `ui5:lib` · `ui5:assemble`

### The hierarchy

A **Module** groups artifacts under one namespace root; it is not addressable and it is where
`#[Slot]` declarations live. An **App** is the addressable root for user-facing content and **is an
OData v4 service** (`AbstractUi5App` extends `ODataService`), so its read surface comes with it. A
**Library** is the other root. Everything else — dashboards, cards, tiles, charts, reports, actions,
resources — hangs off the app and takes its context from it.

### Which artifact for which job

A single number → **Tile**. A small structured payload (list, table, object header) → **Card**. A
series to plot → **Chart**. A page composed of those → **Dashboard** with **Groups**. Printable or
exportable HTML → **Report**. A file or asset served from the module → **Resource**. A whole screen →
**App**. Shared UI5 code → **Library**. **Anything that writes → `Ui5Action`, never an artifact of the
types above.**

A table of many rows the user will filter and page is none of these: that is an OData entity set
consumed by a real UI5 table. **Entity sets are for tables; cards, tiles and charts bring their own
providers.**

**Three skills carry the depth.** `laravelui5-modules` for creating and wiring a module, app or
library. `laravelui5-artifacts` for choosing and authoring an artifact inside one — the shared
declaration rules, the three different provider contracts, and a reference per type.
`laravelui5-actions` for anything that writes.
@endif

@if (class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
## laravelui5/sdk — the stateful half

Core is stateless about the organisation; the SDK is the half that knows **who, when and in what
scope**. Identity and time-aware authorization, scoped settings, one partner model for customers and
suppliers, the shell, and a DB-backed registry.

### `ui5:sync` is not optional, and the deploy order is a contract

The SDK's registry is **database-backed**. Abilities, roles and the metadata catalog are projected
from the code into tables by `php artisan ui5:sync`, and a row's id does not exist until it has run.

**`migrate → sync → cache`, in that order, every deploy.** It is a hard contract, not a
recommendation: `ui5:cache` writes references that carry DB ids, so caching before syncing caches
ids that are not there yet.

**If a newly declared ability, role or artifact "does not work", run `ui5:sync` before debugging
anything else.** Code changes alone do not reach the runtime.

### `Authz = f(App, Actor, Time)`

Every authorization answer depends on three coordinates, and **time is one of them** — grants are
time-bound. That has one consequence an agent must never get wrong:

> **The ability *definition* is identity and may be cached. The ability *grant* — who holds it, and
> when — is never cached.** It stays request-time in the `AbilitySet`.

So: cache an id, never an answer. A cached grant is a security defect, not a performance win.

### The context is the only source of who and when

`SdkContext` carries `artifact()`, `tenant()`, `actor()`, `principal()`, `locale()`, `at()`,
`abilities()` and the resolved settings.

- **Never `Auth::user()`.** Read the acting partner from `$context->actor()`.
- **`actor()` and `principal()` are two different partners.** The *principal* is who is really signed
  in and who holds the delegations; the *actor* is who the request acts as. Under impersonation they
  differ, and **impersonation is authorized against the principal's active delegations**. Using the
  wrong one is a silent privilege bug — it looks like it works.
- **Never `now()` in an authorization or validity decision.** Use `$context->at()`, which is the
  request's time coordinate and what makes time-bound grants reproducible.

### Settings are read here and written elsewhere

`$context->setting('key')` returns a value already resolved across the scope ladder — **Platform <
Installation < Tenant < Site < User, highest scope present wins** — read once when the context is
built. `$context->appSetting('key')` does the same for the artifact's app.

**Application code never writes a setting.** Writing is gated and belongs to the Settings surface.

### Configuration lives under `ui5.*`

The SDK reads `ui5.context`, `ui5.intents`, `ui5.navigation.*`, `ui5.export.*`, `ui5.help.*` and
their siblings. **There is no `sdk.*` namespace and no new one should be invented** — one package
boundary, one config root.

### Writes

The SDK rebinds the action dispatcher, so an action's handler contract here is the **typed** one and
the dispatcher wraps every call in a transaction. Use the **`laravelui5-actions`** skill; it renders
the contract that applies to this installation.

Full SDK documentation: **laravelui5.com/sdk/**.
@endif
