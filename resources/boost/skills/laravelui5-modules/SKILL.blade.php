---
name: laravelui5-modules
description: >-
  Create and wire a LaravelUi5 module — the Composer package that brackets a UI5 app (or library)
  together with its backend. Use this when starting something new, when an app or library will not
  resolve, when deciding how the UI5 sources reach the browser, or when a UI5 library fails to load
  at runtime. Covers ui5:app/ui5:lib scaffolding and their identity options, the namespace
  single-source rule, explicit registration in config/ui5.php, the path-repository wiring, the
  LaravelUi5.init arm-then-route contract, the two places a UI5 library must be declared, the
  workspace vs package source strategies and .ui5-sources.php, and what ui5:assemble is (and is
  not). Adding an artifact to an existing module is a different task — see laravelui5-artifacts.
  Triggers: "new module", "new app", "ui5:app", "ui5:lib", "ui5:assemble", "config/ui5.php",
  "module not found", "app does not resolve", "failed to load library.js", "how do I serve my UI5
  sources", "path repository", "LaravelUi5.init", "Component-preload", "yo easy-ui5".
license: MIT
metadata:
  author: laravelui5
---

# Creating and wiring a module

## What a module is

**A module is a Composer package that contains a UI5 app *and* its backend.** Not a folder in the
application plus a folder in a frontend repo that have to be deployed in step — one package, one
name, one version, one `require` line. That is the whole point of the artifact model, and it is why
every URL carries an `@{version}` coordinate: the address names a *release* of the bracket.

A module holds **an application or a library — never both.** That is enforced by the architecture
and reflected in the interface.

## Never create a module explicitly

Modules are scaffolded alongside their root artifact:

```bash
php artisan ui5:app Users --create \
    --vendor="Acme GmbH" --php-ns-prefix=Acme --js-ns-prefix=com.acme --package-prefix=acme
# → UsersModule + UsersApp

php artisan ui5:lib Core --create --php-ns-prefix=Acme --js-ns-prefix=com.acme
# → CoreModule + CoreLibrary
```

`ui5:app` expects a UI5 source project to exist already, in one of:

```
../ui5-offers/          ← LaravelUi5 naming convention
../com.acme.offers/     ← SAP Easy UI5 convention
```

**If no source project exists yet, scaffold it first** with SAP's Easy UI5 generator — run
`yo easy-ui5` and pick the app or library sub-generator. Two things about that run matter here:

- **Choose the namespace so the folder lands where `ui5:app` looks.** The generator names the
  directory after the namespace, and the Artisan command searches the two sibling locations above —
  `ui5:app Offers` will not find a project called anything else.
- **For a library, answer `Y`** to *"Would you like to omit the namespace in the `src` and `test`
  folder?"*. `ui5 build` produces the right `dist/resources/{namespace-as-path}/` either way, but the
  flat `src/` layout is the one `ui5:lib` has been exercised against; the namespace-folded variant is
  untested with the generator.

Then patch the library dependencies — see *A UI5 library must be declared in TWO places* below. That
is the first thing to do after **every** fresh scaffold, before building.

`ui5:app` runs `npm run build` for you; `--no-build` skips that when you have just built.

## The namespace has exactly one source

**The module does not declare its namespace.** The root artifact — the App or Library — owns the
`NAMESPACE` constant, and the module derives `getNamespace()` from it
(`getArtifactRoot()->getNamespace()`). One source of truth, so the two can never drift.

The namespace **is** the URL coordinate: dots become slashes, `com.acme.portal` →
`/ui5/app/com/acme/portal@1.0.0/`. There is no separate slug to assign anywhere.

## Registration is explicit, twice, and there is no scanning

**1. Register each artifact inside its module** — in the matching `getCards()`, `getReports()`,
`getActions()`, … method. Nothing is auto-registered; every `ui5:*` generator prints a reminder. An
artifact exists only if it was intentionally exposed.

**2. List the module class in `config/ui5.php`** — a flat list of class-strings:

```php
'modules' => [
    \Acme\Sales\SalesModule::class,
    \Acme\Portal\PortalModule::class,
],
```

Core instantiates each and keys it by `getNamespace()`. **There is no automatic scanning**, on
purpose: explicit declaration keeps resolution deterministic and deployment-friendly. Which modules a
host serves is a product decision, not a directory listing.

*(Infrastructure modules — the SDK's own — register themselves through the infrastructure marker.
Only business modules go in this list.)*

## Wiring it as a real package

The module gets its own `composer.json`: the package name, a PSR-4 root over `src/`, and its service
provider under `extra.laravel.providers`. The host then takes it like any package:

```bash
composer config repositories.invoicing path ui5/Invoicing
composer require acme/invoicing:@dev
```

So a module can be developed inside the host, split out later, versioned on its own, and required by
a second host without anything being rewritten.

## The frontend contract: arm, then route — **every app, not just shell apps**

`LaravelUi5.init(this)` is **Core's** contract. It arms the facade every LaravelUi5 app talks to, and
it is required whether or not the SDK is installed. (The shell, when present, also starts from it —
but that is an addition, not the reason it exists.)

```js
init() {
    super.init();
    LaravelUi5.init(this).then(() => {
        this.getRouter().initialize();
    }).catch(console.error);
}
```

