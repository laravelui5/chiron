# The read gate — `#[Read]`

OData is read-only, so reading is the only thing to gate there. `#[Read]` decides whether an actor
may read a set **at all**. *Which rows* they then see is a separate question — see `scoping.md`.

## The warning first

**An app's `#[Access]` gates opening the app, not its OData endpoint.**

> An entity set without `#[Read]` can be read by **every signed-in partner**.

Sets are open unless gated. Gate every set that is not meant for everyone.

## Where it goes

On the class the entity set is **built from** — a custom entity set, or the Eloquent model behind a
`discoverModel` set:

```php
#[Read('readInvoices', AcmeRole::Accounting, note: 'Read the invoice list.')]
final class InvoicesSet extends AbstractEntitySet
{
    // …
}
```

Same shape as `#[Access]` and `#[Act]`, same rules: the role must already be declared on the module
with `#[Role]`, and ability names are unique per app namespace.

The harvest additionally records **which entity set the attribute's class backs**, resolved from the
app's `ResolverMap`. At request time the authorizer goes set → ability → grant.

## What a denied read actually does

| Request | Result |
|:---|:---|
| reading the gated set itself | **403**, an OData error with code `read_forbidden` |
| `$expand` into a gated set | **the expansion is dropped**, the rest answers **200**, and a `sap-messages` header says what was left out |
| a `$batch` | each item is checked on its own |

The `$expand` behaviour is the one to internalise: a partial answer with a message, not a failure.
A client that ignores `sap-messages` will show an incomplete object and no error — so if a related
collection is mysteriously empty, check the gate before checking the query.

The message reads *"You are not authorized to read …"* unless your translations define
`ui5.read_forbidden`.

## Five ways a read is let through anyway

Know these, because four of them look like the gate is broken:

1. **The set has no `#[Read]`.** Sets are open unless gated.
2. **The app gates no set at all.**
3. **The request has no SDK context.** The `odata_middleware` builds it with
   `BindSdkContextForOData`; without that middleware there is nobody to authorize.
4. **The ability is declared but not synced.** Until `ui5:sync` has run, a new `#[Read]` locks
   nobody out — and protects nothing.
5. The actor genuinely holds the grant, within its validity window.

## Read, then rows

| Layer | Decides | Where |
|:---|:---|:---|
| **Access** | may the actor open the app or value help | when it is opened |
| **Read** | may the actor read this set | at the OData boundary |
| **Scope** | which rows come back | inside the query — see `scoping.md` |

These are three different questions. Answering one does not answer the others, and a set that is
readable by the right role can still need row scoping before it is safe.
