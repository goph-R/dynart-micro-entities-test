# Test Plan for dynart-micro-entities

## Infrastructure

Already in place: `ResettableMicro`, `composer.json`, `phpunit.xml.dist`, `configs/app.ini` (SQLite in-memory), empty `tests/` directory.

### Test Fixtures Needed

Create `src/` stubs used by multiple test classes:

```
src/
  ResettableMicro.php          (exists)
  Fixture/
    User.php                   Entity with auto-increment PK (id, name, email)
    Post.php                   Entity with FK to User (id, userId, title, body)
    Tag.php                    Entity with composite PK (tenantId, name)
    StubDatabase.php           Extends Database, SQLite-compatible (no USE/SET NAMES)
    StubQueryBuilder.php       Extends QueryBuilder, minimal implementations
```

`StubDatabase` wraps SQLite via `PdoBuilder` — avoids the MariaDB-specific `USE db` / `SET NAMES` in `connect()`. Implements `escapeName()` with double-quotes and `escapeLike()` passthrough. This allows real SQL execution in tests.

Update `composer.json` autoload to include `Dynart\\Micro\\Entities\\Test\\Fixture\\` from `src/Fixture/`.

---

## Test Classes

### 1. EntityTest

Target: `Entity.php` — state flags, dirty tracking, event names.

| Test | What it verifies |
|------|-----------------|
| `testIsNewByDefault` | New entity returns `isNew() === true` |
| `testSetNew` | `setNew(false)` flips the flag |
| `testGetDirtyFieldsWithoutSnapshot` | No snapshot = all fields returned as dirty |
| `testGetDirtyFieldsUnchanged` | After snapshot, same data = empty array |
| `testGetDirtyFieldsChanged` | After snapshot, modified field returned, unchanged excluded |
| `testGetDirtyFieldsNewKey` | Field present in current but not in snapshot = dirty |
| `testIsDirtyTrue` | Changed data returns true |
| `testIsDirtyFalse` | Unchanged data returns false |
| `testClearSnapshotResetsDirtyTracking` | After clear, all fields dirty again |
| `testTakeSnapshotOverwritesPrevious` | Second snapshot replaces first |
| `testBeforeSaveEvent` | Returns `FullClassName.before_save` |
| `testAfterSaveEvent` | Returns `FullClassName.after_save` |

Approach: create a minimal concrete `TestEntity extends Entity` inline in the test file.

---

### 2. EntityManagerTest

Target: `EntityManager.php` — metadata registration, PK logic, CRUD, dirty-tracking save.

Uses mock `Database` and mock `EventServiceInterface` to verify calls without real SQL.

#### Metadata & Table Names

| Test | What it verifies |
|------|-----------------|
| `testAddColumnRegistersTableAndColumn` | `tableColumns()` and `tableName()` return registered data |
| `testAddColumnMultipleColumns` | Multiple columns on same class accumulate |
| `testTableNameByClassWithPrefix` | Prefix prepended, lowercased simple name |
| `testTableNameByClassWithoutPrefix` | `withPrefix=false` omits prefix |
| `testTableNameByClassHashMode` | `setUseEntityHashName(true)` returns `#ClassName` |
| `testTableNameThrowsForUnregistered` | `EntityManagerException` for unknown class |
| `testTableColumnsThrowsForUnregistered` | `EntityManagerException` for unknown class |

#### Primary Keys

| Test | What it verifies |
|------|-----------------|
| `testPrimaryKeySingleColumn` | Returns string for single PK |
| `testPrimaryKeyComposite` | Returns array for multi-column PK |
| `testPrimaryKeyNone` | Returns null when no PK column |
| `testPrimaryKeyCached` | Second call doesn't recompute |
| `testPrimaryKeyValueSingle` | Extracts scalar from data array |
| `testPrimaryKeyValueComposite` | Extracts array from data array |
| `testPrimaryKeyConditionSingle` | Returns `` `pk` = :pkValue `` |
| `testPrimaryKeyConditionComposite` | Returns `` `pk0` = :pkValue0 and `pk1` = :pkValue1 `` |
| `testPrimaryKeyConditionParamsSingle` | Returns `[':pkValue' => val]` |
| `testPrimaryKeyConditionParamsComposite` | Returns `[':pkValue0' => v0, ':pkValue1' => v1]` |
| `testIsPrimaryKeyAutoIncrementTrue` | Single PK with autoIncrement flag |
| `testIsPrimaryKeyAutoIncrementFalse` | Single PK without flag |
| `testIsPrimaryKeyAutoIncrementComposite` | Multi-column PK always false |

