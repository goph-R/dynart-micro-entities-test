# dynart-micro-entities-test

Test suite for [dynart-micro-entities](../dynart-micro-entities), the ORM/entity library for the [dynart-micro](../dynart-micro) framework.

## Setup

This project is a **separate repository** that symlinks the library under test via a Composer path repository, following the same pattern as `dynart-micro-test`.

### Prerequisites

- PHP 8.0+
- Composer
- MariaDB / MySQL instance (for integration tests only)

### Install

```bash
composer install
```

Both `dynart/micro-entities` and `dynart/micro` are symlinked from sibling directories (`../dynart-micro-entities` and `../dynart-micro`), so changes to the library are reflected immediately without reinstalling.

## Running Tests

```bash
# Unit tests only (no database required)
php vendor/bin/phpunit --testsuite unit --stderr

# Integration tests only (requires MariaDB)
php vendor/bin/phpunit --testsuite integration --stderr

# All tests
php vendor/bin/phpunit --stderr
```

## Project Structure

```
dynart-micro-entities-test/
├── composer.json
├── phpunit.xml.dist
├── configs/
│   └── test.ini                 DB connection config for integration tests
├── src/
│   ├── Integration/
│   │   └── IntegrationTestCase.php  Abstract base for integration tests
│   ├── Entities/
│   │   ├── TestUser.php         id (PK AI), name, email, active, created_at
│   │   └── TestPost.php         id (PK AI), user_id (FK→TestUser), title, body, published_at
│   ├── StubConfig.php           ConfigInterface stub (returns defaults)
│   ├── StubLogger.php           LoggerInterface stub (discards all output)
│   ├── StubEvents.php           EventServiceInterface stub (records emitted events)
│   ├── TestDatabase.php         Database subclass with no-op connect()
│   └── TestHelper.php           Creates EntityManager and registers entities via reflection
└── tests/
    ├── Unit/
    │   ├── ColumnTest.php
    │   ├── EntityTest.php
    │   ├── QueryTest.php
    │   ├── EntityManagerTest.php
    │   ├── MariaQueryBuilderTest.php
    │   └── ColumnAttributeHandlerTest.php
    └── Integration/
        ├── DatabaseTest.php
        ├── QueryExecutorTest.php
        └── EntityManagerIntegrationTest.php
```

## Configuring Integration Tests

Copy or edit `configs/test.ini` with your MariaDB connection details:

```ini
database.default.dsn = "mysql:host=localhost"
database.default.name = micro_entities_test
database.default.username = root
database.default.password =
database.default.table_prefix = te_
```

> **Note:** The DSN value must be quoted if it contains `=` (e.g. `host=localhost`), because
> PHP's `parse_ini_file` with `INI_SCANNER_TYPED` treats bare `=` inside values as a syntax error.

Create the database before running the integration tests:

```sql
CREATE DATABASE micro_entities_test CHARACTER SET utf8;
```

Integration tests create and drop their tables automatically in `setUp`/`tearDown` — the database just needs to exist and be accessible.

## Unit Tests

No database connection required. A `TestDatabase` stub (extends `Database`) overrides `connect()` as a no-op and provides pure-string implementations of `escapeName()` and `escapeLike()`. `StubConfig`, `StubLogger`, and `StubEvents` satisfy the framework interface dependencies.

| Test class | What it covers |
|---|---|
| `ColumnTest` | TYPE_* / ACTION_* / NOW constants, constructor defaults and all-args |
| `EntityTest` | `isNew`, `setNew`, dirty-tracking (`getDirtyFields`, `isDirty`), `clearSnapshot`, event name methods |
| `QueryTest` | All fluent builder methods: `from`, `addFields`, `addCondition`, `addJoin`, `addGroupBy`, `addOrderBy`, `setLimit`, join type constants |
| `EntityManagerTest` | Metadata registration, table name resolution (prefix, hash mode), PK detection (single / composite / none), PK condition/params/value helpers, `fetchDataArray`, `setByDataArray` |
| `MariaQueryBuilderTest` | `columnDefinition` for all types and modifiers, `foreignKeyDefinition`, `primaryKeyDefinition`, `createTable` DDL, `findAll` / `findAllCount` SQL generation, limit clamping |
| `ColumnAttributeHandlerTest` | `attributeClass`, `targets`, `handle` wires Column attribute into EntityManager |

## Integration Tests

Each test class recreates its tables on every test (setUp creates, tearDown drops), so tests are fully isolated and can be run in any order.

| Test class | What it covers |
|---|---|
| `DatabaseTest` | Connection lifecycle, `#ClassName` → table name substitution (including string-literal preservation), `query` / `fetch` / `fetchAll` / `fetchColumn` / `fetchOne`, `insert` + `lastInsertId`, `update` with and without condition, `getInConditionAndParams`, `beginTransaction` / `commit` / `rollBack`, `runInTransaction` success and rollback-on-exception, `escapeLike` |
| `QueryExecutorTest` | `isTableExist` (false/true), `createTable` / `createTableIfNotExists`, `listTables`, `findAll` with and without conditions, `findAllColumn`, `findAllCount`, `findColumns` |
| `EntityManagerIntegrationTest` | `save` new entity (insert, PK backfill, isNew flag, snapshot), `save` dirty entity (partial UPDATE), `save` clean entity (no UPDATE), `findById` (entity hydration, isNew flag, snapshot), before/after save events, `deleteById`, `deleteByIds`, `insert`, `update` with condition |

## Known Gotchas

- **`Database::update()` param name collision** — the condition params must not use the same
  placeholder names as the columns being updated. For example, updating `name` with condition
  `name = :name` causes the condition param to overwrite the SET param in the merged array.
  Use a distinct name like `:oldName` in the condition instead.

- **`orderBy()` only works with aliased fields** — `QueryBuilder::orderBy()` checks whether the
  order field name appears as a *key* in `$query->fields()`. For integer-keyed (non-aliased) fields
  the check always fails and the ORDER BY clause is omitted. To sort, add the field with an alias:
  ```php
  $q->addFields(['name' => 'name']);
  $q->addOrderBy('name');
  ```
