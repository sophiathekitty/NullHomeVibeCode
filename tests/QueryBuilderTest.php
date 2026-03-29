<?php
/**
 * QueryBuilderTest — integration tests for QueryBuilder.
 *
 * Uses a dedicated `query_builder_test` table created in setUpBeforeClass()
 * and re-seeded in setUp() so every test starts from a known state.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/Model.php';

/**
 * Concrete Model subclass used only by these tests.
 */
class QueryBuilderTestModel extends Model
{
    protected static string $table = 'query_builder_test';

    public function getTable(): string { return 'query_builder_test'; }
    public function getFields(): array { return []; }
}

class QueryBuilderTest extends BaseTestCase
{
    // ── Fixed seed data ───────────────────────────────────────────────────────

    /**
     * Rows inserted before every test.
     * Covers a range of statuses, values, and recorded_at timestamps.
     *
     * @var array<int, array<string, mixed>>
     */
    private const SEED_ROWS = [
        // rows whose TIME(recorded_at) falls in the 01:xx hour: Charlie, Eve
        ['name' => 'Alice',   'status' => 'active',   'value' => 10, 'recorded_at' => '2025-01-15 08:30:00'],
        ['name' => 'Bob',     'status' => 'inactive', 'value' => 3,  'recorded_at' => '2025-01-20 13:45:00'],
        ['name' => 'Charlie', 'status' => 'active',   'value' => 7,  'recorded_at' => '2025-02-05 01:30:00'],
        ['name' => 'Diana',   'status' => 'pending',  'value' => 15, 'recorded_at' => '2025-02-15 22:00:00'],
        ['name' => 'Eve',     'status' => 'inactive', 'value' => 2,  'recorded_at' => '2025-03-05 01:15:00'],
        ['name' => 'Frank',   'status' => 'active',   'value' => 8,  'recorded_at' => '2025-03-20 14:20:00'],
        ["name" => "O'Brien", 'status' => 'active',   'value' => 5,  'recorded_at' => '2025-03-25 09:00:00'],
    ];

    // ── Schema setup ──────────────────────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        DB::connection()->exec(
            'CREATE TABLE IF NOT EXISTS `query_builder_test` (
                `id`          INT AUTO_INCREMENT PRIMARY KEY,
                `name`        VARCHAR(100),
                `status`      VARCHAR(50),
                `value`       INT,
                `recorded_at` DATETIME
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** Truncate and re-seed so every test starts from a known state. */
    protected function setUp(): void
    {
        parent::setUp();

        DB::connection()->exec('TRUNCATE TABLE `query_builder_test`');

        $stmt = DB::connection()->prepare(
            'INSERT INTO `query_builder_test` (`name`, `status`, `value`, `recorded_at`)
             VALUES (?, ?, ?, ?)'
        );
        foreach (self::SEED_ROWS as $row) {
            $stmt->execute([$row['name'], $row['status'], $row['value'], $row['recorded_at']]);
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function qb(): QueryBuilder
    {
        return QueryBuilderTestModel::query();
    }

    // ── Basic retrieval ───────────────────────────────────────────────────────

    /** get() with no conditions returns all seeded rows as associative arrays. */
    public function testGetReturnsAllRows(): void
    {
        $rows = $this->qb()->get();

        $this->assertCount(count(self::SEED_ROWS), $rows);
        foreach ($rows as $row) {
            $this->assertIsArray($row);
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
        }
    }

    /** first() returns a single array on a match, null when nothing matches. */
    public function testFirstReturnsOneRowOrNull(): void
    {
        $row = $this->qb()->where('status', 'active')->first();
        $this->assertIsArray($row);
        $this->assertSame('active', $row['status']);

        $none = $this->qb()->where('status', 'nonexistent')->first();
        $this->assertNull($none);
    }

    /** select() restricts the returned keys to only the specified columns. */
    public function testSelectLimitsColumns(): void
    {
        $rows = $this->qb()->select('id', 'name')->get();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame(['id', 'name'], array_keys($row));
        }
    }

    // ── WHERE conditions ──────────────────────────────────────────────────────

    /** where() with the default '=' operator filters correctly. */
    public function testWhereWithDefaultEqualsOperator(): void
    {
        $rows = $this->qb()->where('status', 'active')->get();

        // Alice, Charlie, Frank, O'Brien
        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertSame('active', $row['status']);
        }
    }

