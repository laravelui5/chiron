# Changelog

All notable changes to `laravelui5/chiron` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); this package uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) **on its own line**, independent of the
packages it describes.

Each release names the majors it was written against. A guideline that only holds for one major says
so inline rather than forking the file.

## [3.0.0] - 2026-11-04 – SDK 3.0: modules offer each other services

Written against **odata 4.0 and 5.0 · core 8.0 · sdk 3.0**. A **major**: the `conflict` reads `laravelui5/odata`
`<4.0 || >=6.0`, `laravelui5/core` `<8.0 || >=9.0` and `laravelui5/sdk` `<3.0 || >=4.0`, so an installation still on
SDK 2 or Core 7 keeps Chiron 2. Ships with the SDK 3.0 tag and never before it.

### SDK 3.0 and Core 8

- **Released services — `laravelui5-services`, new.** One module uses another through a released service, never
  through its classes or tables: an operation (`AbstractOperation`, one verb, `#[Call]` mandatory) or a service
  (`AbstractService`, a facade), a plain `INTERFACE` and a `final` `HANDLER`, `#[Uses]` on the using module and
  `getService()` from the registry. A released interface is never bound in the container; an extension point is a
  Laravel service (`bindIf` / `bind`). With `#[BusinessEvent]`, `#[ReadSurface]`, stored files behind
  `FileDeliveryInterface` at `ui5/files/{ulid}`, `ui5:service` and `ui5:describe --check`. The core guideline gains
  *Between modules*. (`meta/specs/sdk/weave-services-v1.0.md`, `meta/specs/foundation/files-v1.0.md` FF‑D14.)
- **Launch tiles** (`laravelui5-shell`). `#[Launchpad('<capability>')]` names the capability alone; at most one launch
  tile per module, exactly one for a module whose app carries `#[ConceptEntry]`; `#[Category(path:)]` only without a
  tile. (`meta/specs/sdk/launchpad-v0.9.md` D22–D27.)
- **Settings on a command or a job** (`laravelui5-settings`): on the declaration, at `EditLevel::Operator`; a unit of
  work declares none (sdk.W5).
- **The label of a command, job or unit is its artifact title.** The commands and jobs-and-units skills teach a literal `TITLE` — the developer's English — translated by convention under the namespace (and `<namespace>.description`) in the module's bundles. (`meta/specs/ui5/audit-v1.0.md` D5, `meta/specs/sdk/i18n-v1.0.md` D36, D37.)
- **Core 8: what the client renders is translated by its namespace** (`laravelui5-artifacts`, dashboards and widgets
  references). A group's Panel header and a chart's header bind `{i18n><namespace>}` (and `.description`); `TITLE`
  stays plain English; the keys go in the module's bundles, core.W8 reports a missing one. The `conflict` on
  `laravelui5/core` moves to `<8.0 || >=9.0`. (`meta/specs/core/dashboard-api-v1.0.md` § Titles bind the namespace.)

### OData 4.0

Written against **odata 4.0** (`odata/CHANGELOG.md`, `[4.0.0]`). What the OData skill teaches is 4.0 only, so the
`conflict` bound on `laravelui5/odata` is now `<4.0 || >=6.0` (was `>=4.0`; odata 5.0 adds only `cachesMetadataXML()` to the service interface, which nothing here teaches, 2026-11-03) — a **major** for this package, as 2.0.0
was when it left SDK 1.x. Ships with the odata 4.0 tag and never before it.

- **Two surfaces, nothing in between** (`laravelui5-odata-modeling`, core guideline). A discovered model is described by
  its table and its class; a rename, another type or a cut of a column is a custom entity set. Nothing OData goes on a
  PHP property of a model any more: the hooked-property idiom and its `set`-block warning are gone, and so are
  `#[ODataProperty]`, `#[ODataIgnore]`, `#[ODataNavigation]` and `useHidden:` (odata `OP38`, `OP41`).
- **`$hidden` and `$visible` are the schema** — not declared, not filterable, not sent; the key must stay
  serializable. The core guideline's secrets rule says so instead of prescribing `useHidden` (odata `OP41`).
- **`#[ODataAnnotate('column', …terms)]`** for a column's vocabulary, and `ColumnAnnotationResolverInterface` for a
  package annotating columns across models; the annotation and code-list examples use it (odata `OP35`).
