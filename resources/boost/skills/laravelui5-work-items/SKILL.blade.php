---
name: laravelui5-work-items
description: >-
  Contribute to the LaravelUi5 inbox — work items for what needs a person's attention. Use this whenever
  something in a module does not fix itself and a person must look at it (a missing tariff, an imminent loss,
  an inconsistent master record, a failed import), or when asked where such a "to-do", "follow-up",
  "worklist", "inbox entry" or "notification for the controller" should go. Covers the two criteria (an
  exception, a hand-over across a boundary) and what does not belong (a process's routine queue, deadlines),
  `WorkItemRuleInterface` and `WorkItemFinding`, tagging the rule, the addressee (a role, a partner, the
  model a scoped role is held on), the free text in the base language, `ui5:work-items`, reading
  (`forPerson`, `open`), dismissing with a reason, and the marks of items left hanging. Triggers: "work item",
  "WorkItem", "inbox", "worklist", "follow-up list", "to-do for the user", "ui5:work-items",
  "WorkItemRuleInterface", "who has to look at this", "notify the controller".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\WorkItems\Contracts\WorkItemRuleInterface::class))
# Work items are an SDK 4.0 capability

The inbox (`LaravelUi5\Sdk\WorkItems\`) ships with `laravelui5/sdk` 4.0. On an older SDK or on Core alone there is
none: show such a finding in the app that owns it.
@else
# Work items — the inbox

## First: does it belong in the inbox?

An item exists for exactly two reasons:

1. **It lies outside the regular course** — it does not fix itself: a missing tariff, an imminent loss, a failed run.
2. **A process needs a person who does not already work in it** — a hand-over across a boundary (an approval by a
   line manager, an objected draft back to its author). *Tasks of this kind are provided in 4.0, not yet built.*

**Never** put a process's routine queue there — drafts to book, a statement to settle, this month's VAT return. That
queue stays in the app that owns the process; copying it creates two truths and buries what matters. A deadline belongs
in a calendar; only a missed one is an exception.

## Write the rule

```php
use LaravelUi5\Sdk\WorkItems\Contracts\WorkItemRuleInterface;
use LaravelUi5\Sdk\WorkItems\Enums\WorkItemSeverity;
use LaravelUi5\Sdk\WorkItems\WorkItemFinding;

final readonly class MissingTariffRule implements WorkItemRuleInterface
{
    public function code(): string
    {
        return 'controlling.tariff_missing';            // <domain>.<name>, unique across all rules
    }

    public function findings(): iterable
    {
        foreach (CostObject::query()->withoutTariff()->get() as $object) {
            yield new WorkItemFinding(
                subject: "co_object:{$object->code}",   // unique within this rule; stable across runs
                role: AppRole::Controller,              // the case your module declares with #[Role]
                text: "{$object->code}: kein Tarif für Beratung.",
                fix: 'Den Tarif in der Controlling-App anlegen.',
                severity: WorkItemSeverity::Action,     // or Notice — asks for a look
                action: ['intent' => 'ui5-artifact.open', 'target' => 'com.example.controlling'],
            );
        }
    }
}
```

Tag it in the module's service provider:

```php
$this->app->tag([MissingTariffRule::class], WorkItemRuleInterface::class);
```

**Rules of the rule:**

- **Compute from the state as it is now.** Never a gate, never a write. The run decides what is new, what stays,
  what is resolved.
- **The subject is stable.** The same thing found tomorrow must carry the same subject, or it becomes a new item and
  the old one resolves.
- **The text is free text in the tenant's base language** — the run writes it at night, with no request to take a
  language from. No message keys.
- **The action opens the subject in the app that owns it.** The inbox never edits.

## The addressee

- **A role** — the enum case or token your module declares with `#[Role]`; `ui5:sync` must have written it into
  `sdk_roles`, or the rule is refused.
- **A scoped role** (`#[Role(…, scope: Model::class)]`) **needs its model as `context:`** — pass the model, never a
  morph key or an id. A global role takes **no** context. A context of another class than the role's scope is refused.
  *A project manager is scoped to the project's cost object: pass the cost object, and let `subject`, `text` and
  `action` name the project.*
- **Optionally one partner** (`partner:`) — then only that person sees it.

A refused finding refuses the **whole rule** at the run, before its first write. Do not catch it: it is the rule's
defect.

## Running it

`php artisan ui5:work-items` — an automation command, each rule in its own unit of work; a failing rule rolls back
alone and is recorded; exit 1 when one failed. **The SDK does not schedule it.** On the Foundation a characteristic does;
on the SDK alone schedule it yourself (`Schedule::command('ui5:work-items')->dailyAt('05:00')`).

## Reading and dismissing

Take `WorkItemServiceInterface` by injection.

| Need | Call |
|:--|:--|
| a person's inbox | `forPerson($person, $at)`, `count($person, $at)` |
| a role's pending items | `open($role, partner: …, context: …)` |
| a person dismisses a finding | `dismiss($item, $reason, $sdk)` — **inside a unit of work**, the reason required |
| the IT lead's view | `all()`, and `marked($at)` — the items left hanging |

`forPerson()` reads **direct role assignments only** — a role held through a group does not fill the inbox. Never
read `sdk_work_items` with your own query to show an inbox; never write it except through the service.

`WorkItemClosed` is dispatched after the commit when an item is resolved or dismissed.

## Never

- Never put the routine queue of your own process into the inbox.
- Never write `sdk_work_items` directly, and never close an item yourself to "clean up" — a finding is true until
  its rule no longer finds it.
- Never pass a morph key or an id as the context — pass the model.
- Never schedule `ui5:work-items` from inside a module.
@endif
