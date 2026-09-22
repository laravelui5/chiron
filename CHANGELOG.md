# Changelog

All notable changes to `laravelui5/chiron` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this package uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) **on its own line**, independent of the
packages it describes.

Each release names the majors it was written against. A guideline that only holds for one major says
so inline rather than forking the file.

## [1.0.0] – 2026-09-22

Written against **odata 3.x · core 2.x · sdk 1.x**.

### The guideline

- `resources/boost/guidelines/core.blade.php` — the always-loaded file. An **unguarded** overview of
  the three-package stack carrying the two rules that prevent most of the damage (*OData is
  read-only, every write is a `Ui5Action`* · *Core is stateless about the organisation*), then one
  **guarded** section per package that renders only when that package is installed:
  - **odata** — the never-dos, declaring a service, and the row-count performance gate.
  - **core** — the Composer bracket, the four never-dos, two-step registration, the `ui5:*`
    scaffolders, which artifact for which job, and the `LaravelUi5.init` requirement.
  - **sdk** — `ui5:sync` and the `migrate → sync → cache` deploy contract, `Authz = f(App, Actor,
    Time)` with the cache-an-id-never-an-answer rule, `actor()` vs `principal()`, the settings scope
    ladder, and the single `ui5.*` config root.

### The skills

**Core and OData**

- `laravelui5-odata-modeling` — the five-step modelling decision tree, the row-count performance
  gate, and three references (discovery, custom entity sets, consuming).
- `laravelui5-modules` — creating and wiring a module: scaffolding and the Easy UI5 seed step, the
  namespace single-source rule, explicit registration, the path-repository wiring, the
  arm-then-route facade contract with its Core/SDK degradation table, the two places a UI5 library
  must be declared, and the two source strategies.
- `laravelui5-artifacts` — choosing and authoring an artifact: the shared declaration model, the
  three different provider contracts (an empty marker for cards, typed contracts for tiles and
  charts), and four references — `widgets`, `dashboards`, `reports`, `resources`.
- `laravelui5-actions` — authoring a `Ui5Action`: the Action + Handler + FormRequest triad,
  `#[Parameter]`, registration, authorization and the frontend call path.

**SDK**

- `laravelui5-security` — the four ability types and where each is enforced, the role-first
  declaration rule, the capability mini-app cut, and two references: `read-gate` and `scoping`.
- `laravelui5-settings` — the three mechanisms that look alike (Setting · Slot · Customizing) and
  how to choose, the five-scope precedence ladder, the five write checks, and a `customizing`
  reference.
- `laravelui5-partners` — the one identity the SDK authorizes, the `users.partner_id` bridge, the
  four different things called "role", org partners vs tenant, and the system actor.
- `laravelui5-shell` — the five things that must be true for an app to get chrome, intent dispatch,
  value helps and their server-resolved scopes, navigation projection, and the Launchpad invariant.
- `laravelui5-dialogs` — global dialogs as shell-dispatched artifacts.

### Conditional by installation

**Every file here is Blade-rendered, and two different mechanisms decide what a reader gets.**
Boost's `isDirect()` decides *whether this package is read at all*; `class_exists()` inside the
guards decides *what it says* — and that one sees the autoloader, so it finds transitively installed
packages too.

- **The guideline** renders only the sections for packages that are present.
- **`laravelui5-actions` renders one handler contract, never both.** With the SDK present it teaches
  the typed `SdkActionHandlerInterface`, `#[Act]`, and the transaction the dispatcher opens; on a
  Core-only host it teaches Core's marker `ActionHandlerInterface`, `handle(): array`, and the fact
  that **Core's dispatcher does not wrap the call in a transaction** — so atomicity is the author's.
  An agent handed the wrong contract writes code that does not even type-check.
- **The five SDK skills carry a Core-only branch.** Skills cannot be installed conditionally, so on
  a Core-only host each renders a short, factual stub instead of its body: what Core offers in its
  place, and why the absence is a deliberate refusal rather than a gap. `laravelui5-settings` is the
  fullest of these — settings *do* exist in Core, they simply always resolve to their declared
  default.

A Core-only host therefore receives **four** full skills and **five** stubs; an SDK host receives
all nine in full.

### Why this package exists

Boost loads a package's guidelines and skills **only when that package is a direct dependency** of
the application. `laravelui5/core` requires `laravelui5/odata`, and the install guide says
`composer require laravelui5/core` — so for anyone following it, odata is *transitive* and its
material would never load. One package that the host requires directly dissolves that by
construction.

The second reason is cadence: **guidelines want to be rewritten the week you learn what an agent
confuses; runtime packages are on strict SemVer with a tag, a Satis rebuild and a smoke test.** This
package releases on its own line.

This material previously lived inside the runtime packages, and **it never shipped from there** —
`laravelui5/odata`'s copy sat under an unreleased `3.1.0`. Nothing is deprecated and no consumer
loses anything.

### Installing is opt-in

`boost:install` asks which third-party guidelines and skills to take and records the answer in the
application's `boost.json`. **A non-interactive run installs only what is already recorded there**,
so the first install has to be answered by a human, or `boost.json` seeded with
`"packages": ["laravelui5/chiron"]`. Verified against `laravel/boost` v2.9.1
(`InstallCommand::selectThirdPartyPackages()`).

The failure mode is silent: a scripted install reports success and simply installs nothing.

### Verified

Rendered and installed against two hosts covering both conditions — a Core-only host with no SDK,
and a host carrying Core and SDK. The guideline and all nine skills render with no Blade residue in
either, the guarded sections appear and disappear as intended, and `references/` directories travel
with their skills.