- **`#[ODataComputed]`** for a value with no column — the code beside a code-list `…_id`, a tenant's base currency —
  its value from `ColumnExpressionResolverInterface`; preferred over a custom entity set when only a value or two is
  missing (odata `OP34`).
- **What the wire carries** (`consuming.md`): exactly the declared properties (no `$appends`, no `pivot`), a JSON cast as
  text, `$compute` on declared properties only, function imports in the service document (odata `OP36`, `OP39`,
  `OP40`, `OP11`).
- A 3.x → 4.0 table in `references/discovery.md`, per removed attribute.
- **Vocabulary records are typed objects, passed by name** (`references/annotations.md`): `#[LineItem([new DataField(…)])]`
  instead of a list of strings, record and record-term fields as named arguments, enum cases and flag lists, the value
  first for an open primitive type (`new Minimum(0)`), and the schema build failing loud on what the vocabulary
  requires. A `Path` in every term and field; a string for a model-path type, a `Path` only where a SAP client reads one —
  `CodeList.StandardCode` (odata `OP07`, `OP29`).
- **`useVocabulary(Vocabulary::CodeList)`** for a service that announces a code list; every shipped vocabulary has its
  enum case now (odata `OP44`).

### Core 7

What Core 7 changed (`core/CHANGELOG.md`, `[7.0.0]`) is taught as well; the guards that served Core 4–6 side by side
(`version_compare(CoreLibrary::VERSION, …)`, which Core 7 removes) are resolved to the current branch (2026-11-03). The
`conflict` bound on `laravelui5/core` went to `<7.0 || >=8.0` with it, and on to `<8.0 || >=9.0` with Core 8 (above).

- **The version belongs to the module** (core guideline, `laravelui5-modules`; `meta/specs/core/module-artifacts-v1.0.md`
  D20). Every example drops `VERSION` from the artifact — app, action, card, tile, chart, dashboard, group, report,
  resource, and the SDK's dialog and command — and the guideline shows it on the module, with the rule: bump it with
  every release that changes a served file, `ui5:doctor` reports a change without a bump (`core.W7`), and a package
  with several modules names it once and lets the others refer to it (`VERSION = SdkModule::VERSION`). A card's
  manifest template reads `$card->getModule()->getVersion()`.
- **`ArtifactInterface` in a vetoer's signature** (`references/dashboards.md`): `Ui5ArtifactInterface` is gone (D19).
- **`odata:cache` runs on the development machine, never at deploy** (core guideline, `consuming.md`). Both said *run it
  on deploy*; the command refuses `production` and `staging`. They now say so, and that it writes the finished
  `$metadata` document beside the Edm classes — a UI5 app's at `resources/ui5/Metadata.xml` — to be committed with them.


## [2.0.0] - 2026-10-27 – SDK 2.0: audited, in the user's language

Written against **odata 3.2 · core 4.4, 5.0 and 6.0 · sdk 2.0** (`meta/specs/sdk/i18n-v1.0.md`, `meta/specs/sdk/audit-v1.0.md`,
`meta/specs/sdk/audited-execution-v1.0.md`, `meta/specs/sdk/installation-v1.0.md`, `meta/specs/core/doctor-v1.0.md`). A
**major**, because it leaves SDK 1.x: what it teaches about the SDK is 2.0 only, so the `conflict` bound on
`laravelui5/sdk` is now `<2.0 || >=3.0` (was `>=2.0`). Ships with the SDK 2.0 tag and never alone.

### Added

- **`ui5:doctor`** (core guideline, Core 5.0 only): run it after scaffolding and before debugging a UI5 app;
  apply the fix each finding names; a host's own check is a `host.W<n>` rule tagged `DoctorRuleInterface`.
  Guarded by `version_compare(CoreLibrary::VERSION, '5.0')`, so the line still serves Core 4.4. The `conflict`
  bound on `laravelui5/core` moves from `>=5.0` to `>=6.0` (and with Core 6.0 to `>=7.0`, below).
- **An action declares `METHOD`, `HANDLER`, `REQUEST` as constants** (`laravelui5-actions`, Core 5.0 only; Core
  ROADMAP `OP35`): both examples show the constants under Core 5.0 and the getters under 4.4.
