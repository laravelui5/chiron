---
name: laravelui5-actions
description: >-
  Author a LaravelUi5 mutating action (Ui5Action). Use this whenever adding or changing a
  create/update/delete in a LaravelUi5 app — in LaravelUi5 OData is READ-ONLY, so every mutation is
  a Ui5Action, scaffolded with `php artisan ui5:action`. Covers the Action + Handler + FormRequest
  triad, `#[Parameter]` route identifiers, module registration, authorization, and the
  `LaravelUi5.call` frontend wiring. **The handler contract differs by installation** — this skill
  renders the one that applies here, so follow it literally rather than a pattern remembered from
  another project. Triggers: "add an action", "mutating endpoint", "ui5:action", "action handler",
  "Ui5Action", "create/update/delete in UI5", "POST to the backend", "why can I not write through
  OData", "form submit", "ActionHandlerInterface", "SdkActionHandlerInterface".
license: MIT
metadata:
  author: laravelui5
---

# Authoring a LaravelUi5 Action (Ui5Action)

## The rule that frames everything

**OData is read-only in LaravelUi5. Every mutation is a Ui5Action.** Reads go through OData
entity sets; creates/updates/deletes go through a named, versioned, authz-gated Ui5Action
(`ArtifactType::Action`, `api/` route prefix, `POST`/`PATCH`/`DELETE` only — never `GET`/`PUT`).
Do not reach for OData entity-set writes; they are not part of the framework.

## Always scaffold — never hand-create

```bash
php artisan ui5:action {App}/{Name} --method=POST|PATCH|DELETE
```

e.g. `php artisan ui5:action Partners/CreateGroup --method=POST`. This generates two files
under `ui5/{App}/src/Actions/`:

- `{Name}Action.php`   — the artifact (identity + method + handler + request wiring)
- `Handler/{Name}Handler.php` — the state-changing logic (the **typed** handler, below)

The generator does **not** register the action, wire a FormRequest, or authorize it — those are
yours, and how you authorize depends on what is installed (below). The UI5 namespace is derived as `{module.namespace}.actions.{snake_name}`
(e.g. `com.laravelui5.partners.actions.create_group`).

@if (class_exists(\LaravelUi5\Sdk\SdkServiceProvider::class))
## The typed contract (the current pattern the scaffolder emits)

