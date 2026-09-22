# Reports — a document, deliberately

A Core report is **HTML and nothing else**, served into an iframe. That is a decision, not a
limitation. Reports kept accreting responsibilities — selection UI, parameter binding, column
metadata, CSV/XLSX/PDF emitters, bulk actions — and each of those turned out to belong somewhere that
already existed:

| The old job | Where it lives now |
|:---|:---|
| Selection screen | the **host app** — it owns a filter bar and binds it to `<lux:Report parameters=…>` |
| Parameter binding | the **slot pipeline** — Request → *(Actor)* → Composition → Setting → Default |
| Column metadata, filtering, paging | an **OData entity set** — reports are documents, not tables |
| Export | the **SDK**, which wraps the same HTML |
| Follow-up actions | a **`Ui5Action`**, callable from anywhere |

**So reach for OData instead whenever what you actually want is a sortable, filterable, pageable
table.** A report is a document you read or print, not a grid you interrogate.

## Three parts

### 1. The artifact — declares its slots and its provider

```php
class HoursReport extends AbstractUi5Report
{
    public const NAMESPACE   = 'com.acme.timesheet.reports.hours';
    public const VERSION     = '1.0.0';
    public const TITLE       = 'Booked Hours';
    public const DESCRIPTION = 'Hours booked by employees this period';

    public function getRequiredSlots(): array
    {
        return [CoreSlots::DateFrom, CoreSlots::DateTo];
    }

    public function getProvider(): string { return HoursProvider::class; }
}
```

`getRequiredSlots()` is what replaces a selection screen. **Declare a slot rather than inventing a
query parameter** — the whole resolution chain, defaults included, comes free.

### 2. The provider — resolves and shapes the data

```php
class HoursProvider implements DataProviderInterface
{
    public function __construct(private TimesheetRepository $timesheets) {}

    public function provide(array $slots, Ui5ContextInterface $context): array
    {
        return [
            'rows' => $this->timesheets->between($slots['date_from'], $slots['date_to']),
            'from' => $slots['date_from'],
            'to'   => $slots['date_to'],
        ];
    }
}
```

The controller invokes `provide()` through `Container::call`, passing the resolved slot bag
explicitly as `$slots`. **Service dependencies go in the constructor; per-request inputs go in the
method signature.** Core's `Ui5ContextInterface` carries the resolved `artifact()` and the request
`locale()` and nothing more — Core is auth-blind.

Note the shape differs from a card's provider: a report's `provide()` takes `$slots`, a card's takes
none (the card path runs no slot pipeline).

### 3. The view — a complete HTML page

`report.blade.php` is a whole document, not a fragment: `<!doctype html>` through `</html>`. **Style
inside the document.** The iframe is an isolation boundary — use it, and write real `@media print`
rules while you are there. Inline SVG or D3 if the report needs charts.

## Displaying it

```xml
<lux:Report
    name="com.acme.timesheet.reports.hours"
    parameters="{selection>/}"
    reportLoaded=".onReportLoaded" />
```

`name` is the namespace, looked up in `laravel.ui5/reports`; `parameters` is a flat object of slot
overrides composed into the query string. Bind it to your own filter bar's model and the control
rewrites the iframe `src` in place.

**A partial map is valid, and so is an empty one.** Any slot you omit falls through the server-side
chain to its declared default. That is the guarantee that replaced the selection screen: **a report
always renders**, from the instant its URL resolves, with no blocking "nothing selected yet" state.

So: **give every slot a default that renders something useful.** The report will be requested with no
parameters at least once; make that first render meaningful.

`reportLoaded` carries the requested `url` and the frame's resolved `finalUrl`. When they differ the
frame followed a redirect — usually an expired session — and the consumer decides what that means.

## Do not

- **Do not invent query parameters.** Declare a slot.
- **Do not build a table here.** Use an OData entity set and a real UI5 table.
- **Do not style from the host.** The iframe isolates on purpose; the document owns its layout.
- **Do not put data shaping in the view.** Keep the provider thin, but keep it the place that decides
  *what* the numbers are; the document decides how they look.