- **`#[Parameter]` on the action** (`laravelui5-actions`, Core 6.0 only; Core ROADMAP `OP37`,
  `meta/specs/sdk/audited-execution-v1.0.md` D39, D40): both parts place it on the action, never on the handler,
  which the registry refuses; the SDK part adds running a handler from a job or a command through an
  `AbstractWorkUnit` declaration. Below 6.0 the handler keeps it. The `conflict` bound on `laravelui5/core` moves to
  `>=7.0`.
- **A card's provider takes `array $slots` from Core 5.0** (`laravelui5-artifacts`, references `widgets` and
  `reports`; Core ROADMAP `OP06`); on 4.x it reads a slot with `$context->slot()`.
- **Two skills for the audited triggers** (SDK): `laravelui5-commands` — the person and automation bases, the
  declaration, `#[Act]` only on person commands, `handleCommand()` and the runner; `laravelui5-jobs-and-units` — the
  unit of work and its seal, the audited job, `afterCommit()`, A1–A3 and K1, the catalogue opt-out. Both start from the
  new generators `ui5:command`, `ui5:unit`, `ui5:job` (`meta/specs/sdk/audited-execution-v1.0.md` D38).

- **The security log** (the SDK guideline): a host's own sign-in path dispatches `SecurityEvent`
  (`meta/specs/sdk/audit-v1.0.md` D43).
- **Audited commands** (the SDK guideline): the two bases, the console sign-in, `#[Act]` only on person commands,
  `--language` (`meta/specs/sdk/audited-execution-v1.0.md` D37).
- **Audited jobs** (the SDK guideline): `AbstractJob` declares, `AbstractAuditedJob` runs units of work through the
  runner with the initiator kept (`meta/specs/sdk/audited-execution-v1.0.md` D36).
- **No audited write outside a unit of work** (the SDK guideline): a plain controller, command or job that writes an
  audited model fails a test (`meta/specs/sdk/audited-execution-v1.0.md` D35).
- **Audited models are listed in `ui5.audit.models`** (the SDK guideline): the write rule checks every write on their
  tables (`meta/specs/sdk/audited-execution-v1.0.md` D34).
- **The `#[Act]` gate is the dispatcher's** (`laravelui5-actions`): a gated action no longer needs a FormRequest, and
  `authorize()` defaults to `true` (`meta/specs/sdk/audited-execution-v1.0.md` D33).
- **A role needs a title and a description** (`laravelui5-security`): `roles.<role>.title` / `.description` in the
  bundle of the module that carries the `#[Role]`; a missing key stops `ui5:sync` (`meta/specs/sdk/i18n-v1.0.md` D34).
- **Units of work and the audit's rules** (`laravelui5-actions`, the SDK guideline): a handler writes only through
  models — no `DB::table` writes, no quiet saves — dispatches with `afterCommit()`, has no outside effects inside the
  transaction and never starts another unit of work; units of work and audited commands are declared
  (`AbstractWorkUnit`, `AbstractCommand`); what runs without a person runs as the automation actor. **Configuration
  tables carry `#[Audited]`, a Customizing catalog never does**; migrations on audited tables use `restrict` only and
  reshape but never correct (`meta/specs/sdk/audit-v1.0.md` D5, D19, D26–D28; `meta/specs/sdk/audited-execution-v1.0.md`
  D8, D10, D29).
- **Contributing a sync worker** (`laravelui5-modules`): `SyncWorkerInterface`, the registry as `$registry`, the
  declaration and the collector, blockers instead of exceptions, the catalogue opt-out only for code-owned tables;
  `ui5:ready` before `ui5:sync`, which has no `--dry` any more (`laravelui5-settings`, `laravelui5-dialogs`,
  `laravelui5-actions`).
- **Translated texts arrive translated** (`laravelui5-odata-modeling`): localized vocabularies and master data
  (`#[Translatable]` + `HasTranslations`) read in the user's language, filter and sort included; editors bind the
  `translations` navigation; `setTranslations()` writes. `LaravelUi5.getLanguages()` in the facade table
  (`laravelui5-modules`).
- **The tables come with `laravelui5/sdk-db`** (the SDK guideline): never required by hand; the pair updates with
  `composer update laravelui5/sdk -W` (`meta/specs/platform/tms-v1.0.md` TR-D15).

### Changed

