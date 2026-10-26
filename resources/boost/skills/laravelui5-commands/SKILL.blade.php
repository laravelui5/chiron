---
name: laravelui5-commands
description: >-
  Author an audited LaravelUi5 artisan command — a console trigger whose writes land in the change log
  with a person or the automation actor, a correlation key and the command's input. Use this whenever
  adding an artisan command that changes data in a LaravelUi5 SDK app, scaffolded with
  `php artisan ui5:command`. Covers the two bases (`AbstractPersonCommand` signs its person in,
  `AbstractAutomationCommand` runs as the automation actor), the declaration (`AbstractCommand`,
  `#[Act]` on person commands only), `handleCommand()` and the runner, `--language`, registration in
  the module and with Artisan, and what never to do (write outside a unit of work). Triggers: "artisan
  command", "console command that writes", "ui5:command", "AbstractPersonCommand",
  "AbstractAutomationCommand", "who ran this command", "command in the audit", "import command".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Audited commands are an SDK capability

An audited command — one whose writes are recorded with a person, a run and its input — needs the
change log, the runner and the automation actor, which are `laravelui5/sdk`. On Core alone, write an
ordinary Laravel command; nothing records who ran it.
@else
# Authoring an audited LaravelUi5 command

## The rule that frames everything

**A command is a trigger, not a unit of work.** It decides who acts, opens one run (one correlation
key), and composes **declared units of work** through the runner. Every write happens inside a unit:
one transaction, one seal, one change-log head each. A write on an `#[Audited]` table outside a unit
fails a test and leaves an *Unmanaged* head in production.

## Always scaffold — never hand-create

```bash
php artisan ui5:command {App}/{Name} --act={ability} --role={role}   # a person command
php artisan ui5:command {App}/{Name} --automation                    # an automation command
```

Two files under `ui5/{App}/src/Commands/`: `{Name}Command.php` and `{Name}CommandDeclaration.php`.
The generator edits nothing else; it prints the three things you add — the declaration in
`{App}Module::ARTIFACTS`, the command in `bootstrap/app.php` (`->withCommands([...])`), and the
`abilities.act.*` texts — and the `ui5:sync` that follows.

## Which base

| Base | Who acts | Asks | `#[Act]` |
|:--|:--|:--|:--|
| `AbstractPersonCommand` | the person who signs in (e-mail, password; three attempts) | yes — refuses `--no-interaction` | on the declaration, checked against them |
| `AbstractAutomationCommand` | the automation actor | never | **not allowed** — the registry refuses it at load |

Pick by the question *could a scheduler run this?* — then it is automation. A correction of a value
someone will be asked about is a person command (`ui5:sync` and imports are automation).

## The command

```php
final class LockPeriodCommand extends AbstractPersonCommand
{
    protected $signature = 'billing:lock-period {period}';

    public function handleCommand(WorkUnitRunnerInterface $runner, SdkContext $sdk, LockPeriodUnit $lock): int
    {
        $runner->run($lock, new BusinessContext(validated: ['period' => $this->argument('period')]), $sdk);

        return self::SUCCESS;
    }
}
```

- Implement **`handleCommand(...)`**, never `handle()` — the base's `handle()` signs in, checks, opens
  the run and binds `SdkContext`. Dependencies arrive as its parameters, not through the constructor.
- **Every command takes `--language`** (default: the app locale); the run and its messages speak it.
- The head records the signature, the arguments and the options given — redacted by `ui5.audit.redact`
  — and, for a person, `cli; user@host`.
- Four hundred records are four hundred `run()` calls: one failing does not undo the others. If they
  must be all-or-nothing, that is **one** unit of work.

## The declaration

```php
#[Act('lockPeriod', SdkRole::TenantAdmin, note: 'Lock a booking period')]
final class LockPeriodCommandDeclaration extends AbstractCommand
{
    public const string NAMESPACE = 'com.acme.billing.commands.lock_period';
    public const string VERSION = '1.0.0';
    public const string TITLE = 'Lock a period';
    public const string DESCRIPTION = 'Closes a booking period for postings.';
    public const string COMMAND = LockPeriodCommand::class;
}
```

One `commands` segment in the namespace. A command no module declares is refused when it runs. The
`#[Act]` needs `abilities.act.<name>.title` / `.description` in the module's bundle, en and de.

## Never

- Never write in `handleCommand()` itself — only inside a unit of work through the runner.
- Never `DB::table(...)` on an audited table, never `saveQuietly()` / `withoutEvents()` (audit S4).
- Never nest: a unit of work starts no other one (A1). A step under other authority is a job.
- Never `#[Act]` on an automation command; never a person command in a scheduler.
@endif
