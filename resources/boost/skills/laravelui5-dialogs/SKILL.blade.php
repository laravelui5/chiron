---
name: laravelui5-dialogs
description: >-
  Author a LaravelUi5 global dialog (a `Dialog` artifact). Use this whenever adding a modal
  create/edit interaction to a LaravelUi5 app — a dialog is a first-class, shell-agitated
  artifact (`ArtifactType::Dialog`): a `sap.m.Dialog`-rooted View/Controller pair, opened
  **through the shell by intent** (`ui5-artifact.open`), authorized server-side against its own
  `#[Access]`, and hosted by the LeanShell. Covers the SDK-owned `AbstractUi5Dialog` base, the
  view/controller shape (single `sap.m.Dialog` root + `initDialog` hook), registration in
  `getDialogs()`, the `#[Access]` gate, and the `LaravelUi5.dispatchIntent` open path. The write
  behind Save is a Ui5Action — see the `laravelui5-actions` skill. Triggers: "add a dialog",
  "global dialog", "create/edit dialog", "modal", "ui5-artifact.open", "openGlobalDialog",
  "Dialog artifact", "how do I open a dialog in a UI5 app".
license: MIT
metadata:
  author: laravelui5
---

@if (! class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
# Global dialogs are an SDK capability

A LaravelUi5 dialog is a **shell-dispatched artifact**: opened by intent through LeanShell,
authorized server-side against its own `#[Access]`, and hosted by the shell. All three of those —
the shell, intents and the ability system — are `laravelui5/sdk`. Core's module interface reserves
the seat (`getDialogs()`), but the base class `AbstractUi5Dialog` lives in the SDK.

**In a Core-only host, use an ordinary `sap.m.Dialog` inside your own app**: instantiate it in the
controller, open it yourself, and authorize the write behind it in the action's `FormRequest`
(see the `laravelui5-actions` skill). You lose the shell hosting and the server-side gate, and you
keep everything else.

Global dialogs: **laravelui5.com/sdk/shell/global-dialogs**.
@else

# Authoring a LaravelUi5 global Dialog

## The rule that frames everything

**A dialog is a shell-agitated artifact, opened by intent — never a direct client call.** A
`Dialog` (`ArtifactType::Dialog`) is a `sap.m.Dialog`-rooted View/Controller pair, owned by an
app, opened through the shell: the app dispatches `ui5-artifact.open`, the server authorizes it
against the dialog's own `#[Access]`, and the LeanShell's `DialogAdapter` opens it (in the
owning app, or by booting a foreign Component if that app isn't on screen). Do **not** call
`component.openGlobalDialog(...)` directly from an app — that bypasses the server gate and the
shell's addressability. `openGlobalDialog` is the LeanShell's *internal* mechanism.

**Where the pieces live (artifact-ownership rule).** The enum case and the `Ui5DialogInterface`
live in **Core** (the contract is Core-expressible — `getViewName(): string`, pure primitives);
the `AbstractUi5Dialog` base and the open runtime live in the **SDK**. You author against the
SDK base. See `sdk-host/ui5/Sdk/doc/artifact-ownership.md`.

**Requires:** Core `≥ 1.2.0` and `@laravelui5/core ≥ 5.2.0` (the client `dispatchIntent` facade +
the corrected `BaseComponent` type), and SDK `≥ 0.17.15` (the intent + the own-app model fix).

## No generator — author from the template

There is **no `ui5:dialog` command.** Hand-author the two-file PHP artifact and the two-file
frontend view/controller from the shapes below. (`ui5:action` exists for the *write*; the
dialog itself is authored.)

## Part 1 — the PHP artifact (SDK side)

Extend `LaravelUi5\Sdk\Ui5\AbstractUi5Dialog` — five constants, no methods; `getViewName()`
returns `static::VIEW`. Gate it with `#[Access]`.

```php
use LaravelUi5\Sdk\Security\Attributes\Access;
use LaravelUi5\Sdk\Settings\Enums\SdkRole;
use LaravelUi5\Sdk\Ui5\AbstractUi5Dialog;

#[Access(ability: 'createCompanyDialog', role: SdkRole::LocalAdmin, note: 'Create a company partner')]
class CreateCompanyDialog extends AbstractUi5Dialog
{
    public const NAMESPACE = 'com.acme.partners.dialogs.create_company';
    public const VERSION = '1.0.0';
    public const TITLE = 'Create Company';
    public const DESCRIPTION = 'Create a new company partner.';
    public const VIEW = 'com.acme.partners.view.dialogs.CreateCompany'; // dotted; the framework slashes it
}
```

Register it in the owning module's `getDialogs()`:

```php
public function getDialogs(): array
{
    return [ new Dialogs\CreateCompanyDialog($this), new Dialogs\CreatePersonDialog($this) ];
}
```

## Part 2 — the view + controller (frontend)

**The view's single root control MUST be `sap.m.Dialog`** (the opener enforces it). Use a
`sap.ui.layout.form.Form` + `ColumnLayout` for the body — never `SimpleForm`. Buttons call
controller handlers.

```xml
<mvc:View controllerName="com.acme.partners.controller.dialogs.CreateCompany"
    xmlns="sap.m" xmlns:mvc="sap.ui.core.mvc" xmlns:form="sap.ui.layout.form">
    <Dialog title="{i18n>dialog.createCompany.title}" contentWidth="32rem">
        <content>
            <form:Form editable="true">
                <form:layout><form:ColumnLayout columnsXL="1" columnsL="1" columnsM="1"/></form:layout>
                <form:formContainers><form:FormContainer><form:formElements>
                    <form:FormElement label="{i18n>dialog.company.name}">
                        <form:fields><Input value="{create>/name}" required="true"/></form:fields>
                    </form:FormElement>
                    <!-- … -->
                </form:formElements></form:FormContainer></form:formContainers>
            </form:Form>
        </content>
        <beginButton><Button text="{i18n>dialog.save}" type="Emphasized" press=".onSave"/></beginButton>
        <endButton><Button text="{i18n>dialog.cancel}" press=".onCancel"/></endButton>
    </Dialog>
</mvc:View>
```

The controller: a plain `Controller` (a shared base for a family of create dialogs works well).
The **`initDialog(dialog)` hook** is called once on afterOpen — store the dialog to `close()` it.
The `create` JSONModel holds the form; Save fires the create **Ui5Action** (see below).

```ts
export default abstract class BaseCreateDialog extends Controller {
    protected dialog: Dialog;
    public onInit(): void { this.getView().setModel(new JSONModel(this.initialData()), "create"); }
    public initDialog(dialog: Dialog): void { this.dialog = dialog; }
    public onCancel(): void { this.dialog.close(); }
    public onSave(): void { /* LaravelUi5.call(createAction, {}, formData) → publish → close */ }
    protected abstract initialData(): Record<string, unknown>; // seed the discriminating fields
}
```

**The app's `Component` must extend `com.laravelui5.core.BaseComponent`** (not plain
`UIComponent`) — the shell calls `openGlobalDialog` on it.

## Part 3 — open it (by intent, from the app)

Dispatch `ui5-artifact.open` — the **generic "open an artifact" intent** — with the **dialog's
namespace** (not the view name). A dialog is just an artifact, so the same intent opens apps and
anything else; there is **no dialog-specific intent**. The machinery (the `ui5-artifact.open`
intent, its authorizer, its type-switching handler, `IntentResult::dialog`) is **already built in
the SDK — you never touch it**; you declare the dialog and open it by namespace, exactly as
`LaravelUi5.call(namespace, …)` identifies an action:

```ts
LaravelUi5.dispatchIntent({ name: "ui5-artifact.open", parameters: { namespace: "com.acme.partners.dialogs.create_company" } });
```

The round trip: `dispatchIntent` → the shell POSTs it → the server authorizes it against the
dialog's `#[Access]` → returns a `dialog` result `{app, dialogView}` → the `DialogAdapter` opens
it. Core-only (no shell) resolves to a no-op.

> You will almost never author an intent for a dialog — the generic open handles it. For the rare
> case of a genuinely *new* intent (a new verb, not a new noun), see the pattern in
> `docs/meta/atoms/CREATING_A_UI5_INTENT.md`.

## The write behind Save

Saving is a mutation, so it is a **Ui5Action**, not an OData write. Author it with the
**`laravelui5-actions` skill** (`ui5:action {App}/{Name} --method=POST`), gate it with `#[Act]`,
and call it from the dialog's `onSave` via `LaravelUi5.call(namespace, {}, formData)`. On success,
signal the list to refresh (component event bus) and close the dialog.

## Secure + activate

- `#[Access]` on the dialog is the **open gate** (checked before the dialog appears). `#[Act]` on
  the create Action is the **write gate**. Two layers.
- Both are **inert until `php artisan ui5:sync`** — the resolver reads the synced DB, not live
  attributes. Before sync, the gate is Open. Run `ui5:sync` (+ `ui5:cache` in prod) after adding.

## Gotchas (learned the hard way)

- **View root must be exactly one `sap.m.Dialog`** — the opener throws otherwise. (Its docblock
  says `SelectDialog`; the code wants `Dialog`. Trust the code.)
- **`Component` not on `BaseComponent`** → `DialogAdapter` throws "does not extend
  com.laravelui5.core.BaseComponent". Extend it; keep your `init()` + `super.init()`.
- **Blank labels?** The dialog view inherits the app's models (i18n, …) automatically as of SDK
  `0.17.15` (the `DialogAdapter` re-parents the dialog under the app root). If labels are empty,
  you're on an older shell — `ui5:refresh leanshell` + hard-reload.
- **The `dialog` param is the NAMESPACE**, not the view name; the handler resolves the view.
- **Don't call `openGlobalDialog` from the app.** Go through `dispatchIntent` — that's the gate.

## Verify

- `php artisan ui5:sync --dry` → the dialog artifact **and** its `#[Access]` ability appear
  (`+ artifact […dialogs.create_company]`, `+ ability […|createCompanyDialog]`).
- Browser: `dispatchIntent` opens the dialog with its labels; a user lacking the ability is denied
  (the dialog never appears).

## Worked example

`sdk-host/ui5/Partners/src/Dialogs/` — `CreateCompanyDialog` + `CreatePersonDialog` (`#[Access]`-gated),
registered in `PartnersModule::getDialogs()`; the views/controllers in
`ui5-partners/webapp/view/dialogs/` + `controller/dialogs/` (shared `BaseCreateDialog`); the Master's
`Create ▾` menu dispatches `ui5-artifact.open`; Save fires `CreatePartnerAction`. Running notes:
`sdk-host/ui5/Sdk/doc/dialogs.md`.

## Checklist

- [ ] PHP artifact extends `AbstractUi5Dialog`; five consts incl. `VIEW`; registered in `getDialogs()`.
- [ ] `#[Access(ability, SdkRole::…, note)]` on the dialog; `ui5:sync` to activate.
- [ ] View root is a single `sap.m.Dialog`; body is `Form` + `ColumnLayout` (not `SimpleForm`).
- [ ] Controller has `initDialog(dialog)` + `onCancel`/`onSave`; app `Component` extends `BaseComponent`.
- [ ] Opened via `LaravelUi5.dispatchIntent("ui5-artifact.open", { namespace: <dialog namespace> })` — never `openGlobalDialog`.
- [ ] Save is a `Ui5Action` (`laravelui5-actions` skill), `#[Act]`-gated, called with `LaravelUi5.call`.
- [ ] Stack: Core ≥ 1.2.0, `@laravelui5/core` ≥ 5.2.0, SDK ≥ 0.17.15.
@endif