#### Data Binding

| Test | What it verifies |
|------|-----------------|
| `testFetchDataArray` | Returns column values from entity properties |
| `testSetByDataArraySetsProperties` | Entity properties populated from array |
| `testSetByDataArrayTakesSnapshot` | Snapshot taken after binding |
| `testSetByDataArrayThrowsOnUnknownColumn` | `EntityManagerException` for bad key |

#### CRUD — Insert/Update/Delete (mocked DB)

| Test | What it verifies |
|------|-----------------|
| `testInsertDelegatesToDatabase` | Calls `db->insert()` with table name and data |
| `testInsertReturnsLastInsertId` | Returns `db->lastInsertId()` |
| `testUpdateDelegatesToDatabase` | Calls `db->update()` with table, data, condition, params |
| `testDeleteByIdExecutesQuery` | Calls `db->query()` with DELETE + id param |
| `testDeleteByIdsExecutesQuery` | Calls `db->query()` with DELETE + IN params |

#### Save Lifecycle (mocked DB + EventService)

| Test | What it verifies |
|------|-----------------|
| `testSaveNewEntityInsertsAndEmitsEvents` | `db->insert()` called; before/after events emitted |
| `testSaveNewEntitySetsAutoIncrementId` | PK property updated with `lastInsertId()` |
| `testSaveNewEntityMarksNotNew` | `isNew()` is false after save |
| `testSaveNewEntityTakesSnapshot` | Snapshot includes auto-increment ID |
| `testSaveExistingEntityUpdatesDirtyOnly` | `db->update()` receives only changed fields |
| `testSaveExistingEntityNoChangesSkipsUpdate` | `db->update()` NOT called; events still emitted |
| `testSaveExistingEntityUpdatesSnapshot` | Snapshot refreshed after update |

#### FindById (mocked DB)

| Test | What it verifies |
|------|-----------------|
| `testFindByIdReturnsEntity` | Entity fetched and returned |
| `testFindByIdMarksNotNew` | `isNew()` is false |
| `testFindByIdTakesSnapshot` | Snapshot matches fetched data |

---

### 3. DatabaseTest

Target: `Database.php` abstract class — tested via `StubDatabase` with real SQLite.

| Test | What it verifies |
|------|-----------------|
| `testConnectedInitiallyFalse` | `connected()` false before any query |
| `testConnectedTrueAfterQuery` | Lazy connect on first `query()` |
| `testQueryReturnsPdoStatement` | Returns `PDOStatement` |
| `testQueryWithParams` | Param binding works correctly |
| `testQueryRethrowsPdoException` | PDOException propagated after logging |
| `testFetchReturnsAssocArray` | Default fetch mode returns associative |
| `testFetchReturnsEntityInstance` | className param hydrates entity via FETCH_CLASS |
| `testFetchAllReturnsAllRows` | Multiple rows returned |
| `testFetchAllEmpty` | Empty table returns `[]` |
| `testFetchColumnReturnsFlatArray` | Single column extracted |
| `testFetchOneReturnsScalar` | First column of first row |
| `testInsertInsertsRow` | Row appears in table after insert |
| `testLastInsertIdAfterInsert` | Returns correct auto-increment value |
| `testUpdateModifiesRow` | Row data changed after update |
| `testUpdateWithCondition` | Only matching rows updated |
| `testGetInConditionAndParams` | Returns `(:in0,:in1)` + param array |
| `testGetInConditionAndParamsCustomPrefix` | Custom prefix used |
| `testReplaceClassHashNames` | `#User` replaced with `prefix_user` |
| `testReplaceClassHashNamesPreservesStrings` | Content in quotes not replaced |
| `testTransactionCommit` | Begin + commit persists data |
| `testTransactionRollback` | Begin + rollback discards data |
| `testRunInTransactionSuccess` | Callable executed, data persisted |
| `testRunInTransactionRollbackOnException` | Exception causes rollback, then re-thrown |
| `testCloseCursorWhenRequested` | `closeCursor=true` doesn't break subsequent queries |

Setup: create a real SQLite table (`CREATE TABLE test_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)`) in `setUp()`.

---

### 4. MariaDatabaseTest

Target: `MariaDatabase.php` — unit tests only (no real MariaDB connection).

| Test | What it verifies |
|------|-----------------|
| `testEscapeNameSingle` | `foo` → `` `foo` `` |
| `testEscapeNameQualified` | `db.table.col` → `` `db`.`table`.`col` `` |
| `testEscapeLikeReplacesPercent` | `test%val` → `test\%val` |
| `testEscapeLikeNoChange` | String without `%` unchanged |
| `testConnectIsIdempotent` | Second `connect()` is a no-op (mock PdoBuilder) |

