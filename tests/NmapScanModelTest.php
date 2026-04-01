<?php
/**
 * NmapScanModelTest — integration tests for the NmapScan model.
 *
 * Verifies table creation, all CRUD methods, pruning logic, and the scan
 * reset workflow. Every test starts from a clean nmap_scans table.
 *
 * Run with:  ./vendor/bin/phpunit --testdox
 */

require_once APP_ROOT . '/models/NmapScan.php';

class NmapScanModelTest extends BaseTestCase
{
    // ── Setup ─────────────────────────────────────────────────────────────────

    /** @var NmapScan */
    private NmapScan $model;

    /**
     * Truncate the nmap_scans table before every test for a clean slate.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NmapScan();
        DB::connection()->exec('TRUNCATE TABLE `nmap_scans`');
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * validate() creates the nmap_scans table if it does not exist.
     *
     * Drops the table, calls validate(), and asserts it is recreated.
     *
     * @return void
     */
    public function testValidateCreatesTable(): void
    {
        DB::connection()->exec('DROP TABLE IF EXISTS `nmap_scans`');

        $this->model->validate();

        $this->assertTableExists('nmap_scans');
    }

    // ── insertIps ─────────────────────────────────────────────────────────────

    /**
     * insertIps() inserts all provided IPs with type = null and checked_at = null.
     *
     * @return void
     */
    public function testInsertIps(): void
    {
        $this->model->insertIps(['192.168.1.1', '192.168.1.2', '192.168.1.3']);

        $rows = DB::query('SELECT * FROM `nmap_scans`')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertNull($row['type'],       'type must be null for a freshly inserted row');
            $this->assertNull($row['checked_at'], 'checked_at must be null for a freshly inserted row');
        }
    }

    /**
     * insertIps() silently skips IPs that are already in the table, leaving
     * the existing row's data unchanged.
     *
     * @return void
     */
    public function testInsertIpsSkipsDuplicates(): void
    {
        $this->model->insertIps(['10.0.0.1']);

        // Mark the row so we can verify it is not reset.
        $this->model->markChecked(
            (int) DB::query("SELECT id FROM `nmap_scans` WHERE ip_address = '10.0.0.1'")
                     ->fetchColumn(),
            'wemo'
        );

        // Insert the same IP again.
        $this->model->insertIps(['10.0.0.1']);

        $rows = DB::query('SELECT * FROM `nmap_scans`')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows, 'Duplicate IP must not create a second row');
        $this->assertSame('wemo', $rows[0]['type'],       'Existing type must be preserved');
        $this->assertNotNull($rows[0]['checked_at'],       'Existing checked_at must be preserved');
    }

    // ── getNextUnchecked ──────────────────────────────────────────────────────

    /**
     * getNextUnchecked() returns the row with the oldest created_at when
     * multiple unchecked rows exist.
     *
     * @return void
     */
    public function testGetNextUncheckedReturnsOldest(): void
    {
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `created_at`) VALUES (?, ?)',
            ['172.16.0.2', '2025-06-01 10:00:00']
        );
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `created_at`) VALUES (?, ?)',
            ['172.16.0.1', '2025-06-01 09:00:00']
        );

        $row = $this->model->getNextUnchecked();

        $this->assertNotNull($row);
        $this->assertSame('172.16.0.1', $row['ip_address'], 'Must return the oldest unchecked row');
    }

    /**
     * getNextUnchecked() returns null when no unchecked rows exist.
     *
     * @return void
     */
    public function testGetNextUncheckedReturnsNullWhenDone(): void
    {
        $this->assertNull($this->model->getNextUnchecked(), 'Empty table must return null');

        // Insert one row and mark it checked; still must return null.
        $this->model->insertIps(['10.10.10.1']);
        $id = (int) DB::query("SELECT id FROM `nmap_scans`")->fetchColumn();
        $this->model->markChecked($id, 'other');

        $this->assertNull($this->model->getNextUnchecked(), 'All-checked table must return null');
    }

    // ── markChecked ───────────────────────────────────────────────────────────

    /**
     * markChecked() sets checked_at to a non-null datetime and stores the
     * given type value on the identified row.
     *
     * @return void
     */
    public function testMarkChecked(): void
    {
        $this->model->insertIps(['192.168.0.50']);
        $id = (int) DB::query("SELECT id FROM `nmap_scans`")->fetchColumn();

        $this->model->markChecked($id, 'wemo');

        $row = DB::query(
            'SELECT * FROM `nmap_scans` WHERE id = ?', [$id]
        )->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row['checked_at'], 'checked_at must be set after markChecked()');
        $this->assertSame('wemo', $row['type'],  'type must match the value passed to markChecked()');
    }

    // ── getRemainingCount ─────────────────────────────────────────────────────

    /**
     * getRemainingCount() returns the number of rows where checked_at IS NULL.
     *
     * @return void
     */
    public function testGetRemainingCount(): void
    {
        $this->model->insertIps(['10.0.0.1', '10.0.0.2', '10.0.0.3']);

        $ids = DB::query('SELECT id FROM `nmap_scans`')->fetchAll(PDO::FETCH_COLUMN);
        $this->model->markChecked((int) $ids[0], 'other');

        $this->assertSame(2, $this->model->getRemainingCount());
    }

    // ── pruneStale ────────────────────────────────────────────────────────────

    /**
     * pruneStale() deletes a record whose checked_at is older than the threshold.
     * Known device types are not deleted even when they are stale.
     *
     * @return void
     */
    public function testPruneStaleByCheckedAt(): void
    {
        // Stale unchecked-type record: checked 10 hours ago.
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, ?)',
            ['192.168.1.10', null, date('Y-m-d H:i:s', strtotime('-10 hours'))]
        );
        // Known-type record that is also stale — must be preserved.
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, ?)',
            ['192.168.1.20', 'wemo', date('Y-m-d H:i:s', strtotime('-10 hours'))]
        );

        $deleted = $this->model->pruneStale(1);

        $this->assertSame(1, $deleted, 'Only the non-known stale row must be deleted');
        $remaining = DB::query('SELECT ip_address FROM `nmap_scans`')
                       ->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('192.168.1.20', $remaining, 'Known-type row must survive pruning');
        $this->assertNotContains('192.168.1.10', $remaining, 'Stale row must be deleted');
    }

    /**
     * pruneStale() deletes a record that was never checked but whose
     * created_at is older than the threshold.
     *
     * @return void
     */
    public function testPruneStaleByCreatedAt(): void
    {
        // Never-checked record with an old created_at.
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `created_at`) VALUES (?, ?)',
            ['10.1.1.1', date('Y-m-d H:i:s', strtotime('-5 hours'))]
        );

        $deleted = $this->model->pruneStale(1);

        $this->assertSame(1, $deleted);
        $this->assertSame(
            0,
            (int) DB::query("SELECT COUNT(*) FROM `nmap_scans`")->fetchColumn()
        );
    }

    /**
     * A type = 'other' record is deleted only when $includeOther is true.
     *
     * @return void
     */
    public function testPruneStaleIncludeOther(): void
    {
        // Recent 'other' record — not stale by age.
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, NOW())',
            ['10.2.2.2', 'other']
        );

        // pruneStale with a large hour threshold and includeOther = false must NOT delete it.
        $this->assertSame(0, $this->model->pruneStale(9999, false));

        // pruneStale with includeOther = true must delete it regardless of age.
        $this->assertSame(1, $this->model->pruneStale(9999, true));
    }

    /**
     * pruneStale() never deletes records with type = 'wemo' or type = 'nullhub',
     * even when they are old and $includeOther is true.
     *
     * @return void
     */
    public function testPruneStalePreservesKnownTypes(): void
    {
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, ?)',
            ['192.168.50.1', 'wemo', date('Y-m-d H:i:s', strtotime('-48 hours'))]
        );
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, ?)',
            ['192.168.50.2', 'nullhub', date('Y-m-d H:i:s', strtotime('-48 hours'))]
        );

        $deleted = $this->model->pruneStale(1, true);

        $this->assertSame(0, $deleted, 'Known-type rows must never be deleted by pruneStale()');
    }

    // ── resetScan ─────────────────────────────────────────────────────────────

    /**
     * resetScan() deletes null-type and 'other'-type rows and resets
     * checked_at to null on wemo and nullhub rows.
     *
     * @return void
     */
    public function testResetScan(): void
    {
        // Unchecked (type = null)
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`) VALUES (?)',
            ['10.0.1.1']
        );
        // type = 'other'
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, NOW())',
            ['10.0.1.2', 'other']
        );
        // type = 'wemo' with checked_at set
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, NOW())',
            ['10.0.1.3', 'wemo']
        );
        // type = 'nullhub' with checked_at set
        DB::query(
            'INSERT INTO `nmap_scans` (`ip_address`, `type`, `checked_at`) VALUES (?, ?, NOW())',
            ['10.0.1.4', 'nullhub']
        );

        $this->model->resetScan();

        $rows = DB::query('SELECT * FROM `nmap_scans`')->fetchAll(PDO::FETCH_ASSOC);
        $ips  = array_column($rows, 'ip_address');

        // null-type and 'other' rows must be gone.
        $this->assertNotContains('10.0.1.1', $ips, 'null-type row must be deleted');
        $this->assertNotContains('10.0.1.2', $ips, "'other' row must be deleted");

        // Known-type rows must survive.
        $this->assertContains('10.0.1.3', $ips, 'wemo row must survive');
        $this->assertContains('10.0.1.4', $ips, 'nullhub row must survive');

        // checked_at must be reset to null on surviving rows.
        foreach ($rows as $row) {
            $this->assertNull(
                $row['checked_at'],
                "checked_at must be null after resetScan() for {$row['ip_address']}"
            );
        }
    }
}
