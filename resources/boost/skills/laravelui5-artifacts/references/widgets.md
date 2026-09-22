# Cards, tiles and charts

The three small artifacts. Same registration, same class-string indirection, **three different
provider contracts** — that difference is the thing to get right.

## Card — a UI5 Integration Card, manifest rendered per request

A card is three files: the artifact, a provider, and a Blade template that renders the Integration
Card manifest.

```php
class RevenueCard extends AbstractUi5Card
{
    public const string NAMESPACE = 'com.acme.invoicing.cards.revenue';
    public const string VERSION   = '1.0.0';

    public function getProvider(): string { return RevenueProvider::class; }
}
```

`getProvider()` returns a **class-string**. The runtime resolves it through the container, so the
provider's constructor dependencies autowire. **Never write `return app(...)` or `return new ...`
here** — a declaration method declares, it does not build. Override `getCard()` only to set
dashboard-grid properties.

```php
class RevenueProvider extends AbstractConfigurable implements DataProviderInterface
{
    public function __construct(private readonly RevenueRepository $revenue) {}

    public function provide(): array
    {
        return ['title' => 'Revenue', 'value' => $this->revenue->thisQuarter(), 'unit' => 'EUR'];
    }
}
```

`DataProviderInterface` is an **empty marker**; `provide(): array` is documented and enforced at
runtime, and its arguments are method-injected — the `Ui5ContextInterface` arrives that way if you
declare it. **Slot values do not**: the card path runs no slot pipeline.

### The manifest template

`manifest.json.blade.php` is rendered to JSON per request. The provider's array is `$data`, and the
card itself is `$card`.

```blade
{
  "_version": "1.15.0",
  "sap.app": {
    "id": "{{ $card->getNamespace() }}",
    "type": "card",
    "applicationVersion": { "version": "{{ $card->getVersion() }}" }
  }
}
```

**Two rules that bite:**

- Bind user-visible strings to the card's own i18n bundle with **`{i18n>KEY}`** — **never** the
  `{{KEY}}` double-brace form. It collides with Blade's echo syntax and silently produces an empty
  string.
- The output must be **valid JSON**. A trailing comma from a Blade `@foreach` is the usual cause of
  a card that renders blank with no error.

### Why server-rendered and not client-bound

The card's manifest is served from `card/{ns}@{ver}/manifest.json`, already filled in. Prefer that
over binding the card to a data source in the client: the server knows the actor, the settings and
the parameters, and the manifest arrives complete instead of the client assembling it in a second
round-trip.

## Tile — one number, launchpad-sized

```php
class ProjectKpiTile extends AbstractUi5Tile
{
    public const string NAMESPACE   = 'com.acme.offers.tiles.project-kpi';
    public const string VERSION     = '1.0.0';
    public const string TITLE       = 'Project KPI';
    public const string DESCRIPTION = 'Displays aggregated project health indicators';

    public function getTileProvider(): string { return ProjectKpiTileProvider::class; }
}
```

Unlike the card's marker, `TileProviderInterface` is **typed**:

```php
class ProjectKpiTileProvider implements TileProviderInterface
{
    public function __construct(private readonly ProjectMetrics $metrics) {}

    public function getTile(array $boundParams, Ui5ContextInterface $context): Tile
    {
        return new GenericTile(
            header: 'Project KPI',
            tileContent: [
                new TileContent(
                    content: new NumericContent(
                        value:     (string) $this->metrics->health(),
                        scale:     '%',
                        indicator: DeviationIndicator::Up,
                    ),
                ),
            ],
        );
    }
}
```

A `Tile` is built from the control vocabulary (`GenericTile`, `TileContent`, `NumericContent`, …),
not from raw markup. `$boundParams` carries the parameters bound where the tile is placed;
`$context` carries the actor and the resolved artifact.

## Chart — a plot

Same shape as a tile, different return type:

```php
class RevenueTrendChartProvider implements ChartProviderInterface
{
    public function __construct(private readonly RevenueRepository $revenue) {}

    public function getChart(array $boundParams, Ui5ContextInterface $context): Chart
    {
        $series = $this->revenue->quarterly();

        return new Chart(
            canvas: new ChartCanvas(
                engine: 'echarts',
                option: [
                    'xAxis'  => ['type' => 'category', 'data' => $series->labels()],
                    'yAxis'  => ['type' => 'value'],
                    'series' => [['type' => 'line', 'data' => $series->values()]],
                ],
            ),
            layoutData: new GridContainerItemLayoutData(columns: 6, rows: 4),
            height:     '100%',
        );
    }
}
```

The canvas carries the engine and its option object; the surrounding `Chart` carries header,
layout and sizing. `layoutData` is how it sits in a dashboard grid.

## Choosing between them

- **A single number, with a trend or a status** → tile.
- **A small structured payload — a list, a table, an object header** → card.
- **A series you want plotted** → chart.

If the answer is "a table of many rows that the user will filter and page", none of the three is
right: that is an OData entity set consumed by a real UI5 table in an app. **Entity sets are for
tables; cards, tiles and charts bring their own providers.**

## Do not

- **Do not write through any of them.** Every mutation is a `Ui5Action`.
- **Do not build one by hand** — `ui5:card`, `ui5:tile`, `ui5:chart` emit the current shape.
- **Do not `app()` or `new` inside a declaration method.** Return the class-string.
