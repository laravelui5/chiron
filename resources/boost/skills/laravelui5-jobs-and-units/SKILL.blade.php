---
name: laravelui5-jobs-and-units
description: >-
  Author a LaravelUi5 unit of work and an audited job — the transactional step every write runs in,
  and the queued trigger that carries a run's correlation key and actor across the queue. Use this
  whenever writing business logic that changes data outside an action's handler (a dunning step, an
  import row, a nightly job), scaffolded with `php artisan ui5:unit` and `php artisan ui5:job`. Covers
  `WorkUnitInterface` and the seal, the declaration (`AbstractWorkUnit`, `AbstractJob`), the runner,
  `AbstractAuditedJob`, `afterCommit()`, the rules A1–A3 and K1, operations, and the catalogue
  opt-out. Triggers: "unit of work", "WorkUnit", "ui5:unit", "ui5:job", "queued job", "nightly job",
  "dispatch a job", "afterCommit", "transaction", "BusinessTransaction outside an action",
  "WorkUnitRunner", "who did this job act for".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Units of work and audited jobs are an SDK capability

The runner, the seal outside an action and the job hull that carries a run across the queue are
`laravelui5/sdk`. On Core alone, an action's handler is the only transactional seam; write ordinary
Laravel jobs for the rest.
@else
# Units of work and audited jobs

## The grammar

| Role | What it is |
|:--|:--|
| **Trigger** | starts a run: who acts, one correlation key — an action, an audited command, an audited job |
| **Unit of work** | one business step: **one transaction, one seal, one actor**, one change-log head |
| **Operation** | logic without a transaction of its own, called by a unit (a price, a validator) |

A trigger composes units; a unit composes operations. An action's handler *is* a unit.

## Always scaffold

```bash
php artisan ui5:unit {App}/{Name}    # Units/{Name}Unit.php + {Name}UnitDeclaration.php
php artisan ui5:job {App}/{Name}     # Jobs/{Name}Job.php + {Name}JobDeclaration.php
```

Each prints what to add: the declaration in `{App}Module::ARTIFACTS`, the unit's label
`units.<namespace>` (and its message key) in the module's bundles, en and de, then `ui5:sync`.

## A unit of work

```php
final class DunInvoiceUnit implements WorkUnitInterface
{
    public function handle(BusinessTransaction $transaction, BusinessContextInterface $context, SdkContext $sdk): Outcome
    {
        $invoice = $context->parameter('invoice');
        $invoice->update(['dunning_level' => $invoice->dunning_level + 1]);   // through the model

        return $transaction->commit(MessageText::of('messages.dunning.raised', [$invoice->number]));
    }
}
```

- The runner opens the transaction; the unit **seals** it — `commit()` or `rollback(MessageText)` — and
  returns the seal. It never opens a transaction of its own. A deadlock gets one more attempt.
- Declared like any artifact (`AbstractWorkUnit`, one `units` segment, `UNIT` naming the class); the
  runner **refuses an undeclared unit**.
- Run it: `$runner->run($unit, new BusinessContext(parameters: [...], validated: [...]), $sdk)`.

## An audited job

```php
final class DunInvoicesJob extends AbstractAuditedJob
{
    public function handle(WorkUnitRunnerInterface $runner, SdkContext $sdk, DunInvoiceUnit $dun): void
    {
        foreach ($this->dueInvoices() as $invoice) {
            $runner->run($dun, new BusinessContext(parameters: ['invoice' => $invoice]), $sdk);
        }
    }
}
```

- Dispatched from an action, a command or a job, it **keeps that run's key and acts for its
  initiator**; scheduled, it acts as the automation actor. The change log reads as one story.
- **Dispatch after the commit:** `DunInvoicesJob::dispatch()->afterCommit()` — never for a change that
  was rolled back. A job carries no `#[Act]`; its trigger's gate is the gate.
- Declared with `AbstractJob` (one `jobs` segment, `JOB`); an undeclared job is refused when it runs.

## The rules

- **A1** — no unit inside another. A step under other authority is a follow-up job.
- **A2** — what a unit dispatches runs after its commit (`afterCommit()`, `after_commit` on the queue).
- **A3** — one actor per unit, fixed when it opens.
- **K1** — HTTP calls, files and mail after the commit, never inside the transaction.
- Write through models only — `create`, `update`, `delete`, a pivot model for relations. Never
  `DB::table` on an audited table, never `saveQuietly()`.

## The catalogue opt-out

A unit that mirrors what **code** declares (a sync worker) implements `CatalogWorkUnitInterface`,
lists its `tables()`, and writes one head without positions. Only an automation command may run it,
and none of its tables carries `#[Audited]`. Anything people decide is never catalogue.
@endif