- **The installation, not a system actor** (`laravelui5-partners`, the SDK guideline; `meta/specs/sdk/installation-v1.0.md`
  D6, D15–D21): the platform owner, the operator and the tenant are rows found by key and `operator_for`; a new
  database is `ui5:intake` → `ui5:sync` → `ui5:tenant`; the automation actor is the SDK setting `automation_actor`
  (an e-mail, by default the support department); `acts_for` beside `employed_by`; the ladder's rungs. The system
  actor, `ui5.system_actor_id` and `UI5_AUTOMATION_ACTOR_ID` are gone.
- **Texts are keys in the bundle of the module that declares them, and missing ones stop the build**
  (`laravelui5-security`, `laravelui5-actions`, `laravelui5-settings`, `laravelui5-shell`): ability titles
  (`abilities.<type>.<ability>.*`), setting titles (`settings.<key>.*`), Customizing fields
  (`customizing.<catalog>.<code>.<column>`) stop `ui5:sync`; rail labels (`route.<key>.label`) stop `ui5:nav`;
  `ui5:i18n` lists every missing one.
- **`#[Weave]` has no `label`** — `weave.<concept>.label` in the declaring app's bundle; **`#[Concept]`'s `label`
  is `marketplaceName`**, literal English for publishers (`laravelui5-shell`).
- **What the server says is a `MessageText`** — an intent's `deny()`/`message()` (with `from(App::NAMESPACE)`),
  an action's headline and messages, resolved on the server in the user's language (`laravelui5-shell`,
  `laravelui5-actions`); a 422's field names from `fields.<field>`.
- **The SDK's texts and the help `Context` live in the SDK's library `com.laravelui5.sdk`** (`laravelui5-shell`):
  a key without `from()` is read there; an app lists `com.laravelui5.sdk` in `getResourceNamespaces()`
  (`meta/specs/sdk/i18n-v1.0.md` D33).
- **A headline is a `MessageText`, never a string; a refusal must have one** (`laravelui5-actions`):
  `commit(?MessageText)`, `rollback(MessageText)` (`meta/specs/sdk/audit-v1.0.md` D41).
- **A setting's declaration and its values are apart; the sync never rewrites a value** (`laravelui5-settings`):
  a removed, renamed or retyped `#[Setting]` whose values no longer fit stops `ui5:sync` instead of deleting or
  converting them (`meta/specs/sdk/audit-v1.0.md` D38).
- **Migrations and `db:seed` are audited steps** (the SDK guideline): a write to an audited table leaves one
  `Migration` head under the automation actor; the old "seeders and migrations are exempt" is gone
  (`meta/specs/sdk/audit-v1.0.md` D47).

## [1.4.0] - 2026-10-22 – Core 4.0.0

