# Chiron

**AI agent guidelines and skills for the LaravelUi5 stack.**

```bash
composer require laravelui5/chiron --dev
php artisan boost:install
```

When the installer asks **"Which third-party AI guidelines/skills would you like to install?"**,
tick **`laravelui5/chiron`**. Third-party material is opt-in — Boost will not take it without being
told, and a scripted `boost:install --no-interaction` silently installs nothing from any third-party
package until the choice is recorded in `boost.json`.

Your coding agent now knows how to write `laravelui5/*` — instead of guessing at it.

---

## What this is

[Laravel Boost](https://laravel.com/framework/docs/13.x/boost) lets a package teach the agent that
works on its users' code. Chiron is the LaravelUi5 stack's half of that: one package carrying the
**guidelines** (loaded upfront, every session) and the **Agent Skills** (loaded on demand) for
`laravelui5/odata`, `laravelui5/core` and `laravelui5/sdk`.

It is named after the centaur who taught Achilles, Jason and Asclepius — the one who never fought a
battle himself, and trained everyone who did.

## What it contains — and nothing else

**No PHP.** No service provider, no autoloading, not a single class. Boost reads `resources/boost/`
straight out of the vendor directory, so this package is prose and nothing but prose. It cannot
break, because it does not run.

```
resources/boost/
├── guidelines/core.blade.php          always loaded; sections render only for what you installed
└── skills/
    ├── laravelui5-odata-modeling/     modelling a read surface: the decision tree, the perf gate
    ├── laravelui5-actions/            authoring a Ui5Action — every write in the stack is one
    └── laravelui5-dialogs/            global dialogs as shell-dispatched artifacts
```

For the same reason it declares **no `require`**. Nothing here is executed, so nothing here has a
platform requirement — and a `php` constraint would only stop someone from installing the package
before they install the stack, which is one of the moments it is most useful.

## One package for three, on purpose

Boost loads a package's material only when that package is a **direct** dependency of the
application — `laravel/roster` marks `direct` from the app's own `composer.json`. `laravelui5/core`
requires `laravelui5/odata`, so a host that follows the install guide has OData as a *transitive*
dependency, and Boost would never load its guidelines. Shipping the material inside the runtime
packages therefore misses exactly the people who followed the instructions.

One package that the host requires directly fixes that by construction. It also decouples two things
that have no business sharing a release: **guidelines want to be rewritten the week you learn what an
agent confuses; runtime packages are on strict SemVer with a tag, a Satis rebuild and a smoke test.**

Sections are guarded, so you only get what applies to you:

```blade
@if (class_exists(\LaravelUi5\Core\CoreLibrary::class)) … @endif
```

With nothing installed, you still get the overview — which is the point when you are evaluating the
stack rather than running it.

## Keeping it current

```bash
php artisan boost:update            # refresh what is already published
php artisan boost:update --discover # also pick up newly installed packages
```

To have it happen on every `composer update`, add it to your application's scripts:

```json
{
    "scripts": {
        "post-update-cmd": ["@php artisan boost:update --ansi"]
    }
}
```

## Versioning

Chiron is versioned **independently** of the packages it describes — that independence is why it
exists. Each release states in its changelog which majors of `odata`, `core` and `sdk` it was written
against. Guidance that only applies to one major says so inline.

## Links

- Documentation — <https://laravelui5.com>
- OData engine — <https://laravelui5.com/odata/>
- Core — <https://laravelui5.com/core/>
- SDK — <https://laravelui5.com/sdk/>

## Licence

MIT. See [LICENSE](LICENSE).

The packages it describes are licensed separately: `laravelui5/odata` MIT, `laravelui5/core` under
the Business Source License 1.1 (production use granted), `laravelui5/sdk` commercially.
