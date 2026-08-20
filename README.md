# Invoicing — Symfony 5.4 exercise

A small Symfony application to create an invoice together with its invoice lines,
persisted with Doctrine ORM.

* PHP 8.1+ (developed and tested on PHP 8.5)
* Symfony 5.4 LTS
* Doctrine ORM 2.20 / DBAL 3
* MySQL 8

## What it does

| Route | Method | Purpose |
|---|---|---|
| `/invoices` | GET | List of invoices with their totals |
| `/invoices/new` | GET/POST | Create an invoice and its lines |
| `/invoices/{id}` | GET | Invoice detail |
| `/invoices/{id}/edit` | GET/POST | Edit header and lines, add/remove lines |
| `/invoices/{id}/delete` | POST | Delete an invoice (CSRF protected) |

## Layers

```
Controller ──> InvoiceManagerInterface ──> InvoiceRepositoryInterface ──> Doctrine
   (DTO)              (service)                   (entities)
                          └──> InvoiceMapperInterface (DTO <-> entity)
```

* **Controllers and templates only ever see DTOs** (`src/Dto/`). No entity, no
  repository and no entity manager reaches this layer, and the forms bind to
  `InvoiceDto` / `InvoiceLineDto` rather than to the Doctrine entities.
* **The service layer** (`src/Service/InvoiceManager.php`) owns the use cases and
  the flush boundary. It is the only place that handles entities.
* **The mapper** (`src/Mapper/InvoiceMapper.php`) is the single spot that knows
  both models. On update it matches lines by id: known ids are updated in place,
  lines without an id are inserted, and the lines the DTO no longer carries are
  detached so `orphanRemoval` deletes them.
* **The repository** (`src/Repository/InvoiceRepository.php`) is read-only data
  access; it neither persists nor flushes.
* Every collaborator is injected **through an interface**
  (`InvoiceManagerInterface`, `InvoiceRepositoryInterface`,
  `InvoiceMapperInterface`), bound to its implementation in
  `config/services.yaml`. Consumers depend on abstractions, so a different
  implementation is a one-line change and unit tests can substitute a double.
* Validation lives on the DTOs, the input model. The invoice number uniqueness
  is a custom class constraint (`src/Validator/UniqueInvoiceNumber.php`) —
  `UniqueEntity` cannot be used because the form binds a DTO, and the id carried
  by the DTO is what lets the check ignore the invoice being edited.
* `InvoiceNotFoundException` is a domain exception; an event subscriber turns it
  into a 404 so the service layer stays free of HTTP concerns.

The invoice lines are handled with a Symfony `CollectionType`
(`allow_add` / `allow_delete` / `by_reference: false`) plus a small vanilla-JS
script that clones the form prototype, so lines can be added and removed without
leaving the page. `Total with VAT` is filled in live in the browser and is always
recomputed server side, so the stored value can never drift from
`amount + vat_amount`.

## Data model

`invoice`

| Column | Type | Notes |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` | primary key |
| `invoice_date` | `DATE` | |
| `invoice_number` | `INT` | unique (`uniq_invoice_number`) |
| `customer_id` | `INT` | plain integer, as per the specification |

`invoice_line`

| Column | Type | Notes |
|---|---|---|
| `id` | `INT AUTO_INCREMENT` | primary key |
| `invoice_id` | `INT` | FK → `invoice.id`, `ON DELETE CASCADE` |
| `description` | `LONGTEXT` | Doctrine type `text` |
| `quantity` | `INT` | |
| `amount` | `DECIMAL(12,2)` | net amount of the line |
| `vat_amount` | `DECIMAL(12,2)` | |
| `total_with_vat` | `DECIMAL(12,2)` | stored, always `amount + vat_amount` |

On the PHP side the relation is a `OneToMany` with
`cascade: ['persist', 'remove']` and `orphanRemoval: true`, so saving the invoice
saves its lines and removing a line in the browser deletes the row on flush.
Decimal columns are mapped as strings and summed with `bcmath`, to avoid float
rounding errors on money.

**Assumption:** the specification lists `Quantity` and `Amount` as independent
fields, so `amount` is treated as the net amount of the whole line (not a unit
price multiplied by the quantity). `vat_amount` is entered by the user, which
keeps mixed VAT rates on the same invoice possible.

## Setup

```bash
composer install

# a MySQL 8 server is expected on 127.0.0.1:3306; if you do not have one:
docker compose up -d

# create the database and the schema
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# ...or load the provided dump instead of running the migration
mysql -u root -e "CREATE DATABASE IF NOT EXISTS invoicing DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root invoicing < sql/invoicing.sql

# development server
php -S 127.0.0.1:8000 -t public router.php
```

Then open <http://127.0.0.1:8000/invoices>.

`router.php` is only there for PHP's built-in web server: it lets the server
deliver the files under `public/` with their real MIME type and sends everything
else to the Symfony front controller. Apache, nginx and `symfony server:start`
do not use it.

The connection string lives in `.env` (`DATABASE_URL`) and points at
`root@127.0.0.1:3306/invoicing`; override it locally in an untracked `.env.local`
rather than editing `.env`.

## Tests

```bash
# once: the test database (invoicing_test) and its schema
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction

vendor/bin/phpunit
```

78 tests / 211 assertions, all green:

| Suite | Covers |
|---|---|
| `tests/Unit/Entity` | totals and both sides of the association, without a database |
| `tests/Unit/Dto` | DTO totals and decimal arithmetic |
| `tests/Unit/Mapper` | entity <-> DTO, and the insert/update/detach logic on lines |
| `tests/Unit/Service` | the use cases, with test doubles of the three interfaces |
| `tests/Unit/Validator` | the constraints on the DTOs through a real validator |
| `tests/Functional` | the service against the real database, and the whole HTTP flow (create, validation errors, edit, delete, CSRF, 404) |

## sql/

* `sql/invoicing.sql` — full dump: structure + three sample invoices with six lines.
* `sql/schema.sql` — structure only.

Both were produced with `mysqldump` from the database created by the migration in
`migrations/`.