A handler seals **exactly one outcome** on a `BusinessTransaction` and returns it. The
dispatcher (`SdkActionDispatchController`, rebound over Core's `ActionDispatchController`)
wraps the call in a DB transaction: a `commit` persists, a `rollback` undoes.
**A handler that returns without sealing is rolled back**, and so is one whose return value is not
the transaction's own response — the dispatcher checks `isSealed()` and identity before committing.
Forgetting to seal therefore loses the work silently rather than half-persisting it. The three
arguments read as a sentence — *what I seal · what I was given · who I am → the outcome.*

### 1. Action — `extends AbstractUi5Action`

`getMethod()` returns an `HttpMethod` (POST/PATCH/DELETE only); `getHandler()` returns the
handler **class-string** (the dispatcher `container->make()`s it, so its constructor deps
autowire); `getRequest()` returns the **FormRequest class-string** that validates the body.

```php
#[Act('createGroup', SdkRole::TenantAdmin, note: 'Create a security group definition.')]
class CreateGroupAction extends AbstractUi5Action
{
    public const NAMESPACE = 'com.laravelui5.partners.actions.create_group';
    public const VERSION = '1.0.0';
    public const TITLE = 'Create Group';
    public const DESCRIPTION = 'Create a security group definition.';

    public function getMethod(): HttpMethod { return HttpMethod::POST; }
    public function getHandler(): string { return CreateGroupHandler::class; }
    public function getRequest(): ?string { return CreateGroupRequest::class; }
}
```

> **`getRequest()` is the authorization gate.** Resolving the FormRequest fires `authorize()`
> (→ **403**) and validation (→ the **422** `{status, message, errors}` envelope) *before* the
> DB transaction opens. **A `null` request SKIPS the gate** — so any `#[Act]`-secured action
> **must** declare a FormRequest, even a bodyless `DELETE` (give it empty `rules()`).

### 2. Handler — `implements SdkActionHandlerInterface`

Fixed signature: `handle(BusinessTransaction $transaction, BusinessContextInterface $context,
SdkContext $sdk): ActionResponse`. Per-request inputs come off `$context`; who-is-acting off
`$sdk`; **services/repositories go in the constructor** (the handler is container-resolved).

```php
#[Parameter(name: 'group', uriKey: 'group', type: ParameterType::Model, model: Group::class)]
class UpdateGroupHandler implements SdkActionHandlerInterface
{
    public function handle(
        BusinessTransaction $transaction,
        BusinessContextInterface $context,
        SdkContext $sdk,
    ): ActionResponse {
        /** @var Group $group */
        $group = $context->parameter('group');       // a resolved #[Parameter] route identifier
        $data  = $context->validated();              // the FormRequest's validated() body

        $group->update(['description' => $data['description']]);

        $transaction->touched('/Groups');            // client refreshes this OData path after commit
        return $transaction->commit('Group updated.');
    }
}
```

**What you work with:**
- `$context->validated()` — the validated body (populated from the action's `getRequest()`).
- `$context->parameter('name')` — a resolved `#[Parameter]` route identifier (a model).
- `$sdk->actor()` / `$sdk->abilities()` / `$sdk->setting('key')` — the acting partner + grants.

**Sealing the outcome (do exactly one):**
- `$transaction->commit($primaryMessage, $data = [])` — success. `$primaryMessage` lands in the
  response `message` slot (a success toast on the client); `$data` is the payload (e.g. a new key).
- `$transaction->rollback(...)` — a **business** failure: undoes the transaction and returns the
  typed error envelope.
- `$transaction->touched('/EntitySet')` — mark OData paths the client should refresh post-commit.
- `$transaction->info(new MessageText('messages.<...>'))` — attach a business message (its text is
  an **i18n key**, resolved client-side under the `messages.*` convention).

Returning without sealing — or returning a different response — is a `SealViolationException`.
A thrown exception rolls the transaction back and serializes a technical failure.

### 3. FormRequest — the payload + authorization contract

`extends AbstractSdkFormRequest` (inherits the `#[Act]`-based `authorize()` and the 422 JSON
envelope). Define `rules()`; the handler reads `$context->validated()`. **Identifiers are NOT
body fields** — they are `#[Parameter]` route segments.

```php
class CreateGroupRequest extends AbstractSdkFormRequest
{
    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:255', 'unique:sdk_groups,code'],
            'description' => ['required', 'string', 'max:2048'],
        ];
    }
}
```

## `#[Parameter]` — route identifiers, **Model-only**, read off `$context`

Place `#[Parameter(name, uriKey, type: ParameterType::Model, model: X::class)]` on the
**Handler** class. They map positionally (declaration order) to the `/{uriKey}` path segments
the manifest builds, and are read in the handler via `$context->parameter('name')` — **not** as
`handle()` arguments. **Hard constraint:** a `#[Parameter]` must be `ParameterType::Model` (an
Eloquent model, route-model-bound). Everything scalar (ids, flags, values) goes in the body.

## Register the action

```php
public function getActions(): array
{
    return [
        new Actions\CreateGroupAction($this),
        new Actions\UpdateGroupAction($this),
        // …
    ];
}
```

## Secure the action with `#[Act]`

Security is opt-in: **an ungated action is an open door.** Declare an `#[Act]` on the **Action
class**; `AbstractSdkFormRequest::authorize()` enforces it when `getRequest()` resolves (which is
why a gated action must declare a FormRequest).

```php
#[Act('createGroup', SdkRole::TenantAdmin, note: 'Create a security group definition.')]
class CreateGroupAction extends AbstractUi5Action { /* … */ }
```

- **Ability name:** camelCase, short (`createGroup`) — the artifact already scopes it.
- **`role`:** the `SdkRole` the ability belongs to (`LocalAdmin`, `TenantAdmin`, …).
- **Activation is via sync.** The gate is **inert until `php artisan ui5:sync`** — the resolver
  reads the *synced DB*, not live attributes. Before sync → Open-Gate (open to everyone). After
  sync, the acting partner must hold the ability (via its role) or 403s.
- **i18n labels (required):** `abilities.act.<ability>.title` / `.description` in the app bundle.
  Renaming an ability is a **breaking security change**.

## Runtime + frontend wiring

- **Route:** `POST|PATCH|DELETE  ui5/api/{namespace}@{version}/{route-ids}` → the SDK dispatcher.
  The action is identified by its **namespace**; `{route-ids}` are only the `#[Parameter]`
  path segments.
- **Manifest:** each action is injected into the served `manifest.json` under
  `/laravel.ui5/actions` as `{namespace: {method, url}}`. Nothing to hand-edit.
- **Frontend call** (`@laravelui5/core` facade):

  ```js
  LaravelUi5.call(
    "com.laravelui5.partners.actions.update_group", // = action NAMESPACE
    { group: id },                                  // fills {uriKey} placeholders
    { description }                                 // JSON body → the FormRequest
  );
  ```

  `params` fill the `{placeholder}` tokens; `body` is the JSON payload. `DELETE` carries a body
  too. The resolved response carries the commit `message` (toast), the `touched` refresh hints,
  and any business `messages`. A call to a name not in the manifest throws on the frontend.

## Verify

- `php artisan ui5:sync --dry` → the action shows in `Artifacts +N`, and its `#[Act]` in
  `Abilities +N` (registry boots and reflects it).
- `app({Name}Handler::class)` resolves → constructor DI is wired.
- A gated action: an un-granted actor 403s; a granted actor commits (mirror
  `sdk-host/tests/Feature/GroupsCrudAuthzTest.php` / `PartnersUpdateTest.php`).

## Worked examples

`sdk-host/ui5/Partners/src/Actions/` —
- **`SaveProfile`** (PATCH upsert) — the first typed handler: `{partner}` `#[Parameter]`,
  body `id`+fields, `touched('/Partners')`, `commit('Profile added.', ['id' => …])`.
- **Group CRUD** (`CreateGroup` / `UpdateGroup` / `DeleteGroup` / `AddGroupRole` /
  `RemoveGroupRole`) — a full `SdkRole::TenantAdmin`-gated set; `AddGroupRole` shows a `{group}`
  route id + a `role_id` body field.

## Legacy pattern (still supported — you'll see it in older handlers)

Pre-typed actions implement the empty marker `ActionHandlerInterface` with
`handle(): array`, resolving the FormRequest + `#[Parameter]`s **as `handle()` arguments** via
`Core\Runtime\ExecutableInvoker`, and returning a status array. The dispatcher's "family branch"
still routes these. **Don't write new ones** — use the typed contract above — but recognize the
shape (e.g. `CreateDepartmentHandler`, `DeleteProfileHandler`) when editing existing code.

## Checklist

- [ ] Scaffolded with `ui5:action` (not hand-created); method is POST/PATCH/DELETE.
- [ ] Handler `implements SdkActionHandlerInterface`; `handle(BusinessTransaction, BusinessContext, SdkContext): ActionResponse`; services in the constructor.
- [ ] Seals exactly one outcome — `commit(...)` / `rollback(...)`; `touched('/…')` for refreshes.
- [ ] Inputs read off `$context->validated()` / `$context->parameter('…')`; actor off `$sdk`.
- [ ] Action declares `getRequest()` (required for the `#[Act]` gate to fire — even bodyless DELETEs).
- [ ] Route identifiers are `#[Parameter]` **Model** params; scalars are in the body.
- [ ] Registered in the module's `getActions()`.
- [ ] Secured with `#[Act(ability, SdkRole::…, note)]`; ability i18n keys provided; run `ui5:sync` to activate.
- [ ] Frontend uses `LaravelUi5.call(namespace, params, body)`.
@else
## The contract — the marker handler

@verbatim
Core's `ActionHandlerInterface` is an **empty marker**. There is no typed method, on purpose: the
arguments of `handle()` are resolved by the container at invoke time, which a PHP interface cannot
express — the same reason Laravel's own `ShouldQueue` never types `handle()`. The contract is
documented and enforced at runtime by `Core\Runtime\ExecutableInvoker`, which throws
`MissingExecutableMethodException` if `handle()` is absent.
@endverbatim

### 1. Action — `extends AbstractUi5Action`

Three methods carry the wiring: `getMethod()` returns an `HttpMethod` (POST/PATCH/DELETE only —
never GET/PUT), `getHandler()` returns the handler **class-string**, and `getRequest()` returns the
**FormRequest class-string** that validates the body, or `null`.

@verbatim
<code-snippet name="The action artifact" lang="php">
use LaravelUi5\Core\Ui5\AbstractUi5Action;
use LaravelUi5\Core\Ui5\Enums\HttpMethod;

class ClearMailboxAction extends AbstractUi5Action
{
    public const string NAMESPACE = 'com.acme.invoicing.actions.clear_mailbox';
    public const string VERSION   = '1.0.0';

    public function getMethod(): HttpMethod { return HttpMethod::POST; }
    public function getHandler(): string    { return ClearMailboxHandler::class; }
    public function getRequest(): ?string    { return ClearMailboxRequest::class; }
}
</code-snippet>
@endverbatim

### 2. Handler — `implements ActionHandlerInterface`, `handle(): array`

**Where each dependency goes is the thing to get right**, because the invoker resolves *method*
parameters with the container's `has()` and will not autowire an unbound class there:

- **Services, repositories, gateways → the constructor.** The dispatcher does `app(Handler::class)`,
  so constructor dependencies autowire normally.
- **Per-request inputs → `handle()`'s parameters.** The FormRequest and any `#[Parameter]`-declared
  values exist only at invocation, exactly like route-model binding lands on a controller method.
  The invoker `make()`s a FormRequest parameter and calls `validateResolved()` on it, so by the
  time your body runs the input is validated.

@verbatim
<code-snippet name="The handler" lang="php">
use LaravelUi5\Core\Ui5\Capabilities\ActionHandlerInterface;

class ClearMailboxHandler implements ActionHandlerInterface
{
    public function __construct(private readonly MailboxRepository $mailboxes) {}

    public function handle(ClearMailboxRequest $request, Mailbox $mailbox): array
    {
        $this->mailboxes->clear($mailbox, $request->validated('before'));

        return ['status' => 'success', 'message' => 'Mailbox cleared'];
    }
}
</code-snippet>
@endverbatim

**Always return a structured array**, even when no payload is strictly needed — the dispatcher
answers with `response()->json($result)`, and the frontend reads that shape.

### 3. Atomicity is yours

**Core's dispatcher does not wrap the call in a database transaction.** It resolves the artifact,
resolves the handler, invokes `handle()` and serialises the result. If your action touches more than
one row and must be all-or-nothing, open the transaction yourself:

@verbatim
<code-snippet name="Atomic work in a Core action" lang="php">
return DB::transaction(fn (): array => $this->doTheWork($request));
</code-snippet>
@endverbatim

*(With the SDK installed this changes: its dispatcher wraps every action and the handler seals one
outcome on a `BusinessTransaction`. That contract is not available here.)*

## `#[Parameter]` — route identifiers

`#[Parameter]` declares a value the route carries into the action. Core's attribute set is
deliberately small — `Parameter` is the only one — and the declared values arrive as `handle()`
arguments through the invoker.

## Register the action

Two steps, and there is no third:

1. Return it from the module's `getActions()`.
2. Make sure the module class is listed in `config/ui5.php` under `modules`.

No route file, no service provider, no frontend config. The address is derived:
`api/{namespace-with-slashes}@{version}/`.

## Authorization

**Core is stateless about your organisation**, so it ships no ability attribute — no `#[Act]`, no
`#[Access]`. Those arrive with the SDK. Until then, an action is protected by:

- the `ui5` route middleware (`EnsureUi5Authenticated`), which is what stops an anonymous call, and
- **your own** Laravel authorization — a policy check or a gate, called inside `handle()` or in the
  FormRequest's `authorize()`.

Putting the check in the FormRequest's `authorize()` is usually the better place: it runs during
`validateResolved()`, before your handler body, and it fails with a 403 rather than halfway through
the work.

## Frontend wiring

@verbatim
<code-snippet name="Calling the action from UI5" lang="js">
const result = await LaravelUi5.call("com.acme.invoicing.actions.clear_mailbox", { before: "2026-01-01" });
</code-snippet>
@endverbatim

The action is invoked **by namespace, not by URL** — the frontend never builds the address.

## Checklist

- [ ] Scaffolded with `ui5:action` (not hand-created); method is POST/PATCH/DELETE.
- [ ] Handler `implements ActionHandlerInterface` and defines `handle(): array`.
- [ ] Services in the **constructor**; the FormRequest and `#[Parameter]` values as `handle()`
      **parameters**.
- [ ] Returns a JSON-serializable array, always — including on the no-payload path.
- [ ] Wrapped in `DB::transaction()` if more than one row changes.
- [ ] Authorized — in the FormRequest's `authorize()` or explicitly in the handler.
- [ ] Returned from the module's `getActions()`, and the module is in `config/ui5.php`.
@endif