**The order is the point.** The first view may bind immediately, and a binding that fires against an
unarmed facade has no base URL and no CSRF token.

`init()` reads the app's `manifest.json` and from it: the OData service URI becomes the connection's
**base URL**; the `laravel.ui5` block supplies the **action and resource addresses** and the app's
**declared settings**; the `routes` and `meta` models are attached to the component; and the
**session-expiry guard** is armed.

**Without it, on any host:** `LaravelUi5.call()` throws, bindings have no base URL, and there is no
CSRF token. On an SDK host you additionally get no chrome at all.

**The facade is a singleton; never instantiate it anywhere else.** A second `init()` replaces the
connection the running views are already bound to.

### The facade has one surface, and Core answers part of it with a fallback

Some of the facade is brokered by the shell, which is an SDK capability. Core answers those calls
with a **defined fallback rather than an error**, so the same app runs on both and a Core-only host
degrades predictably:

| Method | On Core | On an SDK host |
|:---|:---|:---|
| `can(ability)` | **`true`** — Core is auth-blind | the actor's real grant |
| `getActor()` · `getPrincipal()` | `null` | the actor / the principal behind an impersonation |
| `getSetting(key)` | the declared default from the manifest | the stored, scope-resolved value |
| `getClient()` | `null` | the tenant client |
| `dispatchIntent(intent)` | resolves, does nothing | routed by the shell |
| `openValueHelp(options)` | **rejects** — there is no return channel | opens it and resolves with the pick |
| `showHelp(uuid)` | no-op | opens the help viewer |
| `getWeave()` | `[]` | the app's outbound doorways |
| `attach` / `detach` | no-op | shell events, e.g. `context:changed` |
| `log(event, payload, level)` | `console.log` | the shell's log channel |

> **The trap: `can()` returns `true` on Core.** An app that hides a button behind `can('approve')`
> shows it to everyone on a Core-only host — and looks perfectly correct while doing so. Frontend
> `can()` is a UX hint in both cases; **the gate that matters is the server-side one** (`#[Act]` on
> the action, `#[Read]` on the entity set). Never let a `can()` check be the only thing between an
> actor and an operation.

## A UI5 library must be declared in TWO places

This is the single most common runtime failure. After a fresh `yo easy-ui5` scaffold you get a
minimal project declaring only `sap.ui.core` and `sap.m`. If the app renders a Dashboard — or any
server-emitted control that materialises a Card, Chart or grid container — you must add **`sap.f`**,
and **`sap.ui.integration`** as well if it embeds integration cards.

Add them in **both**:

- `manifest.json` under `sap.ui5.dependencies.libs` — drives the application-side load contract;
- `ui5.yaml` (and the sibling `ui5-dist.yaml` / `ui5-coverage.yaml`) under `framework.libraries` —
  drives build-time resolution.

`manifest.json` alone fails at runtime with **`failed to load 'library.js'`**. Treat the patch as the
first thing you do after every fresh scaffold.

## How the sources reach the browser — two strategies

Same artifact, same URL, two ways of getting the bytes there. **Nothing in your PHP changes between
them.**

- **Workspace** — the development shape. The UI5 dev server serves the source; the app boots without
  a preload bundle and the framework comes from the proxied dev server. Edit a view, reload, see it.
- **Package** — the shipped shape. The built `dist/` is imported into the module's `resources/`,
  travels inside the Composer package, and is served from there with a `Component-preload.js` and
  the framework from the CDN.

To point Core at your own working copy instead of the packaged build, add `.ui5-sources.php` in the
project root:

```php
return [
    'modules' => [
        'LaravelUi5\\Auth\\AuthModule' => '../my-auth-app/',
    ],
];
```

Core's `WorkspaceStrategy` then serves that project's `webapp/` live.

## `resources/` is build output

**Never hand-edit it.** `ui5:app --refresh` overwrites `manifest.json` and the generated base; edit
the UI5 source project instead.

What **survives** a `--refresh` is the identity: the PHP namespace and the version live on the App
**leaf** (`OffersApp.php`), which `--refresh` never rewrites. The generator reads the namespace back
out of the leaf and **aborts rather than clobber** if it cannot parse it. Title, description and
bootstrap attributes are source-derived and do refresh.

Pass `--create` or `--refresh` explicitly — without either, the command only reports whether the
module exists and exits with an error.

## `ui5:assemble` is a teaching on-ramp, not an app factory

It scaffolds one self-contained app — backend and frontend, every file owned by your project, no
external UI5 toolchain — that boots onto a working dashboard from a single command. It is always
named **`Showcase`**; there is no name argument and no prefix/vendor options.

Use it to see the whole stack working end to end. **For a real, many-app project use `ui5:app` with a
workspace UI5 CLI build.**

## Do not

- **Do not create a module class by hand.** Scaffold the root artifact and the module comes with it.
- **Do not declare a namespace on the module.** The root artifact owns it.
- **Do not expect auto-discovery.** Register the artifact in the module, and the module in
  `config/ui5.php`.
- **Do not edit `resources/`.** It is regenerated.
- **Do not put an app and a library in one module.**
- **Do not call `LaravelUi5.init()` twice**, or after routing has started.
