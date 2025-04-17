<?php

namespace odara\yii\tests;

use odara\yii\behaviors\LinkManyToManyBehavior;
use odara\yii\tests\fixtures\CategoryFixture;
use odara\yii\tests\fixtures\FeatureFixture;
use odara\yii\tests\fixtures\ItemCategoryFixture;
use odara\yii\tests\fixtures\ItemFeatureFixture;
use odara\yii\tests\fixtures\ItemFixture;
use odara\yii\tests\fixtures\ItemTagFixture;
use odara\yii\tests\fixtures\TagFixture;
use odara\yii\tests\models\Item;
use odara\yii\tests\models\Tag;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecordInterface;
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
            'items'         => ItemFixture::class,
            'tags'          => TagFixture::class,
            'categories'    => CategoryFixture::class,
            'features'      => FeatureFixture::class,
            'item_tag'      => ItemTagFixture::class,
            'item_category' => ItemCategoryFixture::class,
            'item_feature'  => ItemFeatureFixture::class,
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
        $db->createCommand()->dropTable('item_category')->execute();
        $db->createCommand()->dropTable('item_feature')->execute();
        $db->createCommand()->dropTable('tag')->execute();
        $db->createCommand()->dropTable('category')->execute();
        $db->createCommand()->dropTable('feature')->execute();
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
        /** @var Item $item */
        $item = Item::findOne(1);

        $item->tagIds = [1, 2];
        $item->save(false);
        $item->refresh();

        $this->assertNotEmpty($item->tags, 'Expected tags before unlink');
        $this->assertCount(2, $item->tags);

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
        /** @var Item $item */
        $item = Item::findOne(1);

        $item->tagIds = [1, 2];
        $item->save(false);
        $item->refresh();

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
     * Test that when `deleteOnUnlink` is true, unlinked relations are
     * physically removed from the pivot table.
     *
     * This test verifies that:
     * - An item can be created and saved with multiple tags
     * - When one of the tag IDs is removed, the corresponding relation is deleted
     * - The pivot table no longer contains the unlinked tag
     *
     * @return void
     */
    public function testDeleteOnUnlinkRemovesUnlinkedRelations(): void
    {
        $item = new Item();

        $item->name   = 'Test Item';
        $item->tagIds = [1, 2, 3];

        $this->assertTrue($item->save(), 'Initial save failed');

        // Ensure that all three tags are linked
        $this->assertCount(3, $item->tags, 'Initial tags count should be 3');

        // Remove tag ID 2 from the list
        $item->tagIds = [1, 3];

        $this->assertTrue($item->save(), 'Update with reduced tags failed');

        // Refresh and verify that tag ID 2 has been removed
        $item->refresh();

        $tagIds = array_map(fn ($tag) => (int)$tag->id, $item->tags);

        sort($tagIds);

        $this->assertEquals([1, 3], $tagIds, 'Tag with ID 2 should have been unlinked');

        // Ensure the pivot entry was physically removed
        $exists = (new Query())
            ->from('item_tag')
            ->where(['item_id' => $item->id, 'tag_id' => 2])
            ->exists();

        $this->assertFalse($exists, 'Pivot table should not contain tag_id 2 after unlinking');
    }

    /**
     * Test that when `deleteOnUnlink` is false, unlinked relations are not
     * removed from the pivot table.
     *
     * This ensures that:
     * - The unlink logic respects the configuration
     * - Soft unlinks are possible (e.g., for audit/history or custom cleanup)
     *
     * @return void
     */
    public function testUnlinkedRelationsAreNotDeletedWhenDeleteOnUnlinkIsFalse(): void
    {
        // Ensure required tags exist
        foreach ([10, 20] as $id) {
            /** @var Tag $tag */
            $tag = Tag::findOne($id);

            if (!$tag) {
                $tag = new Tag(['id' => $id, 'name' => "Tag $id"]);

                $tag->save(false);
            }
        }

        // Create item with both tag relations
        $item = new Item();

        $item->name   = 'Soft Link Item';
        $item->tagIds = [10, 20];

        $this->assertTrue($item->save(), 'Initial save failed');

        $item->refresh();

        $this->assertCount(2, $item->tags, 'Initial tag count should be 2');

        // Modify tagIds to only keep one
        $item->tagIds = [10];

        $this->assertTrue($item->save(), 'Update with tag removed failed');

        $item->refresh();

        $tagIds = array_map(fn ($tag) => (int)$tag->id, $item->tags);

        $this->assertEquals([10], $tagIds, 'Remaining tag should be ID 10');

        // Now verify that the pivot still contains the removed relation
        $exists = (new Query())
            ->from('item_tag')
            ->where(['item_id' => $item->id, 'tag_id' => 20])
            ->exists();

        $this->assertTrue($exists, 'Pivot entry for tag_id 20 should still exist when deleteOnUnlink=false');
    }

    /**
     * It should throw an exception if the relation does not exist in the model.
     *
     * @return void
     */
    public function testThrowsIfRelationDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Getting unknown property:');

        $model = new Item();

        // Comportamento com relação inválida
        $model->attachBehavior('m2m', [
            'class'              => LinkManyToManyBehavior::class,
            'relation'           => 'nonExistentRelation',
            'referenceAttribute' => 'fakeIds',
        ]);

        // Tentar acessar a propriedade virtual dispara o acesso à relação
        $model->fakeIds;
    }

    /**
     * It should support multiple many-to-many behaviors in one model.
     *
     * @return void
     */
    public function testSupportsMultipleBehaviors(): void
    {
        $item = new Item();

        // Adiciona dois comportamentos independentes
        $item->attachBehavior('tagsBehavior', [
            'class'              => LinkManyToManyBehavior::class,
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]);

        $item->attachBehavior('categoriesBehavior', [
            'class'              => LinkManyToManyBehavior::class,
            'relation'           => 'categories',
            'referenceAttribute' => 'categoryIds',
        ]);

        // Setando valores para os dois virtual attributes
        $item->tagIds      = [10, 20];
        $item->categoryIds = [1, 2];

        $this->assertEquals([10, 20], $item->tagIds, 'Tag IDs should match assigned values.');
        $this->assertEquals([1, 2], $item->categoryIds, 'Category IDs should match assigned values.');
    }

    /**
     * It should support multiple LinkManyToManyBehavior instances on the same model.
     */
    public function testMultipleBehaviorsWorkIndependently(): void
    {
        $item       = new Item();
        $item->name = 'Multi-Behavior Item';

        $item->tagIds      = [10, 20];
        $item->categoryIds = [100, 200];
        $item->save(false);

        $this->assertCount(2, $item->tags, 'Tag relation should have 2 entries.');
        $this->assertCount(2, $item->categories, 'Category relation should have 2 entries.');
    }

    /**
     * It should resolve extraColumns using callables that receive the related model.
     */
    public function testExtraColumnsReceivesRelatedModel(): void
    {
        $called = false;

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
            'extraColumns'       => [
                'called_with_id' => function ($model) use (&$called) {
                    $called = true;
                    $this->assertInstanceOf(ActiveRecordInterface::class, $model);

                    return $model->id;
                },
            ],
        ]);

        $item = new Item();
        $item->attachBehavior('test', $behavior);
        $item->tagIds = [10];
        $item->save(false);

        $this->assertTrue($called, 'Extra column callable should have been called with related model.');
    }

    /**
     * It should normalize single scalar values in reference attribute as array.
     */
    public function testReferenceAttributeScalarValueIsNormalized(): void
    {
        $item = new Item();

        $item->name   = 'Scalar Tag';
        $item->tagIds = 10; // scalar

        $item->save(false);

        $this->assertCount(1, $item->tags, 'Should normalize scalar tag ID to array.');
        $this->assertEquals(10, $item->tags[0]->id);
    }

    /**
     * It should throw an error if the relation is missing or invalid.
     */
    public function testMissingRelationThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $item     = new Item();
        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'nonExistentRelation',
            'referenceAttribute' => 'nonExistentIds',
        ]);
        $item->attachBehavior('invalid', $behavior);

        $behavior->afterSave(new Event(['sender' => $item]));
    }

    /**
     * It should not trigger link/unlink if referenceAttribute was not set.
     */
    public function testReferenceAttributeNotSetDoesNothing(): void
    {
        $item = new Item();

        $item->name = 'No Reference Attribute';

        $item->save(false);

        $this->assertEmpty($item->tags, 'No tags should be linked.');
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

        if (!in_array('category', $tables)) {
            $db->createCommand()->createTable('category', [
                'id'   => 'pk',
                'name' => 'string NOT NULL',
            ])->execute();
        }

        if (!in_array('feature', $tables)) {
            $db->createCommand()->createTable('feature', [
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

        if (!in_array('item_category', $tables)) {
            $db->createCommand()->createTable('item_category', [
                'item_id'     => 'integer NOT NULL',
                'category_id' => 'integer NOT NULL',
                'PRIMARY KEY(item_id, category_id)',
            ])->execute();
        }

        if (!in_array('item_feature', $tables)) {
            $db->createCommand()->createTable('item_feature', [
                'item_id'    => 'integer NOT NULL',
                'feature_id' => 'integer NOT NULL',
                'PRIMARY KEY(item_id, feature_id)',
            ])->execute();
        }
    }
}
