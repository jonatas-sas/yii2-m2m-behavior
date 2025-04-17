<?php

namespace odara\yii\tests;

use odara\yii\tests\fixtures\ItemFixture;
use odara\yii\tests\fixtures\ItemTagFixture;
use odara\yii\tests\fixtures\TagFixture;
use odara\yii\tests\models\Item;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\db\Query;
use yii\test\FixtureTrait;

/**
 * Functional tests for LinkManyToManyBehavior using real database.
 */
class LinkManyToManyBehaviorTest extends TestCase
{
    use FixtureTrait;

    /**
     * Loads the database fixtures.
     *
     * @return array
     */
    public function fixtures()
    {
        return [
            'items'    => ItemFixture::class,
            'tags'     => TagFixture::class,
            'item_tag' => ItemTagFixture::class,
        ];
    }

    /**
     * Prepares the test environment before each test.
     *
     * - Ensures the required database tables exist.
     * - Loads the fixtures to populate the test data.
     *
     * This method is automatically called by PHPUnit before each test method.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestTables();
        $this->loadFixtures();
    }

    /**
     * Cleans up the test environment after each test.
     *
     * Drops all tables related to the test to ensure the database is reset
     * for the next test run. This is important when using SQLite in memory,
     * since the schema persists across test methods.
     *
     * This method is automatically called by PHPUnit after each test method.
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $db = Yii::$app->db;

        $db->createCommand()->dropTable('item_tag')->execute();
        $db->createCommand()->dropTable('tag')->execute();
        $db->createCommand()->dropTable('item')->execute();
    }

    /**
     * It should allow setting and getting the virtual reference attribute (e.g., tagIds).
     */
    public function testItShouldSetAndGetReferenceAttribute(): void
    {
        $item = new Item(['name' => 'Keyboard']);

        $item->tagIds = [1, 2];

        $this->assertEquals([1, 2], $item->tagIds);
        $this->assertTrue($item->canGetProperty('tagIds'));
        $this->assertTrue($item->canSetProperty('tagIds'));
    }

    /**
     * It should link new models after saving a new parent.
     */
    public function testItShouldLinkModelsOnSave(): void
    {
        $item = new Item(['name' => 'Mouse']);

        $item->save(false);

        $item->tagIds = [1, 2];

        $item->save(false);

        $this->assertCount(2, $item->tags);
        $this->assertEqualsCanonicalizing([1, 2], array_map(fn ($tag) => $tag->id, $item->tags));
    }

    /**
     * It should unlink models when removed from the reference attribute.
     *
     * @return void
     */
    public function testItShouldUnlinkModelsOnUpdate(): void
    {
        $item = Item::findOne(1);

        $this->assertNotEmpty($item->tags, 'Expected tags before unlink');

        $item->tagIds = [];

        $item->save(false);

        $item->refresh();

        $this->assertCount(0, $item->tags, 'Expected tags to be unlinked');
    }

    /**
     * It should unlink all models from pivot table when the owner is deleted.
     */
    public function testItShouldUnlinkAllOnDelete(): void
    {
        $item = Item::findOne(1);

        $this->assertNotNull($item);
        $this->assertNotEmpty($item->tags);

        $itemId = $item->id;

        $item->delete();

        $this->assertFalse(
            (new Query())->from('item_tag')->where(['item_id' => $itemId])->exists(),
            'Expected no records in pivot table after delete'
        );
    }

    /**
     * Creates the necessary database tables for the test suite.
     *
     * This method checks if each required table already exists before
     * attempting to create it. This allows tests to be run repeatedly
     * without conflicts from duplicate table definitions.
     *
     * The following tables are created:
     * - `item`
     * - `tag`
     * - `item_tag` (junction table)
     */
    protected function createTestTables(): void
    {
        $db = Yii::$app->getDb();

        $tables = $db->schema->getTableNames();

        if (!in_array('item', $tables)) {
            $db->createCommand()->createTable('item', [
                'id'   => 'pk',
                'name' => 'string NOT NULL',
            ])->execute();
        }

        if (!in_array('tag', $tables)) {
            $db->createCommand()->createTable('tag', [
                'id'   => 'pk',
                'name' => 'string NOT NULL',
            ])->execute();
        }

        if (!in_array('item_tag', $tables)) {
            $db->createCommand()->createTable('item_tag', [
                'item_id' => 'integer NOT NULL',
                'tag_id'  => 'integer NOT NULL',
                'PRIMARY KEY(item_id, tag_id)',
            ])->execute();
        }
    }

}