    /** where() with an explicit '>' operator returns only matching rows. */
    public function testWhereWithExplicitOperator(): void
    {
        $rows = $this->qb()->where('value', 5, '>')->get();

        // Alice=10, Charlie=7, Diana=15, Frank=8
        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertGreaterThan(5, $row['value']);
        }
    }

    /** Two where() calls with OR conjunction return rows matching either. */
    public function testWhereWithOrConjunction(): void
    {
        $rows = $this->qb()
            ->where('status', 'active')
            ->where('status', 'pending', '=', 'OR')
            ->get();

        // Alice, Charlie, Diana, Frank, O'Brien  — 5 rows
        $this->assertCount(5, $rows);
        foreach ($rows as $row) {
            $this->assertContains($row['status'], ['active', 'pending']);
        }
    }

    /** whereBetween() returns only rows within the given date range. */
    public function testWhereBetween(): void
    {
        $rows = $this->qb()
            ->whereBetween('recorded_at', '2025-01-01', '2025-01-31 23:59:59')
            ->get();

        // Alice (Jan 15) and Bob (Jan 20)
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertGreaterThanOrEqual('2025-01-01', $row['recorded_at']);
            $this->assertLessThanOrEqual('2025-01-31 23:59:59', $row['recorded_at']);
        }
    }

    /** whereTimeBetween() filters on the time portion regardless of date. */
    public function testWhereTimeBetween(): void
    {
        $rows = $this->qb()
            ->whereTimeBetween('recorded_at', '01:00:00', '01:59:59')
            ->get();

        // Charlie (01:30) and Eve (01:15)
        $this->assertCount(2, $rows);
        $names = array_column($rows, 'name');
        $this->assertContains('Charlie', $names);
        $this->assertContains('Eve', $names);
    }

    // ── Grouping ──────────────────────────────────────────────────────────────

    /** beginGroup() with OR conjunction correctly groups conditions. */
    public function testGroupedConditions(): void
    {
        // WHERE `status` = 'inactive' OR (`name` = 'Diana')
        // Bob (inactive), Eve (inactive), Diana (name match) → 3 rows
        $rows = $this->qb()
            ->where('status', 'inactive')
            ->beginGroup('OR')
                ->where('name', 'Diana')
            ->endGroup()
            ->get();

        $names = array_column($rows, 'name');
        sort($names);
        $this->assertSame(['Bob', 'Diana', 'Eve'], $names);
    }

    /** A simpler grouped AND scenario with predictable results. */
    public function testGroupedAndConditions(): void
    {
        // status = 'active' AND (value >= 8 OR name = 'Charlie')
        // active rows: Alice=10, Charlie=7, Frank=8, O'Brien=5
        // group (value>=8 OR name='Charlie'): Alice, Charlie, Frank
        // intersection: Alice, Charlie, Frank
        $rows = $this->qb()
            ->where('status', 'active')
            ->beginGroup('AND')
                ->where('value', 8, '>=')
                ->where('name', 'Charlie', '=', 'OR')
            ->endGroup()
            ->get();

        $names = array_column($rows, 'name');
        sort($names);
        $this->assertSame(['Alice', 'Charlie', 'Frank'], $names);
    }

    // ── count() ───────────────────────────────────────────────────────────────

    /** count() returns the number of matching rows. */
    public function testCount(): void
    {
        $total  = $this->qb()->count();
        $active = $this->qb()->where('status', 'active')->count();

        $this->assertSame(count(self::SEED_ROWS), $total);
        $this->assertSame(4, $active); // Alice, Charlie, Frank, O'Brien
    }

    // ── delete() ─────────────────────────────────────────────────────────────

    /** delete() removes matching rows and returns the affected row count. */
    public function testDelete(): void
    {
        $affected = $this->qb()->where('status', 'inactive')->delete();
        $this->assertSame(2, $affected); // Bob, Eve

        $remaining = $this->qb()->count();
        $this->assertSame(count(self::SEED_ROWS) - 2, $remaining);
    }

    // ── limit() ──────────────────────────────────────────────────────────────

    /** limit() restricts the number of rows returned. */
    public function testLimit(): void
    {
        $rows = $this->qb()->limit(3)->get();
        $this->assertCount(3, $rows);
    }

    // ── orderBy() ────────────────────────────────────────────────────────────

    /** orderBy() returns rows in the specified order. */
    public function testOrderBy(): void
    {
        $rows = $this->qb()->orderBy('value', 'ASC')->get();
        $values = array_column($rows, 'value');
        $sorted = $values;
        sort($sorted);
        $this->assertSame($sorted, $values);

        $rowsDesc = $this->qb()->orderBy('value', 'DESC')->get();
        $valuesDesc = array_column($rowsDesc, 'value');
        $sortedDesc = $valuesDesc;
        rsort($sortedDesc);
        $this->assertSame($sortedDesc, $valuesDesc);
    }

    // ── Error cases ───────────────────────────────────────────────────────────

    /** endGroup() without a matching beginGroup() throws RuntimeException. */
    public function testEndGroupWithoutBeginGroupThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->qb()->endGroup();
    }

    /** get() with an unclosed beginGroup() throws RuntimeException. */
    public function testGetWithUnclosedGroupThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->qb()->beginGroup('AND')->where('status', 'active')->get();
    }

    /** An unsupported operator passed to where() throws InvalidArgumentException. */
    public function testInvalidOperatorThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->qb()->where('name', 'Alice', 'LIKE');
    }

    /** An invalid sort direction passed to orderBy() throws InvalidArgumentException. */
    public function testInvalidOrderDirectionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->qb()->orderBy('name', 'SIDEWAYS');
    }

    /** PDO binding correctly handles values containing apostrophes. */
    public function testLegitimateApostropheInValue(): void
    {
        $rows = $this->qb()->where('name', "O'Brien")->get();

        $this->assertCount(1, $rows);
        $this->assertSame("O'Brien", $rows[0]['name']);
    }
}
