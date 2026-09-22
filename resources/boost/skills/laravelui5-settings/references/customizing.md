# Customizing — code-owned reference catalogs

The controlled vocabularies an app switches on: partner roles, relationship types, document types,
tax codes, dropdown values. Declared **in code**, projected into a table by `ui5:sync`. Declare them
once and every install and every new tenant has them — nothing to remember, nothing to seed by hand.

## Customizing vs Tailoring — they never share a table

| | **Customizing** | **Tailoring** |
|:---|:---|:---|
| What | the **vocabulary** — the list of role codes, relationship types | the **values** — who holds which role |
| Owner | **your code** (an attribute) | the running system, or a consultant |
| Travels by | the **deployment** (`ui5:sync`) | runtime writes |
| Example table | `sdk_partner_roles` | `sdk_partner_role_assignments` |

The system *offers* the vocabulary; tailoring *uses* it. Confusing the two puts consultant-owned
data in a table `ui5:sync` will delete from.

## Declaring a catalog

An entry is a PHP attribute fulfilling `CustomizingEntry`. The contract splits the **catalog shape**
(the `static` methods — the same for every row) from the **row** (the instance):

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class DocumentType implements CustomizingEntry
{
    public function __construct(
        public BackedEnum|string $code,
        public string            $name,
        public ?string           $description = null,
        public ?string           $helpUuid = null,
    ) {}

    public static function catalog(): string        { return 'document_types'; }
    public static function table(): string          { return 'app_document_types'; }
    public static function identityColumn(): string { return 'code'; }
    public static function translatable(): array    { return ['name', 'description']; }

    public function identity(): string { /* the stable key */ }
    public function columns(): array   { /* the scalar columns */ }
}
```

Then stack the attribute on the class that owns the vocabulary — it is repeatable, one instance per
row.

## How it syncs

`ui5:sync` reconciles each catalog table to **exactly** what the code declares:

- **inserts** newly declared rows,
- **updates** rows whose columns changed,
- **deletes** rows that are no longer declared.

It is **idempotent** — run it as often as you like; it converges and never duplicates.
`ui5:sync --dry` previews the plan and changes nothing, which is a cross-environment drift diff no
seeder could give you.

```bash
php artisan migrate     # the table
php artisan ui5:sync    # fill and maintain the rows
```

**Because the worker deletes undeclared rows, the catalog is exclusively code-owned.** Never write
to one of these tables at runtime; the next sync removes it.

## Rules of the road

- **Flat catalogs only.** One table, one identity column, scalar columns. A catalog that needs a
  join or a pivot is not a fit for this engine.
- **Catalogs are independent.** No catalog references another, so sync order does not matter.
- **The table is yours; the rows are the SDK's.** Ship the migration that creates it, with the
  identity column unique. `ui5:sync` owns the contents thereafter.
- **`ui5:sync` is mandatory.** A catalog with no sync run is an empty table.

## Do not

- **Do not seed a customizing table.** Declare the rows; the sync is the seeder.
- **Do not write to one at runtime.** Undeclared rows are deleted on the next sync.
- **Do not put assignments in a catalog.** Those are tailoring and belong in their own table.
- **Do not model a relationship as a catalog.** If it needs a join, it is not a catalog.