---

### 5. PdoBuilderTest

Target: `PdoBuilder.php` — fluent API and PDO creation.

| Test | What it verifies |
|------|-----------------|
| `testFluentInterface` | Each setter returns `$this` |
| `testBuildReturnsPdo` | `build()` with SQLite DSN returns `PDO` instance |
| `testBuildWithAllParams` | All params passed correctly to PDO constructor |

---

### 6. QueryTest

Target: `Query.php` — pure data object, no DB needed.

| Test | What it verifies |
|------|-----------------|
| `testFromReturnsConstructorValue` | String table name stored |
| `testFromWithSubquery` | Query object as FROM |
| `testSetFieldsReplacesExisting` | Previous fields overwritten |
| `testAddFieldsMerges` | Appended to existing |
| `testFieldsAutoSelectsWhenEmpty` | Queries EntityManager for columns when empty + string FROM |
| `testFieldsReturnsExplicitWhenSet` | Explicit fields returned as-is |
| `testAddConditionAppendsAndBindsVariables` | Condition added, variables merged |
| `testMultipleConditions` | Multiple conditions accumulate |
| `testAddVariablesMerges` | Variables accumulated |
| `testAddInnerJoin` | Type=inner, from/condition stored |
| `testAddJoinCustomType` | LEFT/RIGHT/OUTER join stored |
| `testJoinWithVariables` | Join variables merged |
| `testAddGroupBy` | Groups accumulate |
| `testAddOrderByDefaultAsc` | Default direction is 'asc' |
| `testAddOrderByDesc` | Explicit 'desc' stored |
| `testSetLimit` | Offset and max stored |
| `testOffsetAndMaxDefaultMinusOne` | -1 when not set |

For `testFieldsAutoSelectsWhenEmpty`: needs Micro DI setup with EntityManager containing registered columns.

---

### 7. MariaQueryBuilderTest

Target: `MariaQueryBuilder.php` — SQL generation. Uses mock DB (backtick escaping) + real EntityManager.

#### Column Definitions

| Test | What it verifies |
|------|-----------------|
| `testColumnDefInt` | `int` type |
| `testColumnDefIntWithSize` | `int(11)` |
| `testColumnDefLong` | `bigint` |
| `testColumnDefFloat` | `float` |
| `testColumnDefDouble` | `double` |
| `testColumnDefBool` | `tinyint(1)` |
| `testColumnDefString` | `varchar(N)` with size |
| `testColumnDefStringFixSize` | `char(N)` with fixSize |
| `testColumnDefStringNoSize` | `longtext` |
| `testColumnDefNumeric` | `decimal(P, S)` with array size |
| `testColumnDefDate` | `date` |
| `testColumnDefTime` | `time` |
| `testColumnDefDatetime` | `datetime` |
| `testColumnDefBlob` | `blob` |
| `testColumnDefNotNull` | Appends `not null` |
| `testColumnDefAutoIncrement` | Appends `auto_increment` |
| `testColumnDefDefaultString` | `default 'value'` with quote escaping |
| `testColumnDefDefaultNull` | `default null` |
| `testColumnDefDefaultBoolTrue` | `default 1` |
| `testColumnDefDefaultBoolFalse` | `default 0` |
| `testColumnDefDefaultNowDatetime` | `default utc_timestamp()` |
| `testColumnDefDefaultNowDate` | `default utc_date()` |
| `testColumnDefDefaultNowTime` | `default utc_time()` |
| `testColumnDefDefaultRawExpression` | Array default `['CURRENT_TIMESTAMP']` |
| `testColumnDefDefaultBlobThrows` | EntityManagerException |
| `testColumnDefDefaultLongtextThrows` | EntityManagerException |
| `testColumnDefUnknownTypeThrows` | EntityManagerException |
| `testColumnDefBadIntSizeThrows` | EntityManagerException |
| `testColumnDefBadNumericSizeThrows` | EntityManagerException |

#### Primary Key & Foreign Key Definitions

| Test | What it verifies |
|------|-----------------|
| `testPrimaryKeyDefSingle` | `primary key (\`id\`)` |
| `testPrimaryKeyDefComposite` | `primary key (\`a\`, \`b\`)` |
| `testPrimaryKeyDefNone` | Empty string |
| `testForeignKeyDefValid` | `foreign key (\`col\`) references \`table\` (\`ref\`)` |
| `testForeignKeyDefWithOnDelete` | Appends `on delete cascade` |
| `testForeignKeyDefWithOnUpdate` | Appends `on update set null` |
| `testForeignKeyDefNoKey` | Empty string |
| `testForeignKeyDefNotArrayThrows` | EntityManagerException |
| `testForeignKeyDefWrongSizeThrows` | EntityManagerException |

