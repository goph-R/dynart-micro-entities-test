<?php

namespace Dynart\Micro\Entities\Test\Integration;

use Dynart\Micro\Micro;
use Dynart\Micro\Entities\EntityManagerException;
use Dynart\Micro\Entities\MigrationHistory;
use Dynart\Micro\Entities\MigrationInterface;
use Dynart\Micro\Entities\Migrations;

class MigrationLog {
    public static array $applied = [];
}

class SecondMigration implements MigrationInterface {
    public function version(): string { return '2026_08_04_002_second'; }
    public function up(): void { MigrationLog::$applied[] = $this->version(); }
}

class FirstMigration implements MigrationInterface {
    public function version(): string { return '2026_08_04_001_first'; }
    public function up(): void { MigrationLog::$applied[] = $this->version(); }
}

class ThirdMigration implements MigrationInterface {
    public function version(): string { return '2026_08_04_003_third'; }
    public function up(): void { MigrationLog::$applied[] = $this->version(); }
}

class FailingMigration implements MigrationInterface {
    public function version(): string { return '2026_08_04_002_failing'; }
    public function up(): void { throw new \RuntimeException('boom'); }
}

class OnlyAddedMigration implements MigrationInterface {
    public function version(): string { return '2026_08_04_100_only_added'; }
    public function up(): void {}
}

class PreRegisteredMigration implements MigrationInterface {
    public function version(): string { return '2026_08_04_101_pre_registered'; }
    public function up(): void {}
}

class NotAMigration {}

class MigrationsTest extends IntegrationTestCase {

    private Migrations $migrations;

    protected function setUp(): void {
        parent::setUp();
        MigrationLog::$applied = [];
        $this->migrations = new Migrations($this->em, $this->db, $this->qe);
        $this->migrations->postConstruct();
        $this->dropTable(MigrationHistory::class);
    }

    protected function tearDown(): void {
        $this->dropTable(MigrationHistory::class);
    }

    // --- Registration ---

    public function testAddRejectsAClassThatIsNotAMigration(): void {
        $this->expectException(EntityManagerException::class);
        $this->migrations->add(NotAMigration::class);
    }

    public function testAddIsIdempotent(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->add(FirstMigration::class);
        $this->assertSame([FirstMigration::class], $this->migrations->migrationClasses());
    }

    /**
     * The runner resolves migrations through the DI container, so registering one has to put it
     * there - otherwise every real caller has to remember a second Micro::add().
     */
    public function testAddRegistersTheMigrationInTheContainer(): void {
        $this->assertFalse(Micro::hasInterface(OnlyAddedMigration::class));
        $this->migrations->add(OnlyAddedMigration::class);
        $this->assertTrue(Micro::hasInterface(OnlyAddedMigration::class));
    }

    public function testAddToleratesAnAlreadyRegisteredClass(): void {
        if (!Micro::hasInterface(PreRegisteredMigration::class)) {
            Micro::add(PreRegisteredMigration::class);
        }
        $this->migrations->add(PreRegisteredMigration::class);
        $this->assertSame([PreRegisteredMigration::class], $this->migrations->migrationClasses());
    }

    // --- Install ---

    public function testIsInstalledIsFalseBeforeInstall(): void {
        $this->assertFalse($this->migrations->isInstalled());
    }

    public function testInstallCreatesTheHistoryTable(): void {
        $this->migrations->install();
        $this->assertTrue($this->migrations->isInstalled());
    }

    public function testInstallIsIdempotent(): void {
        $this->migrations->install();
        $this->migrations->install();
        $this->assertTrue($this->migrations->isInstalled());
    }

    public function testAppliedVersionsIsEmptyBeforeInstall(): void {
        $this->assertSame([], $this->migrations->appliedVersions());
    }

    // --- Running ---

    public function testRunAppliesMigrationsInVersionOrder(): void {
        $this->migrations->add(ThirdMigration::class);
        $this->migrations->add(FirstMigration::class);
        $this->migrations->add(SecondMigration::class);
        $applied = $this->migrations->run();
        $this->assertSame([
            '2026_08_04_001_first',
            '2026_08_04_002_second',
            '2026_08_04_003_third',
        ], $applied);
        $this->assertSame($applied, MigrationLog::$applied);
    }

    public function testRunRecordsTheAppliedVersions(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->run();
        $this->assertSame(['2026_08_04_001_first'], $this->migrations->appliedVersions());
    }

    public function testRunningAgainIsANoOp(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->add(SecondMigration::class);
        $this->migrations->run();
        MigrationLog::$applied = [];
        $applied = $this->migrations->run();
        $this->assertSame([], $applied);
        $this->assertSame([], MigrationLog::$applied);
    }

    public function testOnlyThePendingOnesAreApplied(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->run();
        MigrationLog::$applied = [];
        $this->migrations->add(SecondMigration::class);
        $applied = $this->migrations->run();
        $this->assertSame(['2026_08_04_002_second'], $applied);
        $this->assertSame(['2026_08_04_002_second'], MigrationLog::$applied);
    }

    public function testPendingListsTheUnappliedMigrations(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->add(SecondMigration::class);
        $this->migrations->install();
        $pending = $this->migrations->pending();
        $this->assertCount(2, $pending);
        $this->assertSame('2026_08_04_001_first', $pending[0]->version());
    }

    public function testPendingIsEmptyAfterRun(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->run();
        $this->assertSame([], $this->migrations->pending());
    }

    public function testRunCreatesTheHistoryTableItself(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->run();
        $this->assertTrue($this->migrations->isInstalled());
    }

    /**
     * Each migration is recorded as soon as it succeeds, so the ones before a failure stay
     * applied and the run can be repeated once the cause is fixed. DDL commits implicitly, so a
     * transaction around the whole run would not undo the schema anyway.
     */
    public function testAFailureKeepsTheEarlierMigrationsRecorded(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->add(FailingMigration::class);
        $this->migrations->add(ThirdMigration::class);
        try {
            $this->migrations->run();
            $this->fail('Expected the failing migration to throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
        $this->assertSame(['2026_08_04_001_first'], $this->migrations->appliedVersions());
        $this->assertSame(['2026_08_04_001_first'], MigrationLog::$applied);
    }

    public function testRunResumesAfterAFailureIsFixed(): void {
        $this->migrations->add(FirstMigration::class);
        $this->migrations->add(FailingMigration::class);
        try {
            $this->migrations->run();
        } catch (\RuntimeException $e) {
            // expected
        }
        MigrationLog::$applied = [];
        $resumed = new Migrations($this->em, $this->db, $this->qe);
        $resumed->postConstruct();
        $resumed->add(FirstMigration::class);
        $resumed->add(ThirdMigration::class);
        $applied = $resumed->run();
        $this->assertSame(['2026_08_04_003_third'], $applied);
    }
}