Written against **odata 3.x · core 4.x · sdk 1.x**, and Composer holds it to that: `conflict` names
`laravelui5/core <4.0 || >=5.0`. The guidance describes Core 4 as it is; it no longer carries the forms
of earlier Core majors. The SDK guidance stays on sdk 1.x, except where Core 4 moved something into the
SDK (the dialog's type and contract); sdk 2.0 follows in the next release.

### Changed

- **A module names its artifacts in two constants** (`laravelui5-modules`, `laravelui5-artifacts`,
  `laravelui5-actions`, `laravelui5-dialogs`, the core guideline). The root — the app or library — is
  `ARTIFACT_ROOT`, every other artifact a class name in `ARTIFACTS`; the getters (`getCards()`,
  `getActions()`, `getDialogs()` …) are gone. The examples and checklists say so, and that a module
  without its root fails at boot.
- **Identity constants carry their type** — `public const string NAMESPACE`, `VERSION`, `TITLE`,
  `DESCRIPTION`, and `VIEW` on a dialog. Required since core 4.0, whose artifact bases declare them
  `?string`: an untyped one no longer loads.
- **The artifact types belong to their layer.** An action is `CoreArtifactType::Action`; a dialog is
  `SdkArtifactType::Dialog`, and its contract `Ui5DialogInterface` lives in
  `LaravelUi5\Sdk\Ui5\Contracts`.

### Fixed

- **1.3.0 excluded the major it taught.** Its `conflict` still read `laravelui5/core >=3.0` while its
  guidance followed core 3.0, so it could not be installed beside Core 3.

## [1.3.0]

Follows **core 3.0** where it differs from 2.x; each difference is marked inline with its version.

### Changed

- **`laravelui5-modules`: the identity options are required.** The scaffolding examples pass
  `--package-prefix`, `--php-ns-prefix` and (for an app) `--vendor`, say that core 3.0 refuses a
  `--create` without them, and that `--js-ns-prefix` only widens the search for an Easy UI5 source
  folder. A library is a package since core 3.0 (`ui5-charts-lib` → `ui5/Charts` → `acme/charts`).
- **`laravelui5-actions`: kebab-case namespaces.** A scaffolded action's namespace ends in kebab-case
  since core 3.0 (`actions.clear-mailbox`); existing snake_case namespaces stay valid.
- **`laravelui5-settings`: settings reach every provider.** The "reports are the exception" note is
  replaced: since core 3.0 Tile, Chart and Report providers receive their artifact's settings too,
  and writing to a setting throws.
- **`laravelui5-settings`: no currency slot.** The slot examples are locale, time zone and period;
  the skill says the base currency is not a slot (removed in core 3.0), that a slot is read anywhere
  with `$context->slot()`, and that slot values come from the query string only.

## [1.2.0] – 2026-10-07

Written against **odata 3.x · core 2.x · sdk 1.x**. The OData guidance follows **odata 3.1**; what is
new in 3.1 is marked so inline, per the rule in the README.

### Fixed

- **The discovery skill taught the form that breaks a model.** `references/discovery.md` showed
  `#[ODataProperty(...)] public $amount;` and `#[ODataIgnore] public $internalNote;`. A plain declared
  property shadows Eloquent's attribute bag: reads return `null`, writes are lost on `save()`. The
  samples now use PHP 8.4 property hooks with a **block** `set` and nullable types. The arrow form
  `set($v) => $this->setAttribute(…)` assigns the returned model and throws on direct assignment,
  and the reference says so. The guideline and the skill's *not to do* list carry the rule.

### Added

- **`references/annotations.md`** (new): vocabulary terms as attributes, `Path` values,
  `annotateContainer()`, and the three pieces of the code-list wiring UI5 needs to format currencies
  and units per row. It includes the rules that cost a debugging session: a null
  `UnitSpecificScale` renders amounts empty, code lists must not be paged, texts follow
  `Accept-Language`, and `preserveDecimals` decides whether input is checked.
- **Discovery (odata 3.1):** facets from the column (`Nullable`, `Precision`/`Scale`, `MaxLength`);
  `#[ODataEntity(useHidden: true)]` and why a model with secrets in `$hidden` needs it; `nullable:`,
  `precision:` and `scale:` overrides; `ColumnFacetResolverInterface` for installation facts and its
  interplay with `odata:cache`; polymorphic relations stay out of discovery, with the explicit
  alternatives.
- **Consuming (odata 3.1):** `$filter` refuses what it cannot translate (`501`/`400`, never a silent
  drop), `tolower`/`toupper`, literal `$search`, typed and quoted key literals, `nextLink` that
  repeats the request, `$count` inside `$expand`, `IEEE754Compatible`.
- **Custom entity sets (odata 3.1):** built by the container, the `parent::__construct()` rule,
  declared types on the wire (no casts in row maps), `any`/`all` refused on a SQL source.

### Changed

- **README:** the skill listing shows all nine skills, grouped by the package each teaches. It used
  to show three. The versioning section says that version-specific guidance, a minor included, is
  marked inline.

## [1.1.0] – 2026-10-07

Written against **odata 3.x · core 2.x · sdk 1.x** — and now Composer holds it to that.

### Added

- **`conflict` on the next major of each package it teaches:** `laravelui5/odata >=4.0`,
  `laravelui5/core >=3.0`, `laravelui5/sdk >=2.0`. Until 1.0.0 the binding to a major was a sentence
  in this changelog; a host that moved to the next major kept getting guidance for the previous one,
  silently — plausible and wrong, which is worse for an agent than no guidance at all. Composer now
  refuses that combination, and the fix is to update Chiron. The bound is only an upper one and only
  on what is installed: requiring Chiron on its own, before the stack, still works.
- **The cost, stated:** a major of odata, Core or the SDK can only be installed alongside Chiron once
  a Chiron release has raised the bound. That couples the two cadences at the major and nowhere
  else; prose fixes inside a major still ship on Chiron's own tag.

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