#### CREATE TABLE

| Test | What it verifies |
|------|-----------------|
| `testCreateTableBasic` | Full DDL with columns, PK |
| `testCreateTableIfNotExists` | `if not exists` in output |
| `testCreateTableWithForeignKey` | FK constraint included |

#### SELECT Queries (via QueryBuilder base)

| Test | What it verifies |
|------|-----------------|
| `testFindAllSimple` | `select ... from table` |
| `testFindAllWithCondition` | WHERE clause with AND |
| `testFindAllWithJoin` | JOIN rendered |
| `testFindAllWithJoinAlias` | `table as alias` for array FROM |
| `testFindAllWithGroupBy` | GROUP BY clause |
| `testFindAllWithOrderBy` | ORDER BY clause |
| `testFindAllWithLimit` | LIMIT offset, max |
| `testFindAllLimitCapped` | Max capped to `maxLimit` config |
| `testFindAllCountSimple` | `select count(1) as \`c\` from ...` |
| `testFindAllCountWithGroupBy` | COUNT + GROUP BY |
| `testFieldNamesIntKey` | No alias |
| `testFieldNamesStringKey` | `name as alias` |
| `testFieldNamesArrayValue` | Raw SQL expression |

#### Metadata Queries

| Test | What it verifies |
|------|-----------------|
| `testIsTableExist` | SQL references `information_schema.tables` |
| `testListTables` | Returns `show tables` |
| `testDescribeTable` | Returns `describe tablename` |

---

### 8. QueryExecutorTest

Target: `QueryExecutor.php` — thin delegation layer. Uses mocked DB + QueryBuilder.

| Test | What it verifies |
|------|-----------------|
| `testIsTableExistTrue` | `fetchOne` returns truthy → true |
| `testIsTableExistFalse` | `fetchOne` returns false → false |
| `testCreateTable` | `queryBuilder->createTable()` + `db->query()` called |
| `testListTables` | `db->fetchColumn()` result returned |
| `testFindAll` | `queryBuilder->findAll()` + `db->fetchAll()` with variables |
| `testFindAllColumn` | Column param passed to findAll fields |
| `testFindAllCount` | `queryBuilder->findAllCount()` + `db->fetchOne()` |

---

### 9. ColumnAttributeTest

Target: `Attribute/Column.php` — constructor and defaults.

| Test | What it verifies |
|------|-----------------|
| `testDefaultValues` | Size=0, fixSize=false, notNull=false, autoIncrement=false, primaryKey=false, default=null, foreignKey=null, onDelete=null, onUpdate=null |
| `testAllParamsSet` | All constructor params stored correctly |
| `testIsTargetProperty` | Attribute targets `TARGET_PROPERTY` |

---

### 10. ColumnAttributeHandlerTest

Target: `AttributeHandler/ColumnAttributeHandler.php` — attribute processing.

| Test | What it verifies |
|------|-----------------|
| `testAttributeClass` | Returns `Column::class` |
| `testTargets` | Returns `[TARGET_PROPERTY]` |
| `testHandleMinimalColumn` | Only type set → `addColumn` called with type only |
| `testHandleFullColumn` | All properties set → all metadata keys in `addColumn` call |
| `testHandleSkipsDefaults` | Default/null values not included in metadata array |

Uses mock `EntityManager` to capture `addColumn` call, mock `ReflectionProperty` for `$subject`.

---

## Execution Order

Implement tests in this order (least dependencies first):

1. **EntityTest** — zero dependencies
2. **ColumnAttributeTest** — zero dependencies
3. **PdoBuilderTest** — standalone, SQLite
4. **QueryTest** — needs Micro DI only for auto-fields
5. **MariaDatabaseTest** — escaping is pure logic
6. **ColumnAttributeHandlerTest** — mock EntityManager
7. **EntityManagerTest** — mock Database + EventService
8. **DatabaseTest** — real SQLite, needs StubDatabase fixture
9. **MariaQueryBuilderTest** — mock Database + real EntityManager
10. **QueryExecutorTest** — mock everything

## Running

```bash
cd /c/Users/gopher/Projects/dynart-micro-entities-test
php vendor/bin/phpunit --stderr
php vendor/bin/phpunit --coverage-html reports/coverage-html --stderr
```
