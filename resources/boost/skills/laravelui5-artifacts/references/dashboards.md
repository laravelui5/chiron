# Dashboards and groups — composing a page

A dashboard renders as a fixed nesting: **VBox → Panel → GridContainer → children**, and those are
literally `sap.m.VBox`, `sap.m.Panel` and `sap.f.GridContainer`.

## The one rule that explains every method

**You own the properties. The framework owns the child aggregations. The registry owns resolution.**

Each artifact returns a *template DTO* for the control it renders as — `getVBox()` on the dashboard,
`getPanel()` and `getGridContainer()` on the group. Set any property you like on them. But **never
set `items` or `content` yourself: anything you put there is silently overwritten**, because the
framework injects the built children through `withItems()` / `withContent()`.

The DTOs mirror the real SAP control APIs one-to-one. That is why a group header is `headerText` and
not `title` — it is the Panel's own property name. **Look properties up in the OpenUI5 API
reference**; if `sap.m.Panel` has it, the `Panel` DTO takes it.

## The dashboard

```php
class Overview extends AbstractUi5Dashboard
{
    public const string NAMESPACE   = 'com.acme.sales.dashboards.overview';
    public const string VERSION     = '1.0.0';
    public const string TITLE       = 'Sales Overview';
    public const string DESCRIPTION = 'Pipeline, performance and this month at a glance';

    /** @return Ui5DashboardGroupInterface[] */
    public function getGroups(): array
    {
        return [
            new ThisMonthGroup($this->module),
            new PipelineGroup($this->module),
            new PerformanceGroup($this->module),
        ];
    }
}
```

`getGroups()` is the only required behaviour, and group order is display order. It is called once per
request; if instantiating is expensive, cache the array in the constructor.

Override `getVBox()` to customise the root layout — anything except `items`.

## The group

```php
class PerformanceGroup extends AbstractUi5DashboardGroup
{
    public const string NAMESPACE   = 'com.acme.sales.groups.performance';
    public const string VERSION     = '1.0.0';
    public const string TITLE       = 'Performance';
    public const string DESCRIPTION = 'Outcomes — quota, revenue mix, and account detail';

    public function getChildNamespaces(): array
    {
        return [
            QuotaAttainmentChart::NAMESPACE,
            RevenueMixChart::NAMESPACE,
            AccountSnapshotCard::NAMESPACE,
        ];
    }
}
```

`getChildNamespaces()` is the **only abstract method**; everything else has a working default —
a `sap.m.Panel` with `headerText` bound to the title, and a bare `sap.f.GridContainer`. Array order
is display order.

The list need not be literal. It is resolved per request, so a group may compute it — the LUX
Launchpad harvests every tile carrying a placement marker and returns the namespaces sorted by
weight. The only contract is that each namespace resolves to a registered artifact.

Override `getPanel()` and `getGridContainer()` freely — anything except `Panel.content` and
`GridContainer.items`.

## The asymmetry: instances above, namespaces below

A dashboard holds its **groups as instances**. A group names its **children as namespace strings**.
That is deliberate, and the reason is composition: **a namespace is a reference another package can
also produce.** It lets a foreign module contribute a child, and it keeps a group's declaration free
of `use` statements pointing into packages it should not know about.

## Visibility — use a vetoer, never a filter

To hide a group or a child from some actors, **do not filter `getGroups()` or
`getChildNamespaces()`.** Those stay declarative. Register a `VetoerInterface` by container tag; it
answers one question about one artifact:

```php
public function dispose(
    Ui5ArtifactInterface $artifact,
    Ui5ContextInterface  $context,
): Disposition;
```

The chain folds **most-restrictive-wins**: any `Hide` wins and short-circuits, else any `Lock`, else
`Show`. The outcome is therefore order-independent, and a vetoer needs to know neither the other
vetoers nor the tree. An empty chain shows everything, which is Core's default.

**The chain is consulted before a hidden artifact is built**, so a vetoed child is pruned *pre-data*:
its provider never runs and it costs nothing. `Lock` is declared and folds above `Show` but is not
yet enforced.

This is the seat for access control, lifecycle and personalisation alike. **Reach for a vetoer, not a
subclass**, whenever the question is "should this be visible for this actor".

## Failure isolation — let children fail

A child whose provider throws does **not** 500 the endpoint. The container emitter catches the cause,
records it in the `EmitErrorSink`, omits the node, and the siblings render. The same holds one level
up: a group that throws is dropped while its siblings survive.

After the walk the controller reports each cause once and stamps sanitised entries into the
envelope's `errors` array. The wire `reason` is **debug-gated** — a full `Class: message` in dev, a
stable generic line in production. Raw exception text never reaches a customer browser.

The one unguarded seat is the **root VBox**: if that throws, there is nothing to render and it
bubbles as a whole-dashboard failure.

**So do not defensively `try/catch` inside a provider to return an empty shape.** A recorded, omitted
child is more honest than a rendered lie. A missing child with an `errors` entry is a failure; a
missing child with no entry was vetoed.

## Best practice

- **One dashboard per audience, not per data source.** Groups are the seam for topical structure; a
  second dashboard is warranted when a different *person* looks at it.
- **Keep groups small.** Once a group needs a scroll of its own, it wants to be two groups.
- **Push work to the leaves.** A dashboard resolves nothing itself; the data cost lives in each
  tile/card/chart provider, where failure isolation and caching apply per child.
