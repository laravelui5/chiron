---
name: laravelui5-services
description: >-
  Let one LaravelUi5 module use another — through a released service, never through the other
  module's classes or tables. Use this whenever code in one module needs data or a write that another
  module owns, when offering something to other modules, or when a package should be replaceable.
  Covers the two kinds (an operation: one verb, `#[Call]` mandatory; a service: a facade, `#[Call]`
  optional), the declaration with `INTERFACE` and its `final` `HANDLER`, `#[Uses]` and
  `getService()`, why a released interface is never bound in the container, the Laravel service as
  the other kind of seam (`bindIf` / `bind`), `#[BusinessEvent]`, `#[ReadSurface]`, stored files at
  `ui5/files/{ulid}`, and `ui5:describe --check`. Triggers: "another module", "cross-module",
  "offer an operation", "ui5:service", "AbstractOperation", "AbstractService", "#[Uses]",
  "#[Call]", "getService", "released service", "bindIf", "#[ReadSurface]", "#[BusinessEvent]",
  "FileDeliveryInterface", "serve a stored file".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Released services are an SDK capability

On `laravelui5/core` alone a module calls another the way any Laravel code does — through a class
or a container binding — and nothing checks who calls. Released services, guarded per call and
declared between modules, are `laravelui5/sdk` (laravelui5.com/sdk/weave/services).
@else
# Using another module — released services

## The rule

**A module never reaches into another.** Not its models for writing, not its tables outside what it
released for reading, not its handler classes. What one module offers another is a **released
service**: a declaration, a plain interface, a `final` handler — and every call goes through the
registry, where it is checked.

## Two kinds — the base is the kind

| Kind | Base | Interface | `#[Call]` | Runs |
|:---|:---|:---|:---|:---|
| **Operation** | `AbstractOperation` | exactly **one** method — one verb | **mandatory** | in the caller's unit of work |
| **Service** | `AbstractService` | a facade, any number of methods | optional — **a service that writes carries it** | in the caller's unit of work when it writes |

```php
// Billing/Services/PostDocument.php — the declaration
#[Call('postDocument', BillingRole::Clerk, note: 'Post a document from another module.')]
final class PostDocument extends AbstractOperation
{
    public const string NAMESPACE   = 'com.acme.billing.services.post_document';
    public const string TITLE       = 'Post a document';
    public const string DESCRIPTION = 'Takes a posting over from a module and records its origin.';
    public const string INTERFACE   = PostDocumentInterface::class;
    public const string HANDLER     = PostDocumentHandler::class;
}

// Billing/Contracts/PostDocumentInterface.php — plain PHP, one verb
interface PostDocumentInterface
{
    public function handle(PostingRequest $request): PostingReference;
}
```

List the declaration in the module's `ARTIFACTS`. The `HANDLER` is `final`, implements the
interface and gets its dependencies through its constructor. An operation opens no transaction and
seals nothing: it runs inside the caller's unit of work. Translate the title by convention, under the
namespace in the module's bundle.

**Scaffold it:** `php artisan ui5:service Billing/PostDocument --role=clerk` writes the declaration,
the interface and the handler; `--service` makes a service (`--role` adds `#[Call]`), `--call=` names
the ability. It binds nothing — there is nothing to bind.

## Using one

```php
#[Uses(PostDocumentInterface::class)]
final class OrdersModule extends AbstractUi5Module { /* … */ }

// in the caller, which got ServiceRegistryInterface $services injected
$this->services->getService(PostDocumentInterface::class)->handle($request);
```

- **Declare it on your module with `#[Uses]`.** The registry checks it when it loads: an interface no
  module declares, a service of your own module, the same interface twice — each stops the boot.
- **Take it from the registry with `getService()`.** The registry builds the handler and puts the
  mediator in front, which checks `#[Call]` against the actor of `SdkContext`. A refusal throws
  `ServiceCallRefusedException` (403) and leaves a `CallRefused` row in the security log; a guarded
  call without `SdkContext` throws `MissingServiceContextException`.
- **Inside the owning module, use the handler directly** — the guard is for other modules.

## Two kinds of seam — never mix them

| | Released service | Laravel service |
|:---|:---|:---|
| What it is | an offer to **other modules**, an artifact | an **extension point** meant to be replaced |
| Where it lives | the registry | the container |
| Bound? | **never** — `ui5:doctor` sdk.W6 reports a bound released interface as an error | the owner with `bindIf` / `singletonIf` / `scopedIf`, a replacement with `bind` |
| Guarded | per call, `#[Call]` | no |

A Laravel service is replaced by binding your own in your service provider; package providers boot in
alphabetical order of `installed.json`, and `…If` makes the owner's default yield to whoever binds
first.

## What else a module releases

- **`#[BusinessEvent]`** on an event class: other modules may listen to it, and its shape is a
  promise.
- **`#[ReadSurface(columns: [...], relations: [...])]`** on the owner's model, in its DB package: the
  columns and relations another module may **read** with a SemVer guarantee. Everything else of the
  model — and every write — stays internal. Write through the owner's operation.
- **Stored files** are delivered at one address, `GET ui5/files/{ulid}`. The package that stores
  files binds `FileDeliveryInterface` (a Laravel service) and answers `resolve($ulid)` with a
  `DeliverableFile` — or `null`, which is a 404 for an unknown and a forbidden file alike; it builds the
  owning app's context for the check. How the bytes go out — inline or download, caching, ranges,
  cloud redirects — is the SDK's, and every read is recorded in the report log. Never write a file
  controller of your own.

## Check it

`php artisan ui5:describe` lists, module by module, what a package offers and uses;
`ui5:describe --check` exits non-zero when a declaration is not valid. Put it in CI.

## Do not

- **Do not inject another module's interface**, and do not `new` or `app()` its handler. Declare
  `#[Uses]` and call `getService()`.
- **Do not bind a released interface in the container.**
- **Do not write another module's models**, and do not read its tables past its `#[ReadSurface]`.
- **Do not open a transaction in an operation.** It runs in its caller's unit of work.
- **Do not put `#[Call]` on anything but a service declaration**, nor `#[Read]` on one — `#[Read]` is
  the OData read gate.
@endif
