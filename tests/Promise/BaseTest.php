<?php

/**
 * Spiral Framework. Cycle ProxyFactory
 *
 * @license MIT
 * @author  Valentin V (Vvval)
 */

declare(strict_types=1);

namespace Cycle\ORM\Promise\Tests;

use Cycle\ORM\Config\RelationConfig;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\SchemaInterface;
use PHPUnit\Framework\TestCase;
use BiuradPHP\Database\Config\DatabaseConfig;
use BiuradPHP\Database\Database;
use BiuradPHP\Database\DatabaseManager;
use BiuradPHP\Database\Driver\Driver;
use BiuradPHP\Database\Driver\Handler;
use BiuradPHP\Tokenizer\ClassesInterface;
use BiuradPHP\Tokenizer\Config\TokenizerConfig;
use Spiral\Tokenizer\Tokenizer;

abstract class BaseTest extends TestCase
{
    // currently active driver
    public const DRIVER = null;

    // tests configuration
    public static $config;

    // cross test driver cache
    public static $driverCache = [];

    protected static $lastORM;

    /** @var Driver */
    protected $driver;

    /** @var DatabaseManager */
    protected $dbal;

    /** @var ORM */
    protected $orm;

    /** @var TestLogger */
    protected $logger;

    /** @var ClassesInterface */
    protected $locator;

    /**
     * Init all we need.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->dbal = new DatabaseManager(new DatabaseConfig(['default' => 'default']));
        $this->dbal->addDatabase(new Database(
            'default',
            '',
            $this->getDriver()
        ));

        $this->dbal->addDatabase(new Database(
            'secondary',
            'secondary_',
            $this->getDriver()
        ));

        $this->logger = new TestLogger();
        $this->getDriver()->setLogger($this->logger);

        if (self::$config['debug']) {
            $this->logger->display();
        }

        $this->logger = new TestLogger();
        $this->getDriver()->setLogger($this->logger);

        $this->orm = new ORM(new Factory(
            $this->dbal,
            RelationConfig::getDefault()
        ));

        $tokenizer = new Tokenizer(new TokenizerConfig([
            'directories' => [__DIR__ . '/Fixtures'],
            'exclude'     => [],
        ]));

        $this->locator = $tokenizer->classLocator();
    }

    /**
     * Cleanup.
     */
    public function tearDown(): void
    {
        $this->disableProfiling();
        $this->dropDatabase($this->dbal->database('default'));
        $this->orm = null;
        $this->dbal = null;
    }

    /**
     * Calculates missing parameters for typecasting.
     *
     * @param SchemaInterface $schema
     * @return ORM|ORMInterface
     */
    public function withSchema(SchemaInterface $schema)
    {
        $this->orm = $this->orm->withSchema($schema);

        return $this->orm;
    }

    /**
     * @return Driver
     */
    public function getDriver(): Driver
    {
        if (isset(static::$driverCache[static::DRIVER])) {
            return static::$driverCache[static::DRIVER];
        }

        $config = self::$config[static::DRIVER];
        if (!isset($this->driver)) {
            $class = $config['driver'];

            $this->driver = new $class([
                'connection' => $config['conn'],
                'username'   => $config['user'],
                'password'   => $config['pass'],
                'options'    => []
            ]);
        }

        $this->driver->setProfiling(true);

        return static::$driverCache[static::DRIVER] = $this->driver;
    }

    /**
     * @return Database
     */
    protected function getDatabase(): Database
    {
        return $this->dbal->database('default');
    }

    /**
     * @param Database|null $database
     */
    protected function dropDatabase(Database $database = null): void
    {
        if ($database === null) {
            return;
        }

        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();

            foreach ($schema->getForeignKeys() as $foreign) {
                $schema->dropForeignKey($foreign->getColumns());
            }

            $schema->save(Handler::DROP_FOREIGN_KEYS);
        }

        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schema->save();
        }
    }

    /**
     * For debug purposes only.
     */
    protected function enableProfiling(): void
    {
        if ($this->logger !== null) {
            $this->logger->display();
        }
    }

    /**
     * For debug purposes only.
     */
    protected function disableProfiling(): void
    {
        if ($this->logger !== null) {
            $this->logger->hide();
        }
    }
}
