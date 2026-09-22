---
name: laravelui5-artifacts
description: >-
  Choose and author a LaravelUi5 Core artifact — app, library, card, tile, chart, dashboard, group,
  report or resource. Use this when adding anything a user sees or the launchpad shows, when asking
  "which artifact type do I need", or when wiring a provider behind one. Covers the shared model
  (plain PHP objects; identity in constants, behaviour in a class-string the runtime resolves,
  everything else in attributes), the two-step registration, the `ui5:*` scaffolders, and the three
  different provider shapes (a marker for cards, typed contracts for tiles and charts). Writes are
  NOT here — every mutation is a Ui5Action, see the laravelui5-actions skill. Triggers: "add a
  card", "add a tile", "add a chart", "build a dashboard", "launchpad tile", "ui5:card", "ui5:tile",
  "ui5:app", "which artifact", "DataProviderInterface", "TileProviderInterface", "getProvider",
  "manifest.json for a card", "serve a file from a module", "add a report".
license: MIT
metadata:
  author: laravelui5
---

# Choosing and authoring a Core artifact

## Pick the type first — this is where most mistakes start

| The job | The artifact | Scaffold with |
|:---|:---|:---|
| A screen, a whole application surface | **App** (`AbstractUi5App`) | `ui5:app` |
| Shared UI5 code used by several apps | **Library** | `ui5:lib` |
| A single number or KPI, launchpad-sized | **Tile** | `ui5:tile` |
| A small data widget — list, table, object | **Card** (UI5 Integration Card) | `ui5:card` |
| A plot: bars, lines, donut | **Chart** | `ui5:chart` |
| A page composed of the above | **Dashboard** + **Group** | `ui5:dashboard`, `ui5:group` |
| Printable / exportable HTML output | **Report** | `ui5:report` |
| A file, asset or JSON blob served from the module | **Resource** | `ui5:resource` |
| **Anything that writes** | **not an artifact type here** | `ui5:action` → `laravelui5-actions` skill |

A **Module** is the container that holds them. It is not addressable — nothing is served at a
module's URL — and it is where `#[Slot]` declarations live.

## The shared model — true of every type

**1. Always scaffold, never hand-create.** The generators emit the current contract; a hand-written
class reproduces whatever pattern was remembered from an older project.

**2. Identity lives in constants.**

@verbatim
<code-snippet name="Every artifact looks like this" lang="php">
class RevenueTile extends AbstractUi5Tile
{
    public const string NAMESPACE = 'com.acme.invoicing.tiles.revenue';
    public const string VERSION   = '1.0.0';
}
</code-snippet>
@endverbatim

**3. Behaviour is a class-string, not an instance.** An artifact *points at* its provider or handler
(`getProvider()`, `getTileProvider()`, `getHandler()`), and the runtime resolves it from the
container. So constructor dependencies autowire — put services there.

**4. Registration is two steps and there is no third.** Return the artifact from the module
(`getCards()`, `getTiles()`, `getCharts()`, `getDashboards()`, `getReports()`, `getResources()`, …),
and list the module class in `config/ui5.php` under `modules`. No route file, no service provider, no
frontend config. The address is derived from namespace and version.

**5. `resources/` is build output.** Never hand-edit it; `ui5:app --refresh` overwrites
`manifest.json`. Edit the UI5 source project instead.

**6. Attributes declare; classes do.** Anything the framework must know — a slot, a parameter, an
ability — is stated as an attribute, not wired in code.

## The three provider shapes, and why they differ

This asymmetry is real and worth knowing before you write one:

| Artifact | Contract | Shape |
|:---|:---|:---|
| **Card** | `DataProviderInterface` | an **empty marker** — `provide(): array` is documented and enforced at runtime; its arguments are method-injected by the container |
| **Tile** | `TileProviderInterface` | **typed**: `getTile(array $boundParams, Ui5ContextInterface $context): Tile` |
| **Chart** | `ChartProviderInterface` | **typed**: `getChart(array $boundParams, Ui5ContextInterface $context): Chart` |

The marker exists for the same reason Laravel's `ShouldQueue` never types `handle()`: the arguments
vary per provider and a PHP interface cannot express container-time resolution. Where a contract
*can* be fixed — tiles and charts always get bound parameters and a context — it is fixed, and PHP
enforces it.

**In both shapes: services go in the constructor, per-request inputs come in as arguments.**

## Then read the reference for what you are building

- **`references/widgets.md`** — cards, tiles and charts: the three providers in full, bound
  parameters, and the manifest-endpoint idiom that keeps a card's manifest server-rendered.
- **`references/dashboards.md`** — composing a page: the property/aggregation/resolution split, the
  instances-above-namespaces-below asymmetry, the veto chain for visibility, and failure isolation.
- **`references/reports.md`** — the HTML document, its slots, and why a report is not a table.
- **`references/resources.md`** — the four read mechanisms in order, and why a Resource is almost
  never the right one.

**Setting up a module, app or library** — the Composer bracket, `config/ui5.php`, source strategies,
`ui5:assemble` — is a different task from adding an artifact to one. Use the
**`laravelui5-modules`** skill for that.

## Do not

- **Do not build a card or tile that writes.** Every mutation is a `Ui5Action`.
- **Do not point a card at an OData entity set for a fixed, computed payload.** Entity sets are for
  tables; cards, tiles and charts bring their own providers.
- **Do not hand-write a UI5 artifact's manifest by hand** when a scaffolder exists for it.
