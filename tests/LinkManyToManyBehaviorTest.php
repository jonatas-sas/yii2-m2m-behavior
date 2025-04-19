<?php

namespace odara\yii\tests;

use odara\yii\behaviors\LinkManyToManyBehavior;
use odara\yii\tests\fixtures\CategoryFixture;
use odara\yii\tests\fixtures\ItemCategoryFixture;
use odara\yii\tests\fixtures\ItemFixture;
use odara\yii\tests\fixtures\ItemTagFixture;
use odara\yii\tests\fixtures\TagFixture;
use odara\yii\tests\helpers\TestableLinkManyToManyBehavior;
use odara\yii\tests\models\Item;
use odara\yii\tests\models\Tag;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\base\Event;
use yii\base\InvalidArgumentException;
use yii\base\UnknownPropertyException;
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
     * @return array<string, string>
     */
    public function fixtures()
    {
        return [
            'items'         => ItemFixture::class,
            'tags'          => TagFixture::class,
            'categories'    => CategoryFixture::class,
            'item_tag'      => ItemTagFixture::class,
            'item_category' => ItemCategoryFixture::class,
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
        $db->createCommand()->dropTable('tag')->execute();
        $db->createCommand()->dropTable('category')->execute();
        $db->createCommand()->dropTable('item')->execute();
    }

    /**
     * It should skip attribute reading if property is not the referenceAttribute.
     */
    public function testAccessingUnknownPropertyThrowsException(): void
    {
        $post = new Item();

        $this->expectException(UnknownPropertyException::class);

        //@phpstan-ignore-next-line
        $post->nonExistentAttribute;
    }

    /**
     * It should gracefully handle access to unknown dynamic properties.
     *
     * This test ensures that accessing a non-existent dynamic property
     * on the behavior throws a `yii\base\UnknownPropertyException`,
     * verifying the fallback logic in `__get()`.
     *
     * @return void
     */
    public function testUnknownPropertyAccessTriggersExceptionSafely(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]);

        $item->attachBehavior('tags', $behavior);

        try {
            //@phpstan-ignore-next-line
            $item->getBehavior('tags')->nonExistent;
        } catch (UnknownPropertyException $e) {
            $this->assertStringContainsString('Getting unknown property', $e->getMessage());

            throw $e;
        }
    }

    /**
     * It should throw an exception when setting an unknown dynamic property.
     *
     * This test verifies that writing to a non-existent dynamic property
     * on the behavior triggers a `yii\base\UnknownPropertyException`,
     * confirming fallback behavior via `__set()`.
     *
     * @return void
     */
    public function testUnknownPropertySetTriggersExceptionSafely(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
        ]);

        $item->attachBehavior('tags', $behavior);

        //@phpstan-ignore-next-line
        $item->getBehavior('tags')->nonExistent = [1, 2, 3];
    }

    /**
     * It should initialize the reference value correctly from the tags relation.
     */
    public function testReferenceValueInitializationFromTagsRelation(): void
    {
        $item = new Item();

        $item->name = 'Teste Init';

        $item->populateRelation('tags', [
            Tag::findOne(1),
            Tag::findOne(2),
            Tag::findOne(3),
        ]);

        /** @var LinkManyToManyBehavior $behavior */
        $behavior = $item->getBehavior('tags');

        $this->assertInstanceOf(LinkManyToManyBehavior::class, $behavior);

        $referenceValue = $behavior->getReferenceValue();

        $this->assertEquals([1, 2, 3], $referenceValue);
    }

    /**
     * It should normalize primary keys properly.
     */
    public function testNormalizePrimaryKey(): void
    {
        $behavior = new TestableLinkManyToManyBehavior();

        $this->assertSame(42, $behavior->normalizePrimaryKeyPublic(42));

        $object = new class {
            public function __toString(): string
            {
                return 'abc123';
            }
        };

        $this->assertSame('abc123', $behavior->normalizePrimaryKeyPublic($object));
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
     * It should unlink all models from junction table when the owner is deleted.
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
            'Expected no records in junction table after delete'
        );
    }

    /**
     * Test that when `deleteOnUnlink` is true, unlinked relations are
     * physically removed from the junction table.
     *
     * This test verifies that:
     * - An item can be created and saved with multiple tags
     * - When one of the tag IDs is removed, the corresponding relation is deleted
     * - The junction table no longer contains the unlinked tag
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

        // Ensure the junction entry was physically removed
        $exists = (new Query())
            ->from('item_tag')
            ->where(['item_id' => $item->id, 'tag_id' => 2])
            ->exists();

        $this->assertFalse($exists, 'junction table should not contain tag_id 2 after unlinking');
    }

    /**
     * Test that when `deleteOnUnlink` is false, unlinked relations are not
     * removed from the junction table.
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

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'tags',
            'referenceAttribute' => 'tagIds',
            'deleteOnUnlink'     => false,
            'extraColumns'       => [
                'external_id' => static fn (Tag $model) => $model->id,
            ],
        ]);

        $item->detachBehavior('tasgs');
        $item->attachBehavior('tags', $behavior);

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

        // Now verify that the junction still contains the removed relation
        $exists = (new Query())
            ->from('item_tag')
            ->where(['item_id' => null, 'tag_id' => null, 'external_id' => 20])
            ->exists();

        $message = 'junction entry for external_id 20 should still exist when deleteOnUnlink=false';

        $this->assertTrue($exists, $message);
    }

    /**
     * It should throw an exception if the relation does not exist in the model.
     *
     * @return void
     */
    public function testThrowsIfRelationDoesNotExist(): void
    {
        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Getting unknown property:');

        $model = new Item();

        // Comportamento com relação inválida
        $model->attachBehavior('m2m', [
            'class'              => LinkManyToManyBehavior::class,
            'relation'           => 'nonExistentRelation',
            'referenceAttribute' => 'fakeIds',
        ]);

        // Tentar acessar a propriedade virtual dispara o acesso à relação
        //@phpstan-ignore-next-line
        $model->fakeIds;
    }

    /**
     * It should support multiple LinkManyToManyBehavior instances on the same model.
     */
    public function testMultipleBehaviorsWorkIndependently(): void
    {
        $item = new Item();

        $item->name        = 'Multi-Behavior Item';
        $item->tagIds      = [1, 2];
        $item->categoryIds = [1, 2];

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
                'external_id' => function (Tag $model) use (&$called) {
                    $called = true;

                    $this->assertInstanceOf(ActiveRecordInterface::class, $model);

                    return $model->id;
                },
            ],
        ]);

        $item = new Item();

        $item->detachBehavior('tags');
        $item->attachBehavior('tags', $behavior);

        $item->name   = 'Test Item';
        $item->tagIds = [1];

        $item->save(false);

        $this->assertTrue($called, 'Extra column callable should have been called with related model.');
    }

    /**
     * It should throw if reference attribute is not an array.
     */
    public function testReferenceAttributeThrowsIfNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reference value for "tagIds" must be an arrayof relations.');

        $item       = new Item();
        $item->name = 'Invalid Tag Input';

        // @phpstan-ignore-next-line
        $item->tagIds = 1;
    }

    /**
     * It should throw an error if the relation is missing or invalid.
     */
    public function testMissingRelationThrowsException(): void
    {
        $this->expectException(UnknownPropertyException::class);

        $item = new Item();

        $behavior = new LinkManyToManyBehavior([
            'relation'           => 'nonExistentRelation',
            'referenceAttribute' => 'nonExistentIds',
        ]);

        $item->attachBehavior('invalid', $behavior);

        $item->name = 'Invalid Item';

        // @phpstan-ignore-next-line
        $item->nonExistentIds = [1, 2];

        $item->save(false);

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
                'id'          => 'pk',
                'external_id' => 'integer NULL',
                'name'        => 'string NOT NULL',
            ])->execute();
        }

        if (!in_array('category', $tables)) {
            $db->createCommand()->createTable('category', [
                'id'   => 'pk',
                'name' => 'string NOT NULL',
            ])->execute();
        }
        if (!in_array('item_tag', $tables)) {
            $db->createCommand()->createTable('item_tag', [
                'item_id'     => 'integer NULL',
                'tag_id'      => 'integer NULL',
                'external_id' => 'integer NULL',
                'PRIMARY KEY(item_id, tag_id, external_id)',
            ])->execute();
        }

        if (!in_array('item_category', $tables)) {
            $db->createCommand()->createTable('item_category', [
                'item_id'     => 'integer NOT NULL',
                'category_id' => 'integer NOT NULL',
                'PRIMARY KEY(item_id, category_id)',
            ])->execute();
        }
    }
}
