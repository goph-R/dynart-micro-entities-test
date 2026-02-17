<?php

namespace Dynart\Micro\Entities\Test\Integration;

use Dynart\Micro\Config;
use Dynart\Micro\Entities\Database\MariaDatabase;
use Dynart\Micro\Entities\EntityManager;
use Dynart\Micro\Entities\PdoBuilder;
use Dynart\Micro\Entities\QueryBuilder\MariaQueryBuilder;
use Dynart\Micro\Entities\QueryExecutor;
use Dynart\Micro\Entities\Test\Entities\TestPost;
use Dynart\Micro\Entities\Test\Entities\TestUser;
use Dynart\Micro\Entities\Test\StubEvents;
use Dynart\Micro\Entities\Test\StubLogger;
use Dynart\Micro\Entities\Test\TestHelper;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase {

    protected Config $config;
    protected MariaDatabase $db;
    protected StubEvents $events;
    protected EntityManager $em;
    protected MariaQueryBuilder $qb;
    protected QueryExecutor $qe;

    protected function setUp(): void {
        $this->config = new Config();
        $this->config->load(__DIR__ . '/../../configs/test.ini');
        $this->db = new MariaDatabase($this->config, new StubLogger(), new PdoBuilder());
        $this->events = new StubEvents();
        $this->em = new EntityManager($this->config, $this->db, $this->events);
        TestHelper::registerEntity($this->em, TestUser::class);
        TestHelper::registerEntity($this->em, TestPost::class);
        $this->qb = new MariaQueryBuilder($this->config, $this->db, $this->em);
        $this->qe = new QueryExecutor($this->db, $this->em, $this->qb);
    }

    protected function safeTableName(string $className): string {
        return $this->db->escapeName($this->em->tableName($className));
    }

    protected function dropTable(string $className): void {
        $this->db->query('drop table if exists ' . $this->safeTableName($className));
    }
}
