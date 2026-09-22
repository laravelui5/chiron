---
name: laravelui5-shell
description: >-
  Work with LeanShell — the SDK's chrome around a UI5 app: the navigation rail, the CmdK command
  palette, the help viewer and the acting identity. Use this when an app should run inside the
  shell, when the chrome does not appear, when adding a value-help picker or an intent, or when
  asking how navigation and the launchpad decide what to show. Covers the five things that must be
  true for an app to get chrome, intent dispatch and why the client holds no URLs, value helps and
  their server-resolved scopes, global search over artifacts, navigation contributors, and the
  launchpad's never-gated invariant. Triggers: "shell", "LeanShell", "no chrome", "navigation
  rail", "CmdK", "command palette", "value help", "picker", "dispatchIntent", "openValueHelp",
  "launchpad", "AbstractSdkManifest", "includeIfSdk", "F1 help".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Core serves your app on a bare page

There is no shell in `laravelui5/core`. Core routes the app, assembles its manifest and serves it —
and that page has no navigation rail, no command palette, no help viewer and no identity, because
Core knows nothing about actors or roles.

`LaravelUi5.init(this)` is still required — it arms the facade that gives your app its OData base URL
and CSRF token. It simply has no chrome to start.

**LeanShell is `laravelui5/sdk`** (laravelui5.com/sdk/shell/): navigation, `Cmd+K` search, `F1` help,
intents, value helps and the Launchpad.
@else
# LeanShell

Core serves your app on a bare page; the SDK puts chrome around it. **LeanShell is not a UI5
component** — it is a small web-component bundle (`main.esm.js`) that the host page loads beside your
app and mounts into one `<div>`. Your app stays an ordinary UI5 app.

| Keys | Opens |
|:---|:---|
| `Cmd`/`Ctrl` + `B` | the navigation rail |
| `Cmd`/`Ctrl` + `K` | the command palette |
| `F1` | help for whatever has focus |

## Five things must be true, or there is no chrome

**1 — the app's manifest class extends `AbstractSdkManifest`.** This is *the* opt-in, and it is
deliberate: an app extending Core's `AbstractManifest` gets no shell.

**2 — the component calls `LaravelUi5.init(this)`.** Note this is **Core's** contract, not the
shell's — every LaravelUi5 app needs it to arm the facade (base URL, CSRF, settings, routes). The
shell does not start itself; it replaces `LaravelUi5.init` and starts when your app calls it. **An
app that skips `init()` gets no chrome at all, and its `LaravelUi5.call()` throws.** See the
`laravelui5-modules` skill for what the facade does on each host.

**3 — the host renders the two seats.** Core's page calls `@verbatim@includeIf('ui5.head')@endverbatim`
and `@verbatim@includeIf('ui5.foot')@endverbatim`; the host's views fill them with
`@verbatim@includeIfSdk('ui5::head')@endverbatim` and `@verbatim@includeIfSdk('ui5::foot')@endverbatim`.
That directive renders **only** when the current artifact is an app whose manifest opted in — which
is why a Core-only app on the same host still gets a bare page.

**4 — the assets are published.** The bundle is served from the package at
`ui5/shell/{version}/main.esm.js`, so it moves with every SDK release by itself.
`php artisan ui5:publish` copies the icon fonts and the default avatar to `public/sdk/`.

**5 — `ShellContextArtifactResolver` is in `artifact_resolvers`** in `config/ui5.php`. Missing it does
not degrade a feature — **it makes the shell endpoints 404**, which looks like a broken shell rather
than a missing line of config. Check this first when the chrome misbehaves.

## Intents — the client knows a name, nothing else

An intent is a named thing a user wants to happen: open this app, open that dialog, log out, follow a
link. **The client names it; the server decides whether the actor may have it, does the work, and
answers with what the shell should do next.**

```js
LaravelUi5.dispatchIntent({
    name: 'ui5-artifact.open',
    parameters: { namespace: 'com.acme.orders.dialogs.create' },
});
```

**That inversion is the point: the client holds no URL, no permission rule and no knowledge of what
the intent does.** One route answers them all — `POST /ui5/shell/{slug}/intend.json`. So never build
a navigation URL in the frontend; dispatch an intent.

## Value helps — one picker, many scopes

A value help is a picker that answers a question. The caller awaits a promise, the user picks, the
promise resolves.

```js
const selection = await LaravelUi5.openValueHelp({
    namespace: 'com.laravelui5.partners.valuehelp.partners',
    scope:     'colleagues',
    mode:      'single',
});
// null = cancelled · [] = confirmed with nothing · [{key, text, data?}] = a pick
```

The three-way split is the whole design:

| Who decides | What |
|:---|:---|
| the **picker** | the shape — which columns a row has, how it looks, how you search it |
| the **server** | which rows, for this actor, in this scope |
| the **caller** | which scope to open, and single or multiple |

**A scope is a named facet, and it resolves server-side to an OData entity set.** The client never
sends a filter, and **there is no unscoped picker**. That is what makes a picker safe to share: an
app with no business seeing every row can still open it, because the scope it may open decides what
it gets.

## Global search searches artifacts, not data

`Cmd+K` searches the **apps, dashboards, reports and dialogs** the installation has registered — and
every row it offers is something this actor is allowed to open. It is not a text search over your
business data.

## Navigation is composed, then projected

The rail is assembled from **contributors**, each filling one slot of a fixed shape —
`{ branding, body, identity, footer }` — into the `navigation` key of `context.json`. The shell then
**projects it against the actor's abilities before the frontend renders it**. So you do not filter
navigation yourself; declare the entry and let the projection remove it.

The drawer carries the **host's** branding, not the vendor's.

## The Launchpad is never gated — and that is an invariant

Every user who signs in arrives at the LUX Launchpad: one tile per app they may open, and nothing
they may not.

> **It carries no `#[Access]`, and it never will.** That is a guaranteed invariant, not an oversight.

Anyone signed in can reach it, which is what makes it safe to land on — after a login, after a
password reset, and above all after an **impersonation** switch, where the acting partner may have
access to nothing else. Gating it later would be a breaking change.

## Related

Modal create/edit interactions are **global dialogs** — same artifact idea, same shell hosting,
without the return channel. Use the **`laravelui5-dialogs`** skill.

## Do not

- **Do not build a navigation URL in the frontend.** Dispatch an intent.
- **Do not filter navigation entries yourself.** The projection against abilities does it.
- **Do not open a value help without a scope**, or send a filter from the client.
- **Do not put an `#[Access]` on the Launchpad.**
- **Do not expect chrome from an app extending Core's `AbstractManifest`**, or from one that never
  calls `LaravelUi5.init()`.
@endif